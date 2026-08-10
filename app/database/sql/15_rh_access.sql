/*
    À exécuter après le script 14.
    Objet : introduire un accès RH, en LECTURE SEULE, limité aux
    déclarations VALIDÉES (utile pour la paie / le contrôle RH), avec
    export Excel. Contrairement à Direction (validation/rejet/dévalidation)
    et Major (pré-validation), RH ne peut ni valider, ni rejeter, ni
    dévalider quoi que ce soit.
*/
USE extra_bloc;
GO

IF OBJECT_ID(N'app.rh_users', N'U') IS NULL
BEGIN
    CREATE TABLE app.rh_users (
        erp_username nvarchar(100) NOT NULL PRIMARY KEY,
        actif bit NOT NULL CONSTRAINT DF_rh_users_actif DEFAULT 1,
        ajoute_par nvarchar(100) NULL,
        created_at datetime2 NOT NULL CONSTRAINT DF_rh_users_created DEFAULT SYSDATETIME()
    );
END
GO

GRANT SELECT ON app.rh_users TO extra_bloc_app;
GO

/*
   Pour attribuer l'accès RH à un utilisateur :
   INSERT INTO app.rh_users (erp_username, ajoute_par)
   VALUES (N'IDENTIFIANT_ERP_RH', N'ADMIN');

   Pour retirer l'accès sans supprimer l'historique :
   UPDATE app.rh_users SET actif = 0 WHERE erp_username = N'IDENTIFIANT_ERP_RH';
*/
