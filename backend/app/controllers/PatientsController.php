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

        // Ambil data JSON dari Vue
        $rawBody = $this->request->getJsonRawBody(true);

        $patient = new Patients();
        $patient->name = $rawBody['name'] ?? 'Tanpa Nama';
        
        // Sesuaikan dengan kolom yang ada di tabel 'patients' kamu
        // $patient->phone = $rawBody['phone'] ?? null;
        // $patient->address = $rawBody['address'] ?? null;

        if ($patient->save()) {
            return $this->response->setJsonContent([
                'status' => 'success',
                'message' => 'Pasien berhasil ditambahkan',
                'data' => $patient->toArray()
            ]);
        } else {
            $errors = [];
            foreach ($patient->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            return $this->response->setStatusCode(500)->setJsonContent([
                'status' => 'error',
                'message' => 'Gagal menyimpan pasien: ' . implode(', ', $errors)
            ]);
        }
    }