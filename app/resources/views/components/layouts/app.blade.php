<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extra Bloc</title>
    <style>
        :root { --navy:#123454; --blue:#1779ba; --bg:#f3f7fa; --green:#16846a; --red:#b33a3a; }
        * { box-sizing:border-box } body { margin:0; background:var(--bg); color:#1c2d3b; font:15px Arial,sans-serif; }
        header { padding:15px max(5vw,25px); background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:space-between; }
        header a { color:#fff; text-decoration:none; margin-right:18px } main { max-width:1250px; padding:30px 20px; margin:auto; }
        .card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 3px 14px #18355016; margin-bottom:20px; }
        h1,h2 { margin-top:0 } input,select,button,textarea { border:1px solid #ccd8e1; border-radius:7px; padding:10px; font:inherit; }
        button { background:var(--blue); color:#fff; border:0; cursor:pointer } button.danger { background:var(--red) } button.success { background:var(--green) }
        .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap } .notice { padding:12px; border-radius:7px; margin-bottom:18px; background:#e5f6ed; color:#176444 }
        .error { color:#a32424; margin:8px 0 } table { width:100%; border-collapse:collapse; font-size:14px } th,td { text-align:left; padding:11px 8px; border-bottom:1px solid #e6edf2; vertical-align:top } th { color:#567; }
        .badge { border-radius:20px; padding:4px 9px; background:#e3edf4; font-size:12px; white-space:nowrap } .muted { color:#6a7a86 }
    </style>
</head>
<body>
@auth
<header><strong>Extra Bloc</strong><nav>
    <a href="{{ route('technician.index') }}">Saisie intervenant</a>
    <a href="{{ route('direction.index') }}">Direction</a>
    <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf <button>Déconnexion</button></form>
</nav></header>
@endauth
<main>
    @if(session('success')) <div class="notice">{{ session('success') }}</div> @endif
    {{ $slot }}
</main>
</body>
</html>
