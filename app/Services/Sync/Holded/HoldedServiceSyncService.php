<?php

namespace App\Services\Sync\Holded;

use App\Actions\Helpers\FindFirstMatchingValue;
use App\Http\Controllers\Holded\ServicesHoldedController;
use App\Http\Controllers\ServiceController;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Log;

class HoldedServiceSyncService
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
     * Synchronize services from Holded
     *
     * @return array Synchronization results
     */
    public function syncServices()
    {
        $this->output('Iniciando sincronización de servicios desde Holded...');

        try {
            $servicesHoldedController = new ServicesHoldedController();
            $services = $servicesHoldedController->getServices();

            if (empty($services)) {
                $this->output('No se encontraron servicios en Holded.', 'warning');
                return [
                    'success' => true,
                    'message' => 'No se encontraron servicios en Holded.',
                    'data' => [
                        'created' => 0,
                        'updated' => 0,
                        'errors' => 0,
                        'services' => []
                    ]
                ];
            }

            $this->output('Se encontraron ' . count($services) . ' servicios en total.');

            $services_types = ServiceController::types;

            $updated = 0;
            $created = 0;
            $errors = 0;
            $processedServices = [];

            foreach ($services as $service) {
                try {
                    $service_id = $service['id'];
                    $service_name = $service['name'];
                    $service_description = $service['desc'];
                    $service_tags = $service['tags'];

                    $result = Service::updateOrCreate(
                        ['holded_id' => $service_id],
                        [
                            'name' => $service_name,
                            'type' => FindFirstMatchingValue::run($service_tags, $services_types),
                            'description' => $service_description,
                            'recurring' => FindFirstMatchingValue::run($service_tags, ['mensual']) ? 1 : 0,
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $created++;
                        $status = 'created';
                    } else {
                        $updated++;
                        $status = 'updated';
                    }

                    $this->output("Servicio '{$service_name}' actualizado - ID: {$service_id}");

                    $processedServices[] = [
                        'id' => $service_id,
                        'name' => $service_name,
                        'status' => $status
                    ];
                } catch (Exception $e) {
                    $errors++;
                    Log::error('Error al sincronizar servicio: ' . $e->getMessage(), [
                        'service' => $service['name'] ?? 'N/A',
                        'holded_id' => $service['id'] ?? 'N/A'
                    ]);

                    $this->output("Error con el servicio: {$service['name']} - " . $e->getMessage(), 'error');

                    $processedServices[] = [
                        'id' => $service['id'] ?? 'N/A',
                        'name' => $service['name'] ?? 'N/A',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->output('Sincronización de servicios completada:');
            $this->output('- Creados: ' . $created);
            $this->output('- Actualizados: ' . $updated);

            if ($errors > 0) {
                $this->output('- Errores: ' . $errors . ' (Ver logs para detalles)', 'warning');
            }

            return [
                'success' => true,
                'message' => 'Sincronización de servicios completada',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                    'services' => $processedServices
                ]
            ];
        } catch (Exception $e) {
            $this->output('Error general en la sincronización: ' . $e->getMessage(), 'error');
            Log::error('Error general en la sincronización de servicios: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error general en la sincronización: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}
