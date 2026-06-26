<?php
use Phalcon\Mvc\Controller;

class BillingController extends Controller
{
    // =======================================================
    // 1. FUNGSI UNTUK MEMBUAT NOTA & KERANJANG BARU
    // =======================================================
    public function createAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');

        if (!$this->request->isPost()) {
            return $this->response->setStatusCode(405)->setJsonContent([
                'status' => 'error', 'message' => 'Method Not Allowed'
            ]);
        }

        $rawBody = $this->request->getJsonRawBody(true);

        try {
            $this->db->begin();

            $billing = new PatientBillings();
            $billing->patient_id = $rawBody['patient_id'];
            $billing->billing_number = 'INV-' . time();
            $billing->subtotal = $rawBody['subtotal'];
            $billing->tax = $rawBody['tax'];
            $billing->total_amount = $rawBody['total_amount'];
            $billing->status = 'Unpaid';
            $billing->created_at = date('Y-m-d H:i:s');
            
            if (!$billing->save()) {
                $errors = [];
                foreach ($billing->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }
                throw new \Exception("Error simpan Nota Utama: " . implode(" | ", $errors));
            }

            foreach ($rawBody['items'] as $item) {
                $detail = new BillingDetails();
                
                $detail->patient_billing_id = $billing->id; 
                $detail->healthcare_item_id = $item['item_id'];
                $detail->qty = $item['quantity'];
                $detail->unit_price = $item['price'];
                $detail->subtotal = $item['quantity'] * $item['price'];

                if (!$detail->save()) {
                    $errors = [];
                    foreach ($detail->getMessages() as $message) {
                        $errors[] = $message->getMessage();
                    }
                    throw new \Exception("Error simpan Keranjang: " . implode(" | ", $errors));
                }
            }
            
            $this->db->commit();

            return $this->response->setJsonContent([
                'status'  => 'success',
                'message' => 'Transaksi berhasil dibuat!',
                'invoice' => $billing->billing_number,
                'billing_id' => $billing->id
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // =======================================================
    // 2. FUNGSI UNTUK MENCATAT PEMBAYARAN (SPLIT PAYMENT)
    // =======================================================
    public function paymentAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');

        if (!$this->request->isPost()) {
            return $this->response->setStatusCode(405)->setJsonContent([
                'status' => 'error', 'message' => 'Method Not Allowed'
            ]);
        }

        $rawBody = $this->request->getJsonRawBody(true);

        try {
            $this->db->begin();

            $billing = PatientBillings::findFirst($rawBody['patient_billing_id']);
            if (!$billing) {
                throw new \Exception("Data tagihan tidak ditemukan.");
            }

            if ($billing->status === 'Paid') {
                throw new \Exception("Tagihan ini sudah lunas.");
            }

            $payment = new BillingPayments();
            $payment->patient_billing_id = $billing->id;
            $payment->payment_method_id = $rawBody['payment_method_id']; 
            $payment->amount_paid = $rawBody['amount_paid'];
            $payment->payment_date = date('Y-m-d H:i:s');

            if (!$payment->save()) {
                $errors = [];
                foreach ($payment->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }
                throw new \Exception("Error simpan data uang masuk: " . implode(" | ", $errors));
            }

            $allPayments = BillingPayments::find([
                'conditions' => 'patient_billing_id = :billing_id:',
                'bind'       => ['billing_id' => $billing->id]
            ]);

            $totalPaid = 0;
            foreach ($allPayments as $p) {
                $totalPaid += $p->amount_paid;
            }

            if ($totalPaid >= $billing->total_amount) {
                $billing->status = 'Paid';
                if (!$billing->save()) {
                    throw new \Exception("Gagal mengubah status nota menjadi Lunas.");
                }
            }

            $this->db->commit();

            return $this->response->setJsonContent([
                'status'  => 'success',
                'message' => 'Pembayaran berhasil dicatat!',
                'total_paid' => $totalPaid,
                'sisa_tagihan' => max(0, $billing->total_amount - $totalPaid),
                'payment_status' => $billing->status
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // =======================================================
    // 3. FUNGSI BARU: MENAMPILKAN RIWAYAT & DETAIL INVOICE COMPLETE
    // =======================================================
    // Endpoint: GET /billing/history?id=7
    public function historyAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');

        // Pastikan request adalah GET
        if (!$this->request->isGet()) {
            return $this->response->setStatusCode(405)->setJsonContent([
                'status' => 'error', 'message' => 'Method Not Allowed'
            ]);
        }

        // Ambil ID invoice dari parameter URL (?id=...)
        $billingId = $this->request->getQuery('id');

        if (!$billingId) {
            return $this->response->setStatusCode(400)->setJsonContent([
                'status' => 'error', 'message' => 'Parameter ID invoice diperlukan.'
            ]);
        }

        // 1. Ambil data Invoice Utama
        $billing = PatientBillings::findFirst($billingId);
        if (!$billing) {
            return $this->response->setStatusCode(404)->setJsonContent([
                'status' => 'error', 'message' => 'Invoice tidak ditemukan.'
            ]);
        }

        // 2. Ambil Riwayat Keranjang Belanja (Barang/Jasa)
        $details = BillingDetails::find([
            'conditions' => 'patient_billing_id = :id:',
            'bind'       => ['id' => $billing->id]
        ]);

        $itemKeranjang = [];
        foreach ($details as $d) {
            $itemKeranjang[] = [
                'healthcare_item_id' => $d->healthcare_item_id,
                'qty' => $d->qty,
                'unit_price' => $d->unit_price,
                'subtotal' => $d->subtotal
            ];
        }

        // 3. Ambil Riwayat Pembayaran (Mendukung Multi-payment / Split Payment)
        $payments = BillingPayments::find([
            'conditions' => 'patient_billing_id = :id:',
            'bind'       => ['id' => $billing->id]
        ]);

        $riwayatBayar = [];
        $totalSelesaiDibayar = 0;
        foreach ($payments as $p) {
            $totalSelesaiDibayar += $p->amount_paid;
            $riwayatBayar[] = [
                'payment_id' => $p->id,
                'payment_method_id' => $p->payment_method_id,
                'amount_paid' => $p->amount_paid,
                'payment_date' => $p->payment_date
            ];
        }

        // 4. Bungkus semua requirement menjadi satu kesatuan data utuh
        return $this->response->setJsonContent([
            'status' => 'success',
            'data' => [
                'invoice_info' => [
                    'billing_id' => $billing->id,
                    'patient_id' => $billing->patient_id, // Data Customer
                    'billing_number' => $billing->billing_number,
                    'subtotal_nota' => $billing->subtotal,
                    'tax' => $billing->tax,
                    'total_tagihan' => $billing->total_amount,
                    'status_pembayaran' => $billing->status,
                    'tanggal_buat' => $billing->created_at
                ],
                'items_purchased' => $itemKeranjang, // Data Barang/Jasa
                'payment_history' => $riwayatBayar, // Data Split Payment
                'summary' => [
                    'total_paid' => $totalSelesaiDibayar,
                    'remaining_bill' => max(0, $billing->total_amount - $totalSelesaiDibayar)
                ]
            ]
        ]);
    }
}