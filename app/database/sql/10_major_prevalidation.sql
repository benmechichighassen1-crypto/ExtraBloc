/*
    À exécuter après le script 09.
    Objet : introduire un rôle "major du bloc" pouvant PRÉ-valider ou
    rejeter une déclaration en amont de la validation finale par la
    Direction. Le statut PREVALIDE existe déjà dans la contrainte
    CK_declaration_statut (schéma d'origine) : il ne restait qu'à outiller
    l'accès et la traçabilité de cette étape intermédiaire.

    Circuit complet :
      SOUMIS (intervenant) -> PREVALIDE (major, optionnel) -> VALIDE/REJETE (Direction)
      SOUMIS -> REJETE directement possible, par le major ou la Direction.
*/
USE extra_bloc;
GO

/* Traçabilité de la pré-validation, en miroir des colonnes valide_par_username/valide_le. */
IF COL_LENGTH(N'app.extra_declarations', N'prevalide_par_username') IS NULL
    ALTER TABLE app.extra_declarations ADD prevalide_par_username nvarchar(100) NULL;
GO
IF COL_LENGTH(N'app.extra_declarations', N'prevalide_le') IS NULL
    ALTER TABLE app.extra_declarations ADD prevalide_le datetime2 NULL;
GO

/* Liste blanche des identifiants ERP autorisés à pré-valider (majors du bloc). */
IF OBJECT_ID(N'app.major_users', N'U') IS NULL
BEGIN
    CREATE TABLE app.major_users (
        erp_username nvarchar(100) NOT NULL PRIMARY KEY,
        actif bit NOT NULL CONSTRAINT DF_major_users_actif DEFAULT 1,
        ajoute_par nvarchar(100) NULL,
        created_at datetime2 NOT NULL CONSTRAINT DF_major_users_created DEFAULT SYSDATETIME()
    );
END
GO

GRANT SELECT ON app.major_users TO extra_bloc_app;
GO

/*
   Pour attribuer l'accès "pré-validation" à un major du bloc :
   INSERT INTO app.major_users (erp_username, ajoute_par)
   VALUES (N'IDENTIFIANT_ERP_MAJOR', N'ADMIN');

   Pour retirer l'accès sans supprimer l'historique :
   UPDATE app.major_users SET actif = 0
   WHERE erp_username = N'IDENTIFIANT_ERP_MAJOR';

   NB : les comptes déjà présents dans app.direction_users ont aussi accès
   à l'écran de pré-validation (la Direction peut prévalider si besoin),
   sans qu'il soit nécessaire de les ajouter également dans app.major_users.
*/
