/*
    À exécuter après le script 11.
    Objet : corriger deux anomalies observées dans l'historique intervenant :
      1) La colonne "Patient" affichait parfois une date au lieu du nom.
      2) La colonne "Salle" restait vide.

    Cause probable : app.vw_erp_actes_bloc_direction et app.vw_erp_actes_bloc
    utilisaient `a.*` (toutes les colonnes de la vue sous-jacente) empilé sur
    plusieurs étages de vues, dont une UNION ALL. Le driver SQL Server de PHP
    peut mal aligner les colonnes en sortie dans ce genre de montage. On
    élimine le risque en listant EXPLICITEMENT chaque colonne à chaque étage
    (aucun `SELECT *` ni `a.*` nulle part), ce qui garantit un nommage et un
    ordre de colonnes stables et sans ambiguïté.

    Comportement inchangé par ailleurs : mêmes colonnes disponibles qu'avant.
    Petit bonus : DesignationSalle retombe sur le code brut de la salle
    (a.Salle) si aucune correspondance n'est trouvée dans SALLE_BLOC, plutôt
    que de rester vide.
*/
USE extra_bloc;
GO

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

CREATE OR ALTER VIEW app.vw_erp_actes_bloc_direction
AS
SELECT
    a.NumIntv,
    a.NumDoss,
    a.Nature,
    a.TypeActe,
    a.CodeActe,
    a.LibelleActe,
    a.DatOpe,
    a.Bloc,
    a.Salle,
    a.HDAnest,
    a.HFAnest,
    a.CodMed,
    a.MedRea,
    a.Code_Examen,
    a.NomTech,
    a.Penseus,
    a.Aide2,
    a.Aide3,
    a.NomInf,
    COALESCE(sb.Designation, a.Salle) AS DesignationSalle,
    mc.NomMed AS Chirurgien,
    mr.NomMed AS Reanimateur,
    fa.Debut_Anesthesie,
    fa.Fin_Anesthesie,
    c.NomCli AS NomPatient,
    c.Prenom AS PrenomPatient
FROM app.vw_erp_actes_bloc a
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[SALLE_BLOC] sb ON sb.Code = a.Salle AND sb.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mc ON mc.CodMed = a.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mr ON mr.CodMed = a.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie] fa ON fa.Code_Examen = a.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[client] c ON c.NumDoss = a.NumDoss;
GO
