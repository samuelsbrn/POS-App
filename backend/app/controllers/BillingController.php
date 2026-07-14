<?php
use Phalcon\Mvc\Controller;

require_once __DIR__ . '/../../vendor/autoload.php';

class BillingController extends Controller
{
    // =======================================================
    // 0. PENGATURAN KREDENSIAL ODOO (UBAH SESUAI ODOO ANDA!)
    // =======================================================
    private function getOdooConfig() {
        return [
            'url'      => "http://localhost:8069/jsonrpc",
            'db'       => "pos_accounting", // Pastikan nama database Odoo Anda persis ini
            'username' => "admin",          // Email / Username login Odoo
            'apiKey'   => "admin"           // Password / API Key login Odoo
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

        if (!empty($rawBody['patient_id'])) {
            $unpaidBill = PatientBillings::findFirst([
                'conditions' => 'patient_id = :pid: AND (status = "Unpaid" OR status = "Partially Paid")',
                'bind'       => ['pid' => $rawBody['patient_id']]
            ]);

            if ($unpaidBill) {
                return $this->response->setStatusCode(400)->setJsonContent([
                    'status'  => 'error', 
                    'message' => 'DITOLAK: Pasien ini masih memiliki tunggakan cicilan (Nota: ' . $unpaidBill->billing_number . '). Harap lunasi tagihan sebelumnya!'
                ]);
            }
        }

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
            
            $patientName = 'Walk-in Customer';
            if ($billing->patient_id) {
                $patientDb = Patients::findFirst($billing->patient_id);
                if ($patientDb) $patientName = $patientDb->name;
            }

            // 🚀 WAJIB BERHASIL SINKRON INVOICE ODOO SEBELUM DISIMPAN KE DB KASIR
            $odooInvoiceId = $this->sendInvoiceToOdoo($invoiceNumber, $rawBody['items'], $patientName);
            
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

            // 🚀 WAJIB BERHASIL SINKRON PAYMENT ODOO SEBELUM DISIMPAN KE DB KASIR
            $this->syncPaymentToOdoo($billing->billing_number, $payment->amount_paid, $payment->payment_method_id);
            
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
    // 4. JARINGAN UTAMA ODOO (MENANGKAP ERROR DENGAN JELAS)
    // =======================================================
    private function odooJsonRpc($url, $service, $method, $args, $kwargs = [])
    {
        $payloadParams = ['service' => $service, 'method'  => $method, 'args'    => $args];
        if (!empty($kwargs)) $payloadParams['args'][] = $kwargs;

        $payload = json_encode(['jsonrpc' => '2.0', 'method'  => 'call', 'params'  => $payloadParams, 'id' => rand(1, 1000)]);
        $context  = stream_context_create(['http' => ['header'  => "Content-Type: application/json\r\n", 'method'  => 'POST', 'content' => $payload, 'ignore_errors' => true]]);
        
        $result = file_get_contents($url, false, $context);
        if ($result === false) throw new \Exception("Server Odoo tidak merespon (Pastikan Odoo menyala dan URL benar).");
        
        $response = json_decode($result, true);

        if (isset($response['error'])) {
            $errorMsg = isset($response['error']['data']['message']) ? $response['error']['data']['message'] : json_encode($response['error']);
            throw new \Exception("ODOO ERROR: " . $errorMsg);
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
        if (!$uid) throw new \Exception("Autentikasi Odoo Gagal: Periksa nama DB, Username, atau Password Odoo Anda.");

        $partnerIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'search', [[['name', '=', $customerName]]]]);
        $partnerId = !empty($partnerIds) ? $partnerIds[0] : $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'res.partner', 'create', [['name' => $customerName]]]);

        $invoiceLines = [];
        foreach ($items as $item) {
            $itemDb = HealthcareItems::findFirst($item['item_id']);
            $itemName = $itemDb ? $itemDb->name : 'Item POS (ID: ' . $item['item_id'] . ')';

            $productIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'search', [[['name', '=', $itemName]]]]);
            if (!empty($productIds)) { $productId = $productIds[0]; } else {
                $productId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'product.product', 'create', [['name' => $itemName, 'list_price' => (float) $item['price'], 'type' => 'consu']]]);
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
        if (!$uid) throw new \Exception("Autentikasi Odoo Gagal saat memproses pembayaran.");

        $invoiceIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'search', [[['ref', '=', 'POS / ' . $invoiceNumber]]]]);
        if (empty($invoiceIds)) throw new \Exception("Referensi Invoice (POS / {$invoiceNumber}) tidak ditemukan di Odoo. Sinkronisasi Invoice sebelumnya mungkin gagal.");
        $invoiceId = $invoiceIds[0];

        $invoiceData = $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'read', [[$invoiceId], ['state', 'partner_id']]]);
        $invoiceState = $invoiceData[0]['state'] ?? 'draft';
        $partnerId = $invoiceData[0]['partner_id'][0] ?? null;

        if ($invoiceState === 'draft') {
            $this->odooJsonRpc($config['url'], "object", "execute_kw", [$config['db'], $uid, $config['apiKey'], 'account.move', 'action_post', [[$invoiceId]]]);
        }

        $journalType = ($paymentMethodId == 1) ? 'cash' : 'bank';
        $journalIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
            $config['db'], $uid, $config['apiKey'], 'account.journal', 'search', 
            [[['type', '=', $journalType]]]
        ]);

        if (empty($journalIds)) {
            $journalIds = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'], 'account.journal', 'search', 
                [[['type', 'in', ['cash', 'bank', 'general', 'sale']]]]
            ]);
        }

        if (empty($journalIds)) {
            throw new \Exception("Tidak ada satupun Journal Akuntansi di Odoo Anda. Buat Journal tipe Bank/Cash terlebih dahulu.");
        }
        $journalId = $journalIds[0]; 

        try {
            $wizardArgs = [
                'amount' => (float) $amount, 
                'payment_date' => date('Y-m-d'),
                'journal_id' => $journalId 
            ];

            $wizardId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'], 'account.payment.register', 'create',
                [[$wizardArgs]], ['context' => ['active_model' => 'account.move', 'active_ids' => [$invoiceId]]]
            ]);

            if ($wizardId) {
                $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                    $config['db'], $uid, $config['apiKey'], 'account.payment.register', 'action_create_payments', [[$wizardId]]
                ]);
                return; 
            }
        } catch (\Exception $e) {
            // Fallback jika Wizard ditolak oleh Odoo
            if (!$partnerId) throw new \Exception("Partner ID kosong, tidak bisa melakukan fallback.");

            $pmLines = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'], 'account.payment.method.line', 'search', 
                [[['payment_type', '=', 'inbound'], ['journal_id', '=', $journalId]]]
            ]);
            
            $paymentArgs = [
                'payment_type' => 'inbound',
                'partner_type' => 'customer',
                'partner_id' => $partnerId,
                'amount' => (float) $amount,
                'date' => date('Y-m-d'),
                'journal_id' => $journalId, 
                'ref' => 'POS / ' . $invoiceNumber . ' (Sync Manual)'
            ];

            if (!empty($pmLines)) {
                $paymentArgs['payment_method_line_id'] = $pmLines[0];
            }

            $paymentId = $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                $config['db'], $uid, $config['apiKey'], 'account.payment', 'create', [[$paymentArgs]]
            ]);

            if ($paymentId) {
                $this->odooJsonRpc($config['url'], "object", "execute_kw", [
                    $config['db'], $uid, $config['apiKey'], 'account.payment', 'action_post', [[$paymentId]]
                ]);
            } else {
                 throw new \Exception("Sistem Fallback juga gagal membuat Payment di Odoo.");
            }
        }
    }
}