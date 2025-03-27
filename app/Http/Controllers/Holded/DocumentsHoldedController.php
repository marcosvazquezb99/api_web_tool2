<?php

namespace App\Http\Controllers\Holded;

class DocumentsHoldedController extends HoldedController
{
    protected string $url = parent::url . '/invoicing/v1/documents';

    /**
     * Get documents by type and optional date range
     *
     * @param string $docType Document type (invoice, salesreceipt, etc.)
     * @param mixed $startDate Start date
     * @param mixed $endDate End date
     * @param string|null $contactId Filter by contact ID
     * @return array
     */
    public function getDocuments(string $docType, $startDate = null, $endDate = null, $contactId = null): array
    {
        $endpoint = $this->url . '/' . $docType;
        $params = [];

        if ($startDate && $endDate) {
            $params['starttmp'] = $startDate->timestamp;
            $params['endtmp'] = $endDate->timestamp;
        }

        if ($contactId) {
            $params['contact'] = $contactId;
        }

        $url = $this->buildUrl($endpoint, $params);

        return $this->holdedRequest('GET', $url);
    }

    /**
     * Get document by type and ID
     *
     * @param string $docType Document type
     * @param string $id Document ID
     * @return array
     */
    public function getDocumentById(string $docType, string $id): array
    {
        $url = $this->url . '/' . $docType . '/' . $id;

        return $this->holdedRequest('GET', $url);
    }

    /**
     * Create a new document in Holded
     *
     * @param string $docType Document type
     * @param array $documentData Document data
     * @return array
     */
    public function createDocument(string $docType, array $documentData): array
    {
        $url = $this->url . '/' . $docType;

        return $this->holdedRequest('POST', $url, $documentData);
    }

    /**
     * Update a document in Holded
     *
     * @param string $docType Document type
     * @param string $id Document ID
     * @param array $documentData Document data
     * @return array
     */
    public function updateDocument(string $docType, string $id, array $documentData): array
    {
        $url = $this->url . '/' . $docType . '/' . $id;

        return $this->holdedRequest('PUT', $url, $documentData);
    }
}
