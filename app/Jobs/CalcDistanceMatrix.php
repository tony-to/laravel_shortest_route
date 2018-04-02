<?php

namespace App\Jobs;

use App\Route;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;


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
            $route_dropoffs = $this->route->dropoffs;
            $origin_string = $route_origin->latitube.",".$route_origin->longitube;

            //Result variable
            $total_distance = 0;
            $total_time = 0;
            $dropoffs_result = array();


            //Use all dropoff points as destination to find the optimize path
            for($index = 0; $index < sizeof($route_dropoffs); $index++){
                //Assume one of dropoff is destination
                $last_dropoff = clone($route_dropoffs[$index]);
                $last_dropoff->step = (sizeof($route_dropoffs)-1);
                $dropoff_string = $last_dropoff->latitube.",".$last_dropoff->longitube;
                
                //Create temp waypoints and remove destination point
                $temp_waypoints = clone($route_dropoffs);
                unset($temp_waypoints[$index]);

                //get the waypoint_order with optimized route
                $waypoints_string = "optimize:true"; 
                foreach ($temp_waypoints as $dropoff) {
                    $waypoints_string .= "|".$dropoff->latitube.",".$dropoff->longitube;
                }

                //Call Google direction API to check the distance and duration
                $response_string = \GoogleMaps::load("directions")
                        ->setParam([
                                    "origin"          => $origin_string, 
                                    "destination"     => $dropoff_string, 
                                    "waypoints"       => $waypoints_string
                                    ])
                          ->get();  

                // Log::info($response_string);

                $response = json_decode($response_string);
                if(isset($response->status) && $response->status == "OK"){

                    $legs = $response->routes[0]->legs;

                    //Calc distance and time
                    $temp_total_distance = 0;
                    $temp_total_time = 0;

                    for($i = 0;$i < sizeof($legs); $i++) {
                        $temp_total_distance += $legs[$i]->distance->value;
                        $temp_total_time += $legs[$i]->duration->value;
                    }

                    // Log::info($temp_total_distance);
                    // Log::info($temp_total_time);

                    //Check the time is shortest
                    if($total_distance == 0 || $temp_total_distance < $total_distance){
                        $total_distance = $temp_total_distance;
                        $total_time = $temp_total_time;


                        $waypoint_order = $response->routes[0]->waypoint_order;

                        //Update the step ordering of all dropoff points
                        $i = 0;
                        foreach ($temp_waypoints as $temp_waypoint) {
                            $temp_waypoint->step = $waypoint_order[$i];
                            $i++;
                        }

                        $dropoffs_result = clone($temp_waypoints);
                        $dropoffs_result[] = clone($last_dropoff);

                    }

                }else{
                    $error_message = "Google API error";

                    if(isset($response->error_message)){
                        $error_message = $response->error_message;
                    }
                    $this->route->update(["status" => Route::FAILURE, "error" => $response->error_message]);   
                    return false;
                }
            }
                
            //Save the path, distance and time and mark as success
            $this->route->dropoffs()->saveMany($dropoffs_result);
            $this->route->update(["total_distance"=>$total_distance, "total_time"=>$total_time, "status" => Route::SUCCESS]);
            return true;
            
        }catch(Exception $e){
            return $e;
        }
    }


}
