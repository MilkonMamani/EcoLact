/**
 * Configuración de pagos y Pagos: interacción de los modales (sin librerías).
 * El abrir/cerrar modales lo resuelve app.js; aquí solo se cargan los datos y
 * se validan los campos dinámicos (los mismos se validan de nuevo en el backend).
 */

const TAMANO_MAXIMO_COMPROBANTE = 5 * 1024 * 1024;
const EXTENSIONES_COMPROBANTE = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

function abrirModalesDelServidor() {
    if (document.querySelector('[data-modal].is-open')) {
        document.body.classList.add('overflow-hidden');
    }
}

function iniciarFormularioConfiguracion(form) {
    const radios = form.querySelectorAll('[data-config-tipo]');
    const secciones = form.querySelectorAll('[data-config-seccion]');
    const modal = form.closest('[data-modal]');
    const subtitulo = modal?.querySelector('[data-config-subtitulo]');
    const subtitulos = subtitulo ? JSON.parse(subtitulo.dataset.subtitulos || '{}') : {};

    const sincronizar = () => {
        const tipo = form.querySelector('[data-config-tipo]:checked')?.value;

        secciones.forEach((seccion) => {
            const activa = seccion.dataset.configSeccion === tipo;

            seccion.hidden = !activa;
            seccion.querySelectorAll('input, select, textarea').forEach((campo) => {
                campo.disabled = !activa;
            });
        });

        radios.forEach((radio) => {
            const punto = radio.closest('label')?.querySelector('[data-config-punto]');

            punto?.classList.toggle('opacity-100', radio.checked);
            punto?.classList.toggle('opacity-0', !radio.checked);
        });

        if (subtitulo && subtitulos[tipo]) {
            subtitulo.textContent = subtitulos[tipo];
        }

        form.querySelectorAll('[data-toggle-calidad]').forEach((interruptor) => {
            const raiz = interruptor.closest('[data-config-seccion]') ?? form;

            if (raiz.hidden) {
                return;
            }

            const bloque = raiz.querySelector('[data-ajustes-bloque]');
            const activo = interruptor.checked;

            if (bloque) {
                bloque.hidden = !activo;
                bloque.querySelectorAll('input, select').forEach((campo) => {
                    campo.disabled = !activo;
                });
            }

            const texto = raiz.querySelector('[data-toggle-texto]');

            if (texto) {
                texto.textContent = activo ? 'Sí' : 'No';
            }
        });
    };

    const calcularPreciosFinales = () => {
        const precio = parseFloat(form.querySelector('input[name="precio_base_litro"]:not(:disabled)')?.value ?? '');

        form.querySelectorAll('[data-ajuste-fila]').forEach((fila) => {
            const tipo = fila.querySelector('select')?.value;
            const valor = parseFloat(fila.querySelector('input[type="number"]')?.value ?? '');
            const salida = fila.querySelector('[data-precio-final]');

            if (!salida) {
                return;
            }

            if (Number.isNaN(precio) || Number.isNaN(valor)) {
                salida.textContent = '—';

                return;
            }

            const final = Math.max(0, tipo === 'PORCENTAJE' ? precio * (1 + valor / 100) : precio + valor);

            salida.textContent = `S/ ${final.toFixed(2)}`;
        });
    };

    form.addEventListener('input', calcularPreciosFinales);
    form.addEventListener('change', calcularPreciosFinales);
    calcularPreciosFinales();

    radios.forEach((radio) => radio.addEventListener('change', sincronizar));
    form.querySelectorAll('[data-toggle-calidad]').forEach((interruptor) => interruptor.addEventListener('change', sincronizar));
    sincronizar();

    return sincronizar;
}

function iniciarConfiguracion() {
    const formularios = [...document.querySelectorAll('[data-config-form]')].map((form) => [form, iniciarFormularioConfiguracion(form)]);
    const nueva = formularios.find(([form]) => form.querySelector('[data-config-tipo]'));

    if (!nueva) {
        return;
    }

    const [form, sincronizar] = nueva;

    document.querySelectorAll('[data-config-nueva][data-tipo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const radio = form.querySelector(`[data-config-tipo][value="${boton.dataset.tipo}"]`);

            if (radio) {
                radio.checked = true;
                sincronizar();
            }

            if (boton.dataset.rol) {
                const rol = form.querySelector('select[name="rol_id"]');

                if (rol) {
                    rol.value = boton.dataset.rol;
                }
            }
        });
    });
}

function iniciarRegistroPago() {
    const modal = document.getElementById('modal-registrar-pago');
    const form = modal?.querySelector('[data-pago-form]');

    if (!modal || !form) {
        return;
    }

    const metodosElectronicos = JSON.parse(form.dataset.metodosElectronicos || '[]');
    const metodo = form.querySelector('[data-pago-metodo]');
    const operacion = form.querySelector('[data-pago-operacion]');
    const marcaObligatorio = form.querySelector('[data-pago-operacion-req]');
    const ayudaOperacion = form.querySelector('[data-pago-operacion-ayuda]');
    const comprobante = form.querySelector('[data-pago-comprobante]');
    const errorComprobante = form.querySelector('[data-pago-comprobante-error]');
    const fecha = form.querySelector('[data-pago-fecha]');
    const confirmar = form.querySelector('[data-pago-confirmar]');
    const cajaDetalle = form.querySelector('[data-pago-detalle-caja]');
    const listaDetalle = form.querySelector('[data-pago-detalle]');

    const formatearSoles = (valor) => `S/ ${Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const sincronizarOperacion = () => {
        const obligatorio = metodosElectronicos.includes(metodo.value);

        operacion.required = obligatorio;
        marcaObligatorio.hidden = !obligatorio;
        ayudaOperacion.textContent = obligatorio
            ? 'Obligatorio para este método de pago.'
            : 'Opcional para efectivo; obligatorio para métodos electrónicos.';
    };

    const mostrarErrorComprobante = (mensaje) => {
        errorComprobante.textContent = mensaje;
        errorComprobante.classList.toggle('hidden', mensaje === '');
    };

    const validarComprobante = () => {
        const archivo = comprobante.files[0];

        if (!archivo) {
            mostrarErrorComprobante('');

            return true;
        }

        const extension = archivo.name.split('.').pop().toLowerCase();

        if (!EXTENSIONES_COMPROBANTE.includes(extension)) {
            mostrarErrorComprobante('El comprobante debe ser un PDF o una imagen (JPG, PNG o WEBP).');

            return false;
        }

        if (archivo.size > TAMANO_MAXIMO_COMPROBANTE) {
            mostrarErrorComprobante('El comprobante no puede pesar más de 5 MB.');

            return false;
        }

        mostrarErrorComprobante('');

        return true;
    };

    const cargarDatos = (boton) => {
        const datos = boton.dataset;
        const campo = (nombre) => form.querySelector(`[data-pago-campo="${nombre}"]`);

        form.action = datos.action;
        form.querySelector('[data-pago-obligacion]').value = datos.id;
        modal.querySelector('[data-pago-titulo]').textContent = datos.beneficiario;
        modal.querySelector('[data-pago-subtitulo]').textContent = `${datos.tipo} · ${datos.periodo}`;

        campo('beneficiario').value = datos.beneficiario;
        campo('tipo').value = datos.tipo;
        campo('regla').value = datos.regla;
        campo('periodo').value = `${datos.periodo} (${datos.rango})`;
        campo('montoTexto').value = formatearSoles(datos.monto);
        campo('monto').value = datos.monto;

        if (datos.periodoInicio) {
            fecha.min = datos.periodoInicio;
        }

        listaDetalle.replaceChildren();

        Object.entries(JSON.parse(datos.detalle || '{}')).forEach(([etiqueta, valor]) => {
            const fila = document.createElement('div');
            const dt = Object.assign(document.createElement('dt'), { className: 'text-text-muted', textContent: etiqueta });
            const dd = Object.assign(document.createElement('dd'), { className: 'text-right text-text', textContent: valor });

            fila.className = 'flex justify-between gap-4';
            fila.append(dt, dd);
            listaDetalle.append(fila);
        });

        cajaDetalle.classList.toggle('hidden', listaDetalle.children.length === 0);
    };

    document.querySelectorAll('[data-registrar-pago]').forEach((boton) => {
        boton.addEventListener('click', () => {
            form.reset();
            mostrarErrorComprobante('');
            confirmar.disabled = false;
            cargarDatos(boton);
            sincronizarOperacion();
        });
    });

    metodo.addEventListener('change', sincronizarOperacion);
    comprobante.addEventListener('change', validarComprobante);

    form.addEventListener('submit', (evento) => {
        if (!validarComprobante() || !form.reportValidity()) {
            evento.preventDefault();

            return;
        }

        confirmar.disabled = true;
    });

    sincronizarOperacion();

    if (modal.classList.contains('is-open')) {
        const id = form.querySelector('[data-pago-obligacion]').value;
        const boton = document.querySelector(`[data-registrar-pago][data-id="${id}"]`);

        if (boton) {
            cargarDatos(boton);
        }
    }
}

abrirModalesDelServidor();
iniciarConfiguracion();
iniciarRegistroPago();
