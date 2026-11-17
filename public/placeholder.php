<?php
/**
 * Placeholder view for unbuilt modules.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();

$moduleName = htmlspecialchars($_GET['module'] ?? 'Module');

$pageTitle = $moduleName . ' &mdash; Virtual Time Clock';
$breadcrumbs = [
    ['label' => 'Time Clock', 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => $moduleName, 'url' => '']
];

ob_start();
?>

<div class="max-w-2xl mx-auto py-12 text-center">
    <div class="bg-[#140c1d] border border-[#281b3b] rounded-2xl p-8 sm:p-12 shadow-xl">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-purple-950/60 border border-purple-800/50 flex items-center justify-center text-purple-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </div>
        <h1 class="text-xl font-bold text-white mb-2"><?= $moduleName ?></h1>
        <p class="text-xs text-gray-400 max-w-md mx-auto mb-6">
            This module is reserved for enterprise portal expansion. You can easily plug business logic into this route without restructuring the core attendance system.
        </p>
        <a href="/virtual-time-clock/public/dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-semibold shadow-md shadow-purple-900/40 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Return to Time Clock
        </a>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/views/layouts/main.php';
