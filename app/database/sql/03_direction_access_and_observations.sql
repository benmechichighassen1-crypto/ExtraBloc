/* À exécuter une seule fois après les scripts 01 et 02. */
USE extra_bloc;
GO

IF COL_LENGTH(N'app.extra_declarations', N'observation') IS NULL
    ALTER TABLE app.extra_declarations ADD observation nvarchar(1000) NULL;
GO

IF OBJECT_ID(N'app.direction_users', N'U') IS NULL
BEGIN
    CREATE TABLE app.direction_users (
        erp_username nvarchar(100) NOT NULL PRIMARY KEY,
        actif bit NOT NULL CONSTRAINT DF_direction_users_actif DEFAULT 1,
        ajoute_par nvarchar(100) NULL,
        created_at datetime2 NOT NULL CONSTRAINT DF_direction_users_created DEFAULT SYSUTCDATETIME()
    );
END
GO

GRANT SELECT ON app.direction_users TO extra_bloc_app;
GO

/*
   Pour attribuer l'accès Direction :
   INSERT INTO app.direction_users (erp_username, ajoute_par)
   VALUES (N'IDENTIFIANT_ERP_DIRECTION', N'ADMIN');

   Pour retirer l'accès sans supprimer l'historique :
   UPDATE app.direction_users SET actif = 0
   WHERE erp_username = N'IDENTIFIANT_ERP_DIRECTION';
*/
