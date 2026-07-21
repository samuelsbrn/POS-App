<?php
use Phalcon\Mvc\Controller;

class HealthcareItemsController extends Controller
{
    // Mengambil semua data
    public function indexAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        $items = HealthcareItems::find();
        return $this->response->setJsonContent([
            'status' => 'success',
            'data' => $items
        ]);
    }

    // Menambah data baru
    public function createAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        
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

            $item = new HealthcareItems();
            
            // --- DATA WAJIB ---
            $item->name = $rawBody['name'] ?? null;
            $item->price = isset($rawBody['price']) ? (float)$rawBody['price'] : 0;
            $item->category = $rawBody['category'] ?? 'jasa'; 
            
            // --- DATA OPSIONAL ---
            $item->type = $rawBody['type'] ?? null;
            $item->stock = isset($rawBody['stock']) ? (int)$rawBody['stock'] : 0;

            if ($item->save()) {
                return $this->response->setStatusCode(201)->setJsonContent([
                    'status' => 'success',
                    'message' => 'Item layanan/farmasi berhasil ditambahkan',
                    'data' => $item->toArray()
                ]);
            } else {
                $errors = [];
                foreach ($item->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }
                return $this->response->setStatusCode(400)->setJsonContent([
                    'status' => 'error',
                    'message' => 'Validasi gagal: ' . implode(', ', $errors)
                ]);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJsonContent([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
    }

    // Mengubah data
    public function updateAction($id)
    {
        $this->response->setContentType('application/json', 'UTF-8');
        
        if (!$this->request->isPut()) {
            return $this->response->setStatusCode(405)->setJsonContent(['status' => 'error', 'message' => 'Method Not Allowed']);
        }

        try {
            $item = HealthcareItems::findFirst($id);
            if (!$item) {
                return $this->response->setStatusCode(404)->setJsonContent(['status' => 'error', 'message' => 'Data tidak ditemukan']);
            }

            $rawBody = $this->request->getJsonRawBody(true);
            if (!$rawBody) $rawBody = $this->request->getPut();

            if (isset($rawBody['name'])) $item->name = $rawBody['name'];
            if (isset($rawBody['price'])) $item->price = (float)$rawBody['price'];
            if (isset($rawBody['category'])) $item->category = $rawBody['category'];
            if (isset($rawBody['type'])) $item->type = $rawBody['type'];
            if (isset($rawBody['stock'])) $item->stock = (int)$rawBody['stock'];

            if ($item->save()) {
                return $this->response->setJsonContent(['status' => 'success', 'message' => 'Data berhasil diupdate']);
            } else {
                $errors = [];
                foreach ($item->getMessages() as $message) { 
                    $errors[] = $message->getMessage(); 
                }
                return $this->response->setStatusCode(400)->setJsonContent(['status' => 'error', 'message' => implode(', ', $errors)]);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJsonContent(['status' => 'error', 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
        }
    }

    // Menghapus data
    public function deleteAction($id)
    {
        $this->response->setContentType('application/json', 'UTF-8');
        if (!$this->request->isDelete()) {
            return $this->response->setStatusCode(405)->setJsonContent(['status' => 'error', 'message' => 'Method Not Allowed']);
        }

        try {
            $item = HealthcareItems::findFirst($id);
            if (!$item) {
                return $this->response->setStatusCode(404)->setJsonContent(['status' => 'error', 'message' => 'Data tidak ditemukan']);
            }

            if ($item->delete()) {
                return $this->response->setJsonContent(['status' => 'success', 'message' => 'Data berhasil dihapus']);
            } else {
                return $this->response->setStatusCode(400)->setJsonContent(['status' => 'error', 'message' => 'Gagal menghapus data']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJsonContent(['status' => 'error', 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
        }
    }
}