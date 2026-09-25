import QRCode from 'qrcode';

if (document.querySelector('[data-admin-dashboard]')) {
    import('./pages/admin-dashboard.js');
}

if (document.querySelector('[data-acopio-page]')) {
    import('./pages/admin-acopio.js');
}

if (document.querySelector('[data-ruta-detalle]')) {
    import('./pages/admin-ruta.js');
}

if (document.querySelector('[data-zonas-page]')) {
    import('./pages/admin-zonas.js');
}

if (document.querySelector('[data-recorrido]')) {
    import('./pages/acopiador-recorrido.js');
}

if (document.querySelector('[data-produccion-nueva]')) {
    import('./pages/produccion-nueva.js');
}

if (document.querySelector('[data-recetas-page]')) {
    import('./pages/recetas.js');
}

if (document.querySelector('[data-insumos-page]')) {
    import('./pages/insumos.js');
}

if (document.querySelector('[data-pago-semanal]')) {
    import('./pages/admin-pago-semanal.js');
}

if (document.querySelector('[data-pagos-page]')) {
    import('./pages/admin-pagos.js');
}

if (document.querySelector('[data-pago-trabajadores]')) {
    import('./pages/admin-pago-trabajadores.js');
}

if (document.querySelector('[data-proveedor-pagos]')) {
    import('./pages/admin-proveedor-pagos.js');
}

if (document.querySelector('[data-control-periodo]')) {
    import('./pages/admin-jornadas.js');
}

if (document.querySelector('[data-asistencia-page]')) {
    import('./pages/admin-asistencia.js');
}

// Despliega/oculta una fila de detalle en la misma tabla (sin cambiar de pantalla).
document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-toggle-row]');

    if (!boton) {
        return;
    }

    const fila = document.getElementById(boton.dataset.toggleRow);

    if (fila) {
        fila.hidden = !fila.hidden;
        boton.setAttribute('aria-expanded', String(!fila.hidden));
    }
});

document.querySelectorAll('[data-qr-code]').forEach((canvas) => {
    const qrValue = atob(canvas.dataset.qrCode);

    QRCode.toCanvas(canvas, qrValue, {
        width: 240,
        margin: 2,
        color: { dark: '#143b39', light: '#ffffff' },
    }).catch(() => {
        canvas.replaceWith(Object.assign(document.createElement('p'), {
            className: 'text-sm text-danger',
            textContent: 'No se pudo generar el código QR.',
        }));
    });
});

document.querySelectorAll('[data-provider-search]').forEach((search) => {
    const input = search.querySelector('[data-provider-search-input]');
    const results = search.querySelector('[data-provider-search-results]');
    const selected = search.parentElement.querySelector('[data-provider-selected]');
    const name = selected?.querySelector('[data-provider-name]');
    const zone = selected?.querySelector('[data-provider-zone]');
    let timer;

    input?.addEventListener('input', () => {
        window.clearTimeout(timer);
        selected?.classList.add('hidden');
        results.innerHTML = '';
        results.classList.add('hidden');

        const dni = input.value.replace(/\D/g, '').slice(0, 8);
        input.value = dni;
        if (dni === '') {
            return;
        }

        timer = window.setTimeout(async () => {
            let proveedores = [];

            try {
                const response = await fetch(`${input.dataset.providerSearchUrl}?q=${encodeURIComponent(dni)}`, {
                    headers: { Accept: 'application/json' },
                });
                proveedores = response.ok ? await response.json() : [];
            } catch {
                proveedores = [];
            }

            if (proveedores.length === 0) {
                results.innerHTML = '<p class="px-4 py-3 text-sm text-text-muted">No se encontró un proveedor con ese DNI.</p>';
            } else {
                proveedores.forEach((proveedor) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'block w-full px-4 py-3 text-left text-sm hover:bg-text-muted/10';
                    const title = document.createElement('strong');
                    title.textContent = proveedor.nombre;
                    const dniLabel = document.createElement('span');
                    dniLabel.className = 'ml-2 text-text-muted';
                    dniLabel.textContent = proveedor.dni;
                    const zoneLabel = document.createElement('span');
                    zoneLabel.className = 'block text-xs text-text-muted';
                    zoneLabel.textContent = proveedor.zona ?? '';
                    option.append(title, dniLabel, zoneLabel);
                    option.addEventListener('click', () => {
                        input.value = proveedor.dni;
                        name.textContent = proveedor.nombre;
                        zone.textContent = proveedor.zona ? `Zona: ${proveedor.zona}` : '';
                        selected.classList.remove('hidden');
                        results.classList.add('hidden');
                    });
                    results.append(option);
                });
            }

            results.classList.remove('hidden');
        }, 180);
    });
});

function closeAllDropdowns(except) {
    document.querySelectorAll('[data-dropdown-menu].is-open').forEach((menu) => {
        if (menu !== except) {
            menu.classList.remove('is-open');
        }
    });
}

const sidebarMobile = window.matchMedia('(max-width: 1023px)');
const sidebarFallback = document.querySelector('[data-sidebar-fallback]');
if (sidebarFallback) {
    sidebarFallback.hidden = Boolean(document.querySelector('[data-sidebar-toggle]:not([data-sidebar-fallback])'));
}
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.setAttribute('aria-controls', 'ecolact-sidebar');
    button.setAttribute('aria-expanded', 'false');
});

function toggleSidebar(open) {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!sidebar) {
        return;
    }

    const wasOpen = sidebar.classList.contains('is-open');
    const shouldOpen = sidebarMobile.matches && (open ?? !wasOpen);

    sidebar.classList.toggle('is-open', shouldOpen);
    backdrop?.classList.toggle('is-open', shouldOpen);
    document.body.classList.toggle('overflow-hidden', shouldOpen || Boolean(document.querySelector('[data-modal].is-open')));
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(shouldOpen));
        button.setAttribute('aria-label', shouldOpen ? 'Cerrar menú' : 'Abrir menú');
    });

    if (shouldOpen) {
        window.requestAnimationFrame(() => {
            if (sidebar.classList.contains('is-open')) {
                sidebar.querySelector('[data-sidebar-close]')?.focus();
            }
        });
    } else if (wasOpen && sidebarMobile.matches) {
        document.querySelector('[data-sidebar-toggle]:not([hidden])')?.focus();
    }
}

sidebarMobile.addEventListener('change', () => toggleSidebar(false));

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !sidebarMobile.matches) {
        return;
    }

    const sidebar = document.querySelector('[data-sidebar].is-open');
    if (!sidebar) {
        return;
    }

    const controls = [...sidebar.querySelectorAll('a[href], button, summary, [tabindex="0"]')]
        .filter((element) => element.getClientRects().length > 0);
    const first = controls[0];
    const last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
});

function openModal(id) {
    const modal = document.getElementById(id);

    if (!modal) {
        return;
    }

    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
    modal.querySelector('[autofocus]')?.focus();
}

function closeModal(modal) {
    modal.classList.remove('is-open');

    if (!document.querySelector('[data-modal].is-open')) {
        document.body.classList.remove('overflow-hidden');
    }
}

function activateTab(tabsRoot, targetId) {
    tabsRoot.querySelectorAll('[data-tab-trigger]').forEach((trigger) => {
        const isActive = trigger.dataset.tabTrigger === targetId;
        trigger.classList.toggle('is-active', isActive);
        trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    tabsRoot.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== targetId;
    });
}

document.addEventListener('click', (event) => {
    const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
        toggleSidebar();
        return;
    }

    if (event.target.closest('[data-sidebar-backdrop], [data-sidebar-close]')) {
        toggleSidebar(false);
        return;
    }

    const dropdownToggle = event.target.closest('[data-dropdown-toggle]');
    if (dropdownToggle) {
        const menu = document.getElementById(dropdownToggle.dataset.dropdownToggle);
        const willOpen = menu && !menu.classList.contains('is-open');
        closeAllDropdowns();
        if (willOpen) {
            menu.classList.add('is-open');
        }
        return;
    }

    if (!event.target.closest('[data-dropdown-menu]')) {
        closeAllDropdowns();
    }

    const modalTrigger = event.target.closest('[data-modal-open]');
    if (modalTrigger) {
        openModal(modalTrigger.dataset.modalOpen);
        return;
    }

    const modalClose = event.target.closest('[data-modal-close]');
    if (modalClose) {
        closeModal(modalClose.closest('[data-modal]'));
        return;
    }

    const modalBackdrop = event.target.closest('[data-modal]');
    if (modalBackdrop && event.target === modalBackdrop) {
        closeModal(modalBackdrop);
        return;
    }

    const tabTrigger = event.target.closest('[data-tab-trigger]');
    if (tabTrigger) {
        activateTab(tabTrigger.closest('[data-tabs]'), tabTrigger.dataset.tabTrigger);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    // Con modales apilados (p. ej. historial de pagos + detalle) Escape cierra solo el de encima.
    const ultimoAbierto = [...document.querySelectorAll('[data-modal].is-open')].at(-1);

    if (ultimoAbierto) {
        closeModal(ultimoAbierto);
    }

    closeAllDropdowns();
    toggleSidebar(false);
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-confirm]');

    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});
