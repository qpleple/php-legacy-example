-- Seed data for PHP Legacy Accounting Application
-- SQLite version

-- Users (passwords hashed with MD5 and salt 'legacy')
-- admin/admin123, comptable/comptable123, lecteur/lecteur123
INSERT INTO users (username, password_hash, role, created_at) VALUES
('admin', 'dc3d211a05fd3ee30f403df94956af0c', 'admin', datetime('now')),
('comptable', 'b674405f0a72362160eb19ec872a9c32', 'accountant', datetime('now')),
('lecteur', '95ed37882a8c94f288ff88d39dc5c4c8', 'viewer', datetime('now'));

-- Company settings
INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end, carry_forward_account) VALUES
(1, 'Ketchup Compta', 'EUR', '2026-01-01', '2026-12-31', '110000');

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

-- Basic chart of accounts (French PCG simplified)
INSERT INTO accounts (code, label, type, is_active) VALUES
-- Class 1 - Capital
('101000', 'Capital social', 'general', 1),
('110000', 'Report a nouveau', 'general', 1),
('120000', 'Resultat de l exercice', 'general', 1),

-- Class 4 - Third parties
('401000', 'Fournisseurs', 'vendor', 1),
('401001', 'Fournisseur ABC', 'vendor', 1),
('401002', 'Fournisseur XYZ', 'vendor', 1),
('411000', 'Clients', 'customer', 1),
('411001', 'Client Dupont', 'customer', 1),
('411002', 'Client Martin', 'customer', 1),
('44566', 'TVA deductible sur ABS', 'general', 1),
('44571', 'TVA collectee', 'general', 1),

-- Class 5 - Financial
('512000', 'Banque', 'general', 1),
('512001', 'Banque BNP', 'general', 1),
('530000', 'Caisse', 'general', 1),

-- Class 6 - Expenses
('601000', 'Achats matieres premieres', 'general', 1),
('602000', 'Achats fournitures', 'general', 1),
('606000', 'Achats non stockes', 'general', 1),
('606100', 'Fournitures non stockables', 'general', 1),
('606400', 'Fournitures administratives', 'general', 1),
('613000', 'Locations', 'general', 1),
('616000', 'Assurances', 'general', 1),
('622000', 'Honoraires', 'general', 1),
('626000', 'Frais postaux et telecommunications', 'general', 1),
('627000', 'Services bancaires', 'general', 1),
('641000', 'Remunerations du personnel', 'general', 1),
('645000', 'Charges sociales', 'general', 1),

-- Class 7 - Revenue
('701000', 'Ventes de produits finis', 'general', 1),
('706000', 'Prestations de services', 'general', 1),
('707000', 'Ventes de marchandises', 'general', 1),
('708000', 'Produits des activites annexes', 'general', 1);

-- Third parties
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Client Dupont', id, 'dupont@example.com', datetime('now') FROM accounts WHERE code = '411001';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Client Martin', id, 'martin@example.com', datetime('now') FROM accounts WHERE code = '411002';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Fournisseur ABC', id, 'abc@example.com', datetime('now') FROM accounts WHERE code = '401001';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Fournisseur XYZ', id, 'xyz@example.com', datetime('now') FROM accounts WHERE code = '401002';

-- Periods for 2026 fiscal year (monthly)
INSERT INTO periods (start_date, end_date, status) VALUES
('2026-01-01', '2026-01-31', 'open'),
('2026-02-01', '2026-02-28', 'open'),
('2026-03-01', '2026-03-31', 'open'),
('2026-04-01', '2026-04-30', 'open'),
('2026-05-01', '2026-05-31', 'open'),
('2026-06-01', '2026-06-30', 'open'),
('2026-07-01', '2026-07-31', 'open'),
('2026-08-01', '2026-08-31', 'open'),
('2026-09-01', '2026-09-30', 'open'),
('2026-10-01', '2026-10-31', 'open'),
('2026-11-01', '2026-11-30', 'open'),
('2026-12-01', '2026-12-31', 'open');

-- Bank accounts
INSERT INTO bank_accounts (label, account_id, is_active)
SELECT 'Compte BNP Principal', id, 1 FROM accounts WHERE code = '512001';
INSERT INTO bank_accounts (label, account_id, is_active)
SELECT 'Caisse', id, 1 FROM accounts WHERE code = '530000';

-- ============================================================================
-- ACCOUNTING ENTRIES
-- ============================================================================

-- Entry 1: Opening balance (OD - January 1)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, 4, '2026-01-01', 1, 'OD2026-000001', 'A nouveaux - Ouverture exercice', 'posted', 15000.00, 15000.00, 1, '2026-01-01 08:00:00', '2026-01-01 08:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(1, 1, (SELECT id FROM accounts WHERE code = '512001'), 'Solde banque BNP', 10000.00, 0),
(1, 2, (SELECT id FROM accounts WHERE code = '530000'), 'Solde caisse', 500.00, 0),
(1, 3, (SELECT id FROM accounts WHERE code = '411001'), 'Creance client Dupont', 2000.00, 0),
(1, 4, (SELECT id FROM accounts WHERE code = '411002'), 'Creance client Martin', 2500.00, 0),
(1, 5, (SELECT id FROM accounts WHERE code = '101000'), 'Capital social', 0, 10000.00),
(1, 6, (SELECT id FROM accounts WHERE code = '110000'), 'Report a nouveau', 0, 5000.00);

-- Entry 2: Sales invoice to Client Dupont (VE - January 5)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, 1, '2026-01-05', 1, 'VE2026-000001', 'Facture FA-2026-001 Client Dupont', 'posted', 1200.00, 1200.00, 2, '2026-01-05 10:00:00', '2026-01-05 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount, due_date) VALUES
(2, 1, (SELECT id FROM accounts WHERE code = '411001'), 1, 'Client Dupont - FA-2026-001', 1200.00, 0, NULL, NULL, NULL, '2026-02-05'),
(2, 2, (SELECT id FROM accounts WHERE code = '706000'), NULL, 'Prestations de services', 0, 1000.00, 1, 1000.00, 200.00, NULL),
(2, 3, (SELECT id FROM accounts WHERE code = '44571'), NULL, 'TVA collectee 20%', 0, 200.00, NULL, NULL, NULL, NULL);

-- Entry 3: Sales invoice to Client Martin (VE - January 8)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, 1, '2026-01-08', 1, 'VE2026-000002', 'Facture FA-2026-002 Client Martin', 'posted', 3300.00, 3300.00, 2, '2026-01-08 14:00:00', '2026-01-08 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount, due_date) VALUES
(3, 1, (SELECT id FROM accounts WHERE code = '411002'), 2, 'Client Martin - FA-2026-002', 3300.00, 0, NULL, NULL, NULL, '2026-02-08'),
(3, 2, (SELECT id FROM accounts WHERE code = '707000'), NULL, 'Ventes de marchandises', 0, 2750.00, 1, 2750.00, 550.00, NULL),
(3, 3, (SELECT id FROM accounts WHERE code = '44571'), NULL, 'TVA collectee 20%', 0, 550.00, NULL, NULL, NULL, NULL);

-- Entry 4: Purchase invoice from Fournisseur ABC (AC - January 10)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (4, 2, '2026-01-10', 1, 'AC2026-000001', 'Facture fournisseur ABC - Fournitures', 'posted', 660.00, 660.00, 2, '2026-01-10 09:00:00', '2026-01-10 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount) VALUES
(4, 1, (SELECT id FROM accounts WHERE code = '606400'), NULL, 'Fournitures administratives', 550.00, 0, 1, 550.00, 110.00),
(4, 2, (SELECT id FROM accounts WHERE code = '44566'), NULL, 'TVA deductible 20%', 110.00, 0, NULL, NULL, NULL),
(4, 3, (SELECT id FROM accounts WHERE code = '401001'), 3, 'Fournisseur ABC', 0, 660.00, NULL, NULL, NULL);

-- Entry 5: Purchase invoice from Fournisseur XYZ (AC - January 12)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (5, 2, '2026-01-12', 1, 'AC2026-000002', 'Facture fournisseur XYZ - Location', 'posted', 1100.00, 1100.00, 2, '2026-01-12 11:00:00', '2026-01-12 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount) VALUES
(5, 1, (SELECT id FROM accounts WHERE code = '613000'), NULL, 'Location bureaux janvier', 1000.00, 0, 2, 1000.00, 100.00),
(5, 2, (SELECT id FROM accounts WHERE code = '44566'), NULL, 'TVA deductible 10%', 100.00, 0, NULL, NULL, NULL),
(5, 3, (SELECT id FROM accounts WHERE code = '401002'), 4, 'Fournisseur XYZ', 0, 1100.00, NULL, NULL, NULL);

-- Entry 6: Customer payment from Dupont (BK - January 15)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (6, 3, '2026-01-15', 1, 'BK2026-000001', 'Reglement client Dupont', 'posted', 2000.00, 2000.00, 2, '2026-01-15 16:00:00', '2026-01-15 16:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit) VALUES
(6, 1, (SELECT id FROM accounts WHERE code = '512001'), NULL, 'Virement recu Dupont', 2000.00, 0),
(6, 2, (SELECT id FROM accounts WHERE code = '411001'), 1, 'Reglement creance Dupont', 0, 2000.00);

-- Entry 7: Vendor payment to ABC (BK - January 18)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (7, 3, '2026-01-18', 1, 'BK2026-000002', 'Reglement fournisseur ABC', 'posted', 660.00, 660.00, 2, '2026-01-18 10:00:00', '2026-01-18 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit) VALUES
(7, 1, (SELECT id FROM accounts WHERE code = '401001'), 3, 'Reglement facture ABC', 660.00, 0),
(7, 2, (SELECT id FROM accounts WHERE code = '512001'), NULL, 'Virement emis ABC', 0, 660.00);

-- Entry 8: Bank fees (BK - January 20)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (8, 3, '2026-01-20', 1, 'BK2026-000003', 'Frais bancaires janvier', 'posted', 25.00, 25.00, 2, '2026-01-20 09:00:00', '2026-01-20 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(8, 1, (SELECT id FROM accounts WHERE code = '627000'), 'Frais bancaires BNP', 25.00, 0),
(8, 2, (SELECT id FROM accounts WHERE code = '512001'), 'Prelevement frais BNP', 0, 25.00);

-- Entry 9: Cash sale (VE - January 22)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (9, 1, '2026-01-22', 1, 'VE2026-000003', 'Vente comptoir', 'posted', 55.00, 55.00, 2, '2026-01-22 15:00:00', '2026-01-22 15:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit, vat_rate_id, vat_base, vat_amount) VALUES
(9, 1, (SELECT id FROM accounts WHERE code = '530000'), 'Encaissement especes', 55.00, 0, NULL, NULL, NULL),
(9, 2, (SELECT id FROM accounts WHERE code = '707000'), 'Vente marchandises', 0, 50.00, 2, 50.00, 5.00),
(9, 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA collectee 10%', 0, 5.00, NULL, NULL, NULL);

-- Entry 10: Partial payment from Martin (BK - January 25)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (10, 3, '2026-01-25', 1, 'BK2026-000004', 'Acompte client Martin', 'posted', 1500.00, 1500.00, 2, '2026-01-25 14:00:00', '2026-01-25 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit) VALUES
(10, 1, (SELECT id FROM accounts WHERE code = '512001'), NULL, 'Virement recu Martin', 1500.00, 0),
(10, 2, (SELECT id FROM accounts WHERE code = '411002'), 2, 'Acompte sur FA-2026-002', 0, 1500.00);

-- Entry 11: Salary payment (OD - January 31)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (11, 4, '2026-01-31', 1, 'OD2026-000002', 'Salaires janvier 2026', 'posted', 3500.00, 3500.00, 1, '2026-01-31 18:00:00', '2026-01-31 18:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES
(11, 1, (SELECT id FROM accounts WHERE code = '641000'), 'Salaires bruts janvier', 2500.00, 0),
(11, 2, (SELECT id FROM accounts WHERE code = '645000'), 'Charges sociales janvier', 1000.00, 0),
(11, 3, (SELECT id FROM accounts WHERE code = '512001'), 'Virement salaires', 0, 3500.00);

-- Entry 12: Draft entry (for testing draft workflow)
INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (12, 2, '2026-01-28', 1, NULL, 'Facture en attente validation', 'draft', 240.00, 240.00, 2, '2026-01-28 11:00:00', NULL);

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount) VALUES
(12, 1, (SELECT id FROM accounts WHERE code = '602000'), NULL, 'Fournitures bureau', 200.00, 0, 1, 200.00, 40.00),
(12, 2, (SELECT id FROM accounts WHERE code = '44566'), NULL, 'TVA deductible', 40.00, 0, NULL, NULL, NULL),
(12, 3, (SELECT id FROM accounts WHERE code = '401001'), 3, 'Fournisseur ABC', 0, 240.00, NULL, NULL, NULL);

-- Update journal next_number counters
UPDATE journals SET next_number = 4 WHERE code = 'VE';
UPDATE journals SET next_number = 3 WHERE code = 'AC';
UPDATE journals SET next_number = 5 WHERE code = 'BK';
UPDATE journals SET next_number = 3 WHERE code = 'OD';

-- ============================================================================
-- BANK STATEMENTS
-- ============================================================================

-- Bank statement 1: January 2026 BNP
INSERT INTO bank_statements (id, bank_account_id, imported_at, source_filename)
VALUES (1, 1, '2026-01-31 20:00:00', 'releve_bnp_janvier_2026.csv');

INSERT INTO bank_statement_lines (statement_id, line_date, label, amount, ref, matched_entry_line_id, status) VALUES
-- Matched lines (correspond to entry_lines)
(1, '2026-01-15', 'VIR RECU DUPONT', 2000.00, 'VIR001', (SELECT id FROM entry_lines WHERE entry_id = 6 AND line_no = 1), 'matched'),
(1, '2026-01-18', 'VIR EMIS ABC SARL', -660.00, 'VIR002', (SELECT id FROM entry_lines WHERE entry_id = 7 AND line_no = 2), 'matched'),
(1, '2026-01-20', 'FRAIS BANCAIRES', -25.00, 'FRA001', (SELECT id FROM entry_lines WHERE entry_id = 8 AND line_no = 2), 'matched'),
(1, '2026-01-25', 'VIR RECU MARTIN', 1500.00, 'VIR003', (SELECT id FROM entry_lines WHERE entry_id = 10 AND line_no = 1), 'matched'),
(1, '2026-01-31', 'VIR EMIS SALAIRES', -3500.00, 'VIR004', (SELECT id FROM entry_lines WHERE entry_id = 11 AND line_no = 3), 'matched'),
-- Unmatched lines (need reconciliation)
(1, '2026-01-28', 'PRLV ASSURANCE AUTO', -150.00, 'ASS001', NULL, 'unmatched'),
(1, '2026-01-29', 'CB AMAZON', -89.99, 'CB001', NULL, 'unmatched'),
(1, '2026-01-30', 'VIR RECU DIVERS', 250.00, 'VIR005', NULL, 'unmatched');

-- ============================================================================
-- LETTERING (Invoice/Payment Matching)
-- ============================================================================

-- Lettering group 1: Client Dupont - Invoice paid
INSERT INTO lettering_groups (id, account_id, third_party_id, created_at, created_by)
VALUES (1, (SELECT id FROM accounts WHERE code = '411001'), 1, '2026-01-15 16:30:00', 2);

INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES
-- Opening balance debit
(1, (SELECT id FROM entry_lines WHERE entry_id = 1 AND line_no = 3), 2000.00),
-- Payment credit
(1, (SELECT id FROM entry_lines WHERE entry_id = 6 AND line_no = 2), -2000.00);

-- Lettering group 2: Fournisseur ABC - Invoice paid
INSERT INTO lettering_groups (id, account_id, third_party_id, created_at, created_by)
VALUES (2, (SELECT id FROM accounts WHERE code = '401001'), 3, '2026-01-18 10:30:00', 2);

INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES
-- Invoice credit
(2, (SELECT id FROM entry_lines WHERE entry_id = 4 AND line_no = 3), -660.00),
-- Payment debit
(2, (SELECT id FROM entry_lines WHERE entry_id = 7 AND line_no = 1), 660.00);

-- ============================================================================
-- ATTACHMENTS
-- ============================================================================

INSERT INTO attachments (entry_id, filename, stored_path, uploaded_at) VALUES
(2, 'facture_dupont_2026-001.pdf', 'uploads/2026/01/facture_dupont_2026-001.pdf', '2026-01-05 10:05:00'),
(4, 'facture_abc_fournitures.pdf', 'uploads/2026/01/facture_abc_fournitures.pdf', '2026-01-10 09:05:00'),
(5, 'bail_location_2026.pdf', 'uploads/2026/01/bail_location_2026.pdf', '2026-01-12 11:05:00');
