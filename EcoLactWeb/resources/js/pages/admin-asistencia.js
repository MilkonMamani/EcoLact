import { getCsrfToken } from '../maps.js';

/**
 * Pantalla /admin/asistencia: lista del día + escaneo de QR (cámara nativa,
 * BarcodeDetector, mismo patrón que acopiador-recorrido.js) + corrección
 * administrativa de una jornada. El backend es la única fuente de verdad:
 * este archivo nunca calcula sueldos ni decide asistencia por su cuenta.
 */
const pagina = document.querySelector('[data-asistencia-page]');

if (pagina) {
    iniciarEscaneoQr(pagina);
    iniciarCorreccion(pagina);
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

    pagina.addEventListener('click', (event) => {
        const fila = event.target.closest('[data-fila-asistencia]');

        if (!fila || fila.dataset.corregible !== 'true') {
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
        modal.querySelector('[data-corregir-estado-actual]').textContent = estadoActual === 'FALTA' ? 'Falta' : estadoActual === 'PRESENTE' ? 'Asistió' : 'Sin registrar';

        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
