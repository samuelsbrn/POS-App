<?php
use Phalcon\Mvc\Controller;

require_once __DIR__ . '/../../vendor/autoload.php';

class BillingController extends Controller
{
    // =======================================================
    // 0. PENGATURAN KREDENSIAL ODOO (UBAH DI SINI SAJA)
    // =======================================================
    private function getOdooConfig() {
        return [
            'url'      => "http://localhost:8069/jsonrpc",
            'db'       => "pos_accounting", // <-- PASTIKAN INI BENAR
            'username' => "admin",          // <-- PASTIKAN INI BENAR
            'apiKey'   => "admin"           // <-- PASTIKAN INI BENAR (Gunakan password/API Key yang berhasil di tester)
        ];
    }

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

            $patientName = 'Walk-in Customer';
            if ($billing->patient_id) {
                $patientDb = Patients::findFirst($billing->patient_id);
                if ($patientDb) $patientName = $patientDb->name;
            }

            // 🚀 KIRIM INVOICE DAN PRODUK KE ODOO
            try {
                $odooInvoiceId = $this->sendInvoiceToOdoo($invoiceNumber, $rawBody['items'], $patientName);
                $odooStatus = "Sukses dikirim ke Odoo";
            } catch (\Exception $e) {
                error_log("Odoo Invoice Sync Error: " . $e->getMessage());
                $odooStatus = "Gagal kirim ke Odoo: " . $e->getMessage();
            }

            return $this->response->setJsonContent([
                'status'  => 'success',
                'message' => 'Transaksi berhasil dibuat!',
                'billing_id' => $billing->id,
                'invoice_number' => $invoiceNumber,
                'odoo_sync_status' => $odooStatus
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent(['status'  => 'error', 'message' => $e->getMessage()]);
        }
    }

    // =======================================================
    // 2. FUNGSI UNTUK MENCATAT PEMBAYARAN
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

            //  KIRIM DATA PEMBAYARAN KE ODOO
            try {
                $this->syncPaymentToOdoo($billing->billing_number, $payment->amount_paid, $payment->payment_method_id);
            } catch (\Exception $e) {
                error_log("Odoo Payment Sync Error: " . $e->getMessage());
            }

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
    // 3. FUNGSI RIWAYAT (SUDAH LENGKAP KEMBALI)
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

                $payments = BillingPayments::find([
                    'conditions' => 'patient_billing_id = :id:', 
                    'bind' => ['id' => $b->id], 
                    'order' => 'id ASC'
                ]);

                if (count($payments) == 0) {
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
                    $seq = 1;
                    foreach ($payments as $p) {
                        $methodNames = [ 1 => 'Uang Tunai (Cash)', 2 => 'QRIS / E-Wallet', 3 => 'Kartu Debit / Kredit' ];
                        $methodString = isset($methodNames[$p->payment_method_id]) ? $methodNames[$p->payment_method_id] : 'Lainnya';
                        
                        $list[] = [
                            'id' => $p->id, 
                            'type' => 'payment',
                            'invoice_number' => $b->billing_number . '-' . $seq,
                            'created_at' => $p->payment_date,
                            'patient_name' => $patientName,
                            'total_amount' => $p->amount_paid,
                            'payment_method' => $methodString,
                            'payment_status' => 'Paid (Split ' . $seq . ')'
                        ];
                        $seq++;
                    }
                }
            }

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
    // 4. JARINGAN UTAMA ODOO (DENGAN KWARGS)
    // =======================================================
    private function odooJsonRpc($url, $service, $method, $args, $kwargs = [])
    {
        $payloadParams = [
            'service' => $service,
            'method'  => $method,
            'args'    => $args
        ];
        
        // Odoo Wizard membutuhkan arguments context/kwargs di index terakhir
        if (!empty($kwargs)) {
            $payloadParams['args'][] = $kwargs;
        }

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => $payloadParams,
            'id' => rand(1, 1000)
        ]);

        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => $payload,
                'ignore_errors' => true
            ]
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) throw new \Exception("Gagal menghubungi server Odoo.");
        $response = json_decode($result, true);

        if (isset($response['error'])) {
            $errorMsg = isset($response['error']['data']['message']) ? $response['error']['data']['message'] : json_encode($response['error']);
            throw new \Exception($errorMsg);
        }

        return $response['result'] ?? null;
    }

    // =======================================================
    // 5. AUTO-CREATE PRODUK & INVOICE ODOO
    // =======================================================
    private function sendInvoiceToOdoo($invoiceNumber, $items, $customerName)
    {
        $config = $this->getOdooConfig();
        $uid = $this->odooJsonRpc($config['url'], "common", "authenticate", [$config['db'], $config['username'], $config['apiKey'], []]);
        if (!$uid) throw new \Exception("Autentikasi Odoo gagal.");

        // Cari atau Buat Pelanggan
        $partnerIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'search', [[['name', '=', $customerName]]]]);
        $partnerId = !empty($partnerIds) ? $partnerIds[0] : $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'create', [['name' => $customerName]]]);

        // Cari atau Buat Produk (Otomatis Sync Master Data Barang)
        $invoiceLines = [];
        foreach ($items as $item) {
            $itemDb = HealthcareItems::findFirst($item['item_id']);
            $itemName = $itemDb ? $itemDb->name : 'Item POS (ID: ' . $item['item_id'] . ')';

            $productIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'search', [[['name', '=', $itemName]]]]);
            
            if (!empty($productIds)) {
                $productId = $productIds[0];
            } else {
                // Auto-create produk baru di Odoo jika belum ada
                $productId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'create', [[
                    'name' => $itemName,
                    'list_price' => (float) $item['price'],
                    'type' => 'consu' // Tipe 'Consumable'
                ]]]);
            }

            $invoiceLines[] = [0, 0, [
                'product_id' => $productId, // Terhubung ke Master Data Produk Odoo
                'name'       => $itemName,
                'quantity'   => (float) $item['quantity'],
                'price_unit' => (float) $item['price'],
            ]];
        }

        // Buat Draft Invoice
        $invoice_id = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'],
            'account.move', 'create',
            [[
                'move_type'        => 'out_invoice', 
                'partner_id'       => $partnerId, 
                'invoice_date'     => date('Y-m-d'),
                'ref'              => 'POS / ' . $invoiceNumber,
                'invoice_line_ids' => $invoiceLines
            ]]
        ]);

        // Post / Verifikasi Invoice
        if ($invoice_id) {
            $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'action_post', [[$invoice_id]]]);
        }

        return $invoice_id;
    }

    // =======================================================
    // 6. AUTO-SYNC PEMBAYARAN KE ODOO
    // =======================================================
    private function syncPaymentToOdoo($invoiceNumber, $amount, $paymentMethodId)
    {
        $config = $this->getOdooConfig();
        $uid = $this->odooJsonRpc($config['url'], "common", "authenticate", [$config['db'], $config['username'], $config['apiKey'], []]);
        if (!$uid) return;

        // 1. Cari ID Invoice berdasarkan Nomor Nota POS
        $invoiceIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'search', [[['ref', '=', 'POS / ' . $invoiceNumber]]]]);
        if (empty($invoiceIds)) return; // Jika tidak ketemu, lewati
        $invoiceId = $invoiceIds[0];

        // 2. Pilih Jurnal (Kas/Bank) berdasarkan pilihan kasir
        $journalType = ($paymentMethodId == 1) ? 'cash' : 'bank';
        $journalIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.journal', 'search', [[['type', '=', $journalType]]]]);
        $journalId = !empty($journalIds) ? $journalIds[0] : null;

        $wizardArgs = [
            'amount' => (float) $amount,
            'payment_date' => date('Y-m-d')
        ];
        if ($journalId) $wizardArgs['journal_id'] = $journalId;

        // 3. Daftarkan Pembayaran di Odoo (Memanggil Wizard Payment)
        $wizardId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
            $config['db'], $uid, $config['apiKey'],
            'account.payment.register', 'create',
            [[$wizardArgs]], // Parameter data
            ['context' => ['active_model' => 'account.move', 'active_ids' => [$invoiceId]]] // Identitas Invoice
        ]);

        // 4. Eksekusi Pembayaran
        if ($wizardId) {
            $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'],
                'account.payment.register', 'action_create_payments',
                [[$wizardId]]
            ]);
        }
    }
}