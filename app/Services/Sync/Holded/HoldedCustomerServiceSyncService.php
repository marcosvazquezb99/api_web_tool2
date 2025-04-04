<?php

namespace App\Services\Sync\Holded;

use App\Http\Controllers\Holded\DocumentsHoldedController;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Log;

class HoldedCustomerServiceSyncService
{
    /**
     * Output callback function for logging/display
     *
     * @var callable|null
     */
    private $outputCallback = null;

    /**
     * Set output callback for messages
     *
     * @param callable $callback
     * @return $this
     */
    public function setOutputCallback(callable $callback)
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Send output message
     *
     * @param string $message
     * @param string $type info|error|warning
     * @return void
     */
    private function output($message, $type = 'info')
    {
        if (is_callable($this->outputCallback)) {
            call_user_func($this->outputCallback, $message, $type);
        }
    }

    /**
     * Synchronize customer services from Holded
     *
     * @return array Synchronization results
     */
    public function syncCustomerServices()
    {
        $this->output('Iniciando sincronización de servicios recurrentes de clientes desde Holded...');

        try {
            $documentsHoldedController = new DocumentsHoldedController();
            $documents = $documentsHoldedController->getDocuments('invoicerecurring');

            if (empty($documents)) {
                $this->output('No se encontraron documentos recurrentes en Holded.', 'warning');
                return [
                    'success' => true,
                    'message' => 'No se encontraron documentos recurrentes en Holded.',
                    'data' => [
                        'created' => 0,
                        'updated' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                    ]
                ];
            }

            $this->output('Se encontraron ' . count($documents) . ' documentos recurrentes en total.');

            $updated = 0;
            $created = 0;
            $skipped = 0;
            $errors = 0;
            $processedDocuments = [];

            foreach ($documents as $document) {
                $documentResult = ['id' => $document['id'] ?? 'N/A', 'services' => []];

                try {
                    // Buscar cliente por holded_id
                    $client = Client::where('holded_id', $document['contact']['id'] ?? '')->first();

                    if (!$client) {
                        $skipped++;
                        $this->output("Cliente no encontrado para el documento recurrente ID: {$document['id']}", 'warning');
                        $documentResult['status'] = 'skipped';
                        $documentResult['reason'] = 'client_not_found';
                        $processedDocuments[] = $documentResult;
                        continue;
                    }

                    $documentResult['client'] = [
                        'id' => $client->id,
                        'name' => $client->name
                    ];

                    // Procesar las líneas del documento para cada servicio
                    if (isset($document['items']) && is_array($document['items'])) {
                        foreach ($document['items'] as $item) {
                            $serviceResult = [];

                            // Buscar el servicio por holded_id si está disponible, o nombre
                            $service = null;
                            if (isset($item['productId'])) {
                                $service = Service::where('holded_id', $item['productId'])->first();
                            }

                            if (!$service && isset($item['name'])) {
                                $service = Service::where('name', $item['name'])->first();
                            }

                            if (!$service) {
                                $skipped++;
                                $serviceResult = [
                                    'name' => $item['name'] ?? 'Unknown',
                                    'status' => 'skipped',
                                    'reason' => 'service_not_found'
                                ];
                                $documentResult['services'][] = $serviceResult;
                                continue;
                            }

                            // Crear o actualizar la relación cliente-servicio
                            $result = ClientService::updateOrCreate(
                                [
                                    'client_id' => $client->id,
                                    'service_id' => $service->id,
                                    'holded_document_id' => $document['id']
                                ],
                                [
                                    'quantity' => $item['units'] ?? 1,
                                    'price' => $item['subtotal'] ?? 0,
                                    'recurring' => true,
                                    'frequency' => $this->parseFrequency($document['recurring'] ?? []),
                                    'status' => 'active'
                                ]
                            );

                            if ($result->wasRecentlyCreated) {
                                $created++;
                                $status = 'created';
                            } else {
                                $updated++;
                                $status = 'updated';
                            }

                            $serviceResult = [
                                'id' => $service->id,
                                'name' => $service->name,
                                'status' => $status,
                                'quantity' => $item['units'] ?? 1,
                                'price' => $item['subtotal'] ?? 0
                            ];

                            $documentResult['services'][] = $serviceResult;
                        }
                    }

                    $documentResult['status'] = 'processed';
                    $processedDocuments[] = $documentResult;

                } catch (Exception $e) {
                    $errors++;
                    Log::error('Error al sincronizar servicio de cliente: ' . $e->getMessage(), [
                        'document_id' => $document['id'] ?? 'N/A',
                        'client' => $document['contact']['name'] ?? 'N/A'
                    ]);

                    $this->output("Error con el documento ID: {$document['id']} - " . $e->getMessage(), 'error');

                    $documentResult['status'] = 'error';
                    $documentResult['error'] = $e->getMessage();
                    $processedDocuments[] = $documentResult;
                }
            }

            $this->output('Sincronización de servicios de clientes completada:');
            $this->output('- Creados: ' . $created);
            $this->output('- Actualizados: ' . $updated);
            $this->output('- Omitidos: ' . $skipped);

            if ($errors > 0) {
                $this->output('- Errores: ' . $errors . ' (Ver logs para detalles)', 'warning');
            }

            return [
                'success' => true,
                'message' => 'Sincronización de servicios de clientes completada',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors,
                    'documents' => $processedDocuments
                ]
            ];
        } catch (Exception $e) {
            $this->output('Error general en la sincronización: ' . $e->getMessage(), 'error');
            Log::error('Error general en la sincronización de servicios de clientes: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error general en la sincronización: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convierte la configuración de recurrencia de Holded a un formato estándar
     *
     * @param array $recurringConfig
     * @return string
     */
    private function parseFrequency(array $recurringConfig): string
    {
        if (empty($recurringConfig)) {
            return 'monthly'; // Valor por defecto
        }

        $period = $recurringConfig['period'] ?? '';
        $periodType = $recurringConfig['periodType'] ?? '';

        if ($periodType === 'months' && $period == 1) {
            return 'monthly';
        }

        if ($periodType === 'years' && $period == 1) {
            return 'yearly';
        }

        if ($periodType === 'weeks' && $period == 1) {
            return 'weekly';
        }

        // Para otros casos, retornamos una cadena descriptiva
        return $period . '_' . $periodType;
    }
}
