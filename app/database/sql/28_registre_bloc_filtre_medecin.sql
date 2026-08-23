/*
    À exécuter après le script 27.
    1) Expose CodMed (chirurgien) en plus du nom, pour permettre un filtre
       par médecin (choix exact via code, comme le filtre Intervenant de
       Direction).
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_registre_bloc_actes
AS
SELECT
    i.NumIntv, i.NumDoss, i.Code_Examen, i.DatOpe AS DateActe, i.Bloc, i.Salle,
    i.HDAnest, i.HFAnest, i.Nature, i.CodMed,
    c.NomCli + N' ' + ISNULL(c.Prenom, N'') AS Patient,
    c.NomCli AS NomPatient, c.Prenom AS PrenomPatient,
    c.DatNai AS DateNaissancePatient, c.sex AS SexePatient,
    CASE WHEN i.Nature = 2 THEN o.LibOpe ELSE p.LibPan END AS LibelleActe,
    mc.NomMed AS Chirurgien, mr.NomMed AS Reanimateur,
    COALESCE(sl.Designation, i.Salle) AS DesignationSalle,
    bl.CodBloc, bl.LibBloc,
    d.ValiderMedDictee, d.User_Valid AS CompteRenduValidePar,
    TRY_CONVERT(datetime2, d.Date_Valid) AS CompteRenduValideLe,
    CASE
       WHEN d.Numero_Examen IS NULL THEN N'ABSENT'
       WHEN d.ValiderMedDictee = 1 AND d.Date_Valid IS NOT NULL THEN N'VALIDE'
       ELSE N'EN_COURS'
    END AS StatutCompteRendu
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv] AS i
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[client] AS c ON c.NumDoss = i.NumDoss
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Operat] AS o ON o.CodOpe = i.CodOpe AND i.Nature = 2
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Panier] AS p ON p.CodPan = i.Panier AND i.Nature = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] AS mc ON mc.CodMed = i.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] AS mr ON mr.CodMed = i.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[salles] AS sl ON sl.Salle = i.Salle AND sl.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[bloc] AS bl ON bl.CodBloc = sl.CodBloc AND bl.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d ON d.Numero_Examen = i.Code_Examen
WHERE i.Nature IN (1, 2);
GO

/*
    2) DIAGNOSTIC (lecture seule) — doublons observés sur COLOSCOPIE /
       FIBROSCOPIE GASTRIQUE. Vérifie si ce sont bien 2 lignes Interv
       distinctes (2 NumIntv) pour ce qui semble être le même acte physique.
       Adapter le NumDoss à un des cas vus dans la capture (26025023).
*/
SELECT NumIntv, NumDoss, Nature, CodOpe, Panier, DatOpe, Code_Examen, Salle, CodMed, HDAnest, HFAnest
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv]
WHERE NumDoss = '26025023'
ORDER BY DatOpe;
GO
