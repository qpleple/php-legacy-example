-- Hidden business logic triggers
-- Added for "data integrity" - 2008
-- These run silently on every INSERT/UPDATE/DELETE

-- ==============================================
-- TRAP 1: Auto-round amounts to 2 decimals
-- ==============================================
CREATE TRIGGER IF NOT EXISTS trg_round_entry_line_insert
BEFORE INSERT ON entry_lines
BEGIN
    SELECT CASE
        WHEN NEW.debit > 999999.99 OR NEW.credit > 999999.99
        THEN RAISE(ABORT, 'Montant trop eleve (max 999999.99)')
    END;
END;

CREATE TRIGGER IF NOT EXISTS trg_round_entry_line_update
BEFORE UPDATE ON entry_lines
BEGIN
    SELECT CASE
        WHEN NEW.debit > 999999.99 OR NEW.credit > 999999.99
        THEN RAISE(ABORT, 'Montant trop eleve (max 999999.99)')
    END;
END;

-- ==============================================
-- TRAP 2: Prevent deletion of posted entries
-- ==============================================
CREATE TRIGGER IF NOT EXISTS trg_protect_posted_entries
BEFORE DELETE ON entries
WHEN OLD.status = 'posted'
BEGIN
    SELECT RAISE(ABORT, 'Impossible de supprimer une ecriture validee');
END;

-- ==============================================
-- TRAP 3: Auto-update hidden timestamp column
-- ==============================================
-- First, we need to add the column (will fail silently if exists)
-- ALTER TABLE entries ADD COLUMN _touched_at TEXT;

CREATE TRIGGER IF NOT EXISTS trg_entry_touch_insert
AFTER INSERT ON entries
BEGIN
    UPDATE entries SET created_at = datetime('now') WHERE id = NEW.id AND created_at IS NULL;
END;

-- ==============================================
-- TRAP 4: Silent audit on sensitive deletes
-- ==============================================
CREATE TRIGGER IF NOT EXISTS trg_audit_account_delete
BEFORE DELETE ON accounts
BEGIN
    INSERT INTO audit_log (user_id, action, entity, entity_id, details, created_at)
    VALUES (
        COALESCE((SELECT id FROM users WHERE username = 'system'), 0),
        'DELETE',
        'accounts',
        OLD.id,
        'Code: ' || OLD.code || ', Label: ' || OLD.label,
        datetime('now')
    );
END;
