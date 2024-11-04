<?php

namespace App\Http\Controllers;

use App\Models\TriggeredAction;
use Illuminate\Http\Request;

class TriggeredActionController extends Controller
{
    public function index()
    {
        $triggeredActions = TriggeredAction::all();
        return response()->json($triggeredActions);
    }

    public function show($id)
    {
        $triggeredAction = TriggeredAction::find($id);
        if ($triggeredAction) {
            return response()->json($triggeredAction);
        } else {
            return response()->json(['message' => 'Triggered Action not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|max:255',
        ]);

        $triggeredAction = TriggeredAction::create($validatedData);
        return response()->json($triggeredAction, 201);
    }

    public function update(Request $request, $id)
    {
        $triggeredAction = TriggeredAction::find($id);
        if ($triggeredAction) {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'trigger_event' => 'sometimes|required|string|max:255',
            ]);

            $triggeredAction->update($validatedData);
            return response()->json($triggeredAction);
        } else {
            return response()->json(['message' => 'Triggered Action not found'], 404);
        }
    }

    public function destroy($id)
    {
        $triggeredAction = TriggeredAction::find($id);
        if ($triggeredAction) {
            $triggeredAction->delete();
            return response()->json(['message' => 'Triggered Action deleted']);
        } else {
            return response()->json(['message' => 'Triggered Action not found'], 404);
        }
    }
}
