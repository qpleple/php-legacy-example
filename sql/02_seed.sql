-- Seed data for PHP Legacy Accounting Application (Simplified)
-- SQLite version

-- Users (passwords hashed with MD5 and salt 'legacy')
-- admin/admin123
INSERT INTO users (username, password_hash, created_at) VALUES
('admin', 'dc3d211a05fd3ee30f403df94956af0c', datetime('now'));

-- Company settings
INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end) VALUES
(1, 'Ketchup Compta', 'EUR', '2026-01-01', '2026-12-31');

-- Default journals
INSERT INTO journals (code, label, sequence_prefix, next_number) VALUES
('VE', 'Journal des Ventes', 'VE', 1),
('AC', 'Journal des Achats', 'AC', 1),
('BK', 'Journal de Banque', 'BK', 1),
('OD', 'Opérations Diverses', 'OD', 1);

-- Basic chart of accounts (French PCG simplified)
INSERT INTO accounts (code, label, type, is_active) VALUES
-- Class 1 - Capital
('101000', 'Capital social', 'general', 1),
('110000', 'Report à nouveau', 'general', 1),
('120000', 'Résultat de l''exercice', 'general', 1),

-- Class 4 - Third parties
('401000', 'Fournisseurs', 'general', 1),
('411000', 'Clients', 'general', 1),

-- Class 5 - Financial
('512000', 'Banque', 'general', 1),
('530000', 'Caisse', 'general', 1),

-- Class 6 - Expenses
('601000', 'Achats matières premières', 'general', 1),
('602000', 'Achats fournitures', 'general', 1),
('606000', 'Achats non stockés', 'general', 1),
('613000', 'Locations', 'general', 1),
('616000', 'Assurances', 'general', 1),
('622000', 'Honoraires', 'general', 1),
('626000', 'Frais postaux et télécommunications', 'general', 1),
('627000', 'Services bancaires', 'general', 1),
('641000', 'Rémunérations du personnel', 'general', 1),
('645000', 'Charges sociales', 'general', 1),

-- Class 7 - Revenue
('701000', 'Ventes de produits finis', 'general', 1),
('706000', 'Prestations de services', 'general', 1),
('707000', 'Ventes de marchandises', 'general', 1);

-- ============================================================================
-- ACCOUNTING ENTRIES
-- ============================================================================

-- Entry 1: Opening balance (OD - January 1)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, 4, '2026-01-01', 'OD2026-000001', 'À nouveaux - Ouverture exercice', 'posted', 15000.00, 15000.00, 1, '2026-01-01 08:00:00', '2026-01-01 08:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(1, 1, (SELECT id FROM accounts WHERE code = '512000'), 'Solde banque', 10000.00, 0),
(1, 2, (SELECT id FROM accounts WHERE code = '530000'), 'Solde caisse', 500.00, 0),
(1, 3, (SELECT id FROM accounts WHERE code = '411000'), 'Créances clients', 4500.00, 0),
(1, 4, (SELECT id FROM accounts WHERE code = '101000'), 'Capital social', 0, 10000.00),
(1, 5, (SELECT id FROM accounts WHERE code = '110000'), 'Report à nouveau', 0, 5000.00);

-- Entry 2: Sales invoice (VE - January 5)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, 1, '2026-01-05', 'VE2026-000001', 'Facture client FA-2026-001', 'posted', 1200.00, 1200.00, 1, '2026-01-05 10:00:00', '2026-01-05 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(2, 1, (SELECT id FROM accounts WHERE code = '411000'), 'Client - FA-2026-001', 1200.00, 0),
(2, 2, (SELECT id FROM accounts WHERE code = '706000'), 'Prestations de services', 0, 1200.00);

-- Entry 3: Sales invoice (VE - January 8)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, 1, '2026-01-08', 'VE2026-000002', 'Facture client FA-2026-002', 'posted', 3300.00, 3300.00, 1, '2026-01-08 14:00:00', '2026-01-08 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(3, 1, (SELECT id FROM accounts WHERE code = '411000'), 'Client - FA-2026-002', 3300.00, 0),
(3, 2, (SELECT id FROM accounts WHERE code = '707000'), 'Ventes de marchandises', 0, 3300.00);

-- Entry 4: Purchase invoice (AC - January 10)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (4, 2, '2026-01-10', 'AC2026-000001', 'Facture fournisseur - Fournitures', 'posted', 660.00, 660.00, 1, '2026-01-10 09:00:00', '2026-01-10 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(4, 1, (SELECT id FROM accounts WHERE code = '602000'), 'Fournitures administratives', 660.00, 0),
(4, 2, (SELECT id FROM accounts WHERE code = '401000'), 'Fournisseur', 0, 660.00);

-- Entry 5: Purchase invoice (AC - January 12)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (5, 2, '2026-01-12', 'AC2026-000002', 'Facture fournisseur - Location', 'posted', 1100.00, 1100.00, 1, '2026-01-12 11:00:00', '2026-01-12 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(5, 1, (SELECT id FROM accounts WHERE code = '613000'), 'Location bureaux janvier', 1100.00, 0),
(5, 2, (SELECT id FROM accounts WHERE code = '401000'), 'Fournisseur', 0, 1100.00);

-- Entry 6: Customer payment (BK - January 15)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (6, 3, '2026-01-15', 'BK2026-000001', 'Règlement client', 'posted', 2000.00, 2000.00, 1, '2026-01-15 16:00:00', '2026-01-15 16:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(6, 1, (SELECT id FROM accounts WHERE code = '512000'), 'Virement reçu client', 2000.00, 0),
(6, 2, (SELECT id FROM accounts WHERE code = '411000'), 'Règlement créance', 0, 2000.00);

-- Entry 7: Vendor payment (BK - January 18)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (7, 3, '2026-01-18', 'BK2026-000002', 'Règlement fournisseur', 'posted', 660.00, 660.00, 1, '2026-01-18 10:00:00', '2026-01-18 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(7, 1, (SELECT id FROM accounts WHERE code = '401000'), 'Règlement facture', 660.00, 0),
(7, 2, (SELECT id FROM accounts WHERE code = '512000'), 'Virement émis', 0, 660.00);

-- Entry 8: Bank fees (BK - January 20)
INSERT INTO entries (id, journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (8, 3, '2026-01-20', 'BK2026-000003', 'Frais bancaires janvier', 'posted', 25.00, 25.00, 1, '2026-01-20 09:00:00', '2026-01-20 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(8, 1, (SELECT id FROM accounts WHERE code = '627000'), 'Frais bancaires', 25.00, 0),
(8, 2, (SELECT id FROM accounts WHERE code = '512000'), 'Prélèvement frais', 0, 25.00);

-- Update journal next_number counters
UPDATE journals SET next_number = 3 WHERE code = 'VE';
UPDATE journals SET next_number = 3 WHERE code = 'AC';
UPDATE journals SET next_number = 4 WHERE code = 'BK';
UPDATE journals SET next_number = 2 WHERE code = 'OD';
