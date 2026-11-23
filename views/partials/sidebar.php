<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentUser = AuthMiddleware::getCurrentUser();
?>
<aside id="app-sidebar" class="w-64 bg-[#140c1d] border-r border-[#261936] flex flex-col justify-between shrink-0 transition-transform duration-300 z-40">
    <!-- Top Branding & Navigation -->
    <div class="flex flex-col h-full overflow-y-auto">
        <!-- Brand / Org Info matching screenshot -->
        <div class="px-5 py-4 border-b border-[#261936]">
            <div class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Portal 2</div>
            <div class="text-sm font-bold text-white tracking-wide mt-0.5">ByteDance</div>
            <div class="text-xs text-purple-400 font-medium">ByteDance_Sahara</div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm font-medium">
            <!-- Time Management (Collapsible Group) -->
            <div>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-200 hover:bg-[#221530] hover:text-white transition group" onclick="toggleNavGroup('time-mgmt-group', 'time-mgmt-icon')">
                    <span class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Time Management</span>
                    </span>
                    <svg id="time-mgmt-icon" class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div id="time-mgmt-group" class="pl-7 pr-2 py-1 space-y-1">
                    <a href="/virtual-time-clock/public/dashboard.php" class="block px-3 py-1.5 rounded-md text-xs <?= str_contains($currentUri, 'dashboard.php') ? 'bg-[#2e1c42] text-purple-300 font-semibold' : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        Virtual Time Clock
                    </a>
                    <a href="/virtual-time-clock/public/history.php" class="block px-3 py-1.5 rounded-md text-xs <?= str_contains($currentUri, 'history.php') ? 'bg-[#2e1c42] text-purple-300 font-semibold' : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        My History
                    </a>
                    <a href="/virtual-time-clock/public/time-errors.php" class="block px-3 py-1.5 rounded-md text-xs <?= str_contains($currentUri, '/public/time-errors.php') ? 'bg-[#2e1c42] text-purple-300 font-semibold' : 'text-gray-400 hover:text-gray-200 hover:bg-[#1c1228]' ?> transition">
                        Summary of time errors
                    </a>
                    <a href="/virtual-time-clock/public/placeholder.php?module=My%20Schedule" class="block px-3 py-1.5 rounded-md text-xs text-gray-400 hover:text-gray-200 hover:bg-[#1c1228] transition">
                        My Schedule
                    </a>
                </div>
            </div>

            <!-- Future Modules Placeholders -->
            <a href="/virtual-time-clock/public/placeholder.php?module=Quality%20Knowledge" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition">
                <span>Quality Knowledge</span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Quality%20Grid" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition">
                <span>Quality Grid</span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Communication" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition">
                <span>Communication</span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Back%20Office%20Tools" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition">
                <span>Back Office Tools</span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/virtual-time-clock/public/placeholder.php?module=Production%20Monitoring" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition">
                <span>Production Monitoring</span>
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <?php if (!empty($currentUser) && in_array($currentUser['role'], ['administrator', 'manager'])): ?>
                <!-- Admin Panel Link -->
                <div class="pt-4 mt-2 border-t border-[#261936]">
                    <span class="px-3 text-[10px] uppercase font-bold tracking-wider text-purple-400">Management</span>
                    <a href="/virtual-time-clock/public/admin/index.php" class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition mt-1">
                        <span>Admin Console</span>
                        <span class="text-[10px] bg-purple-900 text-purple-300 font-semibold px-1.5 py-0.5 rounded">Admin</span>
                    </a>
                    <a href="/virtual-time-clock/public/admin/time-errors.php" class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-300 hover:bg-[#221530] hover:text-white transition mt-1">
                        <span>Time Errors</span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Bottom Items -->
        <div class="p-3 border-t border-[#261936] text-xs space-y-2 text-gray-400">
            <div class="flex items-center gap-4 px-2">
                <a href="/virtual-time-clock/public/placeholder.php?module=Help" class="flex items-center gap-1.5 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Help
                </a>
                <a href="/virtual-time-clock/public/placeholder.php?module=Privacy%20%26%20Compliance" class="flex items-center gap-1.5 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Privacy & Compliance
                </a>
            </div>
            <div class="px-2 text-[11px] text-gray-500">
                Portal v1.0.0
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
