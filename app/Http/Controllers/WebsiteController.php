<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index()
    {
        $websites = Website::all();
        return response()->json($websites);
    }

    public function show($id)
    {
        $website = Website::find($id);
        if ($website) {
            return response()->json($website);
        } else {
            return response()->json(['message' => 'Website not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'description' => 'nullable|string',
        ]);

        $website = Website::create($validatedData);
        return response()->json($website, 201);
    }

    public function update(Request $request, $id)
    {
        $website = Website::find($id);
        if ($website) {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'url' => 'sometimes|required|url',
                'description' => 'nullable|string',
            ]);

            $website->update($validatedData);
            return response()->json($website);
        } else {
            return response()->json(['message' => 'Website not found'], 404);
        }
    }

    public function destroy($id)
    {
        $website = Website::find($id);
        if ($website) {
            $website->delete();
            return response()->json(['message' => 'Website deleted']);
        } else {
            return response()->json(['message' => 'Website not found'], 404);
        }
    }
}
