<?php
/**
 * Language picker: a globe button with the list of languages in lang/.
 * Used in the page header and on the sign-in page. Self-contained (does not
 * need app.js). Set $langPickerClass before including to style the button.
 */
$langCurrent = Lang::current();
$langList    = Lang::available();
$langBtnClass = $langPickerClass ?? 'flex items-center justify-center gap-1 h-10 px-2 sm:h-auto sm:p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-[#221530] transition focus:outline-none';
?>
<div class="relative" data-lang-root>
    <button type="button" data-lang-btn class="<?= $langBtnClass ?>" title="<?= t('Language') ?>" aria-label="<?= t('Language') ?>" aria-haspopup="true" aria-expanded="false">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-[11px] font-semibold uppercase"><?= htmlspecialchars(substr($langCurrent, 0, 2)) ?></span>
    </button>
    <div data-lang-menu class="hidden absolute end-0 mt-2 w-44 bg-[#1a1228] border border-[#2d1e44] rounded-lg shadow-xl py-1 text-xs z-50" role="menu">
        <div class="px-4 py-1.5 text-[10px] uppercase tracking-wider text-gray-500"><?= t('Language') ?></div>
        <?php foreach ($langList as $code => $info): ?>
            <button type="button" role="menuitem" data-set-lang="<?= htmlspecialchars($code) ?>" lang="<?= htmlspecialchars($code) ?>" dir="<?= $info['dir'] ?>"
                    <?= $code === $langCurrent ? 'aria-current="true"' : '' ?>
                    class="w-full flex items-center justify-between px-4 py-3 sm:py-2 text-sm sm:text-xs text-start <?= $code === $langCurrent ? 'text-white font-semibold' : 'text-gray-300' ?> hover:bg-[#26173a] hover:text-white transition">
                <span><?= htmlspecialchars($info['name']) ?></span>
                <?php if ($code === $langCurrent): ?><span class="text-purple-400">&check;</span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<?php if (empty($GLOBALS['__langPickerScript'])): $GLOBALS['__langPickerScript'] = true; ?>
<script>
(function () {
    function token() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; }
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-lang-btn]');
        document.querySelectorAll('[data-lang-root]').forEach(function (root) {
            const menu = root.querySelector('[data-lang-menu]');
            const open = btn && root.contains(btn) && menu.classList.contains('hidden');
            menu.classList.toggle('hidden', !open);
            root.querySelector('[data-lang-btn]').setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        const item = e.target.closest('[data-set-lang]');
        if (!item) return;
        e.preventDefault();
        if (item.getAttribute('aria-current') === 'true') return;
        try {
            const res = await fetch('/virtual-time-clock/api/auth/language.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
                body: JSON.stringify({ language: item.getAttribute('data-set-lang') })
            });
            const json = await res.json();
            if (json.success) { window.location.reload(); return; }
            alertMsg(json.message);
        } catch (err) {
            alertMsg(window.t ? t('Could not change the language.') : 'Could not change the language.');
        }
    });
    function alertMsg(m) {
        if (typeof showToast === 'function') showToast(m, 'error'); else console.error(m);
    }
})();
</script>
<?php endif; ?>
