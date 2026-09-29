{{--
    Página 403 "Acceso no permitido": cuenta desactivada por el administrador (EnsureUserIsActive,
    que ya cerró la sesión) o intento de entrar a una sección de otro rol. Autónoma (estilos en
    línea, sin Vite) para que se muestre aunque falle cualquier otra cosa.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso no permitido · EcoLact</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; background: #f7f9f8; color: #1d2939; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        .caja { box-sizing: border-box; width: 100%; max-width: 26rem; padding: 2rem 1.75rem; border: 1px solid #e7ebee; border-radius: .9rem; background: #fff; text-align: center; }
        .codigo { color: #98a2b3; font-size: .8rem; font-weight: 600; letter-spacing: .12em; }
        h1 { margin: .5rem 0 0; font-size: 1.4rem; }
        p { margin: .75rem 0 0; color: #667085; font-size: .925rem; line-height: 1.5; }
        a { display: inline-block; margin-top: 1.5rem; padding: .6rem 1.1rem; border-radius: .5rem; background: #006146; color: #fff; font-size: .875rem; font-weight: 600; text-decoration: none; }
        a:hover { background: #00ab62; }
    </style>
</head>
<body>
    <main class="caja" role="alert">
        <span class="codigo">ERROR 403</span>
        <h1>Acceso no permitido</h1>
        <p>{{ $exception->getMessage() ?: 'No tienes permiso para entrar a esta sección.' }}</p>
        <a href="{{ auth()->check() ? url('/') : route('login') }}">{{ auth()->check() ? 'Volver al inicio' : 'Ir al inicio de sesión' }}</a>
    </main>
</body>
</html>
