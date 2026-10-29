import { getCsrfToken } from '../maps.js';
import { iniciarListaEscalonada } from '../lista-escalonada.js';

/**
 * Pantalla /admin/asistencia: lista del día + escaneo de QR (cámara nativa,
 * BarcodeDetector, mismo patrón que acopiador-recorrido.js) + corrección
 * administrativa de una jornada. El backend es la única fuente de verdad:
 * este archivo nunca calcula sueldos ni decide asistencia por su cuenta.
 */
const pagina = document.querySelector('[data-asistencia-page]');

if (pagina) {
    iniciarListado(pagina);
    iniciarEscaneoQr(pagina);
    iniciarCorreccion(pagina);
}

// --------------------------------------------------------------- listado

/** Skeleton + entrada escalonada (igual que Acopio). Cambiar rol o fecha filtra al instante. */
function iniciarListado(pagina) {
    const filtros = pagina.querySelector('[data-asistencia-filtros]');
    const { mostrarSkeleton } = iniciarListaEscalonada(pagina.querySelector('[data-asistencia-tabla]'), {
        fila: '[data-fila-asistencia]',
        variable: '--asistencia-delay',
        filtros,
        enlaces: [...pagina.querySelectorAll('[data-asistencia-volver-hoy], .ecolact-pager a')],
    });

    filtros?.querySelectorAll('select[name="rol"], input[name="fecha"]').forEach((campo) => {
        campo.addEventListener('change', () => {
            mostrarSkeleton();
            filtros.requestSubmit();
        });
    });
}

// --------------------------------------------------------------- escaneo QR

function iniciarEscaneoQr(pagina) {
    const modal = document.getElementById('modal-escanear-qr');

    if (!modal) {
        return;
    }

    const video = modal.querySelector('[data-escaner-video]');
    const estadoTexto = modal.querySelector('[data-escaner-estado]');
    const aviso = modal.querySelector('[data-escaner-aviso]');
    const resultado = modal.querySelector('[data-escaner-resultado]');
    const resultadoTitulo = modal.querySelector('[data-escaner-resultado-titulo]');
    const resultadoDetalle = modal.querySelector('[data-escaner-resultado-detalle]');
    const requiereCorreccion = modal.querySelector('[data-escaner-requiere-correccion]');
    const correccionMensaje = modal.querySelector('[data-escaner-correccion-mensaje]');
    const botonAbrirCorreccion = modal.querySelector('[data-escaner-abrir-correccion]');
    const botonReiniciar = modal.querySelector('[data-escaner-reiniciar]');

    let stream = null;
    let activo = false;

    const detener = () => {
        activo = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.hidden = true;
    };

    const reiniciar = () => {
        detener();
        aviso.hidden = true;
        resultado.hidden = true;
        requiereCorreccion.hidden = true;
        estadoTexto.hidden = false;
        estadoTexto.textContent = 'Esperando código…';
        iniciarCamara();
    };

    const mostrarError = (mensaje) => {
        detener();
        estadoTexto.hidden = true;
        aviso.hidden = false;
        aviso.textContent = mensaje;
    };

    async function iniciarCamara() {
        if (!('BarcodeDetector' in window)) {
            mostrarError('Tu navegador no admite escaneo de código QR con cámara.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        } catch {
            mostrarError('No se pudo acceder a la cámara: revisa los permisos del navegador.');
            return;
        }

        video.hidden = false;
        estadoTexto.hidden = true;
        video.srcObject = stream;
        await video.play();

        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        activo = true;

        const loop = async () => {
            if (!activo) {
                return;
            }

            try {
                const codigos = await detector.detect(video);

                if (codigos.length > 0) {
                    detener();
                    await registrarAsistencia(codigos[0].rawValue);
                    return;
                }
            } catch {
                // Un fallo puntual de detección no es un error fatal: reintenta en el próximo frame.
            }

            requestAnimationFrame(loop);
        };

        requestAnimationFrame(loop);
    }

    async function registrarAsistencia(qrToken) {
        estadoTexto.hidden = false;
        estadoTexto.textContent = 'Registrando asistencia…';

        try {
            const respuesta = await fetch(pagina.dataset.urlQr, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ qr_token: qrToken }),
            });

            const cuerpo = await respuesta.json().catch(() => ({}));

            if (respuesta.status === 409) {
                estadoTexto.hidden = true;
                requiereCorreccion.hidden = false;
                correccionMensaje.textContent = cuerpo.message || 'Este trabajador ya tiene una falta registrada hoy. Búscalo en la lista para corregirla.';
                return;
            }

            if (!respuesta.ok) {
                mostrarError(cuerpo.message || 'No se pudo registrar la asistencia.');
                return;
            }

            estadoTexto.hidden = true;
            resultado.hidden = false;
            resultadoTitulo.textContent = cuerpo.duplicado ? 'Asistencia ya registrada para hoy.' : '✓ Asistencia registrada';
            resultadoDetalle.textContent = `Hora: ${cuerpo.jornada?.hora_marcacion ?? '—'}`;

            if (typeof pagina.dataset.recargarAlRegistrar === 'undefined') {
                window.setTimeout(() => window.location.reload(), 1200);
            }
        } catch {
            mostrarError('No se pudo registrar la asistencia. Inténtalo de nuevo.');
        }
    }

    botonReiniciar?.addEventListener('click', reiniciar);

    // El backend no revela la identidad del trabajador en el 409 (evita filtrar datos por
    // un QR ajeno): el administrador cierra este modal y corrige desde la fila de la tabla.
    botonAbrirCorreccion?.addEventListener('click', () => {
        modal.querySelector('[data-modal-close]')?.click();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-open="modal-escanear-qr"]')) {
            reiniciar();
        }

        if (event.target.closest('#modal-escanear-qr [data-modal-close]') || (event.target === modal)) {
            detener();
        }
    });
}

// ------------------------------------------------------------- corrección

function iniciarCorreccion(pagina) {
    const modal = document.getElementById('modal-corregir-asistencia');
    const form = modal?.querySelector('[data-form-corregir]');

    if (!modal || !form) {
        return;
    }

    window.abrirModalCorreccionAsistencia = abrirModalCorreccion;

    // Menú de acciones (⋮) de cada fila. Antes de las 23:59: "Editar asistencia" (con jornada) o
    // "Registrar asistencia" (sin jornada), acciones reales del backend. Desde el cierre: solo
    // "Ver detalle" (el backend también rechaza cualquier registro o corrección).
    pagina.addEventListener('click', (event) => {
        const accion = event.target.closest('[data-asistencia-corregir], [data-asistencia-registrar], [data-asistencia-ver]');
        const fila = accion?.closest('[data-fila-asistencia]');

        if (!fila) {
            return;
        }

        accion.closest('details')?.removeAttribute('open');

        if (accion.matches('[data-asistencia-ver]')) {
            abrirModalDetalle(fila);

            return;
        }

        if (accion.matches('[data-asistencia-registrar]')) {
            abrirModalRegistro(fila);

            return;
        }

        abrirModalCorreccion({
            trabajadorId: fila.dataset.trabajadorId,
            userId: fila.dataset.userId,
            nombre: fila.dataset.nombre,
            fecha: fila.dataset.fecha,
            estadoActual: fila.dataset.estado || null,
        });
    });

    // Solo un menú de acciones abierto a la vez; se cierra al hacer clic fuera.
    document.addEventListener('click', (event) => {
        pagina.querySelectorAll('.asistencia-acciones[open]').forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    function abrirModalDetalle(fila) {
        const modalDetalle = document.getElementById('modal-detalle-asistencia');

        if (!modalDetalle) {
            return;
        }

        modalDetalle.querySelector('[data-detalle-nombre]').textContent = fila.dataset.nombre;
        modalDetalle.querySelector('[data-detalle-fecha]').textContent = fila.dataset.fechaEtiqueta || fila.dataset.fecha;
        modalDetalle.querySelector('[data-detalle-estado]').textContent = fila.dataset.estadoEtiqueta || '—';
        modalDetalle.querySelector('[data-detalle-hora]').textContent = fila.dataset.hora || '—';
        modalDetalle.querySelector('[data-detalle-origen]').textContent = fila.dataset.origenEtiqueta || '—';

        modalDetalle.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }

    function abrirModalRegistro(fila) {
        const modalRegistro = document.getElementById('modal-registrar-asistencia');
        const formRegistro = modalRegistro?.querySelector('form');

        if (!formRegistro) {
            return;
        }

        formRegistro.action = `${pagina.dataset.urlCorregirBase}/${fila.dataset.userId}/jornadas`;
        formRegistro.querySelector('[name="fecha"]').value = fila.dataset.fecha;
        modalRegistro.querySelector('[data-registrar-nombre]').textContent = fila.dataset.nombre;
        modalRegistro.querySelector('[data-registrar-fecha]').textContent = fila.dataset.fechaEtiqueta || fila.dataset.fecha;

        modalRegistro.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const boton = form.querySelector('[data-corregir-submit]');
        const error = form.querySelector('[data-corregir-error]');
        const textoOriginal = boton.textContent;
        boton.disabled = true;
        boton.textContent = 'Guardando corrección…';
        error.hidden = true;

        try {
            const datos = new FormData(form);
            const respuesta = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: datos,
            });

            if (!respuesta.ok) {
                const cuerpo = await respuesta.json().catch(() => ({}));
                const mensaje = cuerpo.errors?.jornada?.[0] || cuerpo.message || 'No se pudo guardar la corrección.';
                throw new Error(mensaje);
            }

            window.location.reload();
        } catch (err) {
            error.textContent = err.message || 'No se pudo guardar la corrección.';
            error.hidden = false;
            boton.disabled = false;
            boton.textContent = textoOriginal;
        }
    });

    function abrirModalCorreccion({ userId, nombre, fecha, estadoActual }) {
        if (!userId) {
            return;
        }

        form.action = `${pagina.dataset.urlCorregirBase}/${userId}/jornadas/${fecha}/corregir`;
        modal.querySelector('[data-corregir-nombre]').textContent = nombre;
        modal.querySelector('[data-corregir-fecha]').textContent = fecha;
        modal.querySelector('[data-corregir-estado-actual]').textContent = estadoActual === 'FALTA' ? 'Falta' : estadoActual === 'PRESENTE' ? 'Presente' : 'Pendiente';

        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
