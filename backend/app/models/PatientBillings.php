<?php
use Phalcon\Mvc\Model;

class PatientBillings extends Model
{
    public $id;
    public $patient_id;
    public $billing_number;
    public $subtotal;
    public $tax;
    public $total_amount;
    public $status;
    public $created_at;

    public function initialize()
    {
        $this->setSource('patient_billings');
    }
}