<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Cassandra\Exception\ValidationException;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index()
    {
        $servers = Server::all();
        return response()->json($servers);
    }

    public function show($id)
    {
        $server = Server::find($id);
        if ($server) {
            return response()->json($server);
        } else {
            return response()->json(['message' => 'Server not found'], 404);
        }
    }

    public function store(Request $request)
    {
        try {
//            dd($request->all());
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'ip' => 'required|ip',
                'max_sites' => 'required|string|max:255',
                'system' => 'required|string|max:255',
                'url' => 'required|string|max:255',
                'username' => 'nullable|required|string|max:255',
                'password' => 'nullable|required|string|max:255',
                'token' => 'nullable|required|string|max:255',
            ]);

            $server = Server::create($validatedData);
            return response()->json($server, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $server = Server::find($id);
        if ($server) {
            $validatedData = $request->validate([
                'name' => 'string|max:255',
                'ip' => 'ip',
                'max_sites' => 'string|max:255',
                'system' => 'string|max:255',
                'url' => 'string|max:255',
                'username' => 'nullable|string|max:255',
                'password' => 'nullable|string|max:255',
                'token' => 'nullable|string|max:255',
            ]);

            $server->update($validatedData);
            return response()->json($server);
        } else {
            return response()->json(['message' => 'Server not found'], 404);
        }
    }

    public function destroy($id)
    {
        $server = Server::find($id);
        if ($server) {
            $server->delete();
            return response()->json(['message' => 'Server deleted']);
        } else {
            return response()->json(['message' => 'Server not found'], 404);
        }
    }
}
