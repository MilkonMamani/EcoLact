/**
 * Registrar análisis → "Ticket del analizador".
 *
 * Toma/selecciona la foto del ticket, la envía al OCR local (Tesseract) y
 * rellena los parámetros del formulario con los valores PROPUESTOS. Nunca
 * guarda: el analista revisa, corrige y pulsa "Guardar análisis". Un valor
 * que el OCR no leyó queda vacío con la marca "No detectado" (jamás 0).
 * Si el OCR falla, la foto igual queda asociada y se ingresa a mano.
 */
const MENSAJE_ERROR_RED = 'No se pudo leer el ticket automáticamente.';

document.querySelectorAll('[data-ticket-analizador]').forEach((seccion) => {
    const formulario = seccion.closest('form');
    const inputCamara = seccion.querySelector('[data-ticket-input-camara]');
    const inputArchivo = seccion.querySelector('[data-ticket-input-archivo]');
    const ticketPath = seccion.querySelector('[data-ticket-path]');
    const resultado = seccion.querySelector('[data-ticket-resultado]');
    const preview = seccion.querySelector('[data-ticket-preview]');
    const nombreArchivo = seccion.querySelector('[data-ticket-archivo-nombre]');
    const mensaje = seccion.querySelector('[data-ticket-mensaje]');
    const noConfigurados = seccion.querySelector('[data-ticket-no-configurados]');
    const botones = seccion.querySelector('[data-ticket-botones]');
    const botonManual = seccion.querySelector('[data-ticket-manual]');
    const botonGuardar = seccion.querySelector('[data-ticket-guardar]');
    const textoContenedor = seccion.querySelector('[data-ticket-texto-contenedor]');
    const texto = seccion.querySelector('[data-ticket-texto]');
    const botonesOrigen = seccion.querySelectorAll('[data-ticket-camara], [data-ticket-archivo]');
    let urlPreview = null;

    const campos = () => formulario.querySelectorAll('[data-parametro-codigo]');
    const origen = (codigo) => formulario.querySelector(`[data-parametro-origen="${codigo}"]`);
    const estado = (codigo) => formulario.querySelector(`[data-parametro-estado="${codigo}"]`);

    function mostrarEstado(codigo, textoEstado, clase) {
        const nodo = estado(codigo);

        if (!nodo) {
            return;
        }

        nodo.textContent = textoEstado;
        nodo.className = `mt-1 text-xs ${clase}`;
        nodo.hidden = textoEstado === '';
    }

    function mostrarMensaje(textoMensaje, tipo) {
        const clases = { cargando: 'text-text-muted', exito: 'text-success', aviso: 'text-warning', error: 'text-danger' };
        mensaje.className = `flex items-center gap-2 text-sm ${clases[tipo] ?? 'text-text'}`;
        mensaje.replaceChildren();

        if (tipo === 'cargando') {
            const spinner = document.createElement('span');
            spinner.className = 'inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent';
            spinner.setAttribute('aria-hidden', 'true');
            mensaje.append(spinner);
        }

        mensaje.append(textoMensaje);
    }

    function mostrarBotones({ manual, guardar }) {
        botones.hidden = false;
        botonManual.hidden = !manual;
        botonGuardar.hidden = !guardar;
    }

    function bloquear(bloqueado) {
        botonesOrigen.forEach((boton) => {
            boton.disabled = bloqueado;
        });
    }

    function autocompletar(respuesta) {
        const leidos = new Map(respuesta.valores.map((valor) => [valor.codigo, valor.valor]));

        campos().forEach((campo) => {
            const codigo = campo.dataset.parametroCodigo;

            if (leidos.has(codigo)) {
                campo.value = leidos.get(codigo);
                origen(codigo).value = 'OCR';
                mostrarEstado(codigo, 'Leído del ticket · revisa', 'text-text-muted');
            } else {
                campo.value = '';
                origen(codigo).value = 'MANUAL';
                mostrarEstado(codigo, 'No detectado', 'text-warning');
            }
        });

        const extras = respuesta.detectados_no_configurados ?? [];
        noConfigurados.hidden = extras.length === 0;
        noConfigurados.textContent = extras.length
            ? `El ticket también trae ${extras.map((extra) => extra.codigo).join(', ')}, que no están configurados como parámetros de calidad.`
            : '';
    }

    async function escanear(archivo) {
        if (!archivo) {
            return;
        }

        if (urlPreview) {
            URL.revokeObjectURL(urlPreview);
        }

        urlPreview = URL.createObjectURL(archivo);
        preview.src = urlPreview;
        nombreArchivo.textContent = archivo.name;
        resultado.hidden = false;
        botones.hidden = true;
        textoContenedor.hidden = true;
        noConfigurados.hidden = true;
        mostrarMensaje('Leyendo ticket...', 'cargando');
        bloquear(true);

        const datos = new FormData();
        datos.append('entrega_id', formulario.querySelector('[name="entrega_id"]').value);
        datos.append('imagen', archivo);

        try {
            const respuesta = await fetch(seccion.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': formulario.querySelector('[name="_token"]').value },
                body: datos,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            if (cuerpo.ticket_path) {
                ticketPath.value = cuerpo.ticket_path;
            }

            if (cuerpo.texto_ocr) {
                texto.textContent = cuerpo.texto_ocr;
                textoContenedor.hidden = false;
            }

            if (!respuesta.ok || !cuerpo.success) {
                const errorValidacion = cuerpo.errors ? Object.values(cuerpo.errors)[0]?.[0] : null;
                mostrarMensaje(errorValidacion || cuerpo.mensaje || MENSAJE_ERROR_RED, 'error');
                mostrarBotones({ manual: true, guardar: false });

                return;
            }

            autocompletar(cuerpo);

            if (cuerpo.suficientes) {
                mostrarMensaje(cuerpo.mensaje, 'exito');
                mostrarBotones({ manual: false, guardar: true });
            } else {
                mostrarMensaje(cuerpo.mensaje, 'aviso');
                mostrarBotones({ manual: true, guardar: false });
            }
        } catch {
            mostrarMensaje(MENSAJE_ERROR_RED, 'error');
            mostrarBotones({ manual: true, guardar: false });
        } finally {
            bloquear(false);
        }
    }

    seccion.querySelector('[data-ticket-camara]').addEventListener('click', () => inputCamara.click());
    seccion.querySelector('[data-ticket-archivo]').addEventListener('click', () => inputArchivo.click());
    seccion.querySelector('[data-ticket-reescanear]').addEventListener('click', () => inputCamara.click());

    [inputCamara, inputArchivo].forEach((input) => {
        input.addEventListener('change', () => {
            escanear(input.files[0]);
            // Permite volver a elegir la misma foto.
            input.value = '';
        });
    });

    botonManual.addEventListener('click', () => {
        const primerVacio = [...campos()].find((campo) => campo.value === '') ?? campos()[0];
        primerVacio?.focus();
    });

    // Lo que el analista escribe o corrige deja de ser una lectura del OCR.
    campos().forEach((campo) => {
        campo.addEventListener('input', () => {
            const codigo = campo.dataset.parametroCodigo;
            origen(codigo).value = 'MANUAL';
            mostrarEstado(codigo, '', '');
        });
    });
});
