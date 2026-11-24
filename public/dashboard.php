<?php
/**
 * Employee Dashboard Controller
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();

$pageTitle = 'Time Clock &mdash; ClockWise';
$breadcrumbs = [
    ['label' => 'Time Clock', 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => 'Time Management', 'url' => '#'],
    ['label' => 'Virtual Time Clock', 'url' => '']
];

ob_start();
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Status Banner matching reference screenshot -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex p-1 rounded-full bg-purple-900/50 text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h1 class="text-xl font-bold text-white tracking-wide">Virtual Time Clock</h1>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                You are currently logged in. <span id="status-hint" class="text-purple-300"></span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span id="status-badge" class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-800 text-gray-300 border border-gray-700">
                LOADING...
            </span>
            <div class="text-right">
                <div class="text-[11px] text-gray-500">Casablanca Local Time</div>
                <div id="digital-clock" class="text-lg font-mono font-bold text-purple-200">--:--:--</div>
            </div>
        </div>
    </div>

    <!-- Central Stopwatch Visual (matching reference screenshot) -->
    <div class="flex flex-col items-center justify-center pt-2 pb-6">
        <div class="relative w-48 h-48 sm:w-56 sm:h-56 flex items-center justify-center">
            <!-- Stopwatch SVG graphic styled to match screenshot exactly -->
            <svg class="w-full h-full drop-shadow-[0_10px_25px_rgba(124,58,237,0.15)]" viewBox="0 0 200 200" fill="none">
                <!-- Top Button & Ring -->
                <circle cx="100" cy="22" r="14" stroke="#94a3b8" stroke-width="4" fill="none"/>
                <rect x="94" y="28" width="12" height="12" rx="2" fill="#94a3b8"/>
                <rect x="145" y="42" width="10" height="8" rx="2" fill="#64748b" transform="rotate(35 145 42)"/>

                <!-- Outer Stopwatch Case -->
                <circle cx="100" cy="115" r="75" fill="#1b1226" stroke="#475569" stroke-width="6"/>
                <!-- Inner Bezel Dial -->
                <circle cx="100" cy="115" r="68" fill="#e2e8f0"/>

                <!-- Dial Markers & Numbers -->
                <circle cx="100" cy="115" r="64" stroke="#0f172a" stroke-width="1" stroke-dasharray="2 6" fill="none"/>
                <!-- Numbers 60, 15, 30, 45 -->
                <text x="100" y="65" text-anchor="middle" font-size="8" font-family="monospace" font-weight="bold" fill="#334155">60</text>
                <text x="154" y="118" text-anchor="middle" font-size="8" font-family="monospace" font-weight="bold" fill="#334155">15</text>
                <text x="100" y="172" text-anchor="middle" font-size="8" font-family="monospace" font-weight="bold" fill="#334155">30</text>
                <text x="46" y="118" text-anchor="middle" font-size="8" font-family="monospace" font-weight="bold" fill="#334155">45</text>

                <!-- Minute hash marks -->
                <text x="128" y="73" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">5</text>
                <text x="148" y="93" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">10</text>
                <text x="148" y="142" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">20</text>
                <text x="128" y="162" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">25</text>
                <text x="72" y="162" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">35</text>
                <text x="52" y="142" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">40</text>
                <text x="52" y="93" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">50</text>
                <text x="72" y="73" text-anchor="middle" font-size="6" font-family="monospace" fill="#64748b">55</text>

                <!-- Central Pivot -->
                <circle cx="100" cy="115" r="4" fill="#0f172a"/>

                <!-- Stopwatch Needle (animated with JS) -->
                <line id="stopwatch-needle" x1="100" y1="115" x2="100" y2="65" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" style="transform-origin: 100px 115px; transition: transform 0.2s cubic-bezier(0.4, 2.08, 0.55, 0.44);"/>
            </svg>
        </div>

        <!-- Metric KPI badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-4 w-full max-w-2xl text-center">
            <div class="bg-[#170e22] border border-[#2e1d44] rounded-xl p-3 shadow-md">
                <div class="text-[11px] text-gray-400 uppercase font-medium tracking-wider">Scheduled Shifts</div>
                <div class="text-xs font-semibold text-purple-300 mt-1">08:00-12:00 &bull; 14:00-18:00</div>
            </div>
            <div class="bg-[#170e22] border border-[#2e1d44] rounded-xl p-3 shadow-md">
                <div class="text-[11px] text-gray-400 uppercase font-medium tracking-wider">Shift Punctuality</div>
                <div id="val-punctuality" class="text-xs font-bold text-gray-300 mt-1">--</div>
            </div>
            <div class="bg-[#170e22] border border-[#2e1d44] rounded-xl p-3 shadow-md">
                <div class="text-[11px] text-emerald-400 uppercase font-medium tracking-wider">Working Time</div>
                <div id="val-work-time" class="font-mono text-sm sm:text-base font-bold text-emerald-300 mt-0.5">00:00:00</div>
            </div>
            <div class="bg-[#170e22] border border-[#2e1d44] rounded-xl p-3 shadow-md">
                <div class="text-[11px] text-amber-400 uppercase font-medium tracking-wider">Break (Max 45m)</div>
                <div id="val-break-time" class="font-mono text-sm sm:text-base font-bold text-amber-300 mt-0.5">00:00:00</div>
            </div>
        </div>
    </div>

    <!-- Attendance Action Buttons (Matching Screenshot Colors & Order) -->
    <!-- Green (Start Workday) | Red (End Workday) | Purple (Start Break) | Orange (End Break) -->
    <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4 pt-2 pb-8">
        <!-- Start Workday (Green) -->
        <button id="btn-start-work" type="button" class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-xs sm:text-sm text-white bg-emerald-600 hover:bg-emerald-500 shadow-lg shadow-emerald-950/40 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            <span>Start Workday</span>
        </button>

        <!-- End Workday (Red) -->
        <button id="btn-end-work" type="button" class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-xs sm:text-sm text-white bg-rose-600 hover:bg-rose-500 shadow-lg shadow-rose-950/40 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>End Workday</span>
        </button>

        <!-- Start Break (Purple / Violet) -->
        <button id="btn-start-break" type="button" class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-xs sm:text-sm text-white bg-purple-700 hover:bg-purple-600 shadow-lg shadow-purple-950/40 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Start Break</span>
        </button>

        <!-- End Break (Orange / Amber) -->
        <button id="btn-end-break" type="button" class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-xs sm:text-sm text-white bg-amber-600 hover:bg-amber-500 shadow-lg shadow-amber-950/40 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            <span>End Break</span>
        </button>
    </div>

    <!-- My History Section (Matching screenshot bottom table) -->
    <div class="pt-6 border-t border-[#251738]">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h2 class="text-base font-semibold text-white">My History</h2>
            </div>
            <a href="/virtual-time-clock/public/history.php" class="text-xs text-purple-400 hover:text-purple-300 font-medium transition flex items-center gap-1">
                <span>View Full History</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <!-- Centered Date Pill matching screenshot style -->
        <div class="flex justify-center mb-4">
            <div class="px-6 py-1.5 rounded-lg bg-[#180e25] border border-[#2f1c46] text-xs font-mono font-medium text-gray-300">
                <?= (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('d/m/Y') ?>
            </div>
        </div>

        <!-- History actions table -->
        <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden max-w-2xl mx-auto shadow-lg">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#1c1228] text-gray-400 font-semibold border-b border-[#251738]">
                    <tr>
                        <th class="py-2.5 px-4">Action Time</th>
                        <th class="py-2.5 px-4">Action</th>
                        <th class="py-2.5 px-4 text-right">Session Status</th>
                    </tr>
                </thead>
                <tbody id="today-actions-tbody" class="divide-y divide-[#231733]">
                    <tr>
                        <td colspan="3" class="py-6 text-center text-gray-500">
                            Loading today's logs...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = '
<script src="/virtual-time-clock/public/assets/js/clock.js"></script>
';

require_once ROOT_PATH . '/views/layouts/main.php';
