<?php
use Phalcon\Mvc\Model;

class BillingPayments extends Model
{
    public $id;
    public $patient_billing_id; 
    public $payment_method_id;
    public $amount_paid;
    public $payment_date;

    public function initialize()
    {
        $this->setSource('billing_payments');
    }
}