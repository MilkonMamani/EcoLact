<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · EcoLact</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/pages/login.js'])
</head>
<body class="login-page">
    <a href="#login-username" class="ecolact-skip-link">Ir al formulario de acceso</a>
    <main class="login-shell">
        <section class="login-brand-panel" aria-label="EcoLact, gestión de acopio de leche">
            <svg class="login-corner" viewBox="0 0 400 180" fill="none" aria-hidden="true"><path d="M0 0h310C195 58 110 40 0 178Z" fill="#0F7C86"/><path d="M100 0h290C259 15 247 60 152 73Z" fill="#143B39"/></svg>
            <div class="login-brand-content">
                <div class="login-brand">
                    <svg class="login-brand-mark" viewBox="0 0 104 132" aria-hidden="true">
                        <defs><clipPath id="ecolact-drop"><path d="M52 3C38 27 5 63 5 84a47 47 0 0 0 94 0C99 63 66 27 52 3Z"/></clipPath></defs>
                        <g clip-path="url(#ecolact-drop)">
                            <path fill="#0F7C86" d="M0 0h104v132H0z"/>
                            <path d="m0 67 29-23 12 9L62 26l42 42v17H0Z" fill="#f7f4ee"/>
                            <path d="m29 44 5 19-10-8-8 8Zm33-18-7 26 9-9 14 15-9-20Z" fill="#187782"/>
                            <path d="M0 92q24-23 51 0t53-1v41H0Z" fill="#0B5961"/>
                            <path d="M25 80q5-8 17-7l28 2 11 12-5 11-8-1-2 21h-7l-1-22H38l-3 22h-7l1-26-7-7Z" fill="#f7f4ee"/>
                            <path d="m42 73 14 1 1 14-8 7-13-9Zm27 4 10 8-4 11-8-1-6-10Z" fill="#241b12"/>
                            <path d="m24 80-5 4-1 20m60-18 8-3m-7 1 2-8" stroke="#f7f4ee" stroke-width="3" fill="none" stroke-linecap="round"/>
                        </g>
                    </svg>
                    <div>
                        <p class="login-wordmark">Eco<span>Lact</span></p>
                        <p class="login-brand-description">Gestión inteligente para el acopio<br>y calidad de leche</p>
                    </div>
                </div>

                <div class="login-brand-divider" aria-hidden="true"></div>
                <ul class="login-pillars" aria-label="Áreas de gestión">
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M11 4h10v4H11zM9 12l3-4h8l3 4v15H9zM6 14v7m20-7v7M12 15h8"/></svg>
                        <span>Acopio</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="m16 3 11 4v9c0 6-5 11-11 14C10 27 5 22 5 16V7zM11 16l4 4 7-8"/></svg>
                        <span>Calidad</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="m5 17 14-7 8 7v11H5zM5 17h22M17 9l1-5h6l1 7M9 22h1m6 2h1m5-3h1"/></svg>
                        <span>Producción</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M4 28h25M7 28V18h5v10m4 0V12h5v16m4 0V7h4v21M6 10l7-6 6 3 8-5"/></svg>
                        <span>Gestión</span>
                    </li>
                </ul>
                <p class="login-brand-motto">Impulsando el campo,<br>construyendo un mejor mañana.</p>
            </div>
            <img class="login-landscape" src="{{ asset('images/ecolact-rural.svg') }}" alt="" width="720" height="590">
            <span class="login-brand-footnote">Del campo a tu mesa</span>
        </section>

        <section class="login-form-panel" aria-labelledby="login-title">
            <svg class="login-leaves" viewBox="0 0 210 290" fill="none" aria-hidden="true"><path d="M204 287C170 184 152 90 77 11" stroke="currentColor" stroke-width="2"/><path d="M125 82C78 87 39 48 30 8c47-3 86 29 95 74ZM169 160C111 165 53 132 36 90c72-12 123 23 133 70ZM192 229C142 231 99 209 91 169c55 0 89 18 101 60ZM153 122C141 80 159 50 188 30c11 46-3 77-35 92Z" fill="currentColor"/></svg>
            <div class="login-card">
                <div class="login-card-heading">
                    <span class="login-heading-rule" aria-hidden="true"></span>
                    <h1 id="login-title">¡Bienvenido a <span>EcoLact!</span></h1>
                    <p>Ingresa tus credenciales para acceder al sistema.</p>
                </div>

                @if (session('success'))
                    <div class="login-notice" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div id="login-errors" class="login-errors" role="alert" tabindex="-1" data-login-errors>
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <div class="login-field">
                        <label for="login-username">Usuario</label>
                        <div class="login-input-wrap">
                            <svg class="login-field-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="3.5"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/></svg>
                            <input id="login-username" name="username" type="text" value="{{ old('username') }}"
                                   placeholder="Ingresa tu usuario" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="60" required
                                   @if ($errors->has('username')) aria-invalid="true" aria-describedby="login-errors" @endif>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="login-password">Contraseña</label>
                        <div class="login-input-wrap">
                            <svg class="login-field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2"/></svg>
                            <input id="login-password" name="password" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required
                                   @if ($errors->has('password')) aria-invalid="true" aria-describedby="login-errors" @endif>
                            <button type="button" class="login-password-toggle" data-password-toggle aria-label="Mostrar contraseña" aria-controls="login-password" aria-pressed="false" hidden>
                                <svg data-password-visible-icon viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg data-password-hidden-icon viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10 5.2a10 10 0 0 1 2-.2c6.5 0 10 7 10 7a20 20 0 0 1-3 4M6 6a22 22 0 0 0-4 6s3.5 7 10 7c1.6 0 3-.4 4.3-1M10 10a3 3 0 0 0 4 4"/></svg>
                            </button>
                        </div>
                    </div>
                    <label class="login-remember" for="login-remember">
                        <input id="login-remember" name="remember" type="checkbox" value="1" @checked(old('remember', false))>
                        <span>Recordarme</span>
                    </label>
                    <button type="submit" class="login-submit">
                        <span>Iniciar sesión</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>
                    </button>
                </form>
                <div class="login-secure">
                    <span aria-hidden="true"></span>
                    <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="5" y="8" width="10" height="9" rx="2"/><path d="M7 8V5a3 3 0 0 1 6 0v3m-3 4v2"/></svg>
                    <p>Acceso seguro y protegido</p>
                    <span aria-hidden="true"></span>
                </div>
            </div>
            <p class="login-mobile-footer">EcoLact · Del campo a tu mesa</p>
        </section>
    </main>
</body>
</html>
