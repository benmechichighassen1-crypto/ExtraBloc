/*
    À exécuter après les scripts 01 à 05.
    Objet : répond aux 3 remarques de suivi Etras Bloc.

    1) Interface intervenant : le champ Chirurgien existe déjà dans
       app.vw_erp_actes_bloc_direction, aucune modification SQL requise
       (voir mise à jour Blade côté technician/index.blade.php).

    2) Nom / prénom patient : jointure sur dbo.client (clé NumDoss),
       exposés dans app.vw_erp_actes_bloc_direction pour l'intervenant
       ET la direction.

    3) Heure d'emploi (planning théorique) + heure de pointage réelle,
       pour le jour de l'acte, reliées via
       intervenants.UserName -> Access Control.UserName -> Access Control.Matricule
       (chaîne déjà posée par 05_link_intervenant_access_control.sql).
*/
USE extra_bloc;
GO

/* ------------------------------------------------------------------ */
/* 2) Patient : Nom / Prénom depuis dbo.client, par NumDoss             */
/* ------------------------------------------------------------------ */
CREATE OR ALTER VIEW app.vw_erp_actes_bloc_direction
AS
SELECT a.*, sb.Designation AS DesignationSalle,
       mc.NomMed AS Chirurgien, mr.NomMed AS Reanimateur,
       fa.Debut_Anesthesie, fa.Fin_Anesthesie,
       c.NomCli AS NomPatient, c.Prenom AS PrenomPatient
FROM app.vw_erp_actes_bloc a
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[SALLE_BLOC] sb ON sb.Code = a.Salle AND sb.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mc ON mc.CodMed = a.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mr ON mr.CodMed = a.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie] fa ON fa.Code_Examen = a.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[client] c ON c.NumDoss = a.NumDoss;
GO

/* ------------------------------------------------------------------ */
/* 3) Heure d'emploi (planning) + heure de pointage réelle du jour     */
/* ------------------------------------------------------------------ */

/* Premier / dernier pointage réel du matricule, par jour. */
CREATE OR ALTER VIEW app.vw_erp_pointages_jour
AS
SELECT
    Matricule,
    CAST(PointeLe AS date) AS DateJour,
    MIN(PointeLe) AS HeurePointageEntree,
    MAX(PointeLe) AS HeurePointageSortie
FROM app.vw_erp_pointages
GROUP BY Matricule, CAST(PointeLe AS date);
GO

/* Planning théorique + pointage réel, combinés par matricule et jour. */
CREATE OR ALTER VIEW app.vw_erp_planning_pointage_jour
AS
SELECT
    p.Matricule,
    p.DatePlanning AS DateJour,
    p.He1 AS HeureEmploiDebut1, p.Hs1 AS HeureEmploiFin1,
    p.He2 AS HeureEmploiDebut2, p.Hs2 AS HeureEmploiFin2,
    pt.HeurePointageEntree,
    pt.HeurePointageSortie
FROM app.vw_erp_plannings AS p
LEFT JOIN app.vw_erp_pointages_jour AS pt
    ON pt.Matricule = p.Matricule AND pt.DateJour = p.DatePlanning;
GO

/*
   Intervenants de l'acte enrichis de leur heure d'emploi et de leur
   heure de pointage réelle, pour le jour de l'acte (a.DatOpe).
   Rappel de la chaîne de rapprochement matricule :
   intervenants.UserName -> [Access Control].UserName -> [Access Control].Matricule
*/
CREATE OR ALTER VIEW app.vw_erp_acte_intervenants
AS
SELECT a.NumIntv, a.NumDoss, a.TypeActe, a.CodeActe,
       roles.RoleIntervenant, roles.CodInterv, e.DesInterv, e.TypInterv,
       ti.DesTyp AS DesTypInterv,
       e.UserName AS LoginErp,
       ac.Matricule AS MatriculePointeuse,
       pp.HeureEmploiDebut1, pp.HeureEmploiFin1,
       pp.HeureEmploiDebut2, pp.HeureEmploiFin2,
       pp.HeurePointageEntree, pp.HeurePointageSortie
FROM app.vw_erp_actes_bloc AS a
CROSS APPLY (VALUES
 (CAST(N'TECHNICIEN' AS nvarchar(30)), TRY_CONVERT(int, a.NomTech)),
 (CAST(N'PENSEUSE' AS nvarchar(30)), TRY_CONVERT(int, a.Penseus)),
 (CAST(N'AIDE_2' AS nvarchar(30)), TRY_CONVERT(int, a.Aide2)),
 (CAST(N'AIDE_3' AS nvarchar(30)), TRY_CONVERT(int, a.Aide3))
) roles(RoleIntervenant, CodInterv)
INNER JOIN [ERP_LINK].[gclinique_maroc].[dbo].[intervenants] e ON e.CodInterv = roles.CodInterv
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[TypInterv] ti ON ti.CodTyp = e.TypInterv AND ti.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Access Control] ac
  ON LTRIM(RTRIM(e.UserName)) COLLATE French_CI_AS = LTRIM(RTRIM(ac.UserName)) COLLATE French_CI_AS
LEFT JOIN app.vw_erp_planning_pointage_jour pp
  ON pp.Matricule = ac.Matricule AND pp.DateJour = CAST(a.DatOpe AS date)
WHERE roles.CodInterv IS NOT NULL;
GO

/* Requête de contrôle : intervenants sans heure de pointage rapprochée pour le jour de l'acte. */
SELECT NumIntv, NumDoss, DesInterv, MatriculePointeuse, HeureEmploiDebut1, HeurePointageEntree
FROM app.vw_erp_acte_intervenants
WHERE MatriculePointeuse IS NOT NULL AND HeurePointageEntree IS NULL;
