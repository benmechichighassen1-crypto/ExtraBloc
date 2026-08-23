<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ request()->routeIs('registry.*') ? 'Registre Bloc' : 'Extra Bloc' }}</title>
    <style>
        :root { --navy:#123454; --blue:#1779ba; --bg:#f3f7fa; --green:#16846a; --red:#b33a3a; }
        * { box-sizing:border-box } body { margin:0; background:var(--bg); color:#1c2d3b; font:15px Arial,sans-serif; }
        header { padding:15px max(5vw,25px); background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:space-between; }
        header a { color:#fff; text-decoration:none; margin-right:18px } main { max-width:1700px; padding:30px 20px; margin:auto; }
        .card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 3px 14px #18355016; margin-bottom:20px; }
        h1,h2 { margin-top:0 } input,select,button,textarea { border:1px solid #ccd8e1; border-radius:7px; padding:10px; font:inherit; }
        button,.button { background:var(--blue); color:#fff; border:0; cursor:pointer; display:inline-block; text-decoration:none; border-radius:7px; padding:10px; } button.danger { background:var(--red) } button.success { background:var(--green) }
        .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap } .notice { padding:12px; border-radius:7px; margin-bottom:18px; background:#e5f6ed; color:#176444 }
        .error { color:#a32424; margin:8px 0 } table { width:100%; border-collapse:collapse; font-size:14px } th,td { text-align:left; padding:11px 8px; border-bottom:1px solid #e6edf2; vertical-align:top } th { color:#567; }
        .badge { border-radius:20px; padding:4px 9px; background:#e3edf4; font-size:12px; white-space:nowrap } .status-soumis { background:#fff0c7; color:#805900 }.status-prevalide { background:#d9efff; color:#075a92 }.status-valide { background:#dff5e7; color:#12643e }.status-rejete { background:#fee1e1; color:#9d2424 } .muted { color:#6a7a86 }
        @media print { header, form, button { display:none!important } main { max-width:none; padding:0 } .card { box-shadow:none; padding:0 } }
        .brand { display:flex; flex-direction:column; line-height:1.2; margin-right:22px }
        .brand .brand-eyebrow { font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#9fc2de }
        .brand .brand-name { font-size:15px; font-weight:700; color:#fff }
        .filter-bar { display:flex; flex-direction:column; gap:18px }
        .filter-grid { display:flex; flex-wrap:wrap; gap:22px; align-items:flex-start }
        .filter-field { display:flex; flex-direction:column; gap:6px }
        .filter-label { font-size:12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#3a4a56 }
        .filter-statuses { display:flex; flex-wrap:wrap; gap:8px 14px; align-items:center }
        .status-toggle { font-weight:400; text-transform:none; letter-spacing:normal; color:#1c2d3b; display:inline-flex; align-items:center; gap:5px; background:#f3f7fa; border:1px solid #e6edf2; border-radius:20px; padding:6px 12px 6px 10px }
        .filter-actions { display:flex; flex-wrap:wrap; gap:10px; padding-top:6px; border-top:1px solid #e6edf2 }
        .clear-field-btn { position:absolute; right:2px; top:50%; transform:translateY(-50%); width:22px; height:22px; padding:0; border:0; background:transparent; color:#8a99a6; font-size:16px; line-height:1; cursor:pointer }
        .pointage-info-btn { cursor:pointer; border:none; background:#dcebf7; color:#175d8e; font-weight:700; border-radius:50%; width:20px; height:20px; line-height:19px; padding:0; font-size:12px; margin-left:6px; display:inline-block; text-align:center; box-shadow:inset 0 0 0 1px #a9cbe4; }
        .modal-overlay { display:none; position:fixed; inset:0; background:#12345480; z-index:50; align-items:center; justify-content:center }
        .btn-outline { background:#fff; color:#1779ba; border:1px solid #ccd8e1 }
        .nav-home-btn { background:#1c4d78; padding:7px 14px; border-radius:7px; margin-right:18px }
        .nav-home-btn:hover { background:#1779ba }
    </style>
</head>
@php
    $loginBackgroundStyle = request()->routeIs('login')
        ? "background-image:url('".asset('images/arrierplan.jpeg')."');background-size:cover;background-position:center;background-attachment:fixed;min-height:100vh"
        : '';
    $isRegistre = request()->routeIs('registry.*');
    $isAccueil = request()->routeIs('home');
@endphp
<body style="{{ $loginBackgroundStyle }}">
<header><strong>{{ $isRegistre ? 'Registre Bloc' : 'Extra Bloc' }}</strong><nav>
    <span class="brand"><span class="brand-name">Clinique Internationale Ibn Sina Tanger</span></span>
@auth
    @if($isAccueil)
        {{-- Page d'accueil : navbar minimale, pas de liens applicatifs. --}}
    @elseif($isRegistre)
        <a href="{{ route('home') }}" class="nav-home-btn">🏠 Accueil</a>
    @else
        <a href="{{ route('home') }}" class="nav-home-btn">🏠 Accueil</a>
        <a href="{{ route('technician.index') }}">Saisie intervenant</a>
        <a href="{{ route('technician.history') }}">Historique</a>
        @if(\App\Support\AccessControl::hasMajorAccess(auth()->user()->getAuthIdentifier()))
            <a href="{{ route('major.index') }}">Pré-validation</a>
        @endif
        @if(\App\Support\AccessControl::hasDirectionAccess(auth()->user()->getAuthIdentifier()) || \App\Support\AccessControl::hasRhAccess(auth()->user()->getAuthIdentifier()))
            <a href="{{ route('direction.index') }}">Direction</a>
        @endif
    @endif
    <span class="muted" style="color:#cfe0ee;margin-right:14px">{{ auth()->user()->getAuthIdentifier() }}</span>
    <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf <button>Déconnexion</button></form>
@endauth
</nav></header>
<main>
    @if(session('success')) <div class="notice">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="notice" style="background:#fff0c7;color:#805900">{{ session('warning') }}</div> @endif
    {{ $slot }}
</main>
</body>
</html>
