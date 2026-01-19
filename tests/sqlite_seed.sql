-- SQLite Seed data for Testing

-- Admin user (password: admin123, MD5 with salt 'legacy')
INSERT INTO users (username, password_hash, role, created_at) VALUES
('admin', '5d93ceb70e2bf5daa84ec3d0cd2c731a', 'admin', datetime('now')),
('comptable', 'e5e0f7cd43c1c3e3f3f4a4c5d6e7f8a9', 'accountant', datetime('now')),
('lecteur', 'a1b2c3d4e5f6789012345678901234ab', 'viewer', datetime('now'));

-- Company settings
INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end, carry_forward_account) VALUES
(1, 'Ma Societe SARL', 'EUR', '2024-01-01', '2024-12-31', '110000');

-- Default journals
INSERT INTO journals (code, label, sequence_prefix, next_number) VALUES
('VE', 'Journal des Ventes', 'VE', 1),
('AC', 'Journal des Achats', 'AC', 1),
('BK', 'Journal de Banque', 'BK', 1),
('OD', 'Operations Diverses', 'OD', 1);

-- VAT rates
INSERT INTO vat_rates (label, rate, account_collected, account_deductible, is_active) VALUES
('TVA 20%', 20.00, '44571', '44566', 1),
('TVA 10%', 10.00, '44571', '44566', 1),
('TVA 5.5%', 5.50, '44571', '44566', 1),
('Exonere', 0.00, '44571', '44566', 1);

-- Basic chart of accounts
INSERT INTO accounts (code, label, type, is_active) VALUES
('101000', 'Capital social', 'general', 1),
('110000', 'Report a nouveau', 'general', 1),
('120000', 'Resultat de l exercice', 'general', 1),
('401000', 'Fournisseurs', 'vendor', 1),
('401001', 'Fournisseur ABC', 'vendor', 1),
('401002', 'Fournisseur XYZ', 'vendor', 1),
('411000', 'Clients', 'customer', 1),
('411001', 'Client Dupont', 'customer', 1),
('411002', 'Client Martin', 'customer', 1),
('44566', 'TVA deductible sur ABS', 'general', 1),
('44571', 'TVA collectee', 'general', 1),
('512000', 'Banque', 'general', 1),
('512001', 'Banque BNP', 'general', 1),
('530000', 'Caisse', 'general', 1),
('606000', 'Achats non stockes', 'general', 1),
('707000', 'Ventes de marchandises', 'general', 1);

-- Periods for 2024 fiscal year (monthly)
INSERT INTO periods (start_date, end_date, status) VALUES
('2024-01-01', '2024-01-31', 'open'),
('2024-02-01', '2024-02-29', 'open'),
('2024-03-01', '2024-03-31', 'open'),
('2024-04-01', '2024-04-30', 'open'),
('2024-05-01', '2024-05-31', 'open'),
('2024-06-01', '2024-06-30', 'open'),
('2024-07-01', '2024-07-31', 'open'),
('2024-08-01', '2024-08-31', 'open'),
('2024-09-01', '2024-09-30', 'open'),
('2024-10-01', '2024-10-31', 'open'),
('2024-11-01', '2024-11-30', 'open'),
('2024-12-01', '2024-12-31', 'open');

-- Bank account
INSERT INTO bank_accounts (label, account_id, is_active)
SELECT 'Compte BNP Principal', id, 1 FROM accounts WHERE code = '512001';

-- Third parties
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Client Dupont', id, 'dupont@example.com', datetime('now') FROM accounts WHERE code = '411001';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Client Martin', id, 'martin@example.com', datetime('now') FROM accounts WHERE code = '411002';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Fournisseur ABC', id, 'abc@example.com', datetime('now') FROM accounts WHERE code = '401001';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Fournisseur XYZ', id, 'xyz@example.com', datetime('now') FROM accounts WHERE code = '401002';
