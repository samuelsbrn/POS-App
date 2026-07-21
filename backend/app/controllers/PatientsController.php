<?php

use Phalcon\Mvc\Controller;

class PatientsController extends Controller
{
    /**
     * 1. FUNGSI UNTUK MENGAMBIL SEMUA DATA PASIEN
     * Menangani permintaan: GET /patients
     */
    public function indexAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        
        // Pastikan hanya menerima request GET
        if (!$this->request->isGet()) {
            return $this->response->setStatusCode(405)->setJsonContent([
                'status' => 'error',
                'message' => 'Method Not Allowed'
            ]);
        }

        try {
            // Mengambil semua data dari tabel patients
            // Pastikan model 'Patients' sudah di-load/dibuat
            $patients = Patients::find();
            
            return $this->response->setStatusCode(200)->setJsonContent([
                'status' => 'success',
                'data' => $patients->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJsonContent([
                'status' => 'error',
                'message' => 'Gagal mengambil data pasien: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 2. FUNGSI UNTUK MENAMBAHKAN PASIEN BARU
     * Menangani permintaan: POST /patients/create
     */
    public function createAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        
        // Pastikan hanya menerima request POST
        if (!$this->request->isPost()) {
            return $this->response->setStatusCode(405)->setJsonContent([
                'status' => 'error', 
                'message' => 'Method Not Allowed'
            ]);
        }

        try {
            // Ambil data JSON dari Vue
            $rawBody = $this->request->getJsonRawBody(true);
            if (!$rawBody) {
                $rawBody = $this->request->getPost();
            }

            $patient = new Patients();
            
            // --- PERBAIKAN: Gunakan !empty() agar string kosong ("") diubah jadi null ---
            $patient->mrn = !empty($rawBody['mrn']) ? $rawBody['mrn'] : null; 
            $patient->name = !empty($rawBody['name']) ? $rawBody['name'] : null;
            
            // Data opsional
            $patient->nik = !empty($rawBody['nik']) ? $rawBody['nik'] : null;
            $patient->gender = !empty($rawBody['gender']) ? $rawBody['gender'] : 'L';
            
            // Tanggal tidak boleh "", harus null jika kosong agar MariaDB tidak crash
            $patient->dob = !empty($rawBody['dob']) ? $rawBody['dob'] : null;
            $patient->phone = !empty($rawBody['phone']) ? $rawBody['phone'] : null;
            $patient->address = !empty($rawBody['address']) ? $rawBody['address'] : null;

            if ($patient->save()) {
                return $this->response->setStatusCode(201)->setJsonContent([
                    'status' => 'success',
                    'message' => 'Pasien berhasil ditambahkan',
                    'data' => $patient->toArray()
                ]);
            } else {
                $errors = [];
                foreach ($patient->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }
                // Kirim respons 400 jika ada kolom wajib yang kosong atau MRN duplikat
                return $this->response->setStatusCode(400)->setJsonContent([
                    'status' => 'error',
                    'message' => 'Validasi gagal: ' . implode(', ', $errors)
                ]);
            }
        } catch (\Exception $e) {
            // Mengembalikan error 500 dengan pesan yang spesifik untuk membantu debugging
            return $this->response->setStatusCode(500)->setJsonContent([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
    }
}