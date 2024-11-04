<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlansController extends Controller
{
    public function index()
    {
        $plans = Plan::all();
        return response()->json($plans);
    }

    public function show($id)
    {
        $plan = Plan::find($id);
        if ($plan) {
            return response()->json($plan);
        } else {
            return response()->json(['message' => 'Plan not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'service_id' => 'required|string',
        ]);

        $plan = Plan::create($validatedData);
        return response()->json($plan, 201);
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        if ($plan) {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $plan->update($validatedData);
            return response()->json($plan);
        } else {
            return response()->json(['message' => 'Plan not found'], 404);
        }
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);
        if ($plan) {
            $plan->delete();
            return response()->json(['message' => 'Plan deleted']);
        } else {
            return response()->json(['message' => 'Plan not found'], 404);
        }
    }
}
