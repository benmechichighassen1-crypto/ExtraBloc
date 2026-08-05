/*
    À exécuter UNE SEULE FOIS après 01_create_extra_bloc.sql.
    Ce script ne recrée aucune table et peut être relancé sans perte de données.
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_erp_authentification
AS
SELECT
    ac.UserName,
    ac.Matricule,
    ac.Description,
    ac.Grp,
    ac.fonction,
    ac.iscontrolleur,
    ac.Actif,
    ac.CompteExpire,
    ac.hasched_password AS PasswordHash,
    ac.salt AS PasswordSalt,
    ac.hash_iterations AS HashIterations
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] AS ac;
GO

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
