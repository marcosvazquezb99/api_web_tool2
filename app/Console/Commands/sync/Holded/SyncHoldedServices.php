<?php

namespace App\Console\Commands\sync\Holded;

use App\Actions\Helpers\FindFirstMatchingValue;
use App\Http\Controllers\Holded\ServicesHoldedController;
use App\Http\Controllers\ServiceController;
use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHoldedServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los servicios desde Holded a la base de datos local';

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
        $this->info('Iniciando sincronización de servicios desde Holded...');

        $servicesHoldedController = new ServicesHoldedController();
        $services = $servicesHoldedController->getServices();

        if (empty($services)) {
            $this->warn('No se encontraron servicios en Holded.');
            return 0;
        }

        $this->info('Se encontraron ' . count($services) . ' servicios en total.');

        $progress = $this->output->createProgressBar(count($services));
        $progress->start();

        $services_types = ServiceController::types;

        $updated = 0;
        $created = 0;
        $errors = 0;

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
                } else {
                    $updated++;
                }

                if ($this->getOutput()->isVerbose()) {
                    $this->info("Servicio '{$service_name}' actualizado - ID: {$service_id}");
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error al sincronizar servicio: ' . $e->getMessage(), [
                    'service' => $service['name'] ?? 'N/A',
                    'holded_id' => $service['id'] ?? 'N/A'
                ]);

                $this->error("Error con el servicio: {$service['name']} - " . $e->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->info('Sincronización de servicios completada:');
        $this->info('- Creados: ' . $created);
        $this->info('- Actualizados: ' . $updated);

        if ($errors > 0) {
            $this->warn('- Errores: ' . $errors . ' (Ver logs para detalles)');
        }

        return 0;
    }
}
