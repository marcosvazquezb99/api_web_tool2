<?php

namespace App\Services\Sync\Holded;

use App\Http\Controllers\Holded\ContactsHoldedController;
use App\Models\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class HoldedCustomerSyncService
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
     * Synchronize customers from Holded
     *
     * @return array Synchronization results
     */
    public function syncCustomers()
    {
        $this->output('Iniciando sincronización de clientes desde Holded...');

        try {
            $contactsHoldedController = new ContactsHoldedController();
            $contacts = $contactsHoldedController->getContacts();

            if (empty($contacts)) {
                $this->output('No se encontraron contactos en Holded.', 'warning');
                return [
                    'success' => true,
                    'message' => 'No se encontraron contactos en Holded.',
                    'data' => [
                        'created' => 0,
                        'updated' => 0,
                        'errors' => 0,
                    ]
                ];
            }

            $this->output('Se encontraron ' . count($contacts) . ' contactos en total.');

            // Filtrar solo clientes
            $clients = array_filter($contacts, function ($contact) {
                return $contact['type'] === 'client';
            });

            $this->output('Procesando ' . count($clients) . ' clientes...');

            $updated = 0;
            $created = 0;
            $errors = 0;
            $processedClients = [];

            foreach ($clients as $contact) {
                $contact_internal_id = $this->getCustomFieldValue($contact, 'client_id');

                try {
                    $result = Client::updateOrCreate(
                        ['holded_id' => $contact['id']],
                        [
                            'name' => $contact['name'],
                            'business_name' => is_null($contact['tradeName']) || $contact['tradeName'] == 0 ? $contact['name'] : $contact['tradeName'],
                            'email' => $contact['email'],
                            'internal_id' => $contact_internal_id,
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $created++;
                        $status = 'created';
                    } else {
                        $updated++;
                        $status = 'updated';
                    }

                    $this->output('Cliente ' . $contact['name'] . ' actualizado - ' . $contact['id']);

                    $processedClients[] = [
                        'id' => $contact['id'],
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? 'N/A',
                        'internal_id' => $contact_internal_id,
                        'status' => $status
                    ];
                } catch (Exception $e) {
                    $errors++;
                    Log::error('Error al sincronizar cliente: ' . $e->getMessage(), [
                        'client' => $contact['name'],
                        'email' => $contact['email'] ?? 'N/A',
                        'holded_id' => $contact['id']
                    ]);

                    $this->output('Error con el cliente: ' . $contact['name'] . ' - ' . $e->getMessage(), 'error');

                    $processedClients[] = [
                        'id' => $contact['id'],
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? 'N/A',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->output('Sincronización completada:');
            $this->output('- Creados: ' . $created);
            $this->output('- Actualizados: ' . $updated);

            if ($errors > 0) {
                $this->output('- Errores: ' . $errors . ' (Ver logs para detalles)', 'warning');
            }

            return [
                'success' => true,
                'message' => 'Sincronización de clientes completada',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                    'clients' => $processedClients
                ]
            ];
        } catch (Exception $e) {
            $this->output('Error general en la sincronización: ' . $e->getMessage(), 'error');
            Log::error('Error general en la sincronización de clientes: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error general en la sincronización: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el valor de un campo personalizado específico
     *
     * @param array $contact
     * @param string $fieldName
     * @return mixed|null
     */
    private function getCustomFieldValue(array $contact, string $fieldName)
    {
        if (!isset($contact['customFields']) || empty($contact['customFields'])) {
            return null;
        }

        foreach ($contact['customFields'] as $customField) {
            if ($customField['field'] === $fieldName) {
                return $customField['value'];
            }
        }

        return null;
    }
}
