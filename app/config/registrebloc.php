<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code de sécurité pour modifier/annuler une demande d'anapath
    |--------------------------------------------------------------------------
    |
    | Les utilisateurs listés dans app.anapath_editeurs peuvent modifier ou
    | annuler une demande sans code. Tout autre utilisateur doit saisir ce
    | code pour confirmer l'action (traçabilité conservée dans les deux cas
    | via app.anapath_audits).
    |
    | Se règle via ANAPATH_CODE_SECURITE dans le fichier .env.
    |
    */

    'code_securite' => env('ANAPATH_CODE_SECURITE'),

];
