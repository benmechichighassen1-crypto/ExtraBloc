-- ============================================================================
-- 23_anapath_pj.sql
-- Crée la table des PIÈCES JOINTES (comptes rendus) d'anapath :
--   app.anapath_demandes_pj
-- Une demande peut désormais avoir PLUSIEURS PJ : chaque fichier est une ligne.
-- (Auparavant une seule PJ, écrasée à chaque nouvel envoi.)
-- Idempotent (ré-exécutable). Ne touche PAS à l'ERP.
-- ============================================================================

-- 1) Table des pièces jointes
IF NOT EXISTS (SELECT 1 FROM sys.tables
               WHERE name = 'anapath_demandes_pj' AND schema_id = SCHEMA_ID('app'))
BEGIN
    CREATE TABLE app.anapath_demandes_pj (
        id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
        demande_id bigint NOT NULL,
        nom nvarchar(255) NOT NULL,
        path nvarchar(500) NOT NULL,
        uploaded_by_username nvarchar(120) NULL,
        created_at datetime2 NOT NULL DEFAULT SYSDATETIME()
    );
END
GO

-- 2) Index pour retrouver les PJ d'une demande
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_anapath_pj_demande'
               AND object_id = OBJECT_ID('app.anapath_demandes_pj'))
    CREATE INDEX IX_anapath_pj_demande ON app.anapath_demandes_pj (demande_id, created_at);
GO

-- 3) Reprise des PJ déjà enregistrées (colonne historique resultat_pj_*),
--    pour ne perdre aucune pièce jointe existante. Ré-exécutable sans doublon.
INSERT INTO app.anapath_demandes_pj (demande_id, nom, path, uploaded_by_username, created_at)
SELECT d.id, d.resultat_pj_nom, d.resultat_pj_path, d.modifie_par_username,
       COALESCE(d.resultat_recu_le, d.created_at)
FROM app.anapath_demandes d
WHERE d.resultat_pj_path IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM app.anapath_demandes_pj p WHERE p.demande_id = d.id);
GO

GRANT INSERT, SELECT ON app.anapath_demandes_pj TO extra_bloc_app;
GO

PRINT 'Migration 23 : pièces jointes anapath prêtes.';
GO
