-- Schema for PHP Legacy Accounting Application
-- SQLite version (Simplified)

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);

-- Company settings (single tenant, always id=1)
CREATE TABLE IF NOT EXISTS company (
    id INTEGER PRIMARY KEY DEFAULT 1,
    name TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    fiscal_year_start TEXT NOT NULL,
    fiscal_year_end TEXT NOT NULL,
    fiscal_year_closed INTEGER NOT NULL DEFAULT 0
);

-- Chart of accounts
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'general',
    is_active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_accounts_code ON accounts (code);
CREATE INDEX IF NOT EXISTS idx_accounts_type ON accounts (type);

-- Accounting journals
CREATE TABLE IF NOT EXISTS journals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    sequence_prefix TEXT NOT NULL,
    next_number INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 1
);

-- Accounting entries (pieces)
CREATE TABLE IF NOT EXISTS entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_id INTEGER NOT NULL,
    entry_date TEXT NOT NULL,
    piece_number TEXT NULL,
    label TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'posted',
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

-- Entry lines
CREATE TABLE IF NOT EXISTS entry_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL,
    line_no INTEGER NOT NULL,
    account_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    debit REAL NOT NULL DEFAULT 0,
    credit REAL NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_entry_lines_entry ON entry_lines (entry_id);
CREATE INDEX IF NOT EXISTS idx_entry_lines_account ON entry_lines (account_id);

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
