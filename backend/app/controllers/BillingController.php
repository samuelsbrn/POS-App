<?php
use Phalcon\Mvc\Controller;

// Note: Composer autoload dapat di-uncomment jika diperlukan integrasi Odoo
// require_once __DIR__ . '/../../vendor/autoload.php';

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
            
            $invoiceNumber = 'ZC-POS/' . date('Y/m/d') . '/' . rand(1000, 9999);

            $billing = new PatientBillings();
            $billing->patient_id = $rawBody['patient_id'] ?? null; 
            $billing->billing_number = $invoiceNumber; 
            $billing->subtotal = $rawBody['subtotal'];
            $billing->tax = $rawBody['tax'];
            $billing->discount = $rawBody['discount'] ?? 0;
            $billing->total_amount = $rawBody['total_amount'];
            $billing->status = 'Unpaid';
            $billing->created_at = date('Y-m-d H:i:s');
            
            if (!$billing->save()) throw new \Exception("Error simpan Nota Utama.");

            foreach ($rawBody['items'] as $item) {
                $detail = new BillingDetails();
                $detail->patient_billing_id = $billing->id; 
                $detail->healthcare_item_id = $item['item_id'];
                $detail->qty = $item['quantity'];
                $detail->unit_price = $item['price'];
                $detail->subtotal = $item['quantity'] * $item['price'];

                if (!$detail->save()) throw new \Exception("Error simpan Keranjang.");

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
                'billing_id' => $billing->id,
                'invoice_number' => $invoiceNumber
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

            $payment = new BillingPayments();
            $payment->patient_billing_id = $billing->id;
            $payment->payment_method_id = $rawBody['payment_method_id']; 
            $payment->amount_paid = $rawBody['amount_paid'];
            $payment->change_amount = $rawBody['change_amount'] ?? 0;
            $payment->payment_date = date('Y-m-d H:i:s');

            if (!$payment->save()) throw new \Exception("Error simpan data uang masuk.");

            $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
            $totalPaid = 0;
            $paymentSequence = count($allPayments); 

            foreach ($allPayments as $p) {
                $totalPaid += $p->amount_paid;
            }

            $sisaTagihanReal = max(0, $billing->total_amount - $totalPaid);

            if ($totalPaid >= $billing->total_amount) {
                $billing->status = 'Paid';
            } else {
                $billing->status = 'Partially Paid';
            }
            
            $billing->paid_amount = $totalPaid;
            $billing->save();

            $this->db->commit();

            $methodNames = [ 1 => 'Uang Tunai (Cash)', 2 => 'QRIS / E-Wallet', 3 => 'Kartu Debit / Kredit' ];
            $methodString = isset($methodNames[$payment->payment_method_id]) ? $methodNames[$payment->payment_method_id] : 'Lainnya';

            return $this->response->setJsonContent([
                'status'  => 'success',
                'sisa_tagihan' => $sisaTagihanReal,
                'payment_status' => $billing->status,
                'receipt' => [
                    'receipt_number' => $billing->billing_number . '-' . $paymentSequence,
                    'sequence' => $paymentSequence,
                    'method_name' => $methodString,
                    'amount_paid' => $payment->amount_paid,
                    'change_amount' => $payment->change_amount,
                    'sisa_tagihan' => $sisaTagihanReal,
                    'total_tagihan' => $billing->total_amount
                ]
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent(['status'  => 'error', 'message' => $e->getMessage()]);
        }
    }

    // =======================================================
    // 3. FUNGSI RIWAYAT (DIUBAH AGAR STRUK/PEMBAYARAN TERPISAH DI TABEL)
    // =======================================================
    public function historyAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        $id = $this->request->getQuery('id');
        $type = $this->request->getQuery('type');

        if (!$id) {
            // MENGAMBIL DAFTAR UNTUK TABEL UTAMA
            $billings = PatientBillings::find();
            $list = [];

            foreach ($billings as $b) {
                $patientName = 'Walk-in Customer';
                if ($b->patient_id) {
                    $patient = Patients::findFirst($b->patient_id);
                    if ($patient) $patientName = $patient->name;
                }

                // Cek apakah tagihan ini punya pembayaran yang displit
                $payments = BillingPayments::find([
                    'conditions' => 'patient_billing_id = :id:', 
                    'bind' => ['id' => $b->id], 
                    'order' => 'id ASC'
                ]);

                if (count($payments) == 0) {
                    // Jika belum pernah dibayar sama sekali (Unpaid)
                    $list[] = [
                        'id' => $b->id,
                        'type' => 'billing',
                        'invoice_number' => $b->billing_number,
                        'created_at' => $b->created_at,
                        'patient_name' => $patientName,
                        'total_amount' => $b->total_amount,
                        'payment_method' => '-',
                        'payment_status' => $b->status
                    ];
                } else {
                    // Jika sudah dibayar (baik lunas maupun split), BUAT BARIS TERPISAH UNTUK TIAP PEMBAYARAN!
                    $seq = 1;
                    foreach ($payments as $p) {
                        $methodNames = [ 1 => 'Uang Tunai (Cash)', 2 => 'QRIS / E-Wallet', 3 => 'Kartu Debit / Kredit' ];
                        $methodString = isset($methodNames[$p->payment_method_id]) ? $methodNames[$p->payment_method_id] : 'Lainnya';
                        
                        $list[] = [
                            'id' => $p->id, // Menggunakan ID Pembayaran untuk Pop-up struk
                            'type' => 'payment',
                            'invoice_number' => $b->billing_number . '-' . $seq,
                            'created_at' => $p->payment_date,
                            'patient_name' => $patientName,
                            'total_amount' => $p->amount_paid, // Nominal di tabel sesuai dengan jumlah yang dibayar
                            'payment_method' => $methodString,
                            'payment_status' => 'Paid (Split ' . $seq . ')'
                        ];
                        $seq++;
                    }
                }
            }

            // Urutkan List berdasarkan waktu terbaru di atas
            usort($list, function($a, $b) {
                $timeA = strtotime($a['created_at']);
                $timeB = strtotime($b['created_at']);
                if ($timeA == $timeB) return 0;
                return ($timeA < $timeB) ? 1 : -1;
            });

            return $this->response->setJsonContent(['status' => 'success', 'data' => $list]);
            
        } else {
            // MENGAMBIL DETAIL UNTUK POP-UP STRUK
            if ($type === 'payment') {
                $payment = BillingPayments::findFirst($id);
                if (!$payment) return $this->response->setStatusCode(404)->setJsonContent(['status' => 'error', 'message' => 'Payment tidak ditemukan.']);
                
                $billing = PatientBillings::findFirst($payment->patient_billing_id);
                $patientName = 'Walk-in Customer';
                if ($billing->patient_id) {
                    $patient = Patients::findFirst($billing->patient_id);
                    if ($patient) $patientName = $patient->name;
                }

                // Hitung ini split ke berapa dan sisa tagihannya
                $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id: AND id <= :pid:', 'bind' => ['id' => $billing->id, 'pid' => $payment->id]]);
                $seq = count($allPayments);
                
                $totalPaidUpToNow = 0;
                foreach ($allPayments as $ap) {
                    $totalPaidUpToNow += $ap->amount_paid;
                }
                $sisa = max(0, $billing->total_amount - $totalPaidUpToNow);

                $details = BillingDetails::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
                $itemKeranjang = [];
                foreach ($details as $d) {
                    $itemDb = HealthcareItems::findFirst($d->healthcare_item_id);
                    $itemKeranjang[] = [
                        'item_name' => $itemDb ? $itemDb->name : 'Item Dihapus',
                        'quantity' => $d->qty,
                        'price' => $d->unit_price
                    ];
                }

                $methodNames = [ 1 => 'Uang Tunai (Cash)', 2 => 'QRIS / E-Wallet', 3 => 'Kartu Debit / Kredit' ];
                $methodString = isset($methodNames[$payment->payment_method_id]) ? $methodNames[$payment->payment_method_id] : 'Lainnya';

                return $this->response->setJsonContent([
                    'status' => 'success',
                    'data' => [
                        'is_split' => true,
                        'invoice_number' => $billing->billing_number . '-' . $seq,
                        'created_at' => $payment->payment_date,
                        'patient_name' => $patientName,
                        'total_tagihan_utama' => $billing->total_amount,
                        'items' => $itemKeranjang,
                        'split_sequence' => $seq,
                        'method_name' => $methodString,
                        'amount_paid' => $payment->amount_paid,
                        'change_amount' => $payment->change_amount,
                        'sisa_tagihan' => $sisa
                    ]
                ]);
            } else {
                // TYPE == 'billing' (Berarti invoice belum pernah dibayar / Unpaid)
                $billing = PatientBillings::findFirst($id);
                if (!$billing) return $this->response->setStatusCode(404)->setJsonContent(['status' => 'error', 'message' => 'Invoice tidak ditemukan.']);

                $patientName = 'Walk-in Customer';
                if ($billing->patient_id) {
                    $patient = Patients::findFirst($billing->patient_id);
                    if ($patient) $patientName = $patient->name;
                }

                $details = BillingDetails::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
                $itemKeranjang = [];
                foreach ($details as $d) {
                    $itemDb = HealthcareItems::findFirst($d->healthcare_item_id);
                    $itemKeranjang[] = [
                        'item_name' => $itemDb ? $itemDb->name : 'Item Dihapus',
                        'quantity' => $d->qty,
                        'price' => $d->unit_price
                    ];
                }

                return $this->response->setJsonContent([
                    'status' => 'success',
                    'data' => [
                        'is_split' => false,
                        'invoice_number' => $billing->billing_number,
                        'created_at' => $billing->created_at,
                        'patient_name' => $patientName,
                        'total_tagihan_utama' => $billing->total_amount,
                        'payment_status' => $billing->status,
                        'items' => $itemKeranjang
                    ]
                ]);
            }
        }
    }

    // =======================================================
    // 4. FUNGSI BARU UNTUK SINKRONISASI KE ODOO 19 ACCOUNTING
    // =======================================================
    private function sendInvoiceToOdoo($invoiceNumber, $items, $customerName)
    {
        // Ganti Kredensial di bawah dengan kredensial Odoo lokal Anda
        $url      = "http://localhost:8069";
        $db       = "odoo19_db"; 
        $username = "admin";     
        $password = "admin";     

        // 1. Autentikasi
        $common = \Ripcord\Ripcord::client("$url/xmlrpc/2/common");
        $uid = $common->authenticate($db, $username, $password, []);

        if (!$uid) {
            throw new \Exception("Kredensial Odoo salah atau server mati.");
        }

        // 2. Format Item Keranjang ke format One2many Odoo: [0, 0, { values }]
        $invoiceLines = [];
        foreach ($items as $item) {
            $itemDb = HealthcareItems::findFirst($item['item_id']);
            $itemName = $itemDb ? $itemDb->name : 'Item POS (ID: ' . $item['item_id'] . ')';

            $invoiceLines[] = [0, 0, [
                'name'       => $itemName,
                'quantity'   => (float) $item['quantity'],
                'price_unit' => (float) $item['price'],
            ]];
        }

        // 3. Eksekusi Create Data
        $models = \Ripcord\Ripcord::client("$url/xmlrpc/2/object");

        $invoice_id = $models->execute_kw($db, $uid, $password,
            'account.move', 'create',
            [[
                'move_type'        => 'out_invoice', 
                'invoice_date'     => date('Y-m-d'),
                'ref'              => 'POS / ' . $invoiceNumber,
                'invoice_line_ids' => $invoiceLines
            ]]
        );

        return $invoice_id;
    }
}