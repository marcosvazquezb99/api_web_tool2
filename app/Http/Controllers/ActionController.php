<?php

namespace App\Http\Controllers;

use App\Models\Action;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function index()
    {
        $actions = Action::all();
        return response()->json($actions);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $action = Action::create($validatedData);
        return response()->json($action, 201);
    }

    public function show($id)
    {
        $action = Action::findOrFail($id);
        return response()->json($action);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $action = Action::findOrFail($id);
        $action->update($validatedData);
        return response()->json($action);
    }

    public function destroy($id)
    {
        $action = Action::findOrFail($id);
        $action->delete();
        return response()->json(null, 204);
    }
}
