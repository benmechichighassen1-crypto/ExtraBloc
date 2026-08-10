/*
    À exécuter après le script 12.
    Objet : quand le planning ERP indique un jour de REPOS pour l'intervenant,
    l'application affichait "Non renseigné" (comme si le planning était
    simplement vide), au lieu d'indiquer clairement qu'il est en repos.

    Cause : la colonne "Repos" existe déjà dans app.vw_erp_plannings (issue
    de EmploiT.Repos) mais n'était pas propagée dans
    app.vw_erp_planning_pointage_jour ni dans app.vw_erp_acte_intervenants.
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_erp_planning_pointage_jour
AS
SELECT
    p.Matricule,
    p.DatePlanning AS DateJour,
    p.He1 AS HeureEmploiDebut1, p.Hs1 AS HeureEmploiFin1,
    p.He2 AS HeureEmploiDebut2, p.Hs2 AS HeureEmploiFin2,
    p.Repos,
    pt.HeurePointageEntree,
    pt.HeurePointageSortie
FROM app.vw_erp_plannings AS p
LEFT JOIN app.vw_erp_pointages_jour AS pt
    ON pt.Matricule = p.Matricule AND pt.DateJour = p.DatePlanning;
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
 (CAST(N'TECHNICIEN' AS nvarchar(30)), TRY_CONVERT(int, a.NomTech)),
 (CAST(N'PENSEUSE' AS nvarchar(30)), TRY_CONVERT(int, a.Penseus)),
 (CAST(N'AIDE_2' AS nvarchar(30)), TRY_CONVERT(int, a.Aide2)),
 (CAST(N'AIDE_3' AS nvarchar(30)), TRY_CONVERT(int, a.Aide3)),
 (CAST(N'INSTRUMENTISTE' AS nvarchar(30)), TRY_CONVERT(int, a.NomInf))
) roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenants] e ON e.CodInterv = roles.CodInterv
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[TypInterv] ti ON ti.CodTyp = e.TypInterv AND ti.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] ac
  ON LTRIM(RTRIM(e.UserName)) COLLATE French_CI_AS = LTRIM(RTRIM(ac.UserName)) COLLATE French_CI_AS
LEFT JOIN app.vw_erp_planning_pointage_jour pp
  ON pp.Matricule = ac.Matricule AND pp.DateJour = CAST(a.DatOpe AS date)
WHERE roles.CodInterv IS NOT NULL;
GO
