/*
    À exécuter après le script 18.
    Objet : corrige l'avertissement "PlanAffectingConvert" observé dans le
    plan d'exécution transmis.

    Cause confirmée : Interv.Nature est de type char(1) (NULL possible).
    Nos vues comparaient "i.Nature = 2" / "i.Nature = 1" (littéraux entiers),
    ce qui forçait SQL Server à convertir IMPLICITEMENT la colonne pour
    chaque ligne de la table distante avant de pouvoir comparer — ce type de
    conversion empêche l'optimiseur d'estimer correctement le nombre de
    lignes attendu, et peut donc conduire à un plan sous-optimal (mauvais
    choix d'algorithme de jointure, etc.).

    Correctif : comparer Nature à des littéraux char (N'2' / N'1'), du même
    type que la colonne réelle. Comportement fonctionnel strictement
    identique, seule la façon de comparer change.
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
WHERE i.Nature = '2'
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
WHERE i.Nature = '1';
GO