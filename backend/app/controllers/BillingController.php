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
            return $this->response->setStatusCode(405)->setJsonContent(['status' => 'error', 'message' => 'Method Not Allowed']);
        }

        $rawBody = $this->request->getJsonRawBody(true);

        try {
            $this->db->begin();

            // 1. Simpan Header Tagihan
            $billing = new PatientBillings();
            $billing->patient_id = $rawBody['patient_id'] ?? null; // Null jika Walk-in
            $billing->billing_number = 'ZC-POS/' . date('Y/m/d') . '/' . rand(1000, 9999); // Sesuai PRP
            $billing->subtotal = $rawBody['subtotal'];
            $billing->tax = $rawBody['tax'];
            $billing->discount = $rawBody['discount'] ?? 0;
            $billing->total_amount = $rawBody['total_amount'];
            $billing->status = 'Unpaid';
            $billing->created_at = date('Y-m-d H:i:s');
            
            if (!$billing->save()) {
                throw new \Exception("Error simpan Nota Utama.");
            }

            // 2. Simpan Detail Item Keranjang & Potong Stok
            foreach ($rawBody['items'] as $item) {
                $detail = new BillingDetails();
                $detail->patient_billing_id = $billing->id; 
                $detail->healthcare_item_id = $item['item_id'];
                $detail->qty = $item['quantity'];
                $detail->unit_price = $item['price'];
                $detail->subtotal = $item['quantity'] * $item['price'];

                if (!$detail->save()) {
                    throw new \Exception("Error simpan Keranjang.");
                }

                // FITUR PRP: Potong stok otomatis khusus untuk Barang/Obat
                $itemDb = HealthcareItems::findFirst($item['item_id']);
                if ($itemDb && $itemDb->category === 'obat') {
                    if ($itemDb->stock < $item['quantity']) {
                        throw new \Exception("Stok {$itemDb->name} tidak mencukupi.");
                    }
                    $itemDb->stock -= $item['quantity'];
                    $itemDb->save();
                }
            }
            
            $this->db->commit();

            return $this->response->setJsonContent([
                'status'  => 'success',
                'message' => 'Transaksi berhasil dibuat!',
                'billing_id' => $billing->id
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent(['status'  => 'error', 'message' => $e->getMessage()]);
        }
    }

    // =======================================================
    // 2. FUNGSI UNTUK MENCATAT PEMBAYARAN (SPLIT PAYMENT)
    // =======================================================
    public function paymentAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');

        if (!$this->request->isPost()) {
            return $this->response->setStatusCode(405)->setJsonContent(['status' => 'error', 'message' => 'Method Not Allowed']);
        }

        $rawBody = $this->request->getJsonRawBody(true);

        try {
            $this->db->begin();

            $billing = PatientBillings::findFirst($rawBody['patient_billing_id']);
            if (!$billing) throw new \Exception("Data tagihan tidak ditemukan.");
            if ($billing->status === 'Paid') throw new \Exception("Tagihan ini sudah lunas.");

            // Catat Pembayaran
            $payment = new BillingPayments();
            $payment->patient_billing_id = $billing->id;
            $payment->payment_method_id = $rawBody['payment_method_id']; 
            $payment->amount_paid = $rawBody['amount_paid'];
            $payment->change_amount = $rawBody['change_amount'] ?? 0; // Kembalian untuk Tunai
            $payment->payment_date = date('Y-m-d H:i:s');

            if (!$payment->save()) {
                throw new \Exception("Error simpan data uang masuk.");
            }

            // Hitung akumulasi pembayaran
            $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
            
            $totalPaid = 0;
            foreach ($allPayments as $p) {
                $totalPaid += $p->amount_paid;
            }

            // Update Status Invoice
            if ($totalPaid >= $billing->total_amount) {
                $billing->status = 'Paid';
            } else {
                $billing->status = 'Partially Paid';
            }
            
            $billing->paid_amount = $totalPaid;
            $billing->save();

            $this->db->commit();

            return $this->response->setJsonContent([
                'status'  => 'success',
                'sisa_tagihan' => max(0, $billing->total_amount - $totalPaid),
                'payment_status' => $billing->status
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent(['status'  => 'error', 'message' => $e->getMessage()]);
        }
    }

    // =======================================================
    // 3. FUNGSI RIWAYAT (Mendukung List Tabel & Detail Struk)
    // =======================================================
    public function historyAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        $billingId = $this->request->getQuery('id');

        if (!$billingId) {
            // JIKA TANPA ID: Tampilkan daftar semua riwayat untuk Tabel Frontend
            $billings = PatientBillings::find(['order' => 'created_at DESC']);
            $list = [];

            foreach ($billings as $b) {
                $patientName = 'Walk-in Customer';
                if ($b->patient_id) {
                    $patient = Patients::findFirst($b->patient_id);
                    if ($patient) $patientName = $patient->name;
                }

                $list[] = [
                    'id' => $b->id,
                    'invoice_number' => $b->billing_number,
                    'created_at' => $b->created_at,
                    'patient_name' => $patientName,
                    'total_amount' => $b->total_amount,
                    'payment_status' => $b->status
                ];
            }
            return $this->response->setJsonContent(['status' => 'success', 'data' => $list]);
            
        } else {
            // JIKA ADA ID: Tampilkan detail lengkap untuk Pop-up Struk Termal
            $billing = PatientBillings::findFirst($billingId);
            if (!$billing) return $this->response->setStatusCode(404)->setJsonContent(['status' => 'error', 'message' => 'Invoice tidak ditemukan.']);

            // Join Nama Pasien
            $patientName = 'Walk-in Customer';
            if ($billing->patient_id) {
                $patient = Patients::findFirst($billing->patient_id);
                if ($patient) $patientName = $patient->name;
            }

            // Join Nama Barang/Jasa
            $details = BillingDetails::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
            $itemKeranjang = [];
            foreach ($details as $d) {
                $itemName = 'Item Dihapus';
                $itemDb = HealthcareItems::findFirst($d->healthcare_item_id);
                if ($itemDb) $itemName = $itemDb->name;

                $itemKeranjang[] = [
                    'item_name' => $itemName,
                    'quantity' => $d->qty,
                    'price' => $d->unit_price
                ];
            }

            return $this->response->setJsonContent([
                'status' => 'success',
                'data' => [
                    'id' => $billing->id,
                    'invoice_number' => $billing->billing_number,
                    'created_at' => $billing->created_at,
                    'patient_name' => $patientName,
                    'subtotal' => $billing->subtotal,
                    'tax' => $billing->tax,
                    'discount' => $billing->discount,
                    'total_amount' => $billing->total_amount,
                    'payment_status' => $billing->status,
                    'items' => $itemKeranjang
                ]
            ]);
        }
    }
}