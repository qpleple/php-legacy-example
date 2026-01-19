-- Schema for PHP Legacy Accounting Application
-- SQLite version

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'accountant',
    created_at TEXT NOT NULL
);

-- Company settings (single tenant, always id=1)
CREATE TABLE IF NOT EXISTS company (
    id INTEGER PRIMARY KEY DEFAULT 1,
    name TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    fiscal_year_start TEXT NOT NULL,
    fiscal_year_end TEXT NOT NULL,
    fiscal_year_closed INTEGER NOT NULL DEFAULT 0,
    carry_forward_account TEXT DEFAULT '110000',
    lettering_tolerance REAL NOT NULL DEFAULT 0.05
);

-- Fiscal periods
CREATE TABLE IF NOT EXISTS periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open'
);
CREATE INDEX IF NOT EXISTS idx_periods_dates ON periods (start_date, end_date);

-- Chart of accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'general',
    vat_default_rate_id INTEGER NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_accounts_code ON accounts (code);
CREATE INDEX IF NOT EXISTS idx_accounts_type ON accounts (type);

-- Third parties (customers and vendors)
CREATE TABLE IF NOT EXISTS third_parties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    name TEXT NOT NULL,
    account_id INTEGER NULL,
    email TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_third_parties_type ON third_parties (type);
CREATE INDEX IF NOT EXISTS idx_third_parties_account ON third_parties (account_id);

-- Accounting journals
CREATE TABLE IF NOT EXISTS journals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    sequence_prefix TEXT NOT NULL,
    next_number INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- VAT rates
CREATE TABLE IF NOT EXISTS vat_rates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    rate REAL NOT NULL,
    account_collected TEXT NOT NULL,
    account_deductible TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Accounting entries (pieces)
CREATE TABLE IF NOT EXISTS entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_id INTEGER NOT NULL,
    entry_date TEXT NOT NULL,
    period_id INTEGER NULL,
    piece_number TEXT NULL,
    label TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    total_debit REAL NOT NULL DEFAULT 0,
    total_credit REAL NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    posted_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_entries_journal ON entries (journal_id);
CREATE INDEX IF NOT EXISTS idx_entries_date ON entries (entry_date);
CREATE INDEX IF NOT EXISTS idx_entries_piece ON entries (piece_number);
CREATE INDEX IF NOT EXISTS idx_entries_status ON entries (status);
CREATE INDEX IF NOT EXISTS idx_entries_period ON entries (period_id);

-- Entry lines
CREATE TABLE IF NOT EXISTS entry_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    line_no INTEGER NOT NULL,
    account_id INTEGER NOT NULL,
    third_party_id INTEGER NULL,
    label TEXT NOT NULL,
    debit REAL NOT NULL DEFAULT 0,
    credit REAL NOT NULL DEFAULT 0,
    vat_rate_id INTEGER NULL,
    vat_base REAL NULL,
    vat_amount REAL NULL,
    due_date TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_entry_lines_entry ON entry_lines (entry_id);
CREATE INDEX IF NOT EXISTS idx_entry_lines_account ON entry_lines (account_id);
CREATE INDEX IF NOT EXISTS idx_entry_lines_third_party ON entry_lines (third_party_id);

-- Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    stored_path TEXT NOT NULL,
    uploaded_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attachments_entry ON attachments (entry_id);

-- Bank accounts
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    account_id INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Bank statements
CREATE TABLE IF NOT EXISTS bank_statements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_account_id INTEGER NOT NULL,
    imported_at TEXT NOT NULL,
    source_filename TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_bank_statements_account ON bank_statements (bank_account_id);

-- Bank statement lines
CREATE TABLE IF NOT EXISTS bank_statement_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    statement_id INTEGER NOT NULL,
    line_date TEXT NOT NULL,
    label TEXT NOT NULL,
    amount REAL NOT NULL,
    ref TEXT NULL,
    matched_entry_line_id INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'unmatched'
);
CREATE INDEX IF NOT EXISTS idx_bank_statement_lines_statement ON bank_statement_lines (statement_id);
CREATE INDEX IF NOT EXISTS idx_bank_statement_lines_status ON bank_statement_lines (status);

-- Lettering groups
CREATE TABLE IF NOT EXISTS lettering_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    third_party_id INTEGER NULL,
    letter_code TEXT NOT NULL,
    is_partial INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    created_by INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_lettering_groups_account ON lettering_groups (account_id);
CREATE INDEX IF NOT EXISTS idx_lettering_groups_third_party ON lettering_groups (third_party_id);
CREATE INDEX IF NOT EXISTS idx_lettering_groups_code ON lettering_groups (account_id, letter_code);

-- Lettering items
CREATE TABLE IF NOT EXISTS lettering_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    entry_line_id INTEGER NOT NULL,
    amount REAL NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_lettering_items_group ON lettering_items (group_id);
CREATE INDEX IF NOT EXISTS idx_lettering_items_entry_line ON lettering_items (entry_line_id);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    entity TEXT NOT NULL,
    entity_id INTEGER NULL,
    details TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log (action);
CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log (entity, entity_id);
