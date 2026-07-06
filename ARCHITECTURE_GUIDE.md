# 🏗️ Architecture & Data Flow Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     WEB BROWSER                                  │
│              http://localhost:5173 (Vue 3 + Vite)               │
│                                                                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              FRONTEND APPLICATION                         │  │
│  │                                                            │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │  │
│  │  │ Customers    │  │  Products    │  │    Kasir     │    │  │
│  │  │   View       │  │    View      │  │    View      │    │  │
│  │  │              │  │              │  │              │    │  │
│  │  │ - Add        │  │ - Add Barang │  │ - Select     │    │  │
│  │  │ - Update     │  │ - Add Jasa   │  │   patient    │    │  │
│  │  │ - Delete     │  │ - Update     │  │ - Add items  │    │  │
│  │  │ - List       │  │ - Delete     │  │ - Checkout   │    │  │
│  │  └──────┬───────┘  └──────┬───────┘  │ - Split bill │    │  │
│  │         │                  │          └──────┬───────┘    │  │
│  │         │                  │                 │            │  │
│  │         └──────────┬───────┴─────────────────┘            │  │
│  │                    │                                       │  │
│  │  ┌────────────────▼─────────────────────────────────┐    │  │
│  │  │        PINIA STORE (posStore.ts)                │    │  │
│  │  │      Single Source of Truth                     │    │  │
│  │  │                                                 │    │  │
│  │  │  State:                                        │    │  │
│  │  │  ├─ patients: [] (reactive)                   │    │  │
│  │  │  ├─ products: [] (reactive)                   │    │  │
│  │  │  ├─ history: [] (reactive)                    │    │  │
│  │  │  └─ currentCheckout: {} (reactive)            │    │  │
│  │  │                                                 │    │  │
│  │  │  Actions:                                      │    │  │
│  │  │  ├─ addPatient()                              │    │  │
│  │  │  ├─ updatePatient()                           │    │  │
│  │  │  ├─ deletePatient()                           │    │  │
│  │  │  ├─ addProduct()                              │    │  │
│  │  │  ├─ updateProduct()                           │    │  │
│  │  │  ├─ deleteProduct()                           │    │  │
│  │  │  ├─ prosesCheckoutKasir()                     │    │  │
│  │  │  ├─ prosesPembayaran()                        │    │  │
│  │  │  └─ fetchHistory()                            │    │  │
│  │  │                                                 │    │  │
│  │  │  When state changes:                          │    │  │
│  │  │  ├─ All views using storeToRefs() update      │    │  │
│  │  │  └─ NO manual refresh needed! ✨             │    │  │
│  │  └────────────────┬─────────────────────────────┘    │  │
│  │                   │                                   │  │
│  │                   │ Axios HTTP Calls                  │  │
│  │                   │                                   │  │
│  │  ┌────────────────┐                                   │  │
│  │  │  History View  │                                   │  │
│  │  │  - View txn    │                                   │  │
│  │  │  - View receipt│                                   │  │
│  │  └────────────────┘                                   │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                                │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           │ HTTP/REST API Calls
                           │ (JSON format)
                           │
              ┌────────────▼──────────────┐
              │    BACKEND API SERVER    │
              │  http://localhost:8000   │
              │   (Phalcon PHP Framework)│
              │                          │
              │  ┌──────────────────┐   │
              │  │  Controllers     │   │
              │  │                  │   │
              │  │ - Patients       │   │
              │  │ - HealthcareItems│   │
              │  │ - Billings       │   │
              │  │ - Payments       │   │
              │  └────────┬─────────┘   │
              │           │             │
              │  ┌────────▼──────────┐  │
              │  │  Models/Database  │  │
              │  │                   │  │
              │  │ - Patients.php    │  │
              │  │ - HealthcareItems │  │
              │  │ - Billings        │  │
              │  │ - Payments        │  │
              │  └────────┬──────────┘  │
              │           │             │
              └───────────┼─────────────┘
                          │
                          │ SQL Queries
                          │
              ┌───────────▼──────────────┐
              │   MYSQL DATABASE (pos)  │
              │                         │
              │  Tables:                │
              │  ├─ patients            │
              │  ├─ healthcare_items    │
              │  ├─ payment_methods     │
              │  ├─ patient_billings    │
              │  ├─ billing_details     │
              │  └─ billing_payments    │
              │                         │
              │ Credentials:            │
              │ ├─ host: 127.0.0.1      │
              │ ├─ user: root           │
              │ ├─ pass: mariadb        │
              │ └─ db: pos              │
              └─────────────────────────┘
```

---

## Data Flow: Adding a Patient Example

```
1. USER ACTION (CustomersView)
   └─ Click "Tambah Pasien"
   └─ Fill form: name, phone, address
   └─ Click "Simpan"

2. FRONTEND PROCESSING
   └─ CustomersView triggers posStore.addPatient(data)
   └─ Function validates form data
   └─ Creates axios POST request

3. NETWORK CALL
   └─ POST http://localhost:8000/patients
   └─ Headers: Content-Type: application/json
   └─ Body: {name: "Budi", phone: "081...", address: "Jl..."}

4. BACKEND PROCESSING
   └─ PatientsController receives request
   └─ Validates data
   └─ Creates Patients model instance
   └─ Saves to database

5. DATABASE UPDATE
   └─ MySQL INSERT INTO patients VALUES (...)
   └─ New record with auto-increment ID
   └─ Returns success response

6. RESPONSE TO FRONTEND
   └─ Backend sends: {status: "success", message: "...", data: {id, name, ...}}
   └─ posStore.addPatient() receives response
   └─ Updates store: patients.push(newPatient)

7. PINIA REACTIVITY ✨
   └─ posStore.patients array changed
   └─ Triggers reactivity in ALL components using storeToRefs()
   └─ CustomersView re-renders: patient shows in list
   └─ PosView re-renders: patient dropdown updates
   └─ HistoryView doesn't need it but gets update anyway

8. USER SEES RESULT
   └─ Toast notification: "Pasien berhasil ditambahkan!"
   └─ Patient appears in CustomersView list
   └─ Patient AUTOMATICALLY appears in Kasir dropdown
   └─ NO REFRESH NEEDED! 🎉
```

---

## Data Flow: Processing Transaction Example

```
1. USER ACTIONS (PosView)
   └─ Select patient
   └─ Add items to cart
   └─ Click "Checkout"

2. PAYMENT MODAL
   └─ User selects payment method
   └─ Enters amount
   └─ Clicks "Bayar"

3. CHECKOUT PROCESSING
   └─ posStore.prosesCheckoutKasir() called
   └─ Creates patient_billings record (invoice header)
   └─ Creates billing_details records (line items)
   └─ Calls POST /billing endpoint

4. BACKEND SAVES TRANSACTION
   └─ BillingController receives request
   └─ Creates invoice with patient_id, total_amount
   └─ Creates line items for each product
   └─ Sets initial status: "Unpaid"
   └─ Returns transaction data

5. PAYMENT PROCESSING
   └─ posStore.prosesPembayaran() called
   └─ Amount matches total: Sets status "Paid"
   └─ Amount < total: Sets status "Partially Paid"
   └─ Creates billing_payments record
   └─ Calls POST /payment endpoint

6. RESPONSE & AUTO-REFRESH
   └─ Receipt modal shows with transaction details
   └─ posStore.prosesCheckoutKasir() completes
   └─ AUTO-triggers fetchProducts() (refresh product grid)
   └─ AUTO-triggers fetchHistory() (refresh history)

7. PINIA REACTIVITY CASCADE ✨
   └─ Store updates: products[], history[] change
   └─ PosView re-renders: product grid clears
   └─ HistoryView re-renders: new transaction appears
   └─ NO MANUAL REFRESH NEEDED! 🎉

8. USER SEES RESULT
   └─ Receipt modal visible
   └─ Kasir product grid empty (ready for next sale)
   └─ History automatically shows new transaction
   └─ Next cashier can start fresh sale
```

---

## Data Flow: Split Bill Example

```
SCENARIO: Total 200,000 - Pay in 2 installments

1. FIRST PAYMENT (100,000 by QRIS)
   └─ User selects QRIS method
   └─ Enters: 100,000
   └─ Clicks "Bayar"
   
   Backend:
   └─ Check: 100,000 < 200,000 (total)
   └─ Set status: "Partially Paid" ⚠️
   └─ Create billing_payments record #1
   └─ Calculate remaining: 100,000

2. MODAL RESPONSE
   └─ Shows: "PEMBAYARAN BERHASIL (Sebagian)"
   └─ Receipt shows remaining: 100,000
   └─ Suggests: "Lanjut Pembayaran"

3. SECOND PAYMENT (100,000 by Card)
   └─ User clicks "Lanjut Pembayaran"
   └─ Selects Card method
   └─ Enters: 100,000
   └─ Clicks "Bayar"

   Backend:
   └─ Check: 100,000 == remaining (100,000)
   └─ Update status: "Paid" ✅
   └─ Create billing_payments record #2
   └─ Final total: 100,000 + 100,000 = 200,000

4. FINAL RECEIPT
   └─ Shows: "Pembayaran LUNAS" ✅
   └─ Shows both payments:
      ├─ Payment 1: 100,000 (QRIS)
      └─ Payment 2: 100,000 (Card)

5. HISTORY SHOWS
   └─ Transaction with multiple payment methods
   └─ Total: 200,000 (sum of both)
   └─ Status: "Paid" ✅
   └─ Can view both payment records
```

---

## Reactivity Mechanism: How Pinia Updates All Views

```
┌──────────────────────────────────────┐
│     Store Action Triggered           │
│  (e.g., addPatient(), checkout())   │
└──────────────┬───────────────────────┘
               │
               │ Function executes
               │ └─ API call
               │ └─ Database update
               │ └─ Receive response
               │
               ▼
┌──────────────────────────────────────┐
│   State Update in Pinia Store        │
│   (e.g., patients.push(newPatient)) │
└──────────────┬───────────────────────┘
               │
               │ Reactivity system detects change
               │ (Pinia + Vue 3 Composition API)
               │
               ▼
┌──────────────────────────────────────┐
│  Trigger Update in ALL Components    │
│  Using storeToRefs(posStore)        │
└──────────────────────────────────────┘
               │
      ┌────────┼────────────┬────────────┐
      │        │            │            │
      ▼        ▼            ▼            ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│Customer│ │Product │ │Kasir   │ │History │
│View    │ │View    │ │View    │ │View    │
│        │ │        │ │        │ │        │
│Renders │ │Renders │ │Renders │ │Renders │
│new     │ │new     │ │new     │ │new     │
│patient │ │items   │ │options │ │txns    │
└────────┘ └────────┘ └────────┘ └────────┘
      │        │            │            │
      │        │            │            │
      └────────┴────────────┴────────────┘
               │
               ▼
        ┌──────────────────┐
        │  USER SEES ALL   │
        │  VIEWS UPDATED   │
        │  SIMULTANEOUSLY  │
        │  (no refresh!)   │
        └──────────────────┘
```

**KEY POINT**: This is why you don't need to manually refresh!
- When state changes in store
- Vue 3 reactivity system auto-updates ALL components
- Every component gets fresh data immediately
- All at the same time = synchronized data flow

---

## Component Communication Map

```
CustomersView
    │
    ├─→ posStore.addPatient()
    ├─→ posStore.updatePatient()
    ├─→ posStore.deletePatient()
    │
    └─→ Store change
        └─→ triggers PosView & HistoryView update


ProductsView
    │
    ├─→ posStore.addProduct()
    ├─→ posStore.updateProduct()
    ├─→ posStore.deleteProduct()
    │
    └─→ Store change
        └─→ triggers PosView & HistoryView update


PosView
    │
    ├─→ Uses posStore.patients (dropdown)
    ├─→ Uses posStore.products (grid)
    │
    ├─→ posStore.prosesCheckoutKasir()
    │   ├─→ Auto-calls fetchProducts() (clear grid)
    │   └─→ Auto-calls fetchHistory() (show transaction)
    │
    └─→ posStore.prosesPembayaran()
        └─→ Auto-calls fetchHistory() (update status)


HistoryView
    │
    ├─→ Uses posStore.history (transaction list)
    │
    └─→ Watches for updates
        └─→ Updates when PosView calls fetchHistory()
```

---

## Database Relationships

```
┌──────────────────┐
│    patients      │
├──────────────────┤
│ id (PK)          │
│ mrn              │
│ name             │
│ phone            │
│ address          │
│ date_of_birth    │
└────────┬─────────┘
         │
         │ 1:N
         │
         ▼
┌──────────────────────────┐
│  patient_billings        │
├──────────────────────────┤
│ id (PK)                  │
│ patient_id (FK)          │
│ invoice_number           │
│ total_amount             │
│ status                   │
│   (Unpaid/Partially/Paid)│
└────────┬─────────────────┘
         │
    ┌────┴─────────┐
    │              │
    │ 1:N          │ 1:N
    │              │
    ▼              ▼
┌──────────────────┐  ┌──────────────────────────┐
│ billing_details  │  │ billing_payments         │
├──────────────────┤  ├──────────────────────────┤
│ id (PK)          │  │ id (PK)                  │
│ billing_id (FK)  │  │ billing_id (FK)          │
│ item_id (FK) ────┼──→ (links back to invoice)  │
│ quantity         │  │ amount                   │
│ price            │  │ method                   │
│ subtotal         │  │ timestamp                │
└──────────────────┘  └──────────────────────────┘
                              ▲
                              │
                              └─ One per payment
                                 (split bill = 2+ records)


┌──────────────────────┐
│ healthcare_items     │
├──────────────────────┤
│ id (PK)              │
│ category             │
│   (obat/jasa/etc)    │
│ name                 │
│ price                │
│ stock                │
└──────────────────────┘

┌──────────────────────┐
│ payment_methods      │
├──────────────────────┤
│ id (PK)              │
│ name                 │
│   (Cash/QRIS/Card)   │
└──────────────────────┘
```

---

## API Endpoint Architecture

```
Frontend makes requests to: http://localhost:8000/

┌─────────────────────────────────────────────────────────┐
│              Backend Router (Phalcon)                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  GET / → IndexController                               │
│         └─ Returns: {status: "success", message: ...}   │
│                                                         │
│  GET /patients → PatientsController::get()              │
│         └─ Returns: {status: "success", data: [patients]}
│                                                         │
│  POST /patients → PatientsController::save()            │
│         ├─ Receives: {name, phone, address, ...}        │
│         └─ Returns: {status: "success", data: {id, ...}}│
│                                                         │
│  GET /healthcare-items → HealthcareItemsController     │
│         └─ Returns: {status: "success", data: [items]}  │
│                                                         │
│  POST /healthcare-items → HealthcareItemsController    │
│         ├─ Receives: {name, category, price, ...}      │
│         └─ Returns: {status: "success", data: {id, ...}}│
│                                                         │
│  GET /billing → BillingController::get()               │
│         └─ Returns: {status: "success", data: [txns]}   │
│                                                         │
│  POST /billing → BillingController::save()             │
│         ├─ Creates invoice + line items                 │
│         └─ Returns: transaction details                 │
│                                                         │
│  POST /payment → Payment processing                    │
│         ├─ Updates status (Unpaid/Partial/Paid)        │
│         └─ Creates billing_payments record             │
│                                                         │
└─────────────────────────────────────────────────────────┘

All responses return JSON with format:
{
  "status": "success" | "error",
  "message": "Description",
  "data": {...} | [...]
}
```

---

## Summary: Why This Architecture Works

✅ **Single Source of Truth**: Pinia store holds all data
✅ **Reactive Updates**: Vue 3 reactivity handles sync
✅ **No Manual Refresh**: Components auto-update together
✅ **Scalable**: Easy to add new views or features
✅ **Maintainable**: Clear separation of concerns
✅ **Testable**: Each component independent
✅ **Real-time Feel**: Instant UI updates
✅ **Split Bill Ready**: Database supports multi-payment

**Result**: User sees seamless data flow across all views! 🎉
