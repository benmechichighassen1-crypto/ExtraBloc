/*
    À exécuter après le script 28.

    CAUSE CONFIRMÉE DU DOUBLON (diagnostic du client) :
    Details_ExamenLaboRadio peut contenir PLUSIEURS lignes pour le même
    Numero_Examen (plusieurs validations/révisions du compte rendu, avec
    des Date_Valid différentes). Le simple LEFT JOIN sur
    "d.Numero_Examen = i.Code_Examen" fait donc un fan-out : CHAQUE ligne
    Interv (Coloscopie, Fibroscopie...) se multiplie par le nombre de
    lignes Details_ExamenLaboRadio partageant le même Code_Examen.

    Ce n'est PAS une double saisie côté ERP (confirmé : une seule ligne
    Interv par Nature pour ce dossier) — c'est notre jointure qui doit
    être corrigée pour ne garder qu'UNE seule ligne (la plus récente,
    validée en priorité) par Numero_Examen.
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
-- Une seule ligne Details_ExamenLaboRadio par Code_Examen : la plus
-- récente, en priorisant celle qui est validée.
OUTER APPLY (
    SELECT TOP 1 d2.*
    FROM [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d2
    WHERE d2.Numero_Examen = i.Code_Examen
    ORDER BY
        CASE WHEN d2.ValiderMedDictee = 1 THEN 1 ELSE 0 END DESC,
        TRY_CONVERT(datetime2, d2.Date_Valid) DESC
) AS d
WHERE i.Nature IN (1, 2);
GO

/* Médecin prescripteur : champ obligatoire du formulaire anapath, par
   défaut rempli avec le chirurgien de l'acte (modifiable). */
IF COL_LENGTH(N'app.anapath_demandes', N'medecin_prescripteur') IS NULL
    ALTER TABLE app.anapath_demandes ADD medecin_prescripteur nvarchar(200) NULL;
GO

