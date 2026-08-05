/*
    Extra Bloc - base applicative autonome
    À exécuter sur l'instance SQL Server qui héberge ERP_LINK.

    Prérequis :
      - le linked server ERP_LINK fonctionne déjà ;
      - son compte distant possède uniquement SELECT sur gclinique_maroc et GpointeuseN ;
      - l'exécution se fait avec un compte SQL Server administrateur.
*/

USE master;
GO

IF DB_ID(N'extra_bloc') IS NULL
    CREATE DATABASE extra_bloc;
GO

USE extra_bloc;
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = N'app')
    EXEC(N'CREATE SCHEMA app');
GO

/* Compte utilisé par Laravel : droits limités à la base applicative. */
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'extra_bloc_app')
    CREATE USER extra_bloc_app WITHOUT LOGIN;
GO

CREATE OR ALTER VIEW app.vw_erp_utilisateurs
AS
SELECT
    ac.UserName,
    ac.Matricule,
    ac.Description,
    ac.Grp,
    ac.fonction,
    ac.profil,
    ac.Type_user,
    ac.Actif,
    ac.CompteExpire,
    ac.expire
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] AS ac;
GO

/* Vue strictement réservée au fournisseur d'authentification Laravel. */
CREATE OR ALTER VIEW app.vw_erp_authentification
AS
SELECT
    ac.UserName,
    ac.Matricule,
    ac.Description,
    ac.Grp,
    ac.Actif,
    ac.CompteExpire,
    ac.hasched_password AS PasswordHash,
    ac.salt AS PasswordSalt,
    ac.hash_iterations AS HashIterations
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] AS ac;
GO

/* Une seule vue normalisée pour les actes "opération" et "panier". */
CREATE OR ALTER VIEW app.vw_erp_actes_bloc
AS
SELECT
    i.NumIntv,
    i.NumDoss,
    i.Nature,
    CAST(N'OPERATION' AS nvarchar(20)) AS TypeActe,
    CAST(o.CodOpe AS nvarchar(50)) AS CodeActe,
    o.LibOpe AS LibelleActe,
    i.DatOpe,
    i.Bloc,
    i.Salle,
    i.HDAnest,
    i.HFAnest,
    i.CodMed,
    i.MedRea,
    i.Code_Examen,
    i.NomTech,
    i.Penseus,
    i.Aide2,
    i.Aide3
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] AS i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Operat] AS o ON o.CodOpe = i.CodOpe
WHERE i.Nature = 2
UNION ALL
SELECT
    i.NumIntv,
    i.NumDoss,
    i.Nature,
    CAST(N'PANIER' AS nvarchar(20)) AS TypeActe,
    CAST(p.CodPan AS nvarchar(50)) AS CodeActe,
    p.LibPan AS LibelleActe,
    i.DatOpe,
    i.Bloc,
    i.Salle,
    i.HDAnest,
    i.HFAnest,
    i.CodMed,
    i.MedRea,
    i.Code_Examen,
    i.NomTech,
    i.Penseus,
    i.Aide2,
    i.Aide3
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] AS i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Panier] AS p ON p.CodPan = i.Panier
WHERE i.Nature = 1;
GO

/* Déplie les quatre intervenants d'un acte en lignes exploitables. */
CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT
    a.NumIntv,
    a.NumDoss,
    a.TypeActe,
    a.CodeActe,
    roles.RoleIntervenant,
    roles.CodInterv,
    e.DesInterv,
    e.TypInterv,
    e.Matricule
FROM app.vw_erp_actes_bloc AS a
CROSS APPLY (VALUES
    (CAST(N'TECHNICIEN' AS nvarchar(30)), TRY_CONVERT(int, a.NomTech)),
    (CAST(N'PENSEUSE' AS nvarchar(30)), TRY_CONVERT(int, a.Penseus)),
    (CAST(N'AIDE_2' AS nvarchar(30)), TRY_CONVERT(int, a.Aide2)),
    (CAST(N'AIDE_3' AS nvarchar(30)), TRY_CONVERT(int, a.Aide3))
) AS roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenant] AS e ON e.CodInterv = roles.CodInterv
WHERE roles.CodInterv IS NOT NULL;
GO

/* Source de contrôle de présence : uniquement les pointages réels (Etat = P). */
CREATE OR ALTER VIEW app.vw_erp_pointages
AS
SELECT DISTINCT
    p.MatEmp AS Matricule,
    e.NOMEMP + N' ' + e.PRENEMP AS NomComplet,
    DATEADD(
        SECOND,
        DATEDIFF(SECOND, CAST('00:00:00' AS time), CAST(p.HeurePointage AS time)),
        CAST(p.DatePointage AS datetime2)
    ) AS PointeLe,
    p.Etat
FROM [ERP_LINK].[GpointeuseN].[dbo].[GetSqlPointage] AS p
INNER JOIN [ERP_LINK].[GpointeuseN].[dbo].[EMPLOYER] AS e
    ON p.MatEmp COLLATE French_CI_AS = e.MATEMP COLLATE French_CI_AS
WHERE p.Etat = 'P';
GO

/*
   Planning théorique : les heures restent en type time pour permettre les
   comparaisons fiables avec HDAnest / HFAnest dans l'application.
*/
CREATE OR ALTER VIEW app.vw_erp_plannings
AS
SELECT
    et.Matemp AS Matricule,
    CAST(et.dat AS date) AS DatePlanning,
    CAST(et.He1 AS time) AS He1,
    CAST(et.Hs1 AS time) AS Hs1,
    CAST(et.He2 AS time) AS He2,
    CAST(et.Hs2 AS time) AS Hs2,
    et.Repos,
    CAST(et.hr1 AS time) AS Hr1,
    CAST(et.hr2 AS time) AS Hr2,
    CAST(et.hs_auts AS time) AS HsAuts,
    CAST(et.he_auts AS time) AS HeAuts,
    CAST(et.hs_demi_j AS time) AS HsDemiJournee,
    CAST(et.he_demi_j AS time) AS HeDemiJournee,
    CAST(et.he_ga AS time) AS HeGA,
    CAST(et.hs_ga AS time) AS HsGA,
    CAST(et.he_va AS time) AS HeVA,
    CAST(et.hs_va AS time) AS HsVA,
    CAST(et.he_urg AS time) AS HeUrg,
    CAST(et.hs_urg AS time) AS HsUrg,
    CAST(et.he_urg2 AS time) AS HeUrg2,
    CAST(et.hs_urg2 AS time) AS HsUrg2,
    CAST(et.hs_al AS time) AS HsAL,
    CAST(et.he_al AS time) AS HeAL,
    CAST(et.hs_al2 AS time) AS HsAL2,
    CAST(et.he_al2 AS time) AS HeAL2,
    CAST(et.he_om AS time) AS HeOM,
    CAST(et.hs_om AS time) AS HsOM,
    e.NOMEMP + N' ' + e.PRENEMP AS NomComplet,
    s.LIBSER AS Service,
    po.Lib_Poste AS Poste,
    q.libqual AS Qualification
FROM [ERP_LINK].[GpointeuseN].[dbo].[EmploiT] AS et
INNER JOIN [ERP_LINK].[GpointeuseN].[dbo].[EMPLOYER] AS e ON et.Matemp = e.MATEMP
INNER JOIN [ERP_LINK].[GpointeuseN].[dbo].[SERVICE] AS s ON e.MATSER = s.MATSER
LEFT JOIN [ERP_LINK].[GpointeuseN].[dbo].[Poste] AS po ON et.CodePoste = po.Code_Poste
LEFT JOIN [ERP_LINK].[GpointeuseN].[dbo].[V_QUALIFICATION] AS q ON e.CODQUAL = q.codqual
WHERE et.pasemploie = 0;
GO

CREATE TABLE app.user_intervenant_mappings (
    id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    erp_username nvarchar(100) NOT NULL,
    erp_matricule nvarchar(50) NULL,
    cod_interv int NOT NULL,
    actif bit NOT NULL CONSTRAINT DF_mapping_actif DEFAULT 1,
    created_at datetime2 NOT NULL CONSTRAINT DF_mapping_created DEFAULT SYSUTCDATETIME(),
    updated_at datetime2 NOT NULL CONSTRAINT DF_mapping_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_mapping_username UNIQUE (erp_username),
    CONSTRAINT UQ_mapping_intervenant UNIQUE (cod_interv)
);
GO

CREATE TABLE app.extra_declarations (
    id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    num_intv nvarchar(30) NOT NULL,
    num_doss nvarchar(30) NOT NULL,
    cod_interv int NOT NULL,
    role_intervenant nvarchar(30) NOT NULL,
    declared_by_username nvarchar(100) NOT NULL,
    declared_at datetime2 NOT NULL CONSTRAINT DF_declaration_created DEFAULT SYSUTCDATETIME(),
    statut nvarchar(20) NOT NULL CONSTRAINT DF_declaration_statut DEFAULT N'SOUMIS',
    prevalidation_auto bit NULL,
    motif_prevalidation nvarchar(500) NULL,
    pointage_avant datetime2 NULL,
    pointage_apres datetime2 NULL,
    valide_par_username nvarchar(100) NULL,
    valide_le datetime2 NULL,
    motif_decision nvarchar(500) NULL,
    row_version rowversion NOT NULL,
    CONSTRAINT CK_declaration_statut CHECK (statut IN (N'SOUMIS', N'PREVALIDE', N'VALIDE', N'REJETE')),
    CONSTRAINT UQ_declaration_acte_intervenant UNIQUE (num_intv, cod_interv)
);
GO

CREATE TABLE app.extra_declaration_audits (
    id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    declaration_id bigint NOT NULL,
    action nvarchar(50) NOT NULL,
    acteur_username nvarchar(100) NOT NULL,
    donnees_avant nvarchar(max) NULL,
    donnees_apres nvarchar(max) NULL,
    created_at datetime2 NOT NULL CONSTRAINT DF_audit_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_audit_declaration FOREIGN KEY (declaration_id)
        REFERENCES app.extra_declarations(id)
);
GO

CREATE INDEX IX_declarations_statut_date ON app.extra_declarations(statut, declared_at DESC);
CREATE INDEX IX_declarations_num_doss ON app.extra_declarations(num_doss);
CREATE INDEX IX_audits_declaration ON app.extra_declaration_audits(declaration_id, created_at DESC);
GO

GRANT SELECT ON SCHEMA::app TO extra_bloc_app;
GRANT INSERT, UPDATE ON app.extra_declarations TO extra_bloc_app;
GRANT INSERT ON app.extra_declaration_audits TO extra_bloc_app;
GRANT SELECT, INSERT, UPDATE ON app.user_intervenant_mappings TO extra_bloc_app;
GO

/*
  Après création du login SQL correspondant, lier le login à cet utilisateur :
  USE extra_bloc;
  ALTER USER extra_bloc_app WITH LOGIN = extra_bloc_app;
  Les GRANT ci-dessus restent applicables.
*/
