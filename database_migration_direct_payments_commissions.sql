-- Direct seller payments and commission settlement workflow
-- Run this once against an existing Rwaculture database.

USE rwaculture_db;

CREATE TABLE IF NOT EXISTS seller_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL UNIQUE,
    bank_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    branch_name VARCHAR(150) NULL,
    payment_instructions TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_payment_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('bank', 'mobile_money') NOT NULL DEFAULT 'bank',
    provider_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    instructions TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commission_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    amount_requested DECIMAL(10, 2) NOT NULL,
    proof_path VARCHAR(255) NOT NULL,
    proof_type VARCHAR(50) NULL,
    proof_size INT NULL,
    status ENUM('submitted', 'confirmed', 'rejected') DEFAULT 'submitted',
    admin_verified_amount DECIMAL(10, 2) NULL,
    seller_message TEXT NULL,
    admin_message TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_settlements_seller_status (seller_id, status),
    INDEX idx_settlements_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin_payment_accounts
    (account_type, provider_name, account_name, account_number, instructions)
SELECT 'bank', 'Configure bank name', 'Configure account holder', 'Configure account number', 'Please configure this account before asking sellers to pay.'
WHERE NOT EXISTS (SELECT 1 FROM admin_payment_accounts);
