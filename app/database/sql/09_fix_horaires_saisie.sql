/*
    Corrige le décalage d'environ 1 heure observé sur "Heure de saisie" /
    "Décidé le" / la traçabilité, dans l'interface Direction.

    Cause : les colonnes ci-dessous utilisaient SYSUTCDATETIME() (heure UTC)
    par défaut, alors que toutes les heures issues de l'ERP (planification,
    pointage, anesthésie…) sont stockées en heure locale du Maroc. Résultat :
    une déclaration saisie à 16:26 heure locale était enregistrée comme
    15:26 UTC, puis réaffichée telle quelle (15:26) sans conversion.

    Correctif : basculer ces colonnes sur SYSDATETIME() (heure locale du
    serveur SQL), et aligner le fuseau horaire de l'application Laravel
    sur Africa/Casablanca (voir config/app.php) pour que les timestamps
    générés côté PHP (valide_le, prevalide_le) soient eux aussi en heure
    locale, cohérents avec le reste.

    Prérequis : l'horloge du serveur SQL Server doit être réglée sur
    l'heure locale du Maroc (à vérifier si ce n'est pas déjà le cas).

    Les lignes déjà existantes en base ne sont PAS corrigées par ce script
    (elles seront de toute façon purgées par 07_reset_donnees_mouvement.sql
    avant le démarrage en réel). Seuls les nouveaux enregistrements créés
    après ce script seront à la bonne heure.
*/
USE extra_bloc;
GO

ALTER TABLE app.extra_declarations DROP CONSTRAINT DF_declaration_created;
ALTER TABLE app.extra_declarations ADD CONSTRAINT DF_declaration_created DEFAULT (SYSDATETIME()) FOR declared_at;
GO

ALTER TABLE app.extra_declaration_audits DROP CONSTRAINT DF_audit_created;
ALTER TABLE app.extra_declaration_audits ADD CONSTRAINT DF_audit_created DEFAULT (SYSDATETIME()) FOR created_at;
GO

ALTER TABLE app.direction_users DROP CONSTRAINT DF_direction_users_created;
ALTER TABLE app.direction_users ADD CONSTRAINT DF_direction_users_created DEFAULT (SYSDATETIME()) FOR created_at;
GO

ALTER TABLE app.user_intervenant_mappings DROP CONSTRAINT DF_mapping_created;
ALTER TABLE app.user_intervenant_mappings ADD CONSTRAINT DF_mapping_created DEFAULT (SYSDATETIME()) FOR created_at;
ALTER TABLE app.user_intervenant_mappings DROP CONSTRAINT DF_mapping_updated;
ALTER TABLE app.user_intervenant_mappings ADD CONSTRAINT DF_mapping_updated DEFAULT (SYSDATETIME()) FOR updated_at;
GO
