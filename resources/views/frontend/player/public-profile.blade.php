<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $displayName }} · Cape Tennis</title>
  <style>
    :root { color-scheme: light; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color:#172f35; background:#f2f6f4; }
    * { box-sizing:border-box; }
    body { min-height:100vh; margin:0; display:grid; place-items:center; padding:24px; }
    main { width:min(100%, 560px); padding:32px; border:1px solid #cfe0d8; border-radius:18px; background:#fff; box-shadow:0 18px 50px rgba(22,66,54,.09); }
    .eyebrow { margin:0 0 10px; color:#18705a; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    h1 { margin:0; color:#111; font-size:clamp(28px, 6vw, 42px); line-height:1.1; }
    .notice { margin:22px 0 0; padding:14px 16px; border-left:4px solid #2b78b8; border-radius:8px; background:#edf6ff; color:#365565; line-height:1.55; }
    a { display:inline-flex; margin-top:24px; color:#176448; font-weight:750; text-decoration:none; }
    a:hover { text-decoration:underline; }
    a:focus-visible { outline:3px solid #d69d31; outline-offset:3px; }
  </style>
</head>
<body>
  <main>
    <p class="eyebrow">Cape Tennis player</p>
    <h1>{{ $displayName }}</h1>
    <p class="notice">This public profile currently shows the player’s name only. Personal contact, birth, account and registration information stays private.</p>
    <a href="{{ url('/') }}">&larr; Back to Cape Tennis</a>
  </main>
</body>
</html>
