/*
    Registre Bloc — améliorations (points 7 à 11 de la revue).
    À exécuter après 26_registre_bloc_corrections.sql.
*/
USE extra_bloc;
GO

/* --- 7/8) Expose nom/prénom patient séparément + date de naissance/sexe,
   en plus du champ "Patient" déjà combiné (conservé pour compatibilité). */
CREATE OR ALTER VIEW app.vw_registre_bloc_actes
AS
SELECT
    i.NumIntv, i.NumDoss, i.Code_Examen, i.DatOpe AS DateActe, i.Bloc, i.Salle,
    i.HDAnest, i.HFAnest, i.Nature,
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

/* --- 9) Modification/suppression tracées, avec accès dédié ou code de
   sécurité (voir AnapathRequestController). */
IF COL_LENGTH(N'app.anapath_demandes', N'modifie_par_username') IS NULL
    ALTER TABLE app.anapath_demandes ADD modifie_par_username nvarchar(100) NULL;
GO
IF COL_LENGTH(N'app.anapath_demandes', N'modifie_le') IS NULL
    ALTER TABLE app.anapath_demandes ADD modifie_le datetime2 NULL;
GO

/* --- 10) Liste des laboratoires (gérée manuellement, indépendante de l'ERP). */
IF OBJECT_ID(N'app.anapath_laboratoires', N'U') IS NULL
BEGIN
    CREATE TABLE app.anapath_laboratoires (
        id int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nom nvarchar(200) NOT NULL,
        actif bit NOT NULL CONSTRAINT DF_anapath_labo_actif DEFAULT 1,
        created_at datetime2 NOT NULL CONSTRAINT DF_anapath_labo_created DEFAULT SYSDATETIME()
    );
END
GO
IF COL_LENGTH(N'app.anapath_demandes', N'laboratoire_id') IS NULL
    ALTER TABLE app.anapath_demandes ADD laboratoire_id int NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_anapath_laboratoire')
    ALTER TABLE app.anapath_demandes
        ADD CONSTRAINT FK_anapath_laboratoire FOREIGN KEY (laboratoire_id) REFERENCES app.anapath_laboratoires(id);
GO

/* --- 11) Patient figé au moment de la demande (fiable même si l'ERP change
   ensuite). */
IF COL_LENGTH(N'app.anapath_demandes', N'patient_nom') IS NULL
    ALTER TABLE app.anapath_demandes ADD patient_nom nvarchar(200) NULL;
GO
IF COL_LENGTH(N'app.anapath_demandes', N'patient_prenom') IS NULL
    ALTER TABLE app.anapath_demandes ADD patient_prenom nvarchar(200) NULL;
GO
IF COL_LENGTH(N'app.anapath_demandes', N'patient_date_naissance') IS NULL
    ALTER TABLE app.anapath_demandes ADD patient_date_naissance date NULL;
GO
IF COL_LENGTH(N'app.anapath_demandes', N'patient_sexe') IS NULL
    ALTER TABLE app.anapath_demandes ADD patient_sexe nvarchar(10) NULL;
GO

/* --- Accès dédié à la modification/suppression (alternative au code de
   sécurité) : même schéma que major_users/rh_users. */
IF OBJECT_ID(N'app.anapath_editeurs', N'U') IS NULL
BEGIN
    CREATE TABLE app.anapath_editeurs (
        erp_username nvarchar(100) NOT NULL PRIMARY KEY,
        actif bit NOT NULL CONSTRAINT DF_anapath_editeurs_actif DEFAULT 1,
        ajoute_par nvarchar(100) NULL,
        created_at datetime2 NOT NULL CONSTRAINT DF_anapath_editeurs_created DEFAULT SYSDATETIME()
    );
END
GO

GRANT SELECT, INSERT, UPDATE ON app.anapath_laboratoires TO extra_bloc_app;
GRANT SELECT ON app.anapath_editeurs TO extra_bloc_app;
GO

/*
   Pour attribuer le droit de modifier/annuler une demande d'anapath SANS
   code de sécurité (accès permanent) :
   INSERT INTO app.anapath_editeurs (erp_username, ajoute_par)
   VALUES (N'IDENTIFIANT_ERP', N'ADMIN');

   Pour ajouter un laboratoire :
   INSERT INTO app.anapath_laboratoires (nom) VALUES (N'Laboratoire XYZ');
*/
