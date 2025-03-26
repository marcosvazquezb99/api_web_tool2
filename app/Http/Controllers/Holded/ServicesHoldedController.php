<?php

namespace App\Http\Controllers\Holded;

class ServicesHoldedController extends HoldedController
{
    protected string $url = parent::url . '/invoicing/v1/services';

    /**
     * Get all services from Holded
     *
     * @return array
     */
    public function getServices(): array
    {
        return $this->holdedRequest('GET', $this->url);
    }

    /**
     * Get service by ID
     *
     * @param string $id Service ID
     * @return array
     */
    public function getServiceById(string $id): array
    {
        return $this->holdedRequest('GET', $this->url . '/' . $id);
    }

    /**
     * Create a new service in Holded
     *
     * @param array $serviceData Service data
     * @return array
     */
    public function createService(array $serviceData): array
    {
        return $this->holdedRequest('POST', $this->url, $serviceData);
    }

    /**
     * Update a service in Holded
     *
     * @param string $id Service ID
     * @param array $serviceData Service data
     * @return array
     */
    public function updateService(string $id, array $serviceData): array
    {
        return $this->holdedRequest('PUT', $this->url . '/' . $id, $serviceData);
    }
}
