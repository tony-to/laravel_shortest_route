<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Dirape\Token\Token as TokenFactory;
use App\Token;
use App\Route;
use App\RouteStart;
use App\RouteDropoff;
use App\Jobs\CalcDistanceMatrix;
use DateTime;

class RouteController extends Controller
{
    
    public function index(Request $request)
    {
    	$inputs = $request->all();

        //Input validation
        if(!$this->input_validation($inputs)){
            return response()->json(array("status" =>"incorrect parameter"), 400);
        }

    	//Create a route with pending status
		$route = Route::Create();

        //Save the start point from request
        $route->start()->save(new RouteStart(["latitube"=>$inputs[0][0], "longitube"=>$inputs[0][1]]));

        //Save the dropoff points from request
        $dropoffs = array();
        for($i = 1; $i < sizeof($inputs); $i++){
        	$dropoffs[] = new RouteDropoff(["latitube"=>$inputs[$i][0], "longitube"=>$inputs[$i][1]]);
        }
		$route->dropoffs()->saveMany($dropoffs);

        //Add Call API to queue
		CalcDistanceMatrix::dispatch($route);


        return response()->json(array("token" =>$route->id));
    }


    public function show(Route $route)
    {
        //handle the return json
        if($route->status == Route::SUCCESS){
            $start = $route->start;
            $return_path = array(array("$start->latitube", "$start->longitube"));

            foreach ($route->dropoffs as $dropoff) {
                $return_path[] = array("$dropoff->latitube", "$dropoff->longitube");
            }

            //Temp class for return json
            $return_route = new \stdClass();
            $return_route->status = $route->statusLabel();
            $return_route->paths = $return_path;
            $return_route->total_time = $route->total_time;
            $return_route->total_distance = $route->total_distance;

            return response()->json($return_route);
        }else if($route->status == Route::IN_PROGRESS){
            return response()->json(array("status"=>$route->statusLabel()));
        }

        return response()->json(array("status"=>$route->statusLabel(), "error"=>$route->error), 400);                
    }

    public function input_validation($inputs){
        if(!is_array($inputs) || sizeof($inputs) < 2){
            return false;
        }else{
            foreach ($inputs as $input) {
                if(sizeof($input) != 2){
                    return false;
                }
            }
        }
        return true;
    }

}
