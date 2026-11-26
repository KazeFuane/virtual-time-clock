<?php
/**
 * "Coming soon" page for features that are not built yet.
 * The list of these features is in src/Helpers/ComingSoon.php.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();

$moduleKey = (string) ($_GET['module'] ?? '');
$feature   = ComingSoon::get($moduleKey);
// Only names on the list are shown, so the page never echoes random text
$moduleName = $feature ? t($moduleKey) : t('This feature');
$iconPath   = $feature['icon'] ?? 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10';
$others     = array_filter(ComingSoon::features(), fn($k) => $k !== $moduleKey, ARRAY_FILTER_USE_KEY);

// Plain text: the layout escapes the title
$pageTitle = html_entity_decode($moduleName . ' — ' . t('Coming soon'), ENT_QUOTES, 'UTF-8') . ' — ' . APP_NAME;
$breadcrumbs = [
    ['label' => html_entity_decode(t('Time Clock'), ENT_QUOTES, 'UTF-8'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => html_entity_decode($moduleName, ENT_QUOTES, 'UTF-8'), 'url' => ''],
];

ob_start();
?>

<div class="max-w-2xl mx-auto py-8 sm:py-12">
    <div class="relative overflow-hidden bg-[#140c1d] border border-[#281b3b] rounded-2xl p-8 sm:p-12 shadow-xl text-center">
        <!-- Soft glow behind the icon -->
        <div class="pointer-events-none absolute -top-24 left-1/2 -translate-x-1/2 w-72 h-72 rounded-full bg-purple-600/10 blur-3xl"></div>

        <div class="relative">
            <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-purple-950/60 border border-purple-800/50 flex items-center justify-center text-purple-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars($iconPath) ?>"/></svg>
            </div>

            <span class="inline-flex items-center gap-1.5 mb-3 px-3 py-1 rounded-full bg-amber-950 border border-amber-800 text-amber-300 text-[11px] font-semibold uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <?= t('Coming soon') ?>
            </span>

            <h1 class="text-2xl font-bold text-white mb-3"><?= $moduleName ?></h1>

            <?php if ($feature): ?>
                <p class="text-sm text-gray-300 max-w-md mx-auto mb-2"><?= t($feature['about']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-400 max-w-md mx-auto mb-8">
                <?= t("We're still building this feature. It will show up here as soon as it's ready.") ?>
            </p>

            <a href="/virtual-time-clock/public/dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-semibold shadow-md shadow-purple-900/40 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= t('Return to Time Clock') ?>
            </a>
        </div>
    </div>

    <?php if ($others): ?>
        <div class="mt-6">
            <div class="px-1 mb-2 text-[10px] uppercase font-bold tracking-wider text-purple-400"><?= t('Also on the way') ?></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php foreach ($others as $key => $info): ?>
                    <a href="<?= htmlspecialchars(ComingSoon::url($key)) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-[#140c1d] border border-[#281b3b] hover:bg-[#221530] transition">
                        <span class="w-8 h-8 shrink-0 rounded-lg bg-purple-950/60 border border-purple-800/50 flex items-center justify-center text-purple-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars($info['icon']) ?>"/></svg>
                        </span>
                        <span class="flex-1 min-w-0 text-xs font-medium text-gray-200 truncate"><?= t($key) ?></span>
                        <?= ComingSoon::badge() ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/views/layouts/main.php';
