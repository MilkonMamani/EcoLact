<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Iniciar sesión · Ecolácteos Huata</title>

    @fonts
    @vite('resources/js/pages/login.js')
</head>

<body class="login-page">

    <a href="#login-username" class="ecolact-skip-link">
        Ir al formulario de acceso
    </a>

    <main class="login-shell">

        {{-- =========================================================
             PANEL IZQUIERDO — FORMULARIO
        ========================================================== --}}
        <section class="login-form-panel" aria-labelledby="login-title">

            <div class="login-form-container">

                <div class="login-mini-brand">
                    <img
                        src="{{ asset('images/vaquita.png') }}"
                        alt="Ecolácteos Huata"
                        width="110"
                        height="110"
                    >
                </div>

                <div class="login-card-heading">
                    <h1 id="login-title">Iniciar sesión</h1>

                    <p>
                        Ingresa tus credenciales para acceder al sistema.
                    </p>
                </div>

                {{-- MENSAJE DE ÉXITO --}}
                @if (session('success'))
                    <div class="login-notice" role="status">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- ERRORES --}}
                @if ($errors->any())
                    <div
                        id="login-errors"
                        class="login-errors"
                        role="alert"
                        tabindex="-1"
                        data-login-errors
                    >
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- FORMULARIO --}}
                <form
                    method="POST"
                    action="{{ route('login.store') }}"
                    class="login-form"
                >
                    @csrf

                    {{-- USUARIO --}}
                    <div class="login-field">

                        <label for="login-username">
                            Usuario
                        </label>

                        <div class="login-input-wrap">

                            <svg
                                class="login-field-icon"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="7" r="3.5"/>
                                <path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/>
                            </svg>

                            <input
                                id="login-username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                placeholder="Ingresa tu usuario"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                maxlength="60"
                                required

                                @if ($errors->has('username'))
                                    aria-invalid="true"
                                    aria-describedby="login-errors"
                                @endif
                            >

                        </div>
                    </div>

                    {{-- CONTRASEÑA --}}
                    <div class="login-field">

                        <label for="login-password">
                            Contraseña
                        </label>

                        <div class="login-input-wrap">

                            <svg
                                class="login-field-icon"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="11"
                                    rx="2"
                                />

                                <path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2"/>
                            </svg>

                            <input
                                id="login-password"
                                name="password"
                                type="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                required

                                @if ($errors->has('password'))
                                    aria-invalid="true"
                                    aria-describedby="login-errors"
                                @endif
                            >

                            <button
                                type="button"
                                class="login-password-toggle"
                                data-password-toggle
                                aria-label="Mostrar contraseña"
                                aria-controls="login-password"
                                aria-pressed="false"
                                hidden
                            >

                                <svg
                                    data-password-visible-icon
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>

                                <svg
                                    data-password-hidden-icon
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    hidden
                                >
                                    <path d="m3 3 18 18M10 5.2a10 10 0 0 1 2-.2c6.5 0 10 7 10 7a20 20 0 0 1-3 4M6 6a22 22 0 0 0-4 6s3.5 7 10 7c1.6 0 3-.4 4.3-1M10 10a3 3 0 0 0 4 4"/>
                                </svg>

                            </button>

                        </div>
                    </div>

                    {{-- OPCIONES --}}
                    <div class="login-options">

                        <label
                            class="login-remember"
                            for="login-remember"
                        >
                            <input
                                id="login-remember"
                                name="remember"
                                type="checkbox"
                                value="1"
                                @checked(old('remember', false))
                            >

                            <span>Recordarme</span>
                        </label>

                    </div>

                    {{-- BOTÓN --}}
                    <button
                        type="submit"
                        class="login-submit"
                    >
                        <span>Iniciar sesión</span>

                    </button>

                </form>

                {{-- SEGURIDAD --}}
                <div class="login-secure">

                    <span aria-hidden="true"></span>

                    <svg
                        viewBox="0 0 20 20"
                        aria-hidden="true"
                    >
                        <rect
                            x="5"
                            y="8"
                            width="10"
                            height="9"
                            rx="2"
                        />

                        <path d="M7 8V5a3 3 0 0 1 6 0v3m-3 4v2"/>
                    </svg>

                    <p>Acceso seguro y protegido</p>

                    <span aria-hidden="true"></span>

                </div>

            </div>

        </section>


        {{-- =========================================================
             PANEL DERECHO — ECOLÁCTEOS HUATA
        ========================================================== --}}
        <section class="login-brand-panel" aria-label="Ecolácteos Huata">
            <div class="login-brand-dots" aria-hidden="true"></div>

            <img
                src="{{ asset('images/login.png') }}"
                alt="Ecolácteos Huata: Gobierno Local Innovador, Productivo y Sostenible"
                class="login-brand-image"
                width="2172"
                height="724"
                fetchpriority="high"
            >

            <p class="login-brand-description">
                Sistema integral para la gestión de acopio,
                calidad, producción y administración.
            </p>

            <ul class="login-brand-features" aria-label="Funciones del sistema">
                <li>
                    <span class="login-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 16V5h11v11M14 9h4l3 4v5h-2M7 18h8M3 16v2h1M14 13h7"/><circle cx="5.5" cy="18" r="2.5"/><circle cx="17.5" cy="18" r="2.5"/></svg>
                    </span>
                    <span>Registro y seguimiento del acopio</span>
                </li>
                <li>
                    <span class="login-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 13 3 3 5-6"/></svg>
                    </span>
                    <span>Control de calidad de la leche</span>
                </li>
                <li>
                    <span class="login-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21v-7h3v7M11 21V9h3v12M17 21V3h3v18"/></svg>
                    </span>
                    <span>Gestión de producción e inventario</span>
                </li>
                <li>
                    <span class="login-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
                    </span>
                    <span>Administración y pagos</span>
                </li>
            </ul>
        </section>

    </main>

</body>
</html>
