<?php

namespace App\Http\Controllers\Holded;

class EmployeeHoldedController extends HoldedController
{
    protected string $url = parent::url . '/team/v1/employees';

    /**
     * Get all employees from Holded
     *
     * @return array
     */
    public function getEmployees(): array
    {
        $response = $this->holdedRequest('GET', $this->url);

        return $response['employees'] ?? [];
    }

    /**
     * Get employee by ID
     *
     * @param string $id Employee ID
     * @return array
     */
    public function getEmployeeById(string $id): array
    {
        $response = $this->holdedRequest('GET', $this->url . '/' . $id);

        return $response['employee'] ?? [];
    }

    /**
     * Create a new employee in Holded
     *
     * @param array $employeeData Employee data
     * @return array
     */
    public function createEmployee(array $employeeData): array
    {
        return $this->holdedRequest('POST', $this->url, $employeeData);
    }

    /**
     * Update an employee in Holded
     *
     * @param string $id Employee ID
     * @param array $employeeData Employee data
     * @return array
     */
    public function updateEmployee(string $id, array $employeeData): array
    {
        return $this->holdedRequest('PUT', $this->url . '/' . $id, $employeeData);
    }
}
