/*
    À exécuter après le script 14.
    Objet : gérer le cas où le service facturation transfère un acte vers
    un "sous-dossier" (nouveau NumDoss) après la saisie initiale par
    l'intervenant. Exemple réel rencontré : un patient fait une coronarographie
    puis une dilatation dans le même passage ; le technicien saisit les 2
    actes normalement ; la facturation transfère ensuite le 2ème acte vers un
    sous-dossier (ex: 26T23009). Résultat : le même intervenant / le même
    acte se retrouve sur DEUX NumDoss différents pour le MÊME patient.

    dbo.client possède un champ "Identifiant", stable pour un même patient
    même quand son NumDoss change (contrairement à NumDoss, propre à
    chaque épisode/dossier). On l'expose ici pour permettre un contrôle
    inter-dossiers (voir script 17).
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
    COALESCE(sb.Designation, a.Salle) AS DesignationSalle,
    mc.NomMed AS Chirurgien,
    mr.NomMed AS Reanimateur,
    fa.Debut_Anesthesie,
    fa.Fin_Anesthesie,
    c.NomCli AS NomPatient,
    c.Prenom AS PrenomPatient,
    c.Identifiant AS IdentifiantPatient
FROM app.vw_erp_actes_bloc a
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[SALLE_BLOC] sb ON sb.Code = a.Salle AND sb.Active = 1
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mc ON mc.CodMed = a.CodMed
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[medecin] mr ON mr.CodMed = a.MedRea
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie] fa ON fa.Code_Examen = a.Code_Examen
LEFT JOIN [ERP_LINK].[gclinique_maroc].[dbo].[client] c ON c.NumDoss = a.NumDoss;
GO
