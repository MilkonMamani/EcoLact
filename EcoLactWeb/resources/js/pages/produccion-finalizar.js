/*
 * "Finalizar producción": mientras la persona escribe lo obtenido, muestra la merma y cómo
 * quedará el stock del producto (stock actual + obtenido). Es SOLO una vista previa con los
 * números que entregó el servidor: el stock real cambia únicamente al confirmar, y el servidor
 * vuelve a validar todo (incluido que el lote siga EN_PROCESO).
 */
const formulario = document.querySelector('[data-produccion-finalizar]');

if (formulario) {
    const bloques = [...formulario.querySelectorAll('[data-producto-final]')];
    const resumen = formulario.querySelector('[data-resumen-final]');
    const boton = formulario.querySelector('[data-finalizar]');

    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 2 });
    const conUnidad = (valor, unidad) => {
        if (unidad === 'unidad') {
            return `${numero(valor)} ${Math.abs(Number(valor) - 1) < 0.00001 ? 'unidad' : 'unidades'}`;
        }

        return `${numero(valor)} ${unidad}`;
    };
    const redondear = (valor) => Math.round(valor * 100) / 100;

    function actualizar() {
        const agregados = [];

        bloques.forEach((bloque) => {
            const entrada = bloque.querySelector('[data-obtenido]');
            const planificado = Number(bloque.dataset.planificado);
            const stock = Number(bloque.dataset.stock);
            const unidad = bloque.dataset.unidad;
            const texto = entrada.value.trim();
            const obtenido = Number(texto);
            const valido = texto !== '' && Number.isFinite(obtenido) && obtenido >= 0;

            const etiquetaDiferencia = bloque.querySelector('[data-diferencia-etiqueta]');
            const diferencia = bloque.querySelector('[data-diferencia]');
            const incremento = bloque.querySelector('[data-incremento]');
            const resultante = bloque.querySelector('[data-resultante]');

            if (!valido) {
                etiquetaDiferencia.textContent = 'Merma';
                diferencia.textContent = '—';
                diferencia.classList.remove('text-danger', 'text-text');
                diferencia.classList.add('text-text-muted');
                incremento.textContent = '—';
                resultante.textContent = '—';
                return;
            }

            const excedente = obtenido > planificado;
            etiquetaDiferencia.textContent = excedente ? 'Excedente' : 'Merma';
            const valorDiferencia = redondear(Math.abs(planificado - obtenido));
            diferencia.textContent = conUnidad(valorDiferencia, unidad);
            diferencia.classList.remove('text-text-muted', 'text-danger', 'text-text');
            diferencia.classList.add(!excedente && valorDiferencia > 0 ? 'text-danger' : 'text-text');
            incremento.textContent = `+${conUnidad(obtenido, unidad)}`;
            resultante.textContent = conUnidad(redondear(stock + obtenido), unidad);

            if (obtenido > 0) {
                agregados.push(`${conUnidad(obtenido, unidad)} de ${bloque.dataset.etiqueta}`);
            }
        });

        const completos = bloques.every((bloque) => bloque.querySelector('[data-obtenido]').value.trim() !== '');

        if (!completos) {
            resumen.textContent = 'Escribe la cantidad obtenida para ver cuánto se agregará al stock de Productos.';
        } else if (agregados.length === 0) {
            resumen.textContent = 'Al finalizar no se agregará stock: no se obtuvo producto.';
        } else {
            resumen.textContent = `Al finalizar se agregarán ${agregados.join(' y ')} al stock de Productos.`;
        }
    }

    formulario.addEventListener('input', actualizar);

    formulario.addEventListener('submit', (evento) => {
        // El diálogo de confirmación global corre después de este listener: solo se bloquea
        // el botón si el envío realmente siguió adelante (evita dobles clics).
        setTimeout(() => {
            if (!evento.defaultPrevented) {
                boton.disabled = true;
                boton.textContent = 'Finalizando...';
            }
        }, 0);
    });

    actualizar();
}
