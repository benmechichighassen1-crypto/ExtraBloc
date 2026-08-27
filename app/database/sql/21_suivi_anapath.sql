-- ============================================================================
-- 21_suivi_anapath.sql
-- Ajoute les colonnes de SUIVI sur app.anapath_demandes :
--   - laboratoire (référence locale autonome, sans lien ERP)
--   - réception du résultat + compte rendu (PJ)
--   - mode de paiement anapath (laboratoire / inclus dans la facture patient)
-- Idempotent (ré-exécutable). Ne touche PAS à l'ERP.
-- ============================================================================

-- 1) Référentiel local des laboratoires (préchargé avec 3 valeurs de démo)
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'anapath_laboratoires' AND schema_id = SCHEMA_ID('app'))
    CREATE TABLE app.anapath_laboratoires (
        id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nom nvarchar(200) NOT NULL CONSTRAINT UQ_anapath_labo UNIQUE
    );
GO
INSERT INTO app.anapath_laboratoires (nom) VALUES (N'Laboratoire Anatomix'), (N'Laboratoire Biopôle'), (N'Laboratoire HistoLab');
GO

-- 2) Colonne laboratoire sur les demandes
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'laboratoire_id')
    ALTER TABLE app.anapath_demandes ADD laboratoire_id bigint NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_anapath_laboratoire'
               AND parent_object_id = OBJECT_ID('app.anapath_demandes'))
    ALTER TABLE app.anapath_demandes
        ADD CONSTRAINT FK_anapath_laboratoire FOREIGN KEY (laboratoire_id)
        REFERENCES app.anapath_laboratoires(id);
GO

-- 3) Colonnes de suivi résultat + paiement
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'resultat_recu')
    ALTER TABLE app.anapath_demandes ADD resultat_recu bit NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'resultat_recu_le')
    ALTER TABLE app.anapath_demandes ADD resultat_recu_le datetime2 NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'resultat_pj_nom')
    ALTER TABLE app.anapath_demandes ADD resultat_pj_nom nvarchar(255) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'resultat_pj_path')
    ALTER TABLE app.anapath_demandes ADD resultat_pj_path nvarchar(500) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'paiement_type')
    ALTER TABLE app.anapath_demandes ADD paiement_type nvarchar(20) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('app.anapath_demandes') AND name = 'paiement_date')
    ALTER TABLE app.anapath_demandes ADD paiement_date datetime2 NULL;
GO

-- Contrainte sur le mode de paiement
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_anapath_paiement_type'
           AND parent_object_id = OBJECT_ID('app.anapath_demandes'))
    ALTER TABLE app.anapath_demandes DROP CONSTRAINT CK_anapath_paiement_type;
GO
ALTER TABLE app.anapath_demandes
    ADD CONSTRAINT CK_anapath_paiement_type
    CHECK (paiement_type IS NULL OR paiement_type IN (N'laboratoire', N'facture'));
GO

-- Index pour la liste de suivi
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_anapath_suivi_resultat'
               AND object_id = OBJECT_ID('app.anapath_demandes'))
    CREATE INDEX IX_anapath_suivi_resultat ON app.anapath_demandes (resultat_recu) INCLUDE (paiement_type, created_at, laboratoire_id);
GO

GRANT INSERT, UPDATE ON app.anapath_demandes TO extra_bloc_app;
GO

PRINT 'Migration 21 : colonnes de suivi anapath prêtes.';
GO
