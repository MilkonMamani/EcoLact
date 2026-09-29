{{--
    Historial de pagos del proveedor seleccionado (botón "Ver pagos" de la pestaña Compras).
    Los modales son únicos en la página; admin-proveedor-pagos.js toma las URLs y el título
    del panel activo ([data-proveedor-pagos]) cada vez que se abren.
--}}
@php
    $campoPago = 'flex h-11 items-stretch overflow-hidden rounded-lg border border-text-muted/25 bg-surface shadow-sm focus-within:border-primary focus-within:ring-1 focus-within:ring-primary';
    $iconoPago = 'flex w-11 shrink-0 items-center justify-center border-r border-text-muted/15 bg-background/70 text-primary';
    $selectPago = 'w-full min-w-0 cursor-pointer appearance-none bg-transparent pl-3 pr-8 text-sm text-text focus:outline-none';
@endphp

<x-ui.modal id="modal-pagos-proveedor" :title="$titulo" size="2xl">
    <p class="-mt-3 text-sm text-text-muted">Historial de pagos realizados al proveedor. Consulta los comprobantes por semana.</p>

    <form data-pagos-filtros class="mt-5 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]">
        <div>
            <label for="pagos-filtro-semana" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Semana</label>
            <div class="{{ $campoPago }} relative">
                <span class="{{ $iconoPago }}"><x-ui.icon name="calendar" class="h-[18px] w-[18px]" /></span>
                <select id="pagos-filtro-semana" name="semana" class="{{ $selectPago }}">
                    <option value="todos">Todos</option>
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            </div>
        </div>
        <div>
            <label for="pagos-filtro-estado" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Estado</label>
            <div class="{{ $campoPago }} relative">
                <span class="{{ $iconoPago }}"><x-ui.icon name="clock" class="h-[18px] w-[18px]" /></span>
                <select id="pagos-filtro-estado" name="estado" class="{{ $selectPago }}">
                    <option value="todos">Todos</option>
                    <option value="pendientes">Pendiente</option>
                    <option value="pagados">Pagado</option>
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            </div>
        </div>
        <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-6 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-primary">
            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" /> Filtrar
        </button>
    </form>

    <div data-pagos-resultado class="mt-5 min-h-[10rem]" aria-live="polite"></div>

    <div class="mt-5 flex justify-end border-t border-text-muted/10 pt-4">
        <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
    </div>
</x-ui.modal>

{{-- Segundo modal, encima del historial: al cerrarlo se vuelve al historial tal como estaba. --}}
<x-ui.modal id="modal-pago-detalle" title="Detalle del pago" size="2xl" :nested="true">
    <p data-pago-detalle-sub class="-mt-3 text-sm text-text-muted">Comprobante del pago del proveedor.</p>
    <div data-pago-detalle-cuerpo class="mt-4 min-h-[8rem] rounded-lg bg-background/60 p-3"></div>
    <div class="mt-5 flex justify-end border-t border-text-muted/10 pt-4">
        <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
    </div>
</x-ui.modal>
