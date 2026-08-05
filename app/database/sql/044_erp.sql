/* À exécuter après 01, 02 et 03. Aucun historique local n'est modifié. */
USE extra_bloc;
GO

/* Ajout des identifiants nécessaires aux médecins et à la feuille anesthésie. */
CREATE OR ALTER VIEW app.vw_erp_actes_bloc
AS
SELECT i.NumIntv, i.NumDoss, i.Nature, CAST(N'OPERATION' AS nvarchar(20)) TypeActe,
       CAST(o.CodOpe AS nvarchar(50)) CodeActe, o.LibOpe LibelleActe,
       i.DatOpe, i.Bloc, i.Salle, i.HDAnest, i.HFAnest, i.CodMed, i.MedRea, i.Code_Examen,
       i.NomTech, i.Penseus, i.Aide2, i.Aide3
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Operat] o ON o.CodOpe = i.CodOpe
WHERE i.Nature = 2
UNION ALL
SELECT i.NumIntv, i.NumDoss, i.Nature, CAST(N'PANIER' AS nvarchar(20)) TypeActe,
       CAST(p.CodPan AS nvarchar(50)) CodeActe, p.LibPan LibelleActe,
       i.DatOpe, i.Bloc, i.Salle, i.HDAnest, i.HFAnest, i.CodMed, i.MedRea, i.Code_Examen,
       i.NomTech, i.Penseus, i.Aide2, i.Aide3
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Panier] p ON p.CodPan = i.Panier
WHERE i.Nature = 1;
GO

CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT a.NumIntv, a.NumDoss, a.TypeActe, a.CodeActe,
       roles.RoleIntervenant, roles.CodInterv, e.DesInterv, e.TypInterv,
       ti.DesTyp AS DesTypInterv, e.Matricule
FROM app.vw_erp_actes_bloc AS a
CROSS APPLY (VALUES
 (CAST(N'TECHNICIEN' AS nvarchar(30)), TRY_CONVERT(int, a.NomTech)),
 (CAST(N'PENSEUSE' AS nvarchar(30)), TRY_CONVERT(int, a.Penseus)),
 (CAST(N'AIDE_2' AS nvarchar(30)), TRY_CONVERT(int, a.Aide2)),
 (CAST(N'AIDE_3' AS nvarchar(30)), TRY_CONVERT(int, a.Aide3))
) roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenant] e ON e.CodInterv = roles.CodInterv
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[TypInterv] ti ON ti.CodTyp = e.TypInterv AND ti.Active = 1
WHERE roles.CodInterv IS NOT NULL;
GO

/* Détails présentés à la Direction. */
CREATE OR ALTER VIEW app.vw_erp_actes_bloc_direction
AS
SELECT a.*, sb.Designation AS DesignationSalle,
       mc.NomMed AS Chirurgien, mr.NomMed AS Reanimateur,
       fa.Debut_Anesthesie, fa.Fin_Anesthesie
FROM app.vw_erp_actes_bloc a
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[SALLE_BLOC] sb ON sb.Code = a.Salle AND sb.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mc ON mc.CodMed = a.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mr ON mr.CodMed = a.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie] fa ON fa.Code_Examen = a.Code_Examen;
GO
