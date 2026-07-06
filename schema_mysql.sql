-- ============================================================
-- POS Healthcare Database Schema - MySQL/MariaDB Version
-- ============================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `pos`;
USE `pos`;

-- ============================================================
-- 1. Tabel Pasien / Customers
-- ============================================================
CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mrn` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Medical Record Number',
    `nik` VARCHAR(20) NULL COMMENT 'NIK KTP',
    `name` VARCHAR(150) NOT NULL,
    `gender` ENUM('L', 'P') DEFAULT 'L',
    `date_of_birth` DATE NULL COMMENT 'Tanggal Lahir',
    `dob` DATE NULL COMMENT 'Alias untuk date_of_birth',
    `phone` VARCHAR(20),
    `address` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mrn (mrn),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Tabel Layanan & Obat / Healthcare Items
-- ============================================================
CREATE TABLE IF NOT EXISTS `healthcare_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` ENUM('obat', 'jasa', 'tindakan_medis', 'laboratorium', 'administrasi') NOT NULL DEFAULT 'jasa',
    `type` VARCHAR(50) NULL COMMENT 'Barang atau Jasa',
    `name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(15, 2) NOT NULL,
    `stock` INT DEFAULT 0 COMMENT 'Stok untuk barang/obat',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Tabel Metode Pembayaran / Payment Methods
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_methods` (`id`, `name`) VALUES 
(1, 'Uang Tunai (Cash)'),
(2, 'QRIS / E-Wallet'),
(3, 'Kartu Debit / Kredit');

-- ============================================================
-- 4. Tabel Header Tagihan / Patient Billings (Invoices)
-- ============================================================
CREATE TABLE IF NOT EXISTS `patient_billings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `billing_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor Nota/Invoice',
    `patient_id` INT NULL COMMENT 'NULL jika pasien umum/walk-in',
    `subtotal` DECIMAL(15, 2) DEFAULT 0,
    `tax` DECIMAL(15, 2) DEFAULT 0 COMMENT 'Pajak/PPN',
    `discount` DECIMAL(15, 2) DEFAULT 0 COMMENT 'Diskon dalam Rupiah',
    `total_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Total setelah pajak dan diskon',
    `paid_amount` DECIMAL(15, 2) DEFAULT 0 COMMENT 'Total pembayaran sampai saat ini',
    `status` ENUM('Unpaid', 'Partially Paid', 'Paid') DEFAULT 'Unpaid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL,
    INDEX idx_billing_number (billing_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Tabel Detail Rincian Tagihan / Billing Details
-- ============================================================
CREATE TABLE IF NOT EXISTS `billing_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_billing_id` INT NOT NULL,
    `healthcare_item_id` INT NOT NULL,
    `qty` INT NOT NULL COMMENT 'Quantity',
    `unit_price` DECIMAL(15, 2) NOT NULL,
    `subtotal` DECIMAL(15, 2) NOT NULL COMMENT 'qty * unit_price',
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_billing_id`) REFERENCES `patient_billings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`healthcare_item_id`) REFERENCES `healthcare_items`(`id`) ON DELETE RESTRICT,
    INDEX idx_billing_id (patient_billing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. Tabel Pembayaran / Billing Payments (Split Payment Support)
-- ============================================================
CREATE TABLE IF NOT EXISTS `billing_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_billing_id` INT NOT NULL,
    `payment_method_id` INT NOT NULL,
    `amount_paid` DECIMAL(15, 2) NOT NULL COMMENT 'Jumlah pembayaran untuk split ini',
    `change_amount` DECIMAL(15, 2) DEFAULT 0 COMMENT 'Kembalian (hanya untuk cash)',
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `cashier_name` VARCHAR(100) NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_billing_id`) REFERENCES `patient_billings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT,
    INDEX idx_billing_id (patient_billing_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Sample Data / Contoh Data
-- ============================================================

-- Insert Sample Patients
INSERT INTO `patients` (`mrn`, `nik`, `name`, `gender`, `date_of_birth`, `phone`, `address`) VALUES
('RM-001', '1234567890123456', 'Budi Santoso', 'L', '1985-05-15', '081234567890', 'Jalan Merdeka No. 123'),
('RM-002', '2345678901234567', 'Siti Nurhaliza', 'P', '1990-08-22', '082345678901', 'Jalan Sudirman No. 456');

-- Insert Sample Healthcare Items
INSERT INTO `healthcare_items` (`category`, `type`, `name`, `price`, `stock`) VALUES
('obat', 'Barang', 'Obat Paracetamol 500mg', 15000, 100),
('obat', 'Barang', 'Perban Elastis', 75000, 50),
('jasa', 'Jasa', 'Konsultasi Dokter Umum', 150000, 0),
('jasa', 'Jasa', 'Vaksin COVID-19', 300000, 0),
('laboratorium', 'Jasa', 'Tes Darah Lengkap', 250000, 0);

-- ============================================================
-- Verification Queries
-- ============================================================
-- Check tables created
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'pos';
-- Check patients
-- SELECT * FROM patients;
-- Check healthcare items
-- SELECT * FROM healthcare_items;
