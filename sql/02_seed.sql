-- Seed data for PHP Legacy Accounting Application

SET NAMES utf8;

-- Admin user (password: admin123, MD5 with salt 'legacy')
-- MD5('legacy' . 'admin123') = md5('legacyadmin123')
INSERT INTO users (username, password_hash, role, created_at) VALUES
('admin', '5d93ceb70e2bf5daa84ec3d0cd2c731a', 'admin', NOW()),
('comptable', 'e5e0f7cd43c1c3e3f3f4a4c5d6e7f8a9', 'accountant', NOW()),
('lecteur', 'a1b2c3d4e5f6789012345678901234ab', 'viewer', NOW());

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
INSERT INTO third_parties (type, name, account_id, email, created_at) VALUES
('customer', 'Client Dupont', (SELECT id FROM accounts WHERE code = '411001'), 'dupont@example.com', NOW()),
('customer', 'Client Martin', (SELECT id FROM accounts WHERE code = '411002'), 'martin@example.com', NOW()),
('vendor', 'Fournisseur ABC', (SELECT id FROM accounts WHERE code = '401001'), 'abc@example.com', NOW()),
('vendor', 'Fournisseur XYZ', (SELECT id FROM accounts WHERE code = '401002'), 'xyz@example.com', NOW());

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
INSERT INTO bank_accounts (label, account_id, is_active) VALUES
('Compte BNP Principal', (SELECT id FROM accounts WHERE code = '512001'), 1);
