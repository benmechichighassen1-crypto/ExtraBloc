# Extra Bloc — architecture retenue

L'application est indépendante de l'ERP : elle écrit seulement dans `extra_bloc`.
Les données ERP et de pointage restent leurs sources de vérité et sont consultées en lecture seule par le linked server `ERP_LINK`.

```text
Laravel ── SQL login limité ──> extra_bloc (saisies, décisions, audit)
                                      │
                                      └── ERP_LINK en lecture seule
                                           ├── gclinique_maroc (actes, intervenants, comptes)
                                           └── GpointeuseN (pointages)
```

## Parcours fonctionnel

1. L'intervenant se connecte avec son compte ERP.
2. Il recherche un numéro de dossier.
3. L'application affiche tous les actes bloc du dossier et les intervenants ERP de chaque acte.
4. Le technicien ou surveillant sélectionne les membres de l'équipe à déclarer. Son `UserName` ERP est conservé comme auteur de la saisie. Une même personne ne peut être déclarée deux fois pour le même `NumIntv`.
5. La déclaration est prévalidée automatiquement lorsque les règles de présence sont satisfaites.
6. La direction valide ou rejette, avec motif et journal d'audit immuable.

L'accès Direction est attribué localement dans `app.direction_users`, sans changer l'ERP. L'observation facultative saisie avec une déclaration est conservée et consultable par la Direction. Le rapprochement pointeuse est natif : `intervenants.UserName` → `Access Control.UserName` → `Access Control.Matricule`.

## Règles de sécurité

- Ne jamais lire ni recopier `PassWord` ou `token`. Le fournisseur de connexion lit uniquement le hash PBKDF2, le salt et les itérations, uniquement le temps de vérifier un mot de passe ; ces valeurs ne sont ni journalisées, ni affichées, ni persistées localement.
- Le compte du linked server ERP doit avoir exclusivement le droit `SELECT`.
- La base locale doit être sauvegardée ; c'est elle qui porte les déclarations et décisions.
- Les vues ERP n'exposent volontairement pas de données cliniques inutiles.

## Prévalidation proposée

Pour chaque intervenant et chaque acte, l'application charge le planning de la date (`app.vw_erp_plannings`) et les pointages réels (`app.vw_erp_pointages`).
L'acte est prévalidé lorsque l'intervalle `HDAnest → HFAnest` est situé, entièrement ou partiellement, en dehors des plages normales `He1 → Hs1` et `He2 → Hs2`, et qu'un pointage cohérent encadre l'acte. Les gardes, urgences, repos et absences sont ensuite traités comme des règles métier explicites, à confirmer avec la direction.

## Points à confirmer avant l'écran de connexion

1. Quel est l'algorithme exact de `hasched_password` (ou existe-t-il un service d'authentification ERP) ? Les colonnes seules ne permettent pas d'implémenter une vérification sûre.
2. Quelles sont les heures normales par intervenant/service ? Elles sont nécessaires pour transformer la prévalidation en règle métier exacte.
3. Confirmer que `GpointeuseN` est bien accessible via `ERP_LINK` et que `HeurePointage` est de type `time`/`datetime`.
