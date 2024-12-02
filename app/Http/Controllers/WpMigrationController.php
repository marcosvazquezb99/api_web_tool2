<?php

namespace App\Http\Controllers;

use App\Models\WpMigration;
use Illuminate\Http\Request;

class WpMigrationController extends Controller
{
    public function index()
    {
        $all = WpMigration::all();
        return response()->json($all);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_domain' => 'required',
            'source_user' => 'required',
            'source_password' => 'required',
            'source_port' => 'required',
            'destination_domain' => 'required',
            'destination_user' => 'required',
            'destination_password' => 'required',
            'destination_port' => 'required',
            'wordpress_url' => 'required',
            'wordpress_username' => 'required',
            'wordpress_password' => 'required',
        ]);
        $wpMigration = WpMigration::create($data);
        return response()->json($wpMigration);
    }

    public function show($id)
    {
        $wpMigration = WpMigration::findOrFail($id);
        return response()->json($wpMigration);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'source_domain' => 'required',
            'source_user' => 'required',
            'source_password' => 'required',
            'source_port' => 'required',
            'destination_domain' => 'required',
            'destination_user' => 'required',
            'destination_password' => 'required',
            'destination_port' => 'required',
            'wordpress_url' => 'required',
            'wordpress_username' => 'required',
            'wordpress_password' => 'required',
        ]);
        $wpMigration = WpMigration::findOrFail($id);
        $wpMigration->update($data);
        return response()->json($wpMigration);
    }

    public function destroy($id)
    {
        $wpMigration = WpMigration::findOrFail($id);
        $wpMigration->delete();
        return response()->json($wpMigration);
    }


}
