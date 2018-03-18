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
		$route = new Route();

		//Generate token and associate with route
        $generated_token = (new TokenFactory())->Unique("tokens", "token", 32 );
        $token_obj = Token::create(['token'=>$generated_token]);
        $route->token()->associate($token_obj)->save();

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

        //Format the token
        $generated_token = $this->format_token($generated_token);

        return response()->json(array("token" =>$generated_token));
    }


    public function show($token)
    {
        //Check token exist
        $token = str_replace("-", "", $token);
        if($token_obj = Token::where('token', $token)->first()){
            $token_id = $token_obj->id;
            //Get route by token
            if($route_obj = Route::where('token_id', $token_id)->first()){

                //handle the return json
                if($route_obj->status == Route::SUCCESS){
                    $start = $route_obj->start;
                    $return_path = array(array($start->latitube, $start->longitube));

                    foreach ($route_obj->dropoffs as $dropoff) {
                        $return_path[] = array($dropoff->latitube, $dropoff->longitube);
                    }

                    //Temp class for return json
                    $return_route = new \stdClass();
                    $return_route->status = $route_obj->statusLabel();
                    $return_route->paths = $return_path;
                    $return_route->total_time = $route_obj->total_time;
                    $return_route->total_distance = $route_obj->total_distance;

                    return response()->json($return_route);
                }else if($route_obj->status == Route::IN_PROGRESS){
                    return response()->json(array("status"=>$route_obj->statusLabel()));
                }

                return response()->json(array("status"=>$route_obj->statusLabel(), "error"=>$route_obj->error), 400);                
            }

        }
        return response()->json(['status'=>'not found'], 404);

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

    public function format_token($token){
        //format: 12345678-1234-1234-1234-123456789012
        //e.g.  : 9d3503e0-7236-4e47-a62f-8b01b5646c16

        $positions = array(8, 12, 16, 20);
        $count = 0;
        foreach ($positions as $postion) {
            $token = substr_replace($token, "-", $postion+$count, 0);
            $count++;
        }

        return $token;
    }
}
