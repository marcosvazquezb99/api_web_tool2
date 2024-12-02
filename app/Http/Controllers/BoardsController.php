<?php

namespace App\Http\Controllers;

use App\Models\Boards;
use Illuminate\Http\Request;

class BoardsController extends Controller
{
    public function index()
    {
        $clients = Boards::all();
        return response()->json($clients);
    }

    public function show($id)
    {
        $client = Boards::find($id);
        if ($client) {
            return response()->json($client);
        } else {
            return response()->json(['message' => 'Client not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:clients',
            'phone' => 'nullable|string|max:20',
        ]);
        $client = Boards::create($validatedData);
        return response()->json($client, 201);
    }

    public function update(Request $request, $id)
    {
        $board = Boards::find($id);
        if ($board) {
            /*$validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:clients',
                'phone' => 'nullable|string|max:20',
            ]);*/
            $board->update($request->all());
//            dd($client);
            return response()->json($board);
        } else {
            return response()->json(['message' => 'Client not found'], 404);
        }
    }

    public function destroy($id)
    {
        $client = Boards::find($id);
        if ($client) {
            $client->delete();
            return response()->json(['message' => 'Client deleted']);
        } else {
            return response()->json(['message' => 'Client not found'], 404);
        }
    }
}
