<x-layouts.app>
    <section class="card" style="text-align:center;padding:50px 30px">
        <h1 style="margin-bottom:6px">Bienvenue</h1>
        <p class="muted">Choisissez une application.</p>
        <div class="row" style="justify-content:center;gap:30px;margin-top:30px;flex-wrap:wrap">
            <a href="{{ route('registry.index') }}" style="text-decoration:none;display:block;width:280px;background:#fff;border:1px solid #e6edf2;border-radius:14px;padding:36px 24px;box-shadow:0 3px 14px #18355016;color:inherit">
                <div style="font-size:34px;margin-bottom:10px">🗂️</div>
                <h2 style="margin:0 0 6px">Registre Bloc</h2>
                <p class="muted" style="margin:0">Registre médico-légal : comptes rendus, radiologie, anapath.</p>
            </a>
            <a href="{{ route('technician.index') }}" style="text-decoration:none;display:block;width:280px;background:#fff;border:1px solid #e6edf2;border-radius:14px;padding:36px 24px;box-shadow:0 3px 14px #18355016;color:inherit">
                <div style="font-size:34px;margin-bottom:10px">📋</div>
                <h2 style="margin:0 0 6px">Extras Bloc</h2>
                <p class="muted" style="margin:0">Déclaration et validation des actes extra du personnel.</p>
            </a>
        </div>
    </section>
</x-layouts.app>
