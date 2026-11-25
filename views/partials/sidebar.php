<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentUser = AuthMiddleware::getCurrentUser();

// Highlight the link of the page being viewed.
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
if (str_ends_with($currentPath, '/')) {
    $currentPath .= 'index.php';
}
parse_str(parse_url($currentUri, PHP_URL_QUERY) ?: '', $currentQuery);
$navActive = function (string $href) use ($currentPath, $currentQuery): bool {
    $path = parse_url($href, PHP_URL_PATH);
    if ($path !== $currentPath) {
        return false;
    }
    parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $q);
    return !isset($q['module']) || ($currentQuery['module'] ?? null) === $q['module'];
};
// The current page keeps the same look as when the mouse is over the link
$activeClass    = 'bg-[#221530] text-white';     // top-level links (same as their hover)
$activeSubClass = 'bg-[#1c1228] text-gray-200';  // Time Management links (same as their hover)
$timeMgmtActive = $navActive('/virtual-time-clock/public/dashboard.php')
    || $navActive('/virtual-time-clock/public/history.php')
    || $navActive('/virtual-time-clock/public/time-errors.php')
    || $navActive('/virtual-time-clock/public/schedule.php');
$isPlanner = !empty($currentUser) && in_array($currentUser['role'], ['administrator', 'manager'], true);
?>
<aside id="app-sidebar" class="w-64 bg-[#140c1d] border-e border-[#261936] flex flex-col justify-between shrink-0 transition-transform duration-300 z-40">
    <!-- Top Branding & Navigation -->
    <div class="flex flex-col h-full overflow-y-auto">
        <!-- Brand / Org Info matching screenshot -->
        <div class="px-5 py-4 border-b border-[#261936]">
            <a href="/virtual-time-clock/public/dashboard.php" class="flex items-center gap-2.5 rounded-lg hover:opacity-90 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500" title="<?= t('Virtual Time Clock') ?>">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-white tracking-wide"><?= htmlspecialchars(APP_NAME) ?></div>
                    <div class="text-xs text-purple-400 font-medium"><?= t('Time &amp; attendance') ?></div>
                </div>
            </a>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm font-medium">
            <!-- Time Management (Collapsible Group) -->
            <div>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg <?= $timeMgmtActive ? $activeClass : 'text-gray-200' ?> hover:bg-[#221530] hover:text-white transition group" onclick="toggleNavGroup('time-mgmt-group', 'time-mgmt-icon')">
                    <span class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><?= t('Time Management') ?></span>
                    </span>
                    <svg id="time-mgmt-icon" class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div id="time-mgmt-group" class="ps-7 pe-2 py-1 space-y-1">
                    <a href="/virtual-time-clock/public/dashboard.php" class="block px-3 py-1.5 rounded-md text-xs <?= $navActive('/virtual-time-clock/public/dashboard.php') ? $activeSubClass : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        <?= t('Virtual Time Clock') ?>
                    </a>
                    <a href="/virtual-time-clock/public/history.php" class="block px-3 py-1.5 rounded-md text-xs <?= $navActive('/virtual-time-clock/public/history.php') ? $activeSubClass : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        <?= t('My History') ?>
                    </a>
                    <a href="/virtual-time-clock/public/time-errors.php" class="block px-3 py-1.5 rounded-md text-xs <?= $navActive('/virtual-time-clock/public/time-errors.php') ? $activeSubClass : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        <?= t('Summary of time errors') ?>
                    </a>
                    <a href="/virtual-time-clock/public/schedule.php" class="block px-3 py-1.5 rounded-md text-xs <?= $navActive('/virtual-time-clock/public/schedule.php') ? $activeSubClass : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        <?= $isPlanner ? t('Schedules') : t('My Schedule') ?>
                    </a>
                </div>
            </div>

            <!-- Future Modules Placeholders -->
            <a href="/virtual-time-clock/public/placeholder.php?module=Quality%20Knowledge" class="flex items-center justify-between px-3 py-2.5 rounded-lg <?= $navActive('/virtual-time-clock/public/placeholder.php?module=Quality%20Knowledge') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition">
                <span><?= t('Quality Knowledge') ?></span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Quality%20Grid" class="flex items-center justify-between px-3 py-2.5 rounded-lg <?= $navActive('/virtual-time-clock/public/placeholder.php?module=Quality%20Grid') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition">
                <span><?= t('Quality Grid') ?></span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Communication" class="flex items-center justify-between px-3 py-2.5 rounded-lg <?= $navActive('/virtual-time-clock/public/placeholder.php?module=Communication') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition">
                <span><?= t('Communication') ?></span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Back%20Office%20Tools" class="flex items-center justify-between px-3 py-2.5 rounded-lg <?= $navActive('/virtual-time-clock/public/placeholder.php?module=Back%20Office%20Tools') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition">
                <span><?= t('Back Office Tools') ?></span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Production%20Monitoring" class="flex items-center justify-between px-3 py-2.5 rounded-lg <?= $navActive('/virtual-time-clock/public/placeholder.php?module=Production%20Monitoring') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition">
                <span><?= t('Production Monitoring') ?></span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <?php if (!empty($currentUser) && in_array($currentUser['role'], ['administrator', 'manager'])): ?>
                <!-- Admin Panel Link -->
                <div class="pt-4 mt-2 border-t border-[#261936]">
                    <span class="px-3 text-[10px] uppercase font-bold tracking-wider text-purple-400"><?= t('Management') ?></span>
                    <a href="/virtual-time-clock/public/admin/index.php" class="flex items-center justify-between px-3 py-2 rounded-lg <?= $navActive('/virtual-time-clock/public/admin/index.php') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition mt-1">
                        <span><?= t('Admin Console') ?></span>
                        <span class="text-[10px] bg-purple-900 text-purple-300 font-semibold px-1.5 py-0.5 rounded"><?= t('Admin') ?></span>
                    </a>
                    <a href="/virtual-time-clock/public/admin/time-errors.php" class="flex items-center justify-between px-3 py-2 rounded-lg <?= $navActive('/virtual-time-clock/public/admin/time-errors.php') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition mt-1">
                        <span><?= t('Time Errors') ?></span>
                    </a>
                    <a href="/virtual-time-clock/public/admin/time-corrections.php" class="flex items-center justify-between px-3 py-2 rounded-lg <?= $navActive('/virtual-time-clock/public/admin/time-corrections.php') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition mt-1">
                        <span><?= t('Correct Times') ?></span>
                    </a>
                    <a href="/virtual-time-clock/public/admin/leaves.php" class="flex items-center justify-between px-3 py-2 rounded-lg <?= $navActive('/virtual-time-clock/public/admin/leaves.php') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition mt-1">
                        <span><?= t('Leave &amp; PTO') ?></span>
                    </a>
                    <a href="/virtual-time-clock/public/schedule.php" class="flex items-center justify-between px-3 py-2 rounded-lg <?= $navActive('/virtual-time-clock/public/schedule.php') ? $activeClass : 'text-gray-300 hover:bg-[#221530] hover:text-white' ?> transition mt-1">
                        <span><?= t('Plan Schedules') ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Bottom Items -->
        <div class="p-3 border-t border-[#261936] text-xs space-y-2 text-gray-400">
            <div class="flex items-center gap-4 px-2">
                <a href="/virtual-time-clock/public/placeholder.php?module=Help" class="flex items-center gap-1.5 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= t('Help') ?>
                </a>
                <a href="/virtual-time-clock/public/placeholder.php?module=Privacy%20%26%20Compliance" class="flex items-center gap-1.5 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <?= t('Privacy & Compliance') ?>
                </a>
            </div>
            <div class="px-2 text-[11px] text-gray-500">
                <?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?>
            </div>
        </div>
    </div>
</aside>

<script>
function toggleNavGroup(groupId, iconId) {
    const el = document.getElementById(groupId);
    const icon = document.getElementById(iconId);
    if (!el) return;
    el.classList.toggle('hidden');
    if (icon) {
        icon.classList.toggle('rotate-90');
    }
}
</script>
