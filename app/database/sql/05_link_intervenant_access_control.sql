/* À exécuter après les scripts précédents. */
USE extra_bloc;
GO

/*
  Chaîne native ERP : intervenants.UserName
  → [Access Control].UserName → [Access Control].Matricule (pointeuse).
*/
CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT a.NumIntv, a.NumDoss, a.TypeActe, a.CodeActe,
       roles.RoleIntervenant, roles.CodInterv, e.DesInterv, e.TypInterv,
       ti.DesTyp AS DesTypInterv,
       e.UserName AS LoginErp,
       ac.Matricule AS MatriculePointeuse
FROM app.vw_erp_actes_bloc AS a
CROSS APPLY (VALUES
 (CAST(N'TECHNICIEN' AS nvarchar(30)), TRY_CONVERT(int, a.NomTech)),
 (CAST(N'PENSEUSE' AS nvarchar(30)), TRY_CONVERT(int, a.Penseus)),
 (CAST(N'AIDE_2' AS nvarchar(30)), TRY_CONVERT(int, a.Aide2)),
 (CAST(N'AIDE_3' AS nvarchar(30)), TRY_CONVERT(int, a.Aide3))
) roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenants] e ON e.CodInterv = roles.CodInterv
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[TypInterv] ti ON ti.CodTyp = e.TypInterv AND ti.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] ac
  ON LTRIM(RTRIM(e.UserName)) COLLATE French_CI_AS = LTRIM(RTRIM(ac.UserName)) COLLATE French_CI_AS
WHERE roles.CodInterv IS NOT NULL;
GO

/* Requête de contrôle : les lignes sans matricule pointeuse doivent être corrigées dans l'ERP. */
SELECT CodInterv, DesInterv, LoginErp, MatriculePointeuse
FROM app.vw_erp_acte_intervenants
WHERE MatriculePointeuse IS NULL;
