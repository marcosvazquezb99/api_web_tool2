<?php

namespace App\Http\Controllers\Holded;

use App\Models\Service;
use Carbon\Carbon;

class DocumentsHoldedController extends HoldedController
{
    protected string $url = parent::url . '/invoicing/v1/documents';

    /**
     * Get documents by type and optional date range
     *
     * @param string $docType Document type (invoice, salesreceipt, etc.)
     * @param mixed $startDate Start date
     * @param mixed $endDate End date
     * @param string|null $contactId Filter by contact ID
     * @return array
     */
    public function getDocuments(string $docType, $startDate = null, $endDate = null, $contactId = null): array
    {
        $endpoint = $this->url . '/' . $docType;
        $params = [];

        if ($startDate && $endDate) {
            $params['starttmp'] = $startDate->timestamp;
            $params['endtmp'] = $endDate->timestamp;
        }

        if ($contactId) {
            $params['contactid'] = $contactId;
        }

        $url = $this->buildUrl($endpoint, $params);

        return $this->holdedRequest('GET', $url);
    }

    /**
     * Get document by type and ID
     *
     * @param string $docType Document type
     * @param string $id Document ID
     * @return array
     */
    public function getDocumentById(string $docType, string $id): array
    {
        $url = $this->url . '/' . $docType . '/' . $id;

        return $this->holdedRequest('GET', $url);
    }

    /**
     * Create a new document in Holded
     *
     * @param string $docType Document type
     * @param array $documentData Document data
     * @return array
     */
    public function createDocument(string $docType, array $documentData): array
    {
        $url = $this->url . '/' . $docType;

        return $this->holdedRequest('POST', $url, $documentData);
    }

    /**
     * Update a document in Holded
     *
     * @param string $docType Document type
     * @param string $id Document ID
     * @param array $documentData Document data
     * @return array
     */
    public function updateDocument(string $docType, string $id, array $documentData): array
    {
        $url = $this->url . '/' . $docType . '/' . $id;

        return $this->holdedRequest('PUT', $url, $documentData);
    }

    /**
     * Get client services information from Holded
     *
     * @param string $clientHoldedId Client's Holded ID
     * @param Carbon|null $startDate Start date for documents (defaults to 1 month ago)
     * @param Carbon|null $endDate End date for documents (defaults to now)
     * @return array Returns client services info with formatted text
     */
    public function getClientServicesInfo(string $clientHoldedId, Carbon $startDate = null, Carbon $endDate = null): array
    {
        // Set default dates if not provided
        $startDate = $startDate ?? Carbon::now()->subMonth();
        $endDate = $endDate ?? Carbon::now();

        // Get client information
        $client = \App\Models\Client::where('holded_id', $clientHoldedId)->first();
        if (!$client) {
            return [
                'success' => false,
                'message' => 'Cliente no encontrado',
                'client' => null,
                'services' => []
            ];
        }

        // Get documents for this client
        $documents = $this->getDocuments('invoice', $startDate, $endDate, $clientHoldedId);
        if (empty($documents)) {
            return [
                'success' => true,
                'message' => 'No se encontraron servicios facturados para el cliente en el período especificado',
                'client' => $client,
                'services' => []
            ];
        }

        // Track unique services
        $uniqueServices = [];

        // Process all documents to extract service information
        foreach ($documents as $document) {
            $isRecurring = isset($document['from']['docType']) && $document['from']['docType'] == 'invoicerecurring';

            if (!empty($document['products'])) {
                foreach ($document['products'] as $product) {
                    $name = $product['name'] ?? 'Sin nombre';
                    $description = $product['description'] ?? '';
                    $price = $product['price'] ?? 0;
                    $quantity = $product['units'] ?? 1;

                    // Check if this is a service with a serviceId
                    $serviceId = $product['serviceId'] ?? null;
                    if ($serviceId) {
                        // Look up service details
                        $service = Service::where("holded_id", $serviceId)->first();
                        if ($service) {
                            // Add to unique services if not already there
                            if (!isset($uniqueServices[$serviceId])) {
                                $uniqueServices[$serviceId] = [
                                    'name' => $name,
                                    'description' => $description,
                                    'price' => $price,
                                    'quantity' => $quantity,
                                    'type' => $service->type ?? 'No especificado',
                                    'recurring' => $isRecurring || $service->recurring ? 'Sí' : 'No'
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Format the response text
        $response_text = "*Información del Cliente:*\n";
        $response_text .= "• *Nombre:* " . $client->name . "\n";
        $response_text .= "• *Email:* " . ($client->email ?: 'No especificado') . "\n\n";

        // Add services information
        if (!empty($uniqueServices)) {
            $response_text .= "*Resumen de Servicios Contratados:*\n";
            foreach ($uniqueServices as $serviceId => $service) {
                $response_text .= "• *{$service['name']}*\n";
                $response_text .= "  Tipo: {$service['type']}, Recurrente: {$service['recurring']}\n";
                $response_text .= "  Unidades: {$service['quantity']}\n\n";
            }
        } else {
            $response_text .= "*No se encontraron servicios contratados*\n";
        }

        return [
            'success' => true,
            'message' => 'Información recuperada correctamente',
            'client' => $client,
            'services' => $uniqueServices,
            'formatted_text' => $response_text
        ];
    }
}
