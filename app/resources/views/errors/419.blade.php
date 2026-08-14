<x-layouts.app>
    <section class="card" style="max-width:460px;margin:14vh auto;text-align:center">
        <h1 style="margin-bottom:8px">Session expirée</h1>
        <p class="muted">Votre session a expiré (inactivité prolongée ou page restée ouverte trop longtemps). Vos éventuelles saisies non enregistrées n'ont pas été perdues côté serveur si elles n'avaient pas été soumises.</p>
        <a href="{{ route('login') }}" style="display:inline-block;margin-top:18px;background:#1779ba;color:#fff;text-decoration:none;border-radius:7px;padding:12px 22px;font-weight:600">Retour à la connexion</a>
    </section>
</x-layouts.app>
