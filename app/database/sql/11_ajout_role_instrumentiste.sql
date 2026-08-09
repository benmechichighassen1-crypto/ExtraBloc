/*
    À exécuter après les scripts 01 à 10.
    Objet : la table ERP [Interv] (source de app.vw_erp_actes_bloc, dans ses
    deux branches OPERATION et PANIER) expose un champ NomInf contenant le
    code de l'instrumentiste de l'acte, au même titre que NomTech (technicien),
    Penseus (panseuse), Aide2 et Aide3. Ce champ n'était pas encore repris :
    l'instrumentiste ne remontait donc dans aucune interface.

    Ce script réintègre l'intégralité de app.vw_erp_actes_bloc et
    app.vw_erp_acte_intervenants (dernière version : script 06), en y
    ajoutant simplement NomInf / le rôle INSTRUMENTISTE. Rien d'autre ne
    change dans ces deux vues.
*/
USE extra_bloc;
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
    i.Aide3,
    i.NomInf
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
    i.Aide3,
    i.NomInf
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] AS i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Panier] AS p ON p.CodPan = i.Panier
WHERE i.Nature = 1;
GO

/*
   Intervenants de l'acte (déplie les cinq rôles en lignes), avec heure
   d'emploi et heure de pointage réelle du jour de l'acte.
*/
CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT a.NumIntv, a.NumDoss, a.TypeActe, a.CodeActe,
       roles.RoleIntervenant, roles.CodInterv, e.DesInterv, e.TypInterv,
       ti.DesTyp AS DesTypInterv,
       e.UserName AS LoginErp,
       ac.Matricule AS MatriculePointeuse,
       pp.HeureEmploiDebut1, pp.HeureEmploiFin1,
       pp.HeureEmploiDebut2, pp.HeureEmploiFin2,
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
