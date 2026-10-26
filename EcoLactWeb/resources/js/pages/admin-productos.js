/*
 * INVENTARIO -> Productos: un clic en cualquier parte de la fila la despliega
 * (el botón chevron ya lo hace con [data-toggle-row], de app.js), y dentro del
 * panel se alterna entre ver los precios y editarlos, sin salir de la pantalla.
 */
const pagina = document.querySelector('[data-productos-page]');

if (pagina) {
    pagina.addEventListener('click', (event) => {
        const fila = event.target.closest('[data-producto-fila]');

        if (fila && !event.target.closest('button, a, input, select, form')) {
            fila.querySelector('[data-toggle-row]')?.click();

            return;
        }

        const panel = event.target.closest('[data-precios]');

        if (!panel) {
            return;
        }

        if (event.target.closest('[data-precios-editar]')) {
            panel.dataset.modo = 'editar';
            panel.querySelector('.productos-modo-editar input[type="number"]')?.focus();
        } else if (event.target.closest('[data-precios-cancelar]')) {
            panel.querySelector('.productos-modo-editar').reset();
            panel.dataset.modo = 'ver';
        }
    });

    // Al volver de guardar/eliminar, la fila abierta queda a la vista.
    pagina.querySelector('.productos-fila.is-expanded')?.scrollIntoView({ block: 'center' });
}
