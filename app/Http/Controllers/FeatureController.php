<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        $features = Feature::all();
        return response()->json($features);
    }

    public function show($id)
    {
        $feature = Feature::find($id);
        if ($feature) {
            return response()->json($feature);
        } else {
            return response()->json(['message' => 'Feature not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'plan_id' => 'string',
        ]);

        $feature = Feature::create($validatedData);
        return response()->json($feature, 201);
    }

    public function update(Request $request, $id)
    {
        $feature = Feature::find($id);
        if ($feature) {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'plan_id' => 'string',
            ]);

            $feature->update($validatedData);
            return response()->json($feature);
        } else {
            return response()->json(['message' => 'Feature not found'], 404);
        }
    }

    public function destroy($id)
    {
        $feature = Feature::find($id);
        if ($feature) {
            $feature->delete();
            return response()->json(['message' => 'Feature deleted']);
        } else {
            return response()->json(['message' => 'Feature not found'], 404);
        }
    }
}
