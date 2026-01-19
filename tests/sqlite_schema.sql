-- SQLite Schema for Testing PHP Legacy Accounting Application

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(64) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'accountant',
    created_at DATETIME NOT NULL
);

-- Company settings (single tenant, always id=1)
CREATE TABLE IF NOT EXISTS company (
    id INTEGER PRIMARY KEY DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    fiscal_year_start DATE NOT NULL,
    fiscal_year_end DATE NOT NULL,
    fiscal_year_closed INTEGER NOT NULL DEFAULT 0,
    carry_forward_account VARCHAR(20) DEFAULT '110000'
);

-- Fiscal periods
CREATE TABLE IF NOT EXISTS periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'open'
);

-- Chart of accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'general',
    vat_default_rate_id INTEGER NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Third parties (customers and vendors)
CREATE TABLE IF NOT EXISTS third_parties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    account_id INTEGER NULL,
    email VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
);

-- Accounting journals
CREATE TABLE IF NOT EXISTS journals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(10) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    sequence_prefix VARCHAR(10) NOT NULL,
    next_number INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- VAT rates
CREATE TABLE IF NOT EXISTS vat_rates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label VARCHAR(50) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    account_collected VARCHAR(20) NOT NULL,
    account_deductible VARCHAR(20) NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Accounting entries (pieces)
CREATE TABLE IF NOT EXISTS entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_id INTEGER NOT NULL,
    entry_date DATE NOT NULL,
    period_id INTEGER NULL,
    piece_number VARCHAR(50) NULL,
    label VARCHAR(255) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'draft',
    total_debit DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_credit DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL,
    created_at DATETIME NOT NULL,
    posted_at DATETIME NULL
);

-- Entry lines
CREATE TABLE IF NOT EXISTS entry_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    line_no INTEGER NOT NULL,
    account_id INTEGER NOT NULL,
    third_party_id INTEGER NULL,
    label VARCHAR(255) NOT NULL,
    debit DECIMAL(12,2) NOT NULL DEFAULT 0,
    credit DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_rate_id INTEGER NULL,
    vat_base DECIMAL(12,2) NULL,
    vat_amount DECIMAL(12,2) NULL,
    due_date DATE NULL
);

-- Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL
);

-- Bank accounts
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label VARCHAR(255) NOT NULL,
    account_id INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Bank statements
CREATE TABLE IF NOT EXISTS bank_statements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_account_id INTEGER NOT NULL,
    imported_at DATETIME NOT NULL,
    source_filename VARCHAR(255) NOT NULL
);

-- Bank statement lines
CREATE TABLE IF NOT EXISTS bank_statement_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    statement_id INTEGER NOT NULL,
    line_date DATE NOT NULL,
    label VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    ref VARCHAR(255) NULL,
    matched_entry_line_id INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unmatched'
);

-- Lettering groups
CREATE TABLE IF NOT EXISTS lettering_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    third_party_id INTEGER NULL,
    created_at DATETIME NOT NULL,
    created_by INTEGER NOT NULL
);

-- Lettering items
CREATE TABLE IF NOT EXISTS lettering_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    entry_line_id INTEGER NOT NULL,
    amount DECIMAL(12,2) NOT NULL
);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity VARCHAR(50) NOT NULL,
    entity_id INTEGER NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL
);
