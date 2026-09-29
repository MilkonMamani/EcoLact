{{--
    Modal "Vista previa de impresión". Se llena por JS con las hojas de los proveedores
    seleccionados. Abrirlo NO marca nada como pagado: el pago solo se registra cuando el
    administrador confirma, tras imprimir, en la franja inferior.
--}}
<div id="modal-vista-previa" data-modal data-vista-previa class="fixed inset-0 z-50 items-center justify-center bg-text/50 p-2 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="vista-previa-titulo">
    <div class="flex h-full max-h-[94vh] w-full max-w-[1240px] flex-col overflow-hidden rounded-lg bg-surface">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-text-muted/10 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <div>
                    <h2 id="vista-previa-titulo" class="text-xl font-bold leading-tight text-text">Vista previa de impresión</h2>
                    <p class="text-sm text-text-muted">Se generará 1 hoja por cada proveedor seleccionado.</p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button type="button" data-modal-close class="inline-flex h-11 items-center rounded-lg border border-text-muted/25 bg-surface px-5 text-sm font-medium text-text transition hover:bg-text-muted/10">Cancelar</button>
                <button type="button" data-imprimir disabled class="inline-flex h-11 items-center gap-2.5 rounded-lg bg-primary px-6 text-sm font-medium text-white  transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50">
                    <x-ui.icon name="printer" class="h-[18px] w-[18px]" /> Imprimir
                </button>
                <button type="button" data-modal-close aria-label="Cerrar" class="ml-1 flex h-9 w-9 items-center justify-center rounded-lg text-text-muted transition hover:bg-text-muted/10 hover:text-text">
                    <x-ui.icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
        </header>

        <div class="grid min-h-0 flex-1 md:grid-cols-[17.5rem_minmax(0,1fr)]">
            {{-- Páginas --}}
            <nav class="hidden min-h-0 flex-col border-r border-text-muted/10 bg-background/70 md:flex" aria-label="Páginas a imprimir">
                <p class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wide text-text-muted">Páginas · <span data-total-paginas>0</span></p>
                <ul data-lista-paginas class="min-h-0 flex-1 space-y-1.5 overflow-y-auto px-3 pb-4"></ul>
            </nav>

            {{-- Vista previa --}}
            <div class="flex min-h-0 min-w-0 flex-col">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-text-muted/10 px-5 py-2.5">
                    <p class="text-sm font-semibold text-text"><span data-nombre-pagina>—</span></p>
                    <div class="flex items-center gap-1.5 text-sm text-text">
                        <button type="button" data-pagina-anterior aria-label="Página anterior" class="flex h-9 w-9 items-center justify-center rounded-lg border border-text-muted/25 transition hover:bg-text-muted/10 disabled:opacity-40"><x-ui.icon name="chevron-left" class="h-4 w-4" /></button>
                        <span class="min-w-[3.5rem] text-center font-medium" data-contador>0 / 0</span>
                        <button type="button" data-pagina-siguiente aria-label="Página siguiente" class="flex h-9 w-9 items-center justify-center rounded-lg border border-text-muted/25 transition hover:bg-text-muted/10 disabled:opacity-40"><x-ui.icon name="chevron-right" class="h-4 w-4" /></button>
                        <span class="mx-2 h-6 w-px bg-text-muted/20"></span>
                        <button type="button" data-zoom-menos aria-label="Alejar" class="flex h-9 w-9 items-center justify-center rounded-lg border border-text-muted/25 transition hover:bg-text-muted/10"><x-ui.icon name="zoom-out" class="h-4 w-4" /></button>
                        <span class="min-w-[3rem] text-center text-xs text-text-muted" data-zoom-valor>100%</span>
                        <button type="button" data-zoom-mas aria-label="Acercar" class="flex h-9 w-9 items-center justify-center rounded-lg border border-text-muted/25 transition hover:bg-text-muted/10"><x-ui.icon name="zoom-in" class="h-4 w-4" /></button>
                    </div>
                </div>

                <div data-area-hoja class="min-h-0 flex-1 overflow-auto bg-neutral-100 p-4 sm:p-6">
                    <p data-cargando class="py-20 text-center text-sm text-text-muted">Preparando comprobantes…</p>
                    <div data-hoja-grande hidden class="mx-auto border border-neutral-300"></div>
                </div>
            </div>
        </div>

        {{-- Confirmación final: aparece tras imprimir --}}
        <form method="POST" action="{{ route('admin.pagos.proveedores.print-and-pay') }}" data-form-pagar hidden class="border-t border-text-muted/20 bg-surface px-5 py-3.5 sm:px-6">
            @csrf
            <input type="hidden" name="semana" value="{{ $semana['inicio'] }}">
            <input type="hidden" name="zona" value="{{ $filtro->zonaId }}">
            <input type="hidden" name="estado" value="{{ $filtro->estado }}">
            <input type="hidden" name="buscar" value="{{ $buscar }}" data-buscar-espejo>
            <input type="hidden" name="ver" value="" data-ver-hoja>
            <div data-campos-pago></div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="flex max-w-3xl items-start gap-2.5 text-sm text-text">
                    <x-ui.icon name="info" class="mt-0.5 h-5 w-5 shrink-0 text-warning" />
                    <span>¿Se imprimieron los comprobantes y se entregó el efectivo? Al confirmar, <strong data-conf-cantidad>0 proveedores</strong> pasarán a <strong>Pagado</strong> por <strong data-conf-total>S/ 0.00</strong>. Esta acción no se puede deshacer.</span>
                </p>
                <div class="flex items-center gap-2.5">
                    <button type="button" data-conf-volver class="inline-flex h-10 items-center rounded-lg border border-text-muted/25 bg-surface px-4 text-sm font-medium text-text transition hover:bg-text-muted/10">Todavía no</button>
                    <button type="submit" data-conf-confirmar class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-60">
                        <x-ui.icon name="check" class="h-4 w-4" /> Confirmar pago en efectivo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
