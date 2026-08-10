<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pré-validation obligatoire
    |--------------------------------------------------------------------------
    |
    | Si true : la Direction ne peut VALIDER (validation finale) qu'une
    | déclaration déjà passée par l'étape de pré-validation du major du bloc
    | (statut PREVALIDE). Le rejet (REJETE) reste toujours possible à tout
    | moment, quel que soit ce paramètre.
    |
    | Si false (par défaut) : la Direction peut valider directement une
    | déclaration "En attente" (SOUMIS), sans passer par la pré-validation
    | — l'étape major reste alors optionnelle.
    |
    | Se règle via la variable d'environnement PREVALIDATION_OBLIGATOIRE
    | dans le fichier .env (true/false), sans toucher au code.
    |
    */

    'prevalidation_obligatoire' => env('PREVALIDATION_OBLIGATOIRE', false),

];
