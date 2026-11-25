/**
 * ClockWise - light / dark mode switch.
 * Any element with [data-theme-toggle] flips the mode. The page is already
 * rendered in the saved mode by PHP; this only handles the click and saves it.
 */
(function () {
    const root = document.documentElement;

    function current() {
        return root.classList.contains('theme-light') ? 'light' : 'dark';
    }

    function apply(theme) {
        root.classList.remove('theme-light', 'theme-dark');
        root.classList.add('theme-' + theme);
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            const label = theme === 'light' ? (btn.dataset.labelDark || 'Switch to dark mode')
                                            : (btn.dataset.labelLight || 'Switch to light mode');
            btn.setAttribute('title', label);
            btn.setAttribute('aria-label', label);
            btn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
        });
        try { document.cookie = 'vtc_theme=' + theme + '; path=/; max-age=31536000; samesite=lax'; } catch (e) {}
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }

    async function save(theme) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            await fetch('/virtual-time-clock/api/auth/theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: JSON.stringify({ theme })
            });
        } catch (e) { /* the cookie still remembers it on this browser */ }
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        e.preventDefault();
        const next = current() === 'light' ? 'dark' : 'light';
        apply(next);
        save(next);
    });

    document.addEventListener('DOMContentLoaded', () => apply(current()));
})();
