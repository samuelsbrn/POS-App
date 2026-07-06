-- Buat tipe data ENUM khusus untuk PostgreSQL
CREATE TYPE item_category AS ENUM ('obat', 'tindakan_medis', 'laboratorium', 'administrasi');
CREATE TYPE billing_status AS ENUM ('unpaid', 'partial', 'paid');

-- 1. Tabel Pasien 
CREATE TABLE IF NOT EXISTS patients (
    id SERIAL PRIMARY KEY,
    mrn VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Layanan & Obat 
CREATE TABLE IF NOT EXISTS healthcare_items (
    id SERIAL PRIMARY KEY,
    category item_category NOT NULL,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(15, 2) NOT NULL,
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabel Metode Pembayaran 
CREATE TABLE IF NOT EXISTS payment_methods (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- 4. Tabel Header Tagihan / Billing 
CREATE TABLE IF NOT EXISTS patient_billings (
    id SERIAL PRIMARY KEY,
    billing_number VARCHAR(50) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    paid_amount DECIMAL(15, 2) DEFAULT 0.00,
    status billing_status DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT
);

-- 5. Tabel Detail Rincian Tagihan 
CREATE TABLE IF NOT EXISTS billing_details (
    id SERIAL PRIMARY KEY,
    patient_billing_id INT NOT NULL,
    healthcare_item_id INT NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(15, 2) NOT NULL,
    subtotal DECIMAL(15, 2) NOT NULL,
    notes VARCHAR(255) NULL,
    FOREIGN KEY (patient_billing_id) REFERENCES patient_billings(id) ON DELETE CASCADE,
    FOREIGN KEY (healthcare_item_id) REFERENCES healthcare_items(id) ON DELETE RESTRICT
);

-- 6. Tabel Pembayaran
CREATE TABLE IF NOT EXISTS billing_payments (
    id SERIAL PRIMARY KEY,
    patient_billing_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount_paid DECIMAL(15, 2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cashier_name VARCHAR(100),
    FOREIGN KEY (patient_billing_id) REFERENCES patient_billings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT
);