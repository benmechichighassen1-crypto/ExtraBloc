/*
    Registre Bloc : module médico-légal autonome.
    À exécuter dans la base extra_bloc, après les scripts Extra Bloc.
    Aucune table ERP n'est modifiée : le linked server ERP_LINK reste en SELECT.
*/
USE extra_bloc;
GO

/*
  Liste principale du registre. La jointure vers Details_ExamenLaboRadio se
  fait sur Interv.Code_Examen = Details_ExamenLaboRadio.Numero_Examen.
  Le statut est calculé sans recopier le compte rendu ERP dans la base locale.
*/
CREATE OR ALTER VIEW app.vw_registre_bloc_actes
AS
SELECT
    i.NumIntv, i.NumDoss, i.Code_Examen, i.DatOpe AS DateActe, i.Bloc, i.Salle,
    i.HDAnest, i.HFAnest, i.Nature,
    c.NomCli + N' ' + ISNULL(c.Prenom, N'') AS Patient,
    CASE WHEN i.Nature = 2 THEN o.LibOpe ELSE p.LibPan END AS LibelleActe,
    mc.NomMed AS Chirurgien, mr.NomMed AS Reanimateur, sb.Designation AS DesignationSalle,
    d.ValiderMedDictee, d.User_Valid AS CompteRenduValidePar,
    d.Date_Valid AS CompteRenduValideLe,
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
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[SALLE_BLOC] AS sb ON sb.Code = i.Salle AND sb.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d ON d.Numero_Examen = i.Code_Examen
WHERE i.Nature IN (1, 2);
GO

/*
  Les deux familles radio sont normalisées. UNION ALL est conservé (plus
  rapide que UNION) car CodExam est dans deux catalogues différents.
  Le contrôleur filtre DateValidation avec une borne exclusive le lendemain,
  ce qui préserve l'utilisation de l'index sur Date_Valid si l'ERP en a un.
*/
CREATE OR ALTER VIEW app.vw_registre_bloc_radiologies
AS
SELECT ec.Numero_Dossier AS NumDoss, d.Numero_Examen, r.CodExam,
       r.DesExam AS Designation, md.NomMed AS MedecinDictee,
       d.Date_Ope AS DateRealisation, d.User_Valid AS UserValide,
       d.Date_Valid AS DateValidation,
       CASE WHEN d.ValiderMedDictee = 1 AND d.Date_Valid IS NOT NULL THEN N'Validé' ELSE N'Non validé' END AS Statut
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examen_complementaire] AS ec ON ec.Code_Examen = d.Numero_Examen
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examrad] AS r ON r.CodExam = d.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Medecin] AS md ON md.CodMed = d.MedDictee
WHERE r.Ecriture_Cpt = 1
UNION ALL
SELECT ec.Numero_Dossier, d.Numero_Examen, irm.CodExam,
       irm.DesExam, md.NomMed, d.Date_Ope, d.User_Valid, d.Date_Valid,
       CASE WHEN d.ValiderMedDictee = 1 AND d.Date_Valid IS NOT NULL THEN N'Validé' ELSE N'Non validé' END
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Details_ExamenLaboRadio] AS d
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examen_complementaire] AS ec ON ec.Code_Examen = d.Numero_Examen
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[examirm] AS irm ON irm.CodExam = d.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Medecin] AS md ON md.CodMed = d.MedDictee;
GO

CREATE TABLE app.anapath_sequences (
    annee char(4) NOT NULL PRIMARY KEY,
    dernier_numero int NOT NULL CONSTRAINT CK_anapath_sequence_positive CHECK (dernier_numero > 0)
);
GO
CREATE TABLE app.anapath_demandes (
    id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    numero_demande nvarchar(30) NOT NULL CONSTRAINT UQ_anapath_numero UNIQUE,
    num_intv nvarchar(30) NOT NULL,
    num_doss nvarchar(30) NOT NULL,
    code_examen_erp nvarchar(50) NULL,
    nature_prelevement nvarchar(250) NOT NULL,
    site_anatomique nvarchar(250) NOT NULL,
    nombre_flacons smallint NOT NULL CONSTRAINT CK_anapath_flacons CHECK (nombre_flacons BETWEEN 1 AND 99),
    fixateur nvarchar(100) NULL,
    renseignements_cliniques nvarchar(4000) NOT NULL,
    urgence bit NOT NULL CONSTRAINT DF_anapath_urgence DEFAULT 0,
    cree_par_username nvarchar(100) NOT NULL,
    created_at datetime2 NOT NULL CONSTRAINT DF_anapath_created DEFAULT SYSUTCDATETIME(),
    annule_par_username nvarchar(100) NULL,
    annule_le datetime2 NULL,
    motif_annulation nvarchar(500) NULL,
    row_version rowversion NOT NULL,
    CONSTRAINT UQ_anapath_acte_active UNIQUE (num_intv, annule_le)
);
GO
/* SQL Server autorise plusieurs NULL dans UQ : index filtré garantit une seule demande active. */
CREATE UNIQUE INDEX UX_anapath_demande_active ON app.anapath_demandes(num_intv) WHERE annule_le IS NULL;
CREATE INDEX IX_anapath_dossier ON app.anapath_demandes(num_doss, created_at DESC);
GO
CREATE TABLE app.anapath_audits (
    id bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    demande_id bigint NOT NULL,
    action nvarchar(50) NOT NULL,
    acteur_username nvarchar(100) NOT NULL,
    donnees_avant nvarchar(max) NULL,
    donnees_apres nvarchar(max) NULL,
    created_at datetime2 NOT NULL CONSTRAINT DF_anapath_audit_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_anapath_audit_demande FOREIGN KEY (demande_id) REFERENCES app.anapath_demandes(id)
);
CREATE INDEX IX_anapath_audit_demande ON app.anapath_audits(demande_id, created_at DESC);
GO
GRANT SELECT ON app.vw_registre_bloc_actes TO extra_bloc_app;
GRANT SELECT ON app.vw_registre_bloc_radiologies TO extra_bloc_app;
GRANT SELECT, INSERT, UPDATE ON app.anapath_sequences TO extra_bloc_app;
GRANT SELECT, INSERT, UPDATE ON app.anapath_demandes TO extra_bloc_app;
GRANT SELECT, INSERT ON app.anapath_audits TO extra_bloc_app;
GO
