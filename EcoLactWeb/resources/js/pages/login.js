import '../../css/pages/login.css';

const password = document.querySelector('#login-password');
const toggle = document.querySelector('[data-password-toggle]');

if (password && toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.querySelector('[data-password-visible-icon]').hidden = visible;
        toggle.querySelector('[data-password-hidden-icon]').hidden = !visible;
    });
}

document.querySelector('[data-login-errors]')?.focus();
