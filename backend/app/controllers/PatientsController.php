<?php

use Phalcon\Mvc\Controller;

class PatientsController extends Controller
{
    // Ini akan menjadi endpoint: GET /patients
    public function indexAction()
    {
        // Wajibkan response dalam format JSON
        $this->response->setContentType('application/json', 'UTF-8');

        // Ambil semua data dari tabel patients menggunakan Model
        $patients = Patients::find();

        // Susun data untuk dikirim ke frontend
        return $this->response->setJsonContent([
            'status' => 'success',
            'data'   => $patients
        ]);
    }
}