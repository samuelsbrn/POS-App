<?php

use Phalcon\Mvc\Model;

class Patients extends Model
{
    // Definisikan kolom sesuai tabel di database
    public $id;
    public $mrn;
    public $name;
    public $date_of_birth;
    public $phone;
    public $address;
    public $created_at;

    public function initialize()
    {
        // Beri tahu Phalcon nama tabelnya di MariaDB
        $this->setSource('patients'); 
    }
}