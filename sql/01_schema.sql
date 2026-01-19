-- Schema for PHP Legacy Accounting Application
-- Style: MySQL 5.0.x (2006)

SET NAMES utf8;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(64) NOT NULL,
    role ENUM('admin', 'accountant', 'viewer') NOT NULL DEFAULT 'accountant',
    created_at DATETIME NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Company settings (single tenant, always id=1)
CREATE TABLE IF NOT EXISTS company (
    id INT PRIMARY KEY DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    fiscal_year_start DATE NOT NULL,
    fiscal_year_end DATE NOT NULL,
    fiscal_year_closed TINYINT NOT NULL DEFAULT 0,
    carry_forward_account VARCHAR(20) DEFAULT '110000'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Fiscal periods
CREATE TABLE IF NOT EXISTS periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'locked') NOT NULL DEFAULT 'open',
    INDEX idx_dates (start_date, end_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Chart of accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    type ENUM('general', 'customer', 'vendor') NOT NULL DEFAULT 'general',
    vat_default_rate_id INT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    INDEX idx_code (code),
    INDEX idx_type (type)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Third parties (customers and vendors)
CREATE TABLE IF NOT EXISTS third_parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('customer', 'vendor') NOT NULL,
    name VARCHAR(255) NOT NULL,
    account_id INT NULL,
    email VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_type (type),
    INDEX idx_account (account_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Accounting journals
CREATE TABLE IF NOT EXISTS journals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    sequence_prefix VARCHAR(10) NOT NULL,
    next_number INT NOT NULL DEFAULT 1,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- VAT rates
CREATE TABLE IF NOT EXISTS vat_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    account_collected VARCHAR(20) NOT NULL,
    account_deductible VARCHAR(20) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Accounting entries (pieces)
CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_id INT NOT NULL,
    entry_date DATE NOT NULL,
    period_id INT NULL,
    piece_number VARCHAR(50) NULL,
    label VARCHAR(255) NOT NULL,
    status ENUM('draft', 'posted') NOT NULL DEFAULT 'draft',
    total_debit DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_credit DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    posted_at DATETIME NULL,
    INDEX idx_journal (journal_id),
    INDEX idx_date (entry_date),
    INDEX idx_piece (piece_number),
    INDEX idx_status (status),
    INDEX idx_period (period_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Entry lines
CREATE TABLE IF NOT EXISTS entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    line_no INT NOT NULL,
    account_id INT NOT NULL,
    third_party_id INT NULL,
    label VARCHAR(255) NOT NULL,
    debit DECIMAL(12,2) NOT NULL DEFAULT 0,
    credit DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_rate_id INT NULL,
    vat_base DECIMAL(12,2) NULL,
    vat_amount DECIMAL(12,2) NULL,
    due_date DATE NULL,
    INDEX idx_entry (entry_id),
    INDEX idx_account (account_id),
    INDEX idx_third_party (third_party_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    INDEX idx_entry (entry_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Bank accounts
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    account_id INT NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Bank statements
CREATE TABLE IF NOT EXISTS bank_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    imported_at DATETIME NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    INDEX idx_bank_account (bank_account_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Bank statement lines
CREATE TABLE IF NOT EXISTS bank_statement_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    statement_id INT NOT NULL,
    line_date DATE NOT NULL,
    label VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    ref VARCHAR(255) NULL,
    matched_entry_line_id INT NULL,
    status ENUM('unmatched', 'matched') NOT NULL DEFAULT 'unmatched',
    INDEX idx_statement (statement_id),
    INDEX idx_status (status)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Lettering groups
CREATE TABLE IF NOT EXISTS lettering_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    third_party_id INT NULL,
    created_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    INDEX idx_account (account_id),
    INDEX idx_third_party (third_party_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Lettering items
CREATE TABLE IF NOT EXISTS lettering_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    entry_line_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    INDEX idx_group (group_id),
    INDEX idx_entry_line (entry_line_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity, entity_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
