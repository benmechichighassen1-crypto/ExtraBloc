<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rafraîchit le cache local des données ERP (cache.erp_actes /
// cache.erp_acte_intervenants) utilisé par l'écran Direction.
// Voir app/Console/Commands/ErpCacheSync.php et database/sql/30_cache_erp.sql.
Schedule::command('erp:cache-sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
