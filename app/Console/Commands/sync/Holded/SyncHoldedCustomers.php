<?php

namespace App\Console\Commands\sync\Holded;

use App\Http\Controllers\Holded\ContactsHoldedController;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHoldedCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-customers';

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
        $this->info('Iniciando sincronización de clientes desde Holded...');

        $contactsHoldedController = new ContactsHoldedController();
        $contacts = $contactsHoldedController->getContacts();

        if (empty($contacts)) {
            $this->warn('No se encontraron contactos en Holded.');
            return 0;
        }

        $this->info('Se encontraron ' . count($contacts) . ' contactos en total.');

        // Filtrar solo clientes
        $clients = array_filter($contacts, function ($contact) {
            return $contact['type'] === 'client';
        });

        $this->info('Procesando ' . count($clients) . ' clientes...');

        $progress = $this->output->createProgressBar(count($clients));
        $progress->start();

        $updated = 0;
        $created = 0;
        $errors = 0;
        // dd($clients);
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
                } else {
                    $updated++;
                }

                if ($this->getOutput()->isVerbose()) {
                    $this->info('Cliente ' . $contact['name'] . ' actualizado - ' . $contact['id']);
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error al sincronizar cliente: ' . $e->getMessage(), [
                    'client' => $contact['name'],
                    'email' => $contact['email'] ?? 'N/A',
                    'holded_id' => $contact['id']
                ]);

                $this->error('Error con el cliente: ' . $contact['name'] . ' - ' . $e->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->info('Sincronización completada:');
        $this->info('- Creados: ' . $created);
        $this->info('- Actualizados: ' . $updated);

        if ($errors > 0) {
            $this->warn('- Errores: ' . $errors . ' (Ver logs para detalles)');
        }

        return 0;
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
