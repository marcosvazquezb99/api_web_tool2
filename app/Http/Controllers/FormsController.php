<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FormsController extends Controller
{
    //Create a request that response the same data that the form sends
    public function formCreation(Request $request)
    {

        //if the body has a event key, then send it to the slack channel
        if ($request->has('event')) {
            $slackController = new SlackController();
            $response = $slackController->chat_post_message('C07PF06HF46', json_encode($request['event']));
        }
        //log the request
        return response()->json($request->all());
    }
}
