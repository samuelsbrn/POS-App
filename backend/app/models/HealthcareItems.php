<?php

use Phalcon\Mvc\Model;

class HealthcareItems extends Model
{
    public $id;
    public $category;
    public $name;
    public $price;
    public $stock;
    public $created_at;

    public function initialize()
    {
        // Menghubungkan ke tabel MariaDB
        $this->setSource('healthcare_items'); 
    }
}