<?php
use Phalcon\Mvc\Controller;

require_once __DIR__ . '/../../vendor/autoload.php';

class BillingController extends Controller
{
    // =======================================================
    // 0. PENGATURAN KREDENSIAL ODOO
    // =======================================================
    private function getOdooConfig() {
        return [
            'url'      => "http://localhost:8069",
            'db'       => "accounting", 
            'username' => "samuelsibarani2510@gmail.com", 
            'apiKey'   => "96fe2a9d6286a40802290fcf41cd951c3fda132a"  
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

        // CEK TUNGGAKAN
        if (!empty($rawBody['patient_id'])) {
            $unpaidBill = PatientBillings::findFirst([
                'conditions' => 'patient_id = :pid: AND (status = "Unpaid" OR status = "Partially Paid")',
                'bind'       => ['pid' => $rawBody['patient_id']]
            ]);

            if ($unpaidBill) {
                $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $unpaidBill->id]]);
                $totalPaid = 0;
                foreach ($allPayments as $p) { $totalPaid += $p->amount_paid; }
                $sisaTagihan = max(0, $unpaidBill->total_amount - $totalPaid);

                return $this->response->setStatusCode(400)->setJsonContent([
                    'status'  => 'error', 
                    'message' => 'DITOLAK: Pasien ini masih memiliki tunggakan (Nota: ' . $unpaidBill->billing_number . ').',
                    'data_tunggakan' => [
                        'patient_billing_id' => $unpaidBill->id,
                        'invoice_number'     => $unpaidBill->billing_number,
                        'sisa_tagihan'       => $sisaTagihan
                    ]
                ]);
            }
        }

        $invoiceNumber = 'ZC-POS/' . date('Y/m/d') . '/' . rand(1000, 9999);
        $patientName = 'Walk-in Customer';
        $billingId = null;

        // FASE 1: SIMPAN KE DB KASIR LOKAL (DIPISAHKAN DARI ODOO)
        try {
            $this->db->begin();
            
            $billing = new PatientBillings();
            $billing->patient_id = $rawBody['patient_id'] ?? null; 
            $billing->billing_number = $invoiceNumber; 
            $billing->subtotal = $rawBody['subtotal'];
            $billing->tax = $rawBody['tax'];
            $billing->discount = $rawBody['discount'] ?? 0;
            $billing->total_amount = $rawBody['total_amount'];
            $billing->status = 'Unpaid';
            $billing->created_at = date('Y-m-d H:i:s');
            
            if (!$billing->save()) {
                $errors = []; foreach ($billing->getMessages() as $m) { $errors[] = $m->getMessage(); }
                throw new \Exception("DB Kasir (Nota): " . implode(', ', $errors));
            }
            $billingId = $billing->id;

            foreach ($rawBody['items'] as $item) {
                $detail = new BillingDetails();
                $detail->patient_billing_id = $billing->id; 
                $detail->healthcare_item_id = $item['item_id'];
                $detail->qty = $item['quantity'];
                $detail->unit_price = $item['price'];
                $detail->subtotal = $item['quantity'] * $item['price'];

                if (!$detail->save()) {
                    $errors = []; foreach ($detail->getMessages() as $m) { $errors[] = $m->getMessage(); }
                    throw new \Exception("DB Kasir (Detail): " . implode(', ', $errors));
                }

                $itemDb = HealthcareItems::findFirst($item['item_id']);
                if ($itemDb && $itemDb->category === 'obat') {
                    if ($itemDb->stock < $item['quantity']) {
                        throw new \Exception("Stok {$itemDb->name} tidak mencukupi.");
                    }
                    $itemDb->stock -= $item['quantity'];
                    $itemDb->save();
                }
            }
            
            if ($billing->patient_id) {
                $patientDb = Patients::findFirst($billing->patient_id);
                if ($patientDb) $patientName = $patientDb->name;
            }
            
            $this->db->commit(); // 🚀 SIMPAN PERMANEN DI KASIR SEKARANG
        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent([
                'status'  => 'error', 
                'message' => 'Gagal menyimpan di Kasir: ' . $e->getMessage()
            ]);
        }

        // FASE 2: COBA KIRIM KE ODOO (JIKA GAGAL, DATA KASIR TETAP AMAN)
        try {
            $odooInvoiceId = $this->sendInvoiceToOdoo($invoiceNumber, $rawBody['items'], $patientName);
            return $this->response->setJsonContent([
                'status'  => 'success',
                'message' => 'Transaksi berhasil dibuat & Tersinkronisasi dengan Odoo!',
                'billing_id' => $billingId,
                'invoice_number' => $invoiceNumber,
                'odoo_invoice_id' => $odooInvoiceId
            ]);
        } catch (\Exception $e) {
            // Mengembalikan status HTTP 200 (Bukan 500), tapi dengan label warning
            return $this->response->setJsonContent([
                'status'  => 'warning', 
                'message' => 'Tersimpan di Kasir, TAPI Gagal Sinkron Odoo: ' . $e->getMessage(),
                'billing_id' => $billingId,
                'invoice_number' => $invoiceNumber
            ]);
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
        $paymentData = [];

        // FASE 1: SIMPAN PEMBAYARAN KE KASIR LOKAL
        try {
            $this->db->begin();

            $billing = PatientBillings::findFirst($rawBody['patient_billing_id']);
            if (!$billing) throw new \Exception("Data tagihan tidak ditemukan di DB Lokal.");
            if ($billing->status === 'Paid') throw new \Exception("Tagihan ini sudah lunas.");

            $payment = new BillingPayments();
            $payment->patient_billing_id = $billing->id;
            $payment->payment_method_id = $rawBody['payment_method_id']; 
            $payment->amount_paid = $rawBody['amount_paid'];
            $payment->change_amount = $rawBody['change_amount'] ?? 0;
            $payment->payment_date = date('Y-m-d H:i:s');

            if (!$payment->save()) {
                $errors = []; foreach ($payment->getMessages() as $m) { $errors[] = $m->getMessage(); }
                throw new \Exception("Gagal simpan pembayaran: " . implode(', ', $errors));
            }

            $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $billing->id]]);
            $totalPaid = 0;
            $paymentSequence = count($allPayments); 

            foreach ($allPayments as $p) { $totalPaid += $p->amount_paid; }

            $sisaTagihanReal = max(0, $billing->total_amount - $totalPaid);
            if ($totalPaid >= $billing->total_amount) {
                $billing->status = 'Paid';
            } else {
                $billing->status = 'Partially Paid';
            }
            
            $billing->paid_amount = $totalPaid;
            $billing->save();
            
            $this->db->commit(); // 🚀 SIMPAN PERMANEN DI KASIR SEKARANG

            $methodNames = [ 1 => 'Uang Tunai (Cash)', 2 => 'QRIS / E-Wallet', 3 => 'Kartu Debit / Kredit' ];
            $methodString = isset($methodNames[$payment->payment_method_id]) ? $methodNames[$payment->payment_method_id] : 'Lainnya';

            $paymentData = [
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
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->response->setStatusCode(500)->setJsonContent([
                'status'  => 'error', 
                'message' => 'Proses Bayar di Kasir Gagal: ' . $e->getMessage()
            ]);
        }

        // FASE 2: COBA SINKRON PEMBAYARAN KE ODOO
        try {
            $this->syncPaymentToOdoo($billing->billing_number, $payment->amount_paid, $payment->payment_method_id);
            
            return $this->response->setJsonContent(array_merge([
                'status'  => 'success',
                'message' => 'Pembayaran lunas & Sinkron ke Odoo'
            ], $paymentData));

        } catch (\Exception $e) {
            return $this->response->setJsonContent(array_merge([
                'status'  => 'warning',
                'message' => 'Pembayaran Kasir SUKSES, TAPI gagal ke Odoo: ' . $e->getMessage()
            ], $paymentData));
        }
    }

    // =======================================================
    // 3. FUNGSI RIWAYAT
    // =======================================================
    public function historyAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        $id = $this->request->getQuery('id');
        $type = $this->request->getQuery('type');

        if (!$id) {
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
    // 4. JARINGAN UTAMA ODOO (ERROR HANDLER)
    // =======================================================
    private function odooJsonRpc($url, $service, $method, $args, $kwargs = [])
    {
        $rpcUrl = rtrim($url, '/') . '/jsonrpc';

        $payloadParams = ['service' => $service, 'method'  => $method, 'args'    => $args];
        if (!empty($kwargs)) $payloadParams['args'][] = $kwargs;

        $payload = json_encode(['jsonrpc' => '2.0', 'method'  => 'call', 'params'  => $payloadParams, 'id' => rand(1, 1000)]);
        $context  = stream_context_create(['http' => ['header'  => "Content-Type: application/json\r\n", 'method'  => 'POST', 'content' => $payload, 'ignore_errors' => true]]);
        
        $result = file_get_contents($rpcUrl, false, $context);
        if ($result === false) throw new \Exception("Server Odoo tidak merespon.");
        
        $response = json_decode($result, true);

        if (isset($response['error'])) {
            $errorMsg = isset($response['error']['data']['message']) ? $response['error']['data']['message'] : '';
            $errorDebug = isset($response['error']['data']['debug']) ? substr($response['error']['data']['debug'], 0, 150) . '...' : '';
            throw new \Exception("ODOO REJECTED: " . $errorMsg . " | " . $errorDebug);
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
        if (!$uid) throw new \Exception("Kredensial Odoo Gagal.");

        $partnerIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'search', [[['name', '=', $customerName]]]]);
        $partnerId = !empty($partnerIds) ? $partnerIds[0] : $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'create', [['name' => $customerName]]]);

        $invoiceLines = [];
        foreach ($items as $item) {
            $itemDb = HealthcareItems::findFirst($item['item_id']);
            $itemName = $itemDb ? $itemDb->name : 'Item POS (ID: ' . $item['item_id'] . ')';

            $productIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'search', [[['name', '=', $itemName]]]]);
            if (!empty($productIds)) { 
                $productId = $productIds[0]; 
            } else {
                $productId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'create', [['name' => $itemName, 'list_price' => (float) $item['price']]]]);
            }
            $invoiceLines[] = [0, 0, ['product_id' => $productId, 'name' => $itemName, 'quantity' => (float) $item['quantity'], 'price_unit' => (float) $item['price']]];
        }

        $invoice_id = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
            $config['db'], $uid, $config['apiKey'], 'account.move', 'create',
            [['move_type' => 'out_invoice', 'partner_id' => $partnerId, 'invoice_date' => date('Y-m-d'), 'ref' => 'POS / ' . $invoiceNumber, 'invoice_line_ids' => $invoiceLines]]
        ]);

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
        if (!$uid) throw new \Exception("Autentikasi Odoo Gagal saat Sync Pembayaran.");

        $invoiceIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'search', [[['ref', '=', 'POS / ' . $invoiceNumber]]]]);
        if (empty($invoiceIds)) $invoiceIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'search', [[['ref', 'ilike', $invoiceNumber]]]]);
        if (empty($invoiceIds)) $invoiceIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'search', [[['name', 'ilike', $invoiceNumber]]]]);

        if (empty($invoiceIds)) throw new \Exception("Invoice tidak ditemukan di DB Odoo.");
        $invoiceId = $invoiceIds[0];

        $invoiceData = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'read', [[$invoiceId], ['state']]]);
        $invoiceState = $invoiceData[0]['state'] ?? 'draft';

        if ($invoiceState === 'draft') {
            $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'action_post', [[$invoiceId]]]);
        }

        $journalType = ($paymentMethodId == 1) ? 'cash' : 'bank';
        $journalIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.journal', 'search', [[['type', '=', $journalType]]]]);
        
        if (empty($journalIds)) $journalIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.journal', 'search', [[['type', 'in', ['cash', 'bank', 'general', 'sale']]]]]);
        if (empty($journalIds)) throw new \Exception("Tidak ada Journal Akuntansi di Odoo.");
        $journalId = $journalIds[0]; 

        $wizardArgs = [
            'amount' => (float) $amount, 
            'payment_date' => date('Y-m-d'), 
            'journal_id' => $journalId
        ];
        
        $wizardId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
            $config['db'], $uid, $config['apiKey'], 'account.payment.register', 'create',
            [$wizardArgs], 
            ['context' => ['active_model' => 'account.move', 'active_ids' => [$invoiceId]]]
        ]);
        
        $wizardIdNum = is_array($wizardId) ? $wizardId[0] : $wizardId;

        if ($wizardIdNum) {
            $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'], 'account.payment.register', 'action_create_payments', [[$wizardIdNum]]
            ]);
        } else {
            throw new \Exception("Gagal Register Payment Odoo.");
        }
    }

    // =======================================================
    // 7. FUNGSI UNTUK MENGECEK TUNGGAKAN PASIEN
    // =======================================================
    public function checkDebtAction()
    {
        $this->response->setContentType('application/json', 'UTF-8');
        $patientId = $this->request->getQuery('patient_id');

        if (!$patientId) {
            return $this->response->setStatusCode(400)->setJsonContent(['status' => 'error', 'message' => 'Parameter patient_id diperlukan.']);
        }

        $unpaidBills = PatientBillings::find(['conditions' => 'patient_id = :pid: AND (status = "Unpaid" OR status = "Partially Paid")', 'bind' => ['pid' => $patientId]]);

        $debts = [];
        foreach ($unpaidBills as $bill) {
            $allPayments = BillingPayments::find(['conditions' => 'patient_billing_id = :id:', 'bind' => ['id' => $bill->id]]);
            $totalPaid = 0;
            foreach ($allPayments as $p) { $totalPaid += $p->amount_paid; }
            
            $sisaTagihan = max(0, $bill->total_amount - $totalPaid);
            
            if ($sisaTagihan > 0) {
                $debts[] = [
                    'patient_billing_id' => $bill->id,
                    'invoice_number'     => $bill->billing_number,
                    'total_amount'       => $bill->total_amount,
                    'paid_amount'        => $totalPaid,
                    'sisa_tagihan'       => $sisaTagihan,
                    'status'             => $bill->status,
                    'created_at'         => $bill->created_at
                ];
            }
        }

        return $this->response->setJsonContent(['status' => 'success', 'has_debt' => count($debts) > 0, 'data' => $debts]);
    }
}