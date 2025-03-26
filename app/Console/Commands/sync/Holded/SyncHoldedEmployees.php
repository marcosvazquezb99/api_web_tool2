<?php

namespace App\Console\Commands\sync\Holded;

use App\Http\Controllers\Holded\EmployeeHoldedController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHoldedEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize holded employees from holded to local database';

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
        $this->info('Iniciando sincronización de empleados desde Holded...');

        $employeeHoldedController = new EmployeeHoldedController();
        $employees = $employeeHoldedController->getEmployees();

        if (empty($employees)) {
            $this->warn('No se encontraron empleados en Holded.');
            return 0;
        }

        $this->info('Se encontraron ' . count($employees) . ' empleados en total.');

        $progress = $this->output->createProgressBar(count($employees));
        $progress->start();

        $updated = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($employees as $employee) {
            $employee_id = $employee['id'];
            $employee_name = $employee['name'];
            $employee_email = $employee['mainEmail'] ?? $employee['email'];

            try {
                $databaseEmployee = User::where('email', $employee_email)->first();

                if ($databaseEmployee) {
                    $databaseEmployee->update([
                        'holded_id' => $employee_id,
                    ]);
                    $updated++;

                    if ($this->getOutput()->isVerbose()) {
                        $this->info("Empleado '{$employee_name}' actualizado - ID: {$employee_id}");
                    }
                } else {
                    $notFound++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->warn("Empleado '{$employee_name}' ({$employee_email}) no encontrado en la base de datos local");
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error al sincronizar empleado: ' . $e->getMessage(), [
                    'employee' => $employee_name,
                    'email' => $employee_email,
                    'holded_id' => $employee_id
                ]);

                $this->error("Error con el empleado: {$employee_name} - " . $e->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->info('Sincronización de empleados completada:');
        $this->info('- Actualizados: ' . $updated);
        $this->info('- No encontrados: ' . $notFound);

        if ($errors > 0) {
            $this->warn('- Errores: ' . $errors . ' (Ver logs para detalles)');
        }

        return 0;
    }
}
