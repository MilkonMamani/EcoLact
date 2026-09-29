import QRCode from 'qrcode';

/**
 * Pinta los <canvas data-qr-code="base64(token)"> que haya dentro de `raiz`.
 * Lo usa app.js al cargar la página y las pantallas que insertan HTML por
 * fetch (p. ej. el panel de Proveedores).
 */
export function pintarQrs(raiz = document) {
    raiz.querySelectorAll('[data-qr-code]').forEach((canvas) => {
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
}
