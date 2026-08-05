<x-layouts.app>
    <section class="card" style="max-width:430px;margin:12vh auto">
        <h1>Extra Bloc</h1>
        <p class="muted">Connectez-vous avec votre compte ERP clinique.</p>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <p><label>Identifiant<br><input name="username" value="{{ old('username') }}" required autofocus style="width:100%"></label></p>
            <p><label>Mot de passe<br><input type="password" name="password" required style="width:100%"></label></p>
            @error('username') <p class="error">{{ $message }}</p> @enderror
            <button style="width:100%">Se connecter</button>
        </form>
    </section>
</x-layouts.app>
