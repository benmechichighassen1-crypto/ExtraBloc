/*
    À exécuter après le script 19.

    1) intervenants.CodInterv est nvarchar(25) (confirmé). Notre vue
       comparait ce champ à un entier (TRY_CONVERT(int, ...)), forçant SQL
       Server à convertir CHAQUE ligne de la table distante "intervenants"
       avant de pouvoir joindre — potentiellement coûteux si cette table est
       volumineuse. Correctif : produire une chaîne nvarchar(25) trimée du
       même type des deux côtés de la jointure, au lieu d'un entier.

    2) EmploiT.PasEmploie est varchar(1) (confirmé). Notre vue comparait
       "= 0" (entier). Correctif : comparer à '0' (même type).

    3) Bug bloquant "Impossible de trouver le serveur '[ERP_LINK]'" : venait
       du contrôleur PHP qui interrogeait directement une table liée avec un
       nom entre crochets ("[ERP_LINK].[gclinique_maroc]...") — Laravel
       rajoute AUTOMATIQUEMENT des crochets autour de chaque segment de nom
       de table pour SQL Server, ce qui doublait les crochets et cassait la
       résolution du nom de serveur lié. Comme partout ailleurs dans
       l'application, on passe désormais par une VUE locale (app.vw_erp_blocs)
       interrogée avec un nom simple, sans crochets manuels.
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT a.NumIntv, a.NumDoss, a.TypeActe, a.CodeActe,
       roles.RoleIntervenant, roles.CodInterv, e.DesInterv, e.TypInterv,
       ti.DesTyp AS DesTypInterv,
       e.UserName AS LoginErp,
       ac.Matricule AS MatriculePointeuse,
       pp.HeureEmploiDebut1, pp.HeureEmploiFin1,
       pp.HeureEmploiDebut2, pp.HeureEmploiFin2,
       pp.Repos,
       pp.HeurePointageEntree, pp.HeurePointageSortie
FROM app.vw_erp_actes_bloc AS a
CROSS APPLY (VALUES
 (CAST(N'TECHNICIEN' AS nvarchar(30)), NULLIF(LTRIM(RTRIM(CAST(a.NomTech AS nvarchar(25)))), N'')),
 (CAST(N'PENSEUSE' AS nvarchar(30)), NULLIF(LTRIM(RTRIM(CAST(a.Penseus AS nvarchar(25)))), N'')),
 (CAST(N'AIDE_2' AS nvarchar(30)), NULLIF(LTRIM(RTRIM(CAST(a.Aide2 AS nvarchar(25)))), N'')),
 (CAST(N'AIDE_3' AS nvarchar(30)), NULLIF(LTRIM(RTRIM(CAST(a.Aide3 AS nvarchar(25)))), N'')),
 (CAST(N'INSTRUMENTISTE' AS nvarchar(30)), NULLIF(LTRIM(RTRIM(CAST(a.NomInf AS nvarchar(25)))), N''))
) roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenants] e ON e.CodInterv = roles.CodInterv
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[TypInterv] ti ON ti.CodTyp = e.TypInterv AND ti.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] ac
  ON LTRIM(RTRIM(e.UserName)) COLLATE French_CI_AS = LTRIM(RTRIM(ac.UserName)) COLLATE French_CI_AS
LEFT JOIN app.vw_erp_planning_pointage_jour pp
  ON pp.Matricule = ac.Matricule AND pp.DateJour = CAST(a.DatOpe AS date)
WHERE roles.CodInterv IS NOT NULL;
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
WHERE et.pasemploie = '0';
GO

/* Vue dédiée pour le filtre "Bloc" — corrige le bug ERP_LINK. */
CREATE OR ALTER VIEW app.vw_erp_blocs
AS
SELECT CodBloc, LibBloc
FROM [ERP_LINK].[gclinique_maroc].[dbo].[bloc]
WHERE Active = 1;
GO
