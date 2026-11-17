<?php
$currentUser = AuthMiddleware::getCurrentUser();
?>
<header class="h-14 border-b border-[#261936] bg-[#140c1d] px-4 md:px-6 flex items-center justify-between shrink-0">
    <!-- Breadcrumb Area -->
    <div class="flex items-center gap-2 text-xs md:text-sm text-gray-400">
        <button id="mobile-sidebar-toggle" class="md:hidden mr-2 p-1 text-gray-400 hover:text-white focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?>
                    <span class="text-gray-600 font-semibold">&rsaquo;</span>
                <?php endif; ?>
                <?php if (!empty($crumb['url']) && $index < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>" class="hover:text-purple-400 transition font-medium"><?= htmlspecialchars($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="text-gray-200 font-medium"><?= htmlspecialchars($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="text-gray-200 font-medium">Virtual Time Clock</span>
        <?php endif; ?>
    </div>

    <!-- Right Controls: Search & User Profile -->
    <div class="flex items-center gap-3 md:gap-4">
        <!-- Search bar (styled to match screenshot) -->
        <div class="relative hidden sm:block">
            <input type="text" placeholder="Search..." class="w-40 md:w-56 bg-[#1f152b] text-xs text-gray-200 placeholder-gray-500 rounded-full pl-3 pr-8 py-1.5 border border-[#312246] focus:outline-none focus:border-purple-500 transition">
            <svg class="w-3.5 h-3.5 text-gray-400 absolute right-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>

        <!-- User profile widget -->
        <?php if ($currentUser): ?>
            <div class="relative">
                <button type="button" id="user-menu-btn" class="flex items-center gap-2.5 hover:opacity-90 focus:outline-none py-1">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-inner">
                        <?= strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)) ?>
                    </div>
                    <div class="text-left hidden md:block">
                        <div class="text-xs font-semibold text-white leading-tight">
                            <?= htmlspecialchars(($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?: $currentUser['username']) ?>
                        </div>
                        <div class="text-[10px] text-purple-400 capitalize leading-tight">
                            <?= htmlspecialchars($currentUser['role'] ?? 'Agent') ?>
                        </div>
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7 7"/></svg>
                </button>

                <!-- Dropdown Menu -->
                <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-[#1a1228] border border-[#2d1e44] rounded-lg shadow-xl py-1 text-xs z-50">
                    <div class="px-4 py-2 border-b border-[#2d1e44] md:hidden">
                        <p class="font-medium text-white"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></p>
                        <p class="text-[11px] text-gray-400"><?= htmlspecialchars($currentUser['email']) ?></p>
                    </div>
                    <a href="/virtual-time-clock/public/history.php" class="block px-4 py-2 text-gray-300 hover:bg-[#26173a] hover:text-white transition">My History</a>
                    <?php if (in_array($currentUser['role'], ['administrator', 'manager'])): ?>
                        <a href="/virtual-time-clock/public/admin/index.php" class="block px-4 py-2 text-gray-300 hover:bg-[#26173a] hover:text-white transition">Admin Panel</a>
                    <?php endif; ?>
                    <button type="button" class="w-full text-left px-4 py-2 text-red-400 hover:bg-[#26173a] hover:text-red-300 transition border-t border-[#2d1e44] btn-trigger-logout">Sign Out</button>
                </div>
            </div>

            <!-- Prominent Quick Sign Out button in header -->
            <button type="button" class="btn-trigger-logout hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-900/60 bg-red-950/40 text-red-300 hover:bg-red-900/60 hover:text-white text-xs font-medium transition" title="Log out from session">
                <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Sign Out</span>
            </button>
        <?php endif; ?>
    </div>
</header>
