<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FormsController extends Controller
{
    //Create a request that response the same data that the form sends
    public function formCreation(Request $request)
    {
        //log the request
        \Log::info($request->all());
        return response()->json($request->all());
    }
}
