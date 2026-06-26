<?php
use Phalcon\Mvc\Model;

class BillingDetails extends Model
{
    public $id;
    public $patient_billing_id; 
    public $healthcare_item_id; 
    public $qty;                
    public $unit_price;         
    public $subtotal;

    public function initialize()
    {
        $this->setSource('billing_details');
    }
}