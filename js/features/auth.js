/* ==================================================================
   DocuFlow AI — FORMULARIO DE LOGIN & VALIDACIONES
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
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
        icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-transparent';
        hint.textContent = '';
        hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-transparent';
        return false;
    }

    if (emailRegex.test(email)) {
        input.classList.remove('input-invalid');
        input.classList.add('input-valid');
        icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-emerald-400';
        icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
        hint.textContent = 'Correo válido';
        hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-emerald-400 font-semibold';
        return true;
    } else {
        input.classList.remove('input-valid');
        input.classList.add('input-invalid');
        icon.className = 'absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-rose-400';
        icon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
        hint.textContent = 'Formato inválido (ej: usuario@docuflow.ai)';
        hint.className = 'text-[11px] mt-1.5 h-4 transition-all text-rose-400 font-semibold';
        return false;
    }
};

window.togglePasswordVisibility = function () {
    const pwd = document.getElementById('login-password');
    const icon = document.getElementById('password-toggle-icon');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
    icon.className = `fa-solid ${pwd.type === 'password' ? 'fa-eye' : 'fa-eye-slash'} text-sm`;
};

window.fillDemoCredentials = function () {
    const emailInput = document.getElementById('login-email');
    const pwdInput = document.getElementById('login-password');
    emailInput.value = CONFIG.VALID_USER;
    pwdInput.value = CONFIG.VALID_PASS;
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

    btn.disabled = true;
    spinner.classList.remove('hidden-view');
    btnText.textContent = 'Verificando...';

    try {
        const res = await fakeFetch('/api/auth/login', {
            method: 'POST',
            body: { email: emailInput.value.trim(), password: pwdInput.value }
        });

        if (res.ok && res.status === 200) {
            localStorage.setItem(CONFIG.STORAGE_KEYS.TOKEN, res.data.token);
            showToast('¡Autenticación exitosa! Bienvenido al Dashboard (HTTP 200 OK)', 'success', 200);
            resetIdleTimer();
            
            // Si está en la página modular login.html, redirige a dashboard.html
            if (window.location.pathname.includes('login.html')) {
                setTimeout(() => { window.location.href = 'dashboard.html'; }, 400);
            } else if (document.getElementById('view-dashboard')) {
                showView('dashboard');
            } else {
                window.location.href = 'dashboard.html';
            }
        } else {
            showToast(res.data?.message || 'Error de autenticación.', 'error', res.status);
        }
    } catch (err) {
        showToast(err.data?.message || 'Error en el servidor de autenticación.', 'error', 500);
    } finally {
        btn.disabled = false;
        spinner.classList.add('hidden-view');
        btnText.textContent = 'Iniciar Sesión';
    }
};

window.handleLogout = function () {
    if (typeof idleTimeoutTimer !== 'undefined' && idleTimeoutTimer) clearTimeout(idleTimeoutTimer);
    localStorage.removeItem(CONFIG.STORAGE_KEYS.TOKEN);
    showToast('Sesión cerrada correctamente. Token eliminado.', 'info');
    
    if (window.location.pathname.includes('dashboard.html')) {
        setTimeout(() => { window.location.href = 'login.html'; }, 300);
    } else {
        showView('landing');
    }
};
