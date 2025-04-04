<?php

namespace App\Services\Sync\Holded;

use App\Http\Controllers\Holded\EmployeeHoldedController;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class HoldedEmployeeSyncService
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
     * Synchronize employees from Holded
     *
     * @return array Synchronization results
     */
    public function syncEmployees()
    {
        $this->output('Iniciando sincronización de empleados desde Holded...');

        try {
            $employeeHoldedController = new EmployeeHoldedController();
            $employees = $employeeHoldedController->getEmployees();

            if (empty($employees)) {
                $this->output('No se encontraron empleados en Holded.', 'warning');
                return [
                    'success' => true,
                    'message' => 'No se encontraron empleados en Holded.',
                    'data' => [
                        'updated' => 0,
                        'notFound' => 0,
                        'errors' => 0,
                        'employees' => []
                    ]
                ];
            }

            $this->output('Se encontraron ' . count($employees) . ' empleados en total.');

            $updated = 0;
            $notFound = 0;
            $errors = 0;
            $processedEmployees = [];

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

                        $this->output("Empleado '{$employee_name}' actualizado - ID: {$employee_id}");

                        $processedEmployees[] = [
                            'id' => $employee_id,
                            'name' => $employee_name,
                            'email' => $employee_email,
                            'status' => 'updated'
                        ];
                    } else {
                        $notFound++;
                        $this->output("Empleado '{$employee_name}' ({$employee_email}) no encontrado en la base de datos local", 'warning');

                        $processedEmployees[] = [
                            'id' => $employee_id,
                            'name' => $employee_name,
                            'email' => $employee_email,
                            'status' => 'not_found'
                        ];
                    }
                } catch (Exception $e) {
                    $errors++;
                    Log::error('Error al sincronizar empleado: ' . $e->getMessage(), [
                        'employee' => $employee_name,
                        'email' => $employee_email,
                        'holded_id' => $employee_id
                    ]);

                    $this->output("Error con el empleado: {$employee_name} - " . $e->getMessage(), 'error');

                    $processedEmployees[] = [
                        'id' => $employee_id,
                        'name' => $employee_name,
                        'email' => $employee_email,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->output('Sincronización de empleados completada:');
            $this->output('- Actualizados: ' . $updated);
            $this->output('- No encontrados: ' . $notFound);

            if ($errors > 0) {
                $this->output('- Errores: ' . $errors . ' (Ver logs para detalles)', 'warning');
            }

            return [
                'success' => true,
                'message' => 'Sincronización de empleados completada',
                'data' => [
                    'updated' => $updated,
                    'notFound' => $notFound,
                    'errors' => $errors,
                    'employees' => $processedEmployees
                ]
            ];
        } catch (Exception $e) {
            $this->output('Error general en la sincronización: ' . $e->getMessage(), 'error');
            Log::error('Error general en la sincronización de empleados: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error general en la sincronización: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}
