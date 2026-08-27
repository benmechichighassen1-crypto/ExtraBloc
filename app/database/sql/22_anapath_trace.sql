-- ============================================================================
-- 22_anapath_trace.sql
-- Crée la table de TRAÇABILITÉ des demandes d'anapath :
--   app.anapath_demandes_audits
-- Chaque action (réception du résultat, PJ, paiement, annulation, réactivation)
-- est journalisée avec l'utilisateur, la date et le détail.
-- Idempotent (ré-exécutable). Ne touche PAS à l'ERP.
-- ============================================================================

-- 1) Table de journalisation
IF NOT EXISTS (SELECT 1 FROM sys.tables
               WHERE name = 'anapath_demandes_audits' AND schema_id = SCHEMA_ID('app'))
BEGIN
    CREATE TABLE app.anapath_demandes_audits (
        id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
        demande_id bigint NOT NULL,
        action nvarchar(60) NOT NULL,
        acteur_username nvarchar(120) NULL,
        action_date datetime2 NOT NULL DEFAULT SYSDATETIME(),
        details nvarchar(500) NULL
    );
END
GO

-- 2) Index pour retrouver l'historique d'une demande
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_anapath_audits_demande'
               AND object_id = OBJECT_ID('app.anapath_demandes_audits'))
    CREATE INDEX IX_anapath_audits_demande ON app.anapath_demandes_audits (demande_id, action_date);
GO

GRANT INSERT, SELECT ON app.anapath_demandes_audits TO extra_bloc_app;
GO

PRINT 'Migration 22 : traçabilité anapath prête.';
GO
