-- ============================================================================
-- 26_resoudre_doublons_declarations.sql
-- Supprime les déclarations en double dans app.extra_declarations.
--
-- Origine du problème : quand un acte est modifié dans l'ERP (numéro
-- d'intervention changé, sous-dossier créé par la facturation, ré-acte…),
-- DEUX déclarations peuvent exister pour le même intervenant + même patient +
-- même acte + même jour, avec des heures de pointage différentes. L'écran
-- Direction affichait donc deux lignes identiques pour un même acte.
--
-- Ce script conserve UNE seule déclaration par (num_doss, cod_interv,
-- acte_code, jour) : celle au statut le plus avancé (Validé > Prévalidé >
-- En attente > Refusé), puis la plus récente. La traçabilité des doublons
-- supprimés est conservée (leurs audits sont rattachés à la ligne gardée).
--
-- ⚠ À exécuter quand l'application est peu utilisée. Laissez-le se terminer.
-- ============================================================================

-- 1) DIAGNOSTIC : liste les doublons détectés (avec leur nombre) AVANT toute suppression.
SELECT num_doss, cod_interv, acte_code, acte_libelle, CONVERT(date, date_acte) AS jour, COUNT(*) AS nb
FROM app.extra_declarations
GROUP BY num_doss, cod_interv, acte_code, acte_libelle, CONVERT(date, date_acte)
HAVING COUNT(*) > 1
ORDER BY num_doss, cod_interv, acte_code, jour;
GO

-- 2) CORRECTION : supprime les doublons (en bloc, dans une transaction).
--    Vérifiez d'abord le résultat du diagnostic ci-dessus.
BEGIN TRANSACTION;

    -- Carte des doublons : id supprimé -> id conservé (la ligne la plus
    -- avancée, puis la plus récente).
    DECLARE @map TABLE (old_id int PRIMARY KEY, new_id int NOT NULL);
    INSERT INTO @map (old_id, new_id)
    SELECT r.id, k.id
    FROM (
        SELECT id, num_doss, cod_interv, acte_code, date_acte,
               ROW_NUMBER() OVER (
                   PARTITION BY num_doss, cod_interv, acte_code, CONVERT(date, date_acte)
                   ORDER BY CASE statut WHEN 'VALIDE' THEN 0 WHEN 'PREVALIDE' THEN 1 WHEN 'SOUMIS' THEN 2 ELSE 3 END,
                            declared_at DESC, id DESC
               ) AS rn
        FROM app.extra_declarations
    ) r
    JOIN (
        SELECT id, num_doss, cod_interv, acte_code, date_acte,
               ROW_NUMBER() OVER (
                   PARTITION BY num_doss, cod_interv, acte_code, CONVERT(date, date_acte)
                   ORDER BY CASE statut WHEN 'VALIDE' THEN 0 WHEN 'PREVALIDE' THEN 1 WHEN 'SOUMIS' THEN 2 ELSE 3 END,
                            declared_at DESC, id DESC
               ) AS rn
        FROM app.extra_declarations
    ) k ON  k.num_doss = r.num_doss AND k.cod_interv = r.cod_interv
       AND k.acte_code = r.acte_code AND CONVERT(date, k.date_acte) = CONVERT(date, r.date_acte)
       AND k.rn = 1
    WHERE r.rn > 1;

    -- Conserver la traçabilité : rattacher les audits des doublons à la ligne conservée.
    UPDATE aud
    SET declaration_id = m.new_id
    FROM app.extra_declaration_audits aud
    JOIN @map m ON m.old_id = aud.declaration_id;

    -- Supprimer les doublons.
    DELETE d
    FROM app.extra_declarations d
    JOIN @map m ON m.old_id = d.id;

    SELECT COUNT(*) AS doublons_supprimes FROM @map;

COMMIT;
GO

-- 3) CONTRÔLE : il ne doit plus rester de doublon.
SELECT num_doss, cod_interv, acte_code, CONVERT(date, date_acte) AS jour, COUNT(*) AS nb
FROM app.extra_declarations
GROUP BY num_doss, cod_interv, acte_code, CONVERT(date, date_acte)
HAVING COUNT(*) > 1;
GO

PRINT '26 : dé-duplication des déclarations terminée.';
GO
