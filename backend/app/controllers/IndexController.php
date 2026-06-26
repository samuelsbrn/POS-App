<?php

use Phalcon\Mvc\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        // Ubah header menjadi JSON
        $this->response->setContentType('application/json', 'UTF-8');

        // Berikan pesan sukses
        return $this->response->setJsonContent([
            'status'  => 'success',
            'message' => 'Selamat datang di API POS Healthcare (ZiCare)!'
        ]);
    }
}