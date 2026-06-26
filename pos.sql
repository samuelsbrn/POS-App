-- 1. Tabel Pasien (Menggantikan Customers)
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mrn VARCHAR(20) NOT NULL UNIQUE, -- Medical Record Number (Nomor Rekam Medis)
    name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Layanan & Obat (Menggantikan Items)
CREATE TABLE IF NOT EXISTS healthcare_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('obat', 'tindakan_medis', 'laboratorium', 'administrasi') NOT NULL,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(15, 2) NOT NULL,
    stock INT DEFAULT 0, -- Berlaku untuk 'obat', kategori lain set 0
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabel Metode Pembayaran (Sama, untuk Kasir Faskes)
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL -- Contoh: 'Tunai', 'Debit BCA', 'QRIS Mandiri'
);

-- 4. Tabel Header Tagihan / Billing (Menggantikan Invoices)
CREATE TABLE IF NOT EXISTS patient_billings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_number VARCHAR(50) NOT NULL UNIQUE, -- Contoh: INV-RS-260625-001
    patient_id INT NOT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    paid_amount DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT
);

-- 5. Tabel Detail Rincian Tagihan (Menggantikan Invoice Items)
CREATE TABLE IF NOT EXISTS billing_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_billing_id INT NOT NULL,
    healthcare_item_id INT NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(15, 2) NOT NULL,
    subtotal DECIMAL(15, 2) NOT NULL,
    notes VARCHAR(255) NULL, -- Catatan tambahan (misal: dosis obat atau nama dokter)
    FOREIGN KEY (patient_billing_id) REFERENCES patient_billings(id) ON DELETE CASCADE,
    FOREIGN KEY (healthcare_item_id) REFERENCES healthcare_items(id) ON DELETE RESTRICT
);

-- 6. Tabel Pembayaran (Tetap Mendukung Split Payment)
CREATE TABLE IF NOT EXISTS billing_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_billing_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount_paid DECIMAL(15, 2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cashier_name VARCHAR(100), -- Opsional: Mencatat siapa kasir yang menerima uang
    FOREIGN KEY (patient_billing_id) REFERENCES patient_billings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT
);