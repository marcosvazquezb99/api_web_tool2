<?php

namespace App\Http\Controllers\Holded;

use App\Models\Client;

class ContactsHoldedController extends HoldedController
{
    protected string $url = parent::url . '/invoicing/v1/contacts';

    /**
     * Get all contacts from Holded
     *
     * @return array
     */
    public function getContacts(): array
    {
        return $this->holdedRequest('GET', $this->url);
    }

    /**
     * Get contact by ID with optional local cache
     *
     * @param string $id Contact ID
     * @param bool $bypass Skip local cache check
     * @return array|Client
     */
    public function getContactById(string $id, bool $bypass = false)
    {
        if (!$bypass) {
            $response = Client::where('holded_id', $id)->first();
            if ($response) {
                return $response;
            }
        }

        return $this->holdedRequest('GET', $this->url . '/' . $id);
    }

    /**
     * Create a new contact in Holded
     *
     * @param array $contactData Contact data
     * @return array
     */
    public function createContact(array $contactData): array
    {
        return $this->holdedRequest('POST', $this->url, $contactData);
    }

    /**
     * Update a contact in Holded
     *
     * @param string $id Contact ID
     * @param array $contactData Contact data
     * @return array
     */
    public function updateContact(string $id, array $contactData): array
    {
        return $this->holdedRequest('PUT', $this->url . '/' . $id, $contactData);
    }
}
