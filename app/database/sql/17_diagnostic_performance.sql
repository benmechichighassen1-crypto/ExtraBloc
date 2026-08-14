/*
    DIAGNOSTIC PERFORMANCE — à exécuter sur la base "extra_bloc"
    (PAS sur la base ERP gclinique_maroc) : c'est là que vivent nos vues,
    même si elles lisent des données via le lien ERP_LINK.

    Objectif : mesurer le coût réel des requêtes utilisées par l'écran
    Direction (celle qui combine le plus de jointures + la sous-requête de
    détection de chevauchement).

    IMPORTANT : contrairement à un simple SELECT sans résultat, il faut que
    la requête retourne réellement des lignes pour que les temps
    d'exécution soient significatifs. Adapte les dates ci-dessous à une
    période où il existe des déclarations en base.
*/
USE extra_bloc;
GO

SET STATISTICS IO ON;
SET STATISTICS TIME ON;
GO

-- 1) Coût de la vue de base (jointures ERP_LINK seules, sans nos tables).
SELECT TOP 50 *
FROM app.vw_erp_acte_intervenants
WHERE NumIntv IN (
    SELECT TOP 50 NumIntv FROM app.vw_erp_actes_bloc ORDER BY DatOpe DESC
);
GO

-- 2) Coût de la requête réelle de l'écran Direction (avec les 2 sous-requêtes
--    de détection ajoutées récemment : chevauchement + doublon inter-dossier).
--    Adapter les dates à une période où il existe des données.
SELECT TOP 25 d.*, a.LibelleActe, a.DatOpe, a.DesignationSalle
FROM app.extra_declarations d
LEFT JOIN app.vw_erp_actes_bloc_direction a ON d.num_intv = a.NumIntv
LEFT JOIN app.vw_erp_acte_intervenants i ON d.num_intv = i.NumIntv AND d.cod_interv = i.CodInterv
WHERE a.DatOpe >= '2026-08-01' AND a.DatOpe <= '2026-08-14';
GO

SET STATISTICS IO OFF;
SET STATISTICS TIME OFF;
GO

/*
   Ce qu'il faut me transmettre pour une vraie analyse :
   - La sortie complète de "STATISTICS IO" pour chaque requête (nombre de
     "logical reads" par table/vue — la ligne qui compte vraiment).
   - Le plan d'exécution graphique (clic droit > "Afficher le plan
     d'exécution réel" dans SSMS avant de lancer la requête), ou son export
     XML (clic droit sur le plan > "Enregistrer le plan d'exécution sous").
   - Le nombre de lignes dans app.extra_declarations et dans les tables ERP
     concernées (Interv, intervenants) à titre de volumétrie.
   Sans ces éléments, "0 ms" ne veut rien dire : soit la requête n'a rien
   retourné, soit les deux commandes SET STATISTICS n'ont pas été exécutées
   dans le même lot que la requête mesurée.
*/
