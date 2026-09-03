<?php

namespace App\Providers;

use App\Auth\ErpUserProvider;
use App\Repositories\DeclarationQuery;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repository de lecture optimisé (cache ERP + tables locales).
        // Utilisé par DirectionController, PrevalidationController et Registre Bloc.
        // Ne touche PAS à la saisie des déclarations (qui reste en temps réel via le serveur lié).
        $this->app->singleton(DeclarationQuery::class, fn () => new DeclarationQuery());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('erp', fn () => new ErpUserProvider());

        // Vue de pagination en français (numéros de page), utilisée par tous les ->links().
        Paginator::defaultView('pagination.custom');
    }
}
