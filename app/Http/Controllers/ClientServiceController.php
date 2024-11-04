<?php

namespace App\Http\Controllers;

use App\Models\ClientService;
use Illuminate\Http\Request;

class ClientServiceController extends AppBaseController
{
    public function index()
    {
        $clientServices = ClientService::all();
        return $this->sendResponse($clientServices, 'Client Services retrieved successfully');
    }

    public function show($id)
    {
        $clientService = ClientService::find($id);
        if ($clientService) {
            return $this->sendResponse($clientService, 'Client Service retrieved successfully');
        } else {
            return $this->sendError('Client Service not found');
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'service_id' => 'required|integer|exists:services,id',
            'status' => 'required|string|max:255',
        ]);

        $clientService = ClientService::create($validatedData);
        return $this->sendResponse($clientService, 'Client Service created successfully');
    }

    public function update(Request $request, $id)
    {
        $clientService = ClientService::find($id);
        if ($clientService) {
            $validatedData = $request->validate([
                'client_id' => 'sometimes|required|integer|exists:clients,id',
                'service_id' => 'sometimes|required|integer|exists:services,id',
                'status' => 'sometimes|required|string|max:255',
            ]);

            $clientService->update($validatedData);
            return $this->sendResponse($clientService, 'Client Service updated successfully');
        } else {
            return $this->sendError('Client Service not found');
        }
    }

    public function destroy($id)
    {
        $clientService = ClientService::find($id);
        if ($clientService) {
            $clientService->delete();
            return $this->sendSuccess('Client Service deleted successfully');
        } else {
            return $this->sendError('Client Service not found');
        }
    }
}
