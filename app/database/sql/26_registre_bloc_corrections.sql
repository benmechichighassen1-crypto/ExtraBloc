/*
    Registre Bloc — corrections suite à revue de code + bug remonté.
    À exécuter après 22_registre_bloc.sql.

    1) "Conversion nvarchar -> datetime hors limites" sur DateValidation :
       Details_ExamenLaboRadio.Date_Valid / Date_Ope sont très probablement
       stockées en nvarchar côté ERP (comme Interv.Nature, PasEmploie...
       déjà rencontrés dans Extra Bloc). TRY_CONVERT rend la vue robuste
       quel que soit le type réel, sans faire planter la requête sur une
       valeur invalide.

    2) [SALLE_BLOC] est une table DEVINÉE, jamais confirmée, et déjà
       remplacée dans Extra Bloc par les vraies tables fournies par vos
       soins : dbo.bloc (CodBloc, LibBloc) et dbo.salles (CodBloc, Salle,
       Designation). On applique la même correction ici.

    3) created_at utilisait SYSUTCDATETIME() (heure UTC) alors que
       l'application (config/app.php) est en Africa/Casablanca depuis la
       correction faite sur Extra Bloc — incohérence corrigée en
       SYSDATETIME() (heure locale du serveur).

    4) PROBLÈME IMPORTANT : NumIntv seul n'est PAS un identifiant stable
       côté ERP (réutilisé d'une année sur l'autre pour des actes
       différents — confirmé sur Extra Bloc). L'index unique filtré qui
       n'autorise qu'une seule demande active PAR NumIntv doit donc être
       étendu à (NumIntv, NumDoss), sous peine de bloquer une demande
       légitime sur un acte différent qui partage un NumIntv réutilisé.
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_registre_bloc_actes
AS
SELECT
    i.NumIntv, i.NumDoss, i.Code_Examen, i.DatOpe AS DateActe, i.Bloc, i.Salle,
    i.HDAnest, i.HFAnest, i.Nature,
    c.NomCli + N' ' + ISNULL(c.Prenom, N'') AS Patient,
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

CREATE OR ALTER VIEW app.vw_registre_bloc_radiologies
AS
SELECT ec.Numero_Dossier AS NumDoss, d.Numero_Examen, r.CodExam,
       r.DesExam AS Designation, md.NomMed AS MedecinDictee,
       TRY_CONVERT(datetime2, d.Date_Ope) AS DateRealisation,
       d.User_Valid AS UserValide,
       TRY_CONVERT(datetime2, d.Date_Valid) AS DateValidation,
       CASE WHEN d.ValiderMedDictee = 1 AND d.Date_Valid IS NOT NULL THEN N'Validé' ELSE N'Non validé' END AS Statut
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examen_complementaire] AS ec ON ec.Code_Examen = d.Numero_Examen
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examrad] AS r ON r.CodExam = d.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Medecin] AS md ON md.CodMed = d.MedDictee
WHERE r.Ecriture_Cpt = 1
UNION ALL
SELECT ec.Numero_Dossier, d.Numero_Examen, irm.CodExam,
       irm.DesExam, md.NomMed,
       TRY_CONVERT(datetime2, d.Date_Ope),
       d.User_Valid,
       TRY_CONVERT(datetime2, d.Date_Valid),
       CASE WHEN d.ValiderMedDictee = 1 AND d.Date_Valid IS NOT NULL THEN N'Validé' ELSE N'Non validé' END
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examen_complementaire] AS ec ON ec.Code_Examen = d.Numero_Examen
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examirm] AS irm ON irm.CodExam = d.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Medecin] AS md ON md.CodMed = d.MedDictee;
GO

/* Timestamps en heure locale, cohérent avec Extra Bloc. */
ALTER TABLE app.anapath_demandes DROP CONSTRAINT DF_anapath_created;
ALTER TABLE app.anapath_demandes ADD CONSTRAINT DF_anapath_created DEFAULT (SYSDATETIME()) FOR created_at;
GO
ALTER TABLE app.anapath_audits DROP CONSTRAINT DF_anapath_audit_created;
ALTER TABLE app.anapath_audits ADD CONSTRAINT DF_anapath_audit_created DEFAULT (SYSDATETIME()) FOR created_at;
GO

/* Corrige l'index unique : "une seule demande active" doit se vérifier par
   (NumIntv, NumDoss), pas NumIntv seul (réutilisé d'une année sur l'autre). */
DROP INDEX UX_anapath_demande_active ON app.anapath_demandes;
GO
CREATE UNIQUE INDEX UX_anapath_demande_active ON app.anapath_demandes(num_intv, num_doss) WHERE annule_le IS NULL;
GO
