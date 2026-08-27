-- ============================================================================
-- 20_montant_prevalidation.sql
-- Ajoute la colonne "montant" (100 / 150 / 200 / 250 / 300) à app.extra_declarations
-- pour la pré-validation par le major du bloc, et la correction par la Direction.
-- S'exécute en mode idempotent (ré-exécutable sans erreur).
-- Ne touche PAS à l'ERP : tout se passe dans la base applicative extra_bloc (schéma app.*).
-- ============================================================================

-- 1) Ajouter la colonne si absente
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('app.extra_declarations') AND name = 'montant')
    ALTER TABLE app.extra_declarations ADD montant int NULL;
GO

-- 2) (Re)créer la contrainte de contrôle sur les valeurs autorisées
IF EXISTS (SELECT 1 FROM sys.check_constraints
           WHERE name = 'CK_extra_declarations_montant'
             AND parent_object_id = OBJECT_ID('app.extra_declarations'))
    ALTER TABLE app.extra_declarations DROP CONSTRAINT CK_extra_declarations_montant;
GO
ALTER TABLE app.extra_declarations
    ADD CONSTRAINT CK_extra_declarations_montant
    CHECK (montant IS NULL OR montant IN (100, 150, 200, 250, 300));
GO

-- 3) Droits d'écriture (déjà accordés, conservé pour être explicite)
GRANT UPDATE ON app.extra_declarations TO extra_bloc_app;
GO

-- 4) Index utile pour filtrer/afficher le montant en prévalidation
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_extra_declarations_montant'
                 AND object_id = OBJECT_ID('app.extra_declarations'))
    CREATE INDEX IX_extra_declarations_montant ON app.extra_declarations (statut) INCLUDE (montant);
GO

PRINT 'Migration 20 : colonne montant prête.';
GO
