/**
 * Pagos: interacción de los modales (sin librerías). Configuración de pagos
 * tiene su propio módulo (admin-configuracion-pagos.js).
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
iniciarRegistroPago();
