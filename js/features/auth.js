/* ==================================================================
   DocuFlow AI — FORMULARIO DE LOGIN & VALIDACIONES
   ------------------------------------------------------------------
   Autenticación real contra MySQL docuflow_db mediante API PHP nativa.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 7. FORMULARIO DE LOGIN & VALIDACIONES
// ═══════════════════════════════════════════════════════

window.validateEmailRealTime = function (input) {
    const email = input.value.trim();
    const icon = document.getElementById('email-validation-icon');
    const hint = document.getElementById('email-validation-hint');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!email) {
        input.classList.remove('input-valid', 'input-invalid');
        if (icon) icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-transparent';
        if (hint) {
            hint.textContent = '';
            hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-transparent';
        }
        return false;
    }

    if (emailRegex.test(email)) {
        input.classList.remove('input-invalid');
        input.classList.add('input-valid');
        if (icon) {
            icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-emerald-400';
            icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
        }
        if (hint) {
            hint.textContent = 'Formato de correo válido';
            hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-emerald-400 font-semibold';
        }
        return true;
    } else {
        input.classList.remove('input-valid');
        input.classList.add('input-invalid');
        if (icon) {
            icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-rose-400';
            icon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
        }
        if (hint) {
            hint.textContent = 'Formato inválido (ej: operador.demo@docuflow.test)';
            hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-rose-400 font-semibold';
        }
        return false;
    }
};

window.togglePasswordVisibility = function () {
    const pwd = document.getElementById('login-password');
    const icon = document.getElementById('password-toggle-icon');
    if (!pwd || !icon) return;
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
    icon.className = `fa-solid ${pwd.type === 'password' ? 'fa-eye' : 'fa-eye-slash'} text-sm`;
};

window.fillDemoCredentials = function (role = 'ope') {
    const emailInput = document.getElementById('login-email');
    const pwdInput = document.getElementById('login-password');
    if (!emailInput || !pwdInput) return;

    if (role === 'ope') {
        emailInput.value = 'operador.demo@docuflow.test';
        pwdInput.value = 'DocuFlowDemo2026!';
    } else {
        emailInput.value = 'admin@docuflow.ai';
        pwdInput.value = 'DocuFlow2026';
    }
    window.validateEmailRealTime(emailInput);
};

window.handleLoginSubmit = async function (e) {
    e.preventDefault();
    const emailInput = document.getElementById('login-email');
    const pwdInput = document.getElementById('login-password');
    const btn = document.getElementById('login-btn');
    const spinner = document.getElementById('login-btn-spinner');
    const btnText = document.getElementById('login-btn-text');

    if (!validateEmailRealTime(emailInput)) {
        showToast('HTTP 400 Bad Request: Ingrese un correo electrónico válido.', 'error', 400);
        return;
    }

    if (!pwdInput.value) {
        showToast('HTTP 400 Bad Request: Ingrese su contraseña.', 'error', 400);
        return;
    }

    btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden-view');
    if (btnText) btnText.textContent = 'Verificando en MySQL...';

    try {
        const res = await apiFetch('/api/auth/login', {
            method: 'POST',
            body: {
                email: emailInput.value.trim(),
                password: pwdInput.value
            }
        });

        if (res.ok && res.status === 200) {
            const usuario = res.data.usuario;
            const csrfToken = res.data.csrf_token;

            // Guardar perfil no sensible en el cliente
            localStorage.setItem(CONFIG.STORAGE_KEYS.USER, JSON.stringify(usuario));
            localStorage.setItem(CONFIG.STORAGE_KEYS.CSRF, csrfToken);

            showToast(`¡Bienvenido ${escapeHTML(usuario.nombre)}! (${usuario.rol_codigo})`, 'success', 200);

            if (typeof resetIdleTimer === 'function') resetIdleTimer();

            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 300);
        }
    } catch (err) {
        console.warn('[Login Error]', err);
        showToast(err.message || 'Error de autenticación.', 'error', err.status || 500);
    } finally {
        btn.disabled = false;
        if (spinner) spinner.classList.add('hidden-view');
        if (btnText) btnText.textContent = 'Iniciar Sesión';
    }
};

window.handleLogout = async function () {
    if (typeof idleTimeoutTimer !== 'undefined' && idleTimeoutTimer) {
        clearTimeout(idleTimeoutTimer);
    }

    try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch (e) {
        console.warn('Error durante logout en servidor:', e);
    } finally {
        localStorage.removeItem(CONFIG.STORAGE_KEYS.USER);
        localStorage.removeItem(CONFIG.STORAGE_KEYS.CSRF);
        showToast('Sesión cerrada correctamente.', 'info');

        setTimeout(() => {
            window.location.href = 'login.html';
        }, 200);
    }
};
