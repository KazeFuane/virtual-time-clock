// Shared App Utilities, CSRF handling, Notifications & Sidebar logic

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
                message: 'Server error (' + response.status + '). Please try again.'
            };
        }
        return json;
    } catch (err) {
        console.error('API Network/Fetch Error:', err);
        return {
            success: false,
            message: 'Network communication error: ' + (err.message || 'Please check your connection.')
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
        });
    }
});
