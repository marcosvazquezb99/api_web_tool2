<?php

namespace App\Console\Commands\sync\Holded;

use App\Http\Controllers\Holded\DocumentsHoldedController;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHoldedCustomerServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-customerservices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los servicios recurrentes de clientes desde Holded';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de servicios recurrentes de clientes desde Holded...');

        $documentsHoldedController = new DocumentsHoldedController();
        $documents = $documentsHoldedController->getDocuments('invoicerecurring');

        if (empty($documents)) {
            $this->warn('No se encontraron documentos recurrentes en Holded.');
            return 0;
        }

        $this->info('Se encontraron ' . count($documents) . ' documentos recurrentes en total.');

        $progress = $this->output->createProgressBar(count($documents));
        $progress->start();

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($documents as $document) {
            try {
                // Buscar cliente por holded_id
                $client = Client::where('holded_id', $document['contact']['id'] ?? '')->first();

                if (!$client) {
                    $skipped++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->warn("Cliente no encontrado para el documento recurrente ID: {$document['id']}");
                    }
                    $progress->advance();
                    continue;
                }

                // Procesar las líneas del documento para cada servicio
                if (isset($document['items']) && is_array($document['items'])) {
                    foreach ($document['items'] as $item) {
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
                        } else {
                            $updated++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error al sincronizar servicio de cliente: ' . $e->getMessage(), [
                    'document_id' => $document['id'] ?? 'N/A',
                    'client' => $document['contact']['name'] ?? 'N/A'
                ]);

                $this->error("Error con el documento ID: {$document['id']} - " . $e->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->info('Sincronización de servicios de clientes completada:');
        $this->info('- Creados: ' . $created);
        $this->info('- Actualizados: ' . $updated);
        $this->info('- Omitidos: ' . $skipped);

        if ($errors > 0) {
            $this->warn('- Errores: ' . $errors . ' (Ver logs para detalles)');
        }

        return 0;
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
