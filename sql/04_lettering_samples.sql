-- ============================================================================
-- LETTERING SAMPLE DATA
-- Rich sample data for testing automatic lettering feature
-- ============================================================================

-- ============================================================================
-- ADDITIONAL ACCOUNTS AND THIRD PARTIES
-- ============================================================================

-- More customer accounts
INSERT INTO accounts (code, label, type, is_active) VALUES
('411003', 'Client Leroy SARL', 'customer', 1),
('411004', 'Client Moreau & Fils', 'customer', 1),
('411005', 'Client Bernard SAS', 'customer', 1),
('411006', 'Client Petit Industries', 'customer', 1),
('411007', 'Client Roux Consulting', 'customer', 1);

-- More vendor accounts
INSERT INTO accounts (code, label, type, is_active) VALUES
('401003', 'Fournisseur Tech Solutions', 'vendor', 1),
('401004', 'Fournisseur Bureau Plus', 'vendor', 1),
('401005', 'Fournisseur LogiTrans', 'vendor', 1);

-- Third parties for new accounts
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Leroy SARL', id, 'contact@leroy-sarl.fr', datetime('now') FROM accounts WHERE code = '411003';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Moreau & Fils', id, 'comptabilite@moreau-fils.fr', datetime('now') FROM accounts WHERE code = '411004';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Bernard SAS', id, 'finance@bernard-sas.com', datetime('now') FROM accounts WHERE code = '411005';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Petit Industries', id, 'ap@petit-industries.fr', datetime('now') FROM accounts WHERE code = '411006';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Roux Consulting', id, 'admin@roux-consulting.fr', datetime('now') FROM accounts WHERE code = '411007';

INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Tech Solutions', id, 'facturation@tech-solutions.fr', datetime('now') FROM accounts WHERE code = '401003';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'Bureau Plus', id, 'commercial@bureau-plus.fr', datetime('now') FROM accounts WHERE code = '401004';
INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'vendor', 'LogiTrans', id, 'compta@logitrans.fr', datetime('now') FROM accounts WHERE code = '401005';

-- ============================================================================
-- SCENARIO 1: LEROY SARL - Perfect 1-to-1 matching
-- Invoice 2500 EUR, Payment 2500 EUR (exact match - easy auto-letter)
-- ============================================================================

-- Invoice FA-2026-010 to Leroy SARL
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-03', 1, 'VE2026-000010', 'Facture FA-2026-010 Leroy SARL', 'posted', 2500.00, 2500.00, 2, '2026-01-03 09:00:00', '2026-01-03 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411003'),
       (SELECT id FROM third_parties WHERE name = 'Leroy SARL'),
       'Leroy SARL - FA-2026-010', 2500.00, 0, '2026-02-03';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Prestations consulting', 0, 2083.33;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 416.67;

-- Payment from Leroy SARL (exact match)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-18', 1, 'BK2026-000010', 'Reglement Leroy SARL FA-2026-010', 'posted', 2500.00, 2500.00, 2, '2026-01-18 14:00:00', '2026-01-18 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Leroy SARL ref FA-2026-010', 2500.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411003'),
       (SELECT id FROM third_parties WHERE name = 'Leroy SARL'),
       'Reglement FA-2026-010', 0, 2500.00;

-- ============================================================================
-- SCENARIO 2: MOREAU & FILS - Multiple invoices, one payment (N-to-1)
-- 3 invoices (800 + 1200 + 500 = 2500), 1 payment of 2500
-- ============================================================================

-- Invoice FA-2026-020
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-05', 1, 'VE2026-000020', 'Facture FA-2026-020 Moreau', 'posted', 800.00, 800.00, 2, '2026-01-05 10:00:00', '2026-01-05 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411004'),
       (SELECT id FROM third_parties WHERE name = 'Moreau & Fils'),
       'Moreau & Fils - FA-2026-020', 800.00, 0, '2026-02-05';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Vente marchandises', 0, 666.67;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 133.33;

-- Invoice FA-2026-021
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-08', 1, 'VE2026-000021', 'Facture FA-2026-021 Moreau', 'posted', 1200.00, 1200.00, 2, '2026-01-08 11:00:00', '2026-01-08 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411004'),
       (SELECT id FROM third_parties WHERE name = 'Moreau & Fils'),
       'Moreau & Fils - FA-2026-021', 1200.00, 0, '2026-02-08';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Services maintenance', 0, 1000.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 200.00;

-- Invoice FA-2026-022
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-10', 1, 'VE2026-000022', 'Facture FA-2026-022 Moreau', 'posted', 500.00, 500.00, 2, '2026-01-10 14:00:00', '2026-01-10 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411004'),
       (SELECT id FROM third_parties WHERE name = 'Moreau & Fils'),
       'Moreau & Fils - FA-2026-022', 500.00, 0, '2026-02-10';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Vente pieces detachees', 0, 416.67;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 83.33;

-- Single payment covering all 3 invoices
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-20', 1, 'BK2026-000020', 'Reglement global Moreau & Fils', 'posted', 2500.00, 2500.00, 2, '2026-01-20 16:00:00', '2026-01-20 16:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Moreau FA-020/021/022', 2500.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411004'),
       (SELECT id FROM third_parties WHERE name = 'Moreau & Fils'),
       'Reglement factures FA-020 FA-021 FA-022', 0, 2500.00;

-- ============================================================================
-- SCENARIO 3: BERNARD SAS - One invoice, multiple payments (1-to-N)
-- 1 invoice of 3000, paid in 3 installments (1000 + 1000 + 1000)
-- ============================================================================

-- Large invoice FA-2026-030
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-02', 1, 'VE2026-000030', 'Facture FA-2026-030 Bernard SAS', 'posted', 3000.00, 3000.00, 2, '2026-01-02 09:00:00', '2026-01-02 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411005'),
       (SELECT id FROM third_parties WHERE name = 'Bernard SAS'),
       'Bernard SAS - FA-2026-030 Projet Alpha', 3000.00, 0, '2026-02-02';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Projet Alpha - Phase 1', 0, 2500.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 500.00;

-- Payment 1 of 3
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-10', 1, 'BK2026-000030', 'Acompte 1/3 Bernard SAS', 'posted', 1000.00, 1000.00, 2, '2026-01-10 10:00:00', '2026-01-10 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Bernard acompte 1', 1000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411005'),
       (SELECT id FROM third_parties WHERE name = 'Bernard SAS'),
       'Acompte 1/3 sur FA-2026-030', 0, 1000.00;

-- Payment 2 of 3
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-17', 1, 'BK2026-000031', 'Acompte 2/3 Bernard SAS', 'posted', 1000.00, 1000.00, 2, '2026-01-17 11:00:00', '2026-01-17 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Bernard acompte 2', 1000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411005'),
       (SELECT id FROM third_parties WHERE name = 'Bernard SAS'),
       'Acompte 2/3 sur FA-2026-030', 0, 1000.00;

-- Payment 3 of 3
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-24', 1, 'BK2026-000032', 'Solde Bernard SAS', 'posted', 1000.00, 1000.00, 2, '2026-01-24 14:00:00', '2026-01-24 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Bernard solde', 1000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411005'),
       (SELECT id FROM third_parties WHERE name = 'Bernard SAS'),
       'Solde FA-2026-030', 0, 1000.00;

-- ============================================================================
-- SCENARIO 4: PETIT INDUSTRIES - Payment with tolerance difference
-- Invoice 1500.00, Payment 1499.97 (within 0.05 tolerance)
-- ============================================================================

-- Invoice FA-2026-040
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-06', 1, 'VE2026-000040', 'Facture FA-2026-040 Petit Industries', 'posted', 1500.00, 1500.00, 2, '2026-01-06 10:00:00', '2026-01-06 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411006'),
       (SELECT id FROM third_parties WHERE name = 'Petit Industries'),
       'Petit Industries - FA-2026-040', 1500.00, 0, '2026-02-06';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Fournitures industrielles', 0, 1250.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 250.00;

-- Payment with small difference (bank rounding)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-22', 1, 'BK2026-000040', 'Reglement Petit Industries', 'posted', 1499.97, 1499.97, 2, '2026-01-22 15:00:00', '2026-01-22 15:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Petit Ind. FA-040', 1499.97, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411006'),
       (SELECT id FROM third_parties WHERE name = 'Petit Industries'),
       'Reglement FA-2026-040', 0, 1499.97;

-- ============================================================================
-- SCENARIO 5: ROUX CONSULTING - Unpaid old invoices (for manual review)
-- 2 invoices from different dates, no payments yet
-- ============================================================================

-- Old invoice FA-2026-050 (overdue)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-04', 1, 'VE2026-000050', 'Facture FA-2026-050 Roux Consulting', 'posted', 4200.00, 4200.00, 2, '2026-01-04 09:00:00', '2026-01-04 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411007'),
       (SELECT id FROM third_parties WHERE name = 'Roux Consulting'),
       'Roux Consulting - FA-2026-050 Mission conseil', 4200.00, 0, '2026-01-19';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Mission conseil strategique', 0, 3500.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 700.00;

-- Recent invoice FA-2026-051
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-15', 1, 'VE2026-000051', 'Facture FA-2026-051 Roux Consulting', 'posted', 1800.00, 1800.00, 2, '2026-01-15 14:00:00', '2026-01-15 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411007'),
       (SELECT id FROM third_parties WHERE name = 'Roux Consulting'),
       'Roux Consulting - FA-2026-051 Formation', 1800.00, 0, '2026-02-15';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Formation equipe commerciale', 0, 1500.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 300.00;

-- ============================================================================
-- SCENARIO 6: TECH SOLUTIONS (Vendor) - Multiple invoices, partial payment
-- 2 invoices (1800 + 2200 = 4000), payment of 3000 (partial)
-- ============================================================================

-- Vendor invoice from Tech Solutions #1
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, '2026-01-07', 1, 'AC2026-000010', 'Facture Tech Solutions - Serveur', 'posted', 1800.00, 1800.00, 2, '2026-01-07 09:00:00', '2026-01-07 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '606000'), 'Serveur informatique', 1500.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '44566'), 'TVA deductible 20%', 300.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '401003'),
       (SELECT id FROM third_parties WHERE name = 'Tech Solutions'),
       'Tech Solutions - Fact. serveur', 0, 1800.00;

-- Vendor invoice from Tech Solutions #2
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, '2026-01-14', 1, 'AC2026-000011', 'Facture Tech Solutions - Logiciels', 'posted', 2200.00, 2200.00, 2, '2026-01-14 10:00:00', '2026-01-14 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '606000'), 'Licences logiciels', 1833.33, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '44566'), 'TVA deductible 20%', 366.67, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '401003'),
       (SELECT id FROM third_parties WHERE name = 'Tech Solutions'),
       'Tech Solutions - Fact. logiciels', 0, 2200.00;

-- Partial payment to Tech Solutions
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-25', 1, 'BK2026-000050', 'Acompte Tech Solutions', 'posted', 3000.00, 3000.00, 2, '2026-01-25 11:00:00', '2026-01-25 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '401003'),
       (SELECT id FROM third_parties WHERE name = 'Tech Solutions'),
       'Acompte factures serveur/logiciels', 3000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Tech Solutions', 0, 3000.00;

-- ============================================================================
-- SCENARIO 7: BUREAU PLUS (Vendor) - Perfect 1-to-1 match
-- ============================================================================

-- Vendor invoice from Bureau Plus
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, '2026-01-09', 1, 'AC2026-000020', 'Facture Bureau Plus - Mobilier', 'posted', 960.00, 960.00, 2, '2026-01-09 14:00:00', '2026-01-09 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '606400'), 'Mobilier bureau', 800.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '44566'), 'TVA deductible 20%', 160.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '401004'),
       (SELECT id FROM third_parties WHERE name = 'Bureau Plus'),
       'Bureau Plus - Fact. mobilier', 0, 960.00;

-- Payment to Bureau Plus
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-23', 1, 'BK2026-000051', 'Reglement Bureau Plus', 'posted', 960.00, 960.00, 2, '2026-01-23 09:00:00', '2026-01-23 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '401004'),
       (SELECT id FROM third_parties WHERE name = 'Bureau Plus'),
       'Reglement fact. mobilier', 960.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Bureau Plus', 0, 960.00;

-- ============================================================================
-- SCENARIO 8: LOGITRANS (Vendor) - Unpaid invoices
-- 2 invoices waiting for payment
-- ============================================================================

-- Vendor invoice from LogiTrans #1
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, '2026-01-11', 1, 'AC2026-000030', 'Facture LogiTrans - Transport janvier', 'posted', 450.00, 450.00, 2, '2026-01-11 08:00:00', '2026-01-11 08:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '606000'), 'Transport marchandises', 375.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '44566'), 'TVA deductible 20%', 75.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '401005'),
       (SELECT id FROM third_parties WHERE name = 'LogiTrans'),
       'LogiTrans - Transport janv.', 0, 450.00;

-- Vendor invoice from LogiTrans #2
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (2, '2026-01-19', 1, 'AC2026-000031', 'Facture LogiTrans - Livraison express', 'posted', 180.00, 180.00, 2, '2026-01-19 15:00:00', '2026-01-19 15:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '606000'), 'Livraison express', 150.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '44566'), 'TVA deductible 20%', 30.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '401005'),
       (SELECT id FROM third_parties WHERE name = 'LogiTrans'),
       'LogiTrans - Livr. express', 0, 180.00;

-- ============================================================================
-- SCENARIO 9: DURAND DISTRIBUTION - SHOWCASE ACCOUNT
-- A realistic customer with complex history demonstrating automatic lettering
-- ============================================================================

-- Create the showcase customer account
INSERT INTO accounts (code, label, type, is_active) VALUES
('411010', 'Client Durand Distribution', 'customer', 1);

INSERT INTO third_parties (type, name, account_id, email, created_at)
SELECT 'customer', 'Durand Distribution', id, 'compta@durand-distrib.fr', datetime('now')
FROM accounts WHERE code = '411010';

-- ---------------------------------------------------------------------------
-- PART A: ALREADY LETTERED (History - shows the system works)
-- Invoice 5000 + Payment 5000 = perfectly lettered (AA)
-- ---------------------------------------------------------------------------

-- Old invoice FA-2025-100 (December 2025, carried forward)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-01', 1, 'VE2026-000100', 'Report a nouveau - Facture Durand Dec 2025', 'posted', 5000.00, 5000.00, 2, '2026-01-01 08:00:00', '2026-01-01 08:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-2025-100 Report', 5000.00, 0, '2026-01-15';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Report ventes Dec 2025', 0, 4166.67;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 833.33;

-- Payment for the old invoice
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-05', 1, 'BK2026-000100', 'Reglement Durand FA-2025-100', 'posted', 5000.00, 5000.00, 2, '2026-01-05 09:00:00', '2026-01-05 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Virement Durand ref 2025-100', 5000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Reglement FA-2025-100', 0, 5000.00;

-- Create lettering group for the above (already matched)
INSERT INTO lettering_groups (account_id, third_party_id, letter_code, is_partial, created_at, created_by)
SELECT
    (SELECT id FROM accounts WHERE code = '411010'),
    (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
    'AA', 0, datetime('now'), 2;

INSERT INTO lettering_items (group_id, entry_line_id, amount)
SELECT
    (SELECT MAX(id) FROM lettering_groups),
    el.id,
    CASE WHEN el.debit > 0 THEN el.debit ELSE -el.credit END
FROM entry_lines el
JOIN entries e ON el.entry_id = e.id
WHERE el.account_id = (SELECT id FROM accounts WHERE code = '411010')
  AND e.piece_number IN ('VE2026-000100', 'BK2026-000100');

-- ---------------------------------------------------------------------------
-- PART B: EASY 1-TO-1 MATCH (Auto-suggestion should find this immediately)
-- Invoice 2400 EUR + Payment 2400 EUR
-- ---------------------------------------------------------------------------

-- Invoice FA-2026-101
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-08', 1, 'VE2026-000101', 'Facture FA-2026-101 Durand Distribution', 'posted', 2400.00, 2400.00, 2, '2026-01-08 10:00:00', '2026-01-08 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-2026-101 Commande 5001', 2400.00, 0, '2026-02-08';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Marchandises lot A', 0, 2000.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 400.00;

-- Exact payment
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-20', 1, 'BK2026-000101', 'Virement Durand ref 101', 'posted', 2400.00, 2400.00, 2, '2026-01-20 14:00:00', '2026-01-20 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Durand Distrib - ref FA101', 2400.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Reglement FA-2026-101', 0, 2400.00;

-- ---------------------------------------------------------------------------
-- PART C: INVOICE + CREDIT NOTE MATCH
-- Invoice 3600 EUR - Credit note 600 EUR = Net 3000 EUR, Payment 3000 EUR
-- ---------------------------------------------------------------------------

-- Invoice FA-2026-102
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-10', 1, 'VE2026-000102', 'Facture FA-2026-102 Durand Distribution', 'posted', 3600.00, 3600.00, 2, '2026-01-10 11:00:00', '2026-01-10 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-2026-102 Equipement', 3600.00, 0, '2026-02-10';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Equipement professionnel', 0, 3000.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 600.00;

-- Credit note AV-2026-001 (avoir)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-12', 1, 'VE2026-000103', 'Avoir AV-2026-001 Durand - Retour partiel', 'posted', 600.00, 600.00, 2, '2026-01-12 09:00:00', '2026-01-12 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - AV-2026-001 Retour defectueux', 0, 600.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Retour equipement defectueux', 500.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20% avoir', 100.00, 0;

-- Payment for net amount (3600 - 600 = 3000)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-22', 1, 'BK2026-000102', 'Virement Durand net FA102-AV001', 'posted', 3000.00, 3000.00, 2, '2026-01-22 15:00:00', '2026-01-22 15:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Durand - Solde FA102 moins avoir', 3000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Reglement FA-102 net avoir AV-001', 0, 3000.00;

-- ---------------------------------------------------------------------------
-- PART D: MULTIPLE SMALL INVOICES, ONE PAYMENT (N-to-1)
-- 4 invoices: 450 + 550 + 600 + 400 = 2000 EUR, Payment 2000 EUR
-- ---------------------------------------------------------------------------

-- Invoice FA-2026-104
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-14', 1, 'VE2026-000104', 'Facture FA-2026-104 Durand', 'posted', 450.00, 450.00, 2, '2026-01-14 08:00:00', '2026-01-14 08:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-104 Fournitures', 450.00, 0, '2026-02-14';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Fournitures diverses', 0, 375.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 75.00;

-- Invoice FA-2026-105
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-15', 1, 'VE2026-000105', 'Facture FA-2026-105 Durand', 'posted', 550.00, 550.00, 2, '2026-01-15 09:00:00', '2026-01-15 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-105 Consommables', 550.00, 0, '2026-02-15';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Consommables bureau', 0, 458.33;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 91.67;

-- Invoice FA-2026-106
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-16', 1, 'VE2026-000106', 'Facture FA-2026-106 Durand', 'posted', 600.00, 600.00, 2, '2026-01-16 10:00:00', '2026-01-16 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-106 Accessoires', 600.00, 0, '2026-02-16';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Accessoires informatiques', 0, 500.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 100.00;

-- Invoice FA-2026-107
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-17', 1, 'VE2026-000107', 'Facture FA-2026-107 Durand', 'posted', 400.00, 400.00, 2, '2026-01-17 11:00:00', '2026-01-17 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-107 Petit materiel', 400.00, 0, '2026-02-17';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Petit materiel', 0, 333.33;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 66.67;

-- Single payment for all 4 invoices
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-25', 1, 'BK2026-000103', 'Virement global Durand FA104-107', 'posted', 2000.00, 2000.00, 2, '2026-01-25 14:00:00', '2026-01-25 14:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Durand - Reglement groupe janv', 2000.00, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Reglement FA-104/105/106/107', 0, 2000.00;

-- ---------------------------------------------------------------------------
-- PART E: PAYMENT WITH TOLERANCE (Bank rounding)
-- Invoice 1850 EUR, Payment 1849.98 EUR (difference within 0.05 tolerance)
-- ---------------------------------------------------------------------------

-- Invoice FA-2026-108
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-18', 1, 'VE2026-000108', 'Facture FA-2026-108 Durand', 'posted', 1850.00, 1850.00, 2, '2026-01-18 10:00:00', '2026-01-18 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-108 Services', 1850.00, 0, '2026-02-18';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '706000'), 'Prestation conseil', 0, 1541.67;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 308.33;

-- Payment with minor difference (within tolerance)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (3, '2026-01-28', 1, 'BK2026-000104', 'Virement Durand FA-108', 'posted', 1849.98, 1849.98, 2, '2026-01-28 11:00:00', '2026-01-28 11:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '512001'), 'Durand - FA108 (arrondi banque)', 1849.98, 0;
INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Reglement FA-2026-108', 0, 1849.98;

-- ---------------------------------------------------------------------------
-- PART F: UNPAID INVOICES (Outstanding balance)
-- 2 invoices waiting - shows real balance situation
-- ---------------------------------------------------------------------------

-- Invoice FA-2026-109 (unpaid)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-26', 1, 'VE2026-000109', 'Facture FA-2026-109 Durand', 'posted', 4500.00, 4500.00, 2, '2026-01-26 09:00:00', '2026-01-26 09:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-109 Grosse commande', 4500.00, 0, '2026-02-26';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Commande speciale Q1', 0, 3750.00;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 750.00;

-- Invoice FA-2026-110 (unpaid)
INSERT INTO entries (journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
VALUES (1, '2026-01-29', 1, 'VE2026-000110', 'Facture FA-2026-110 Durand', 'posted', 2200.00, 2200.00, 2, '2026-01-29 10:00:00', '2026-01-29 10:00:00');

INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, due_date)
SELECT last_insert_rowid(), 1, (SELECT id FROM accounts WHERE code = '411010'),
       (SELECT id FROM third_parties WHERE name = 'Durand Distribution'),
       'Durand - FA-110 Complement', 2200.00, 0, '2026-02-28';
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 2, (SELECT id FROM accounts WHERE code = '707000'), 'Complement commande Q1', 0, 1833.33;
INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
SELECT (SELECT MAX(id) FROM entries), 3, (SELECT id FROM accounts WHERE code = '44571'), 'TVA 20%', 0, 366.67;

-- ============================================================================
-- SUMMARY for DURAND DISTRIBUTION (411010):
-- ============================================================================
-- ALREADY LETTERED (AA):
--   FA-2025-100: +5000 | Payment: -5000 = 0
--
-- TO BE LETTERED BY AUTO-SUGGESTION:
--   B: FA-101 +2400 | Payment -2400 = 0 (easy 1-to-1)
--   C: FA-102 +3600, AV-001 -600 | Payment -3000 = 0 (invoice + credit note)
--   D: FA-104 +450, FA-105 +550, FA-106 +600, FA-107 +400 | Payment -2000 = 0 (N-to-1)
--   E: FA-108 +1850 | Payment -1849.98 = 0.02 (within tolerance)
--
-- UNPAID (Outstanding):
--   FA-109: +4500 (due Feb 26)
--   FA-110: +2200 (due Feb 28)
--   Total outstanding: 6700 EUR
-- ============================================================================

-- ============================================================================
-- UPDATE COMPANY LETTERING TOLERANCE
-- ============================================================================
UPDATE company SET lettering_tolerance = 0.05 WHERE id = 1;

-- ============================================================================
-- UPDATE JOURNAL COUNTERS
-- ============================================================================
UPDATE journals SET next_number = (SELECT MAX(CAST(SUBSTR(piece_number, 8) AS INTEGER)) + 1 FROM entries WHERE piece_number LIKE 'VE%') WHERE code = 'VE';
UPDATE journals SET next_number = (SELECT MAX(CAST(SUBSTR(piece_number, 8) AS INTEGER)) + 1 FROM entries WHERE piece_number LIKE 'AC%') WHERE code = 'AC';
UPDATE journals SET next_number = (SELECT MAX(CAST(SUBSTR(piece_number, 8) AS INTEGER)) + 1 FROM entries WHERE piece_number LIKE 'BK%') WHERE code = 'BK';
