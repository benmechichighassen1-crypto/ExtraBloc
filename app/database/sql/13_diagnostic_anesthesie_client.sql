/*
    Script de DIAGNOSTIC — 100% lecture seule, aucune modification de schéma
    ni de données. À exécuter et à me transmettre le résultat (copier/coller
    ou capture d'écran) pour que je puisse corriger précisément :
      - la jointure vers dbo.client (nom/prénom patient),
      - la jointure vers Feuille_Anesthesie (heure début/fin anesthésie).
*/
USE extra_bloc;
GO

/* 1) dbo.client.NumDoss est-elle unique ? Si NbLignes > 1 pour un NumDoss,
      la jointure fait un "fan-out" (plusieurs lignes pour un même acte). */
PRINT '--- 1) Doublons éventuels dans dbo.client par NumDoss (top 20) ---';
SELECT TOP 20 NumDoss, COUNT(*) AS NbLignes
FROM [ERP_LINK].[gclinique_maroc].[dbo].[client]
GROUP BY NumDoss
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC;
GO

/* 2) Existe-t-il vraiment une table/vue Feuille_Anesthesie, et quelles
      colonnes contient-elle réellement ? */
PRINT '--- 2) Colonnes réelles de Feuille_Anesthesie (si elle existe) ---';
SELECT c.name AS NomColonne, t.name AS TypeColonne, c.max_length
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID(N'[ERP_LINK].[gclinique_maroc].[dbo].[Feuille_Anesthesie]')
ORDER BY c.column_id;
GO

/* 3) Sur un dossier connu où l'ERP affiche des heures d'anesthésie,
      remplace NUMDOSS_A_TESTER par un vrai numéro de dossier, puis compare
      le Code_Examen côté Interv et côté Feuille_Anesthesie. */
PRINT '--- 3) Comparaison Code_Examen (remplacer NUMDOSS_A_TESTER) ---';
SELECT NumIntv, NumDoss, Code_Examen, DatOpe
FROM [ERP_LINK].[gclinique_maroc].[dbo].[Interv]
WHERE NumDoss = N'NUMDOSS_A_TESTER';
GO
