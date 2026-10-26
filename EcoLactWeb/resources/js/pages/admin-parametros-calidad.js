/**
 * Parámetros de calidad. La tabla + panel lateral los maneja admin-fichas.js; aquí solo:
 *
 * - Código automático en "Nuevo parámetro": "Grasa láctea" → GRASA_LACTEA. Se sigue
 *   generando mientras el administrador no lo edite a mano; si lo edita, se normaliza al
 *   formato permitido (A-Z, 0-9, _). En "Editar" el código es de solo lectura.
 */
const LARGO_CODIGO = 40;

export function generarCodigo(texto) {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, LARGO_CODIGO)
        .replace(/_+$/, '');
}

function normalizarCodigo(texto) {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toUpperCase()
        .replace(/[\s-]+/g, '_')
        .replace(/[^A-Z0-9_]/g, '')
        .slice(0, LARGO_CODIGO);
}

document.querySelectorAll('[data-parametro-form="nuevo"]').forEach((formulario) => {
    const nombre = formulario.querySelector('[data-codigo-origen]');
    const codigo = formulario.querySelector('[data-codigo-destino]');

    if (!nombre || !codigo) {
        return;
    }

    // Si vuelve con errores y el código no coincide con el generado, lo escribió el administrador.
    let editadoAMano = codigo.value !== '' && codigo.value !== generarCodigo(nombre.value);

    nombre.addEventListener('input', () => {
        if (!editadoAMano) {
            codigo.value = generarCodigo(nombre.value);
        }
    });

    codigo.addEventListener('input', () => {
        const posicion = codigo.selectionStart;
        const normalizado = normalizarCodigo(codigo.value);

        if (normalizado !== codigo.value) {
            codigo.value = normalizado;
            codigo.setSelectionRange(posicion, posicion);
        }

        editadoAMano = codigo.value !== '';
    });
});

