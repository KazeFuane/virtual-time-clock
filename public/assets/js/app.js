// Shared App Utilities, CSRF handling, Notifications & Sidebar logic

// Translation: t() is defined in the page head (Lang::jsScript()).
// This fallback keeps English text if a page doesn't include it.
if (typeof window.t !== 'function') {
    window.t = function (s, p) {
        let o = s;
        if (p) { for (const k in p) { o = o.split('{' + k + '}').join(String(p[k])); } }
        return o;
    };
}

// Get CSRF Token
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Fetch API Wrapper
async function apiRequest(url, method = 'GET', data = null) {
    const headers = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
    };

    const options = {
        method,
        headers,
        credentials: 'same-origin'
    };

    if (data !== null) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        let json;
        try {
            json = await response.json();
        } catch (parseErr) {
            const rawText = await response.text();
            console.error('Non-JSON server response:', rawText);
            return {
                success: false,
                message: t('Server error ({status}). Please try again.', { status: response.status })
            };
        }
        return json;
    } catch (err) {
        console.error('API Network/Fetch Error:', err);
        return {
            success: false,
            message: t('Network communication error: {msg}', { msg: err.message || t('Please check your connection.') })
        };
    }
}

// Toast Notifications
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg text-sm shadow-xl border transform transition-all duration-300 translate-y-[-10px] opacity-0 max-w-sm';

    let iconSvg = '';
    if (type === 'success') {
        toast.classList.add('bg-[#102419]', 'border-emerald-800', 'text-emerald-200');
        iconSvg = `<svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`;
    } else if (type === 'error') {
        toast.classList.add('bg-[#291017]', 'border-rose-900', 'text-rose-200');
        iconSvg = `<svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
    } else if (type === 'warning') {
        toast.classList.add('bg-[#2b1f0e]', 'border-amber-800', 'text-amber-200');
        iconSvg = `<svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
    } else {
        toast.classList.add('bg-[#1a122e]', 'border-purple-800', 'text-purple-200');
        iconSvg = `<svg class="w-5 h-5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
    }

    toast.innerHTML = `${iconSvg}<div class="flex-1">${message}</div>`;
    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-[-10px]', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-[-10px]');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// "Undo" pop-up after a manager/admin change (see api/admin/undo.php).
// undo = data.undo from the API answer: {token, seconds}. Without it this
// is a plain success toast. onUndone() runs after a successful undo, to
// reload the page's data. The countdown pauses while the mouse is over it.
const UNDO_API = '/virtual-time-clock/api/admin/undo.php';
function showUndoToast(message, undo, onUndone) {
    if (!undo || !undo.token) { showToast(message, 'success'); return; }
    const container = document.getElementById('toast-container');
    if (!container) return;

    const total = (undo.seconds || 10) * 1000;
    let left = total;
    let last = Date.now();
    let paused = false;
    let done = false;

    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.className = 'pointer-events-auto relative overflow-hidden flex items-center gap-3 ps-4 pe-3 py-3 rounded-lg text-sm shadow-xl border bg-[#102419] border-emerald-800 text-emerald-200 transform transition-all duration-300 translate-y-[-10px] opacity-0 max-w-sm';
    toast.innerHTML = `<svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <div class="flex-1 min-w-0" data-undo-msg></div>
        <button type="button" data-undo-btn class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-400 disabled:opacity-60">
            <svg class="w-3.5 h-3.5 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l5-5M3 10l5 5"/></svg>
            <span>${t('Undo')}</span>
            <span class="font-mono opacity-80" data-undo-count></span>
        </button>
        <div class="absolute bottom-0 start-0 h-0.5 bg-emerald-500/70" data-undo-bar style="width:100%"></div>`;
    toast.querySelector('[data-undo-msg]').textContent = message;
    const btn = toast.querySelector('[data-undo-btn]');
    const count = toast.querySelector('[data-undo-count]');
    const bar = toast.querySelector('[data-undo-bar]');
    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-[-10px]', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    const close = () => {
        if (done) return;
        done = true;
        clearInterval(timer);
        toast.classList.add('opacity-0', 'translate-y-[-10px]');
        setTimeout(() => toast.remove(), 300);
    };
    const tick = () => {
        const now = Date.now();
        if (!paused) left -= now - last;
        last = now;
        if (left <= 0) { close(); return; }
        count.textContent = Math.ceil(left / 1000) + 's';
        bar.style.width = (left / total * 100) + '%';
    };
    const timer = setInterval(tick, 100);
    tick();
    toast.addEventListener('mouseenter', () => { paused = true; });
    toast.addEventListener('mouseleave', () => { paused = false; });

    btn.addEventListener('click', async () => {
        if (done) return;
        btn.disabled = true;
        paused = true;
        const res = await apiRequest(UNDO_API, 'POST', { token: undo.token });
        close();
        showToast(res.message, res.success ? 'info' : 'error');
        if (res.success && typeof onUndone === 'function') onUndone(res);
    });
}

// User Menu Dropdown & Logout
document.addEventListener('DOMContentLoaded', () => {
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userDropdown = document.getElementById('user-menu-dropdown');
    const logoutBtn = document.getElementById('header-logout-btn');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    }

    const logoutButtons = document.querySelectorAll('.btn-trigger-logout, #header-logout-btn');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            await apiRequest('/virtual-time-clock/api/auth/logout.php', 'POST');
            window.location.href = '/virtual-time-clock/public/login.php';
        });
    });

    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('rtl:translate-x-full');
        });
    }
});

// "Coming soon" pop-up for a button whose feature is not built yet.
// Put data-coming-soon on the button (optionally with the feature's English
// name as its value, e.g. data-coming-soon="Export to PDF") and clicking it
// shows a friendly pop-up instead of doing nothing.
function showComingSoon(feature) {
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const msg = feature
        ? t('{name} is coming soon. We are still building it.', { name: esc(t(feature)) })
        : t('This feature is coming soon. We are still building it.');
    showToast(`<span class="font-semibold">${t('Coming soon')}</span> &middot; ${msg}`, 'warning');
}
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-coming-soon]');
    if (!el) return;
    e.preventDefault();
    showComingSoon(el.getAttribute('data-coming-soon'));
});
