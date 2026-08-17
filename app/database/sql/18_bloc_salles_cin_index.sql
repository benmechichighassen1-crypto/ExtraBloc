/*
    À exécuter après le script 17.
    Regroupe plusieurs corrections issues des retours terrain et de
    l'analyse du plan d'exécution fourni :

    1) Remplace la jointure vers [SALLE_BLOC] (table devinée, jamais
       confirmée) par les VRAIES tables ERP données par le client :
         - dbo.bloc   (CodBloc, LibBloc, Active...)
         - dbo.salles (CodBloc, Salle, Designation, Active...)
       Expose aussi CodBloc/LibBloc pour permettre un filtre "Bloc" (choix
       unique, ex: "Bloc Opératoire" regroupe les salles 1 à 5) plutôt
       qu'un filtre multi-salles jugé peu pratique.

    2) Ajoute c.NumCIN (carte d'identité nationale) sous le nom CinPatient :
       plus fiable que c.Identifiant pour rapprocher un même patient entre
       son dossier d'origine et un sous-dossier créé par la facturation
       (l'Identifiant peut différer d'un dossier à l'autre, le NumCIN non).

    3) Ajoute des index locaux sur app.extra_declarations : le plan
       d'exécution fourni signale l'absence de statistiques sur num_intv et
       l'absence d'index dédié sur cod_interv/statut, très sollicités par
       les filtres (intervenant, historique, détection de doublons).
*/
USE extra_bloc;
GO

CREATE OR ALTER VIEW app.vw_erp_actes_bloc_direction
AS
SELECT
    a.NumIntv,
    a.NumDoss,
    a.Nature,
    a.TypeActe,
    a.CodeActe,
    a.LibelleActe,
    a.DatOpe,
    a.Bloc,
    a.Salle,
    a.HDAnest,
    a.HFAnest,
    a.CodMed,
    a.MedRea,
    a.Code_Examen,
    a.NomTech,
    a.Penseus,
    a.Aide2,
    a.Aide3,
    a.NomInf,
    COALESCE(sl.Designation, a.Salle) AS DesignationSalle,
    bl.CodBloc,
    bl.LibBloc,
    mc.NomMed AS Chirurgien,
    mr.NomMed AS Reanimateur,
    fa.Debut_Anesthesie,
    fa.Fin_Anesthesie,
    c.NomCli AS NomPatient,
    c.Prenom AS PrenomPatient,
    c.Identifiant AS IdentifiantPatient,
    c.NumCIN AS CinPatient
FROM app.vw_erp_actes_bloc a
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[salles] sl ON sl.Salle = a.Salle AND sl.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[bloc] bl ON bl.CodBloc = sl.CodBloc AND bl.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mc ON mc.CodMed = a.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mr ON mr.CodMed = a.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie] fa ON fa.Code_Examen = a.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[client] c ON c.NumDoss = a.NumDoss;
GO

/* Index locaux : accélèrent le filtre intervenant, l'historique et les
   sous-requêtes de détection de chevauchement/doublon. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_extra_declarations_cod_interv' AND object_id = OBJECT_ID('app.extra_declarations'))
    CREATE INDEX IX_extra_declarations_cod_interv ON app.extra_declarations (cod_interv) INCLUDE (statut, num_doss, num_intv);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_extra_declarations_statut' AND object_id = OBJECT_ID('app.extra_declarations'))
    CREATE INDEX IX_extra_declarations_statut ON app.extra_declarations (statut) INCLUDE (num_intv, cod_interv, num_doss);
GO

/*
   À VÉRIFIER DE VOTRE CÔTÉ (je ne peux pas le déterminer sans accès à la
   base) : le plan d'exécution signale un avertissement
   "PlanAffectingConvert" sur Interv.Nature — nos vues comparent
   "Nature = 2" / "Nature = 1" en littéral entier, ce qui force SQL Server
   à faire une conversion implicite si la colonne réelle n'est pas de type
   int (ex: char/varchar). Merci d'exécuter la requête suivante et de me
   transmettre le résultat pour que j'ajuste le type exact dans la vue :

   SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
   FROM [ERP_LINK].[gclinique_maroc].INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_NAME = 'Interv' AND COLUMN_NAME = 'Nature';
*/
