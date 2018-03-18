<?php

namespace App\Jobs;

use App\Route;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CalcDistanceMatrix implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    protected $route;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{

            //Convert the db data to Google API parameter
            $route_origin = $this->route->start;
            $route_waypoints = $this->route->dropoffs;

            $origin_string = $route_origin->latitube.",".$route_origin->longitube;
            //get the waypoint_order with optimized route
            $waypoints_string = "optimize:true"; 

            foreach ($route_waypoints as $waypoint) {
                $waypoints_string .= "|".$waypoint->latitube.",".$waypoint->longitube;
            }


            //When request path(A->B->C->A), path(A->B->C) - path(C->A) will be the shortest path
            $response_string = \GoogleMaps::load("directions")
                    ->setParam([
                                "origin"          => $origin_string, 
                                "destination"     => $origin_string, 
                                "waypoints"       => $waypoints_string
                                ])
                      ->get();  


            $response = json_decode($response_string);
            if(isset($response->status) && $response->status == "OK"){
                $total_distance = 0;
                $total_time = 0;
                $waypoint_order = $response->routes[0]->waypoint_order;

                //Update the step ordering of all dropoff points
                for($i = 0;$i < sizeof($route_waypoints); $i++) {
                    $route_waypoints[$i]->step = $waypoint_order[$i];
                }

                $legs = $response->routes[0]->legs;

                //Calc distance and time (Will not count last leg (C->A))
                for($i = 0;$i < sizeof($legs) - 1; $i++) {
                    $total_distance += $legs[$i]->distance->value;
                    $total_time += $legs[$i]->duration->value;
                }

                //Save the path, distance and time and mark as success
                $this->route->dropoffs()->saveMany($route_waypoints);
                $this->route->update(["total_distance"=>$total_distance, "total_time"=>$total_time, "status" => Route::SUCCESS]);
                return true;
            }

            $error_message = "Google API error";

            if(isset($response->error_message)){
                $error_message = $response->error_message;
            }
            $this->route->update(["status" => Route::FAILURE, "error" => $response->error_message]);
            
        }catch(Exception $e){
            return $e;
        }
    }


}
