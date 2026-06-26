<?php

use Phalcon\Mvc\Controller;

class HealthcareItemsController extends Controller
{
    // Endpoint: GET /healthcare_items
    public function indexAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');

        // Ambil semua data layanan, tindakan, dan obat dari database
        $items = HealthcareItems::find();

        return $this->response->setJsonContent([
            'status' => 'success',
            'data'   => $items
        ]);
    }
}