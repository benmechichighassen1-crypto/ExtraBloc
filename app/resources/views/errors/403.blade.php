<x-layouts.app>
    <section class="card" style="max-width:460px;margin:14vh auto;text-align:center">
        <h1 style="margin-bottom:8px">Accès non autorisé</h1>
        <p class="muted">{{ $exception->getMessage() ?: "Vous n'avez pas les droits nécessaires pour accéder à cette page." }}</p>
        <a href="{{ route('technician.index') }}" style="display:inline-block;margin-top:18px;background:#1779ba;color:#fff;text-decoration:none;border-radius:7px;padding:12px 22px;font-weight:600">Retour à l'accueil</a>
    </section>
</x-layouts.app>
