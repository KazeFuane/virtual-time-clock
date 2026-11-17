// Live Clock, Timers and Attendance UI Controller

let timerInterval = null;
let currentAttendance = null;
let timeOffsetMs = 0; // Server-Client sync offset

document.addEventListener('DOMContentLoaded', () => {
    initClock();
    loadTodayAttendance();
    bindActionButtons();
});

// Synchronize with server time
function initClock() {
    updateClockDisplay();
    setInterval(updateClockDisplay, 1000);
}

function updateClockDisplay() {
    const now = new Date(Date.now() + timeOffsetMs);
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    const clockEl = document.getElementById('digital-clock');
    if (clockEl) {
        clockEl.textContent = `${hours}:${minutes}:${seconds}`;
    }

    // Update stopwatch needle angle if analog stopwatch is shown
    const stopwatchNeedle = document.getElementById('stopwatch-needle');
    if (stopwatchNeedle) {
        const secVal = now.getSeconds() + (now.getMilliseconds() / 1000);
        const deg = (secVal / 60) * 360;
        stopwatchNeedle.style.transform = `rotate(${deg}deg)`;
    }
}

// Format seconds into HH:MM:SS
function formatDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    const h = String(Math.floor(sec / 3600)).padStart(2, '0');
    const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
    const s = String(sec % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

// Fetch today's attendance state
async function loadTodayAttendance() {
    const res = await apiRequest('/virtual-time-clock/api/attendance/today.php');
    if (res.success && res.data) {
        if (res.server_time) {
            const serverMs = new Date(res.server_time).getTime();
            timeOffsetMs = serverMs - Date.now();
        }
        currentAttendance = res.data;
        renderAttendanceUI(res.data);
    } else {
        showToast(res.message || 'Failed to load attendance info', 'error');
    }
}

// Render Status, Metrics, and Action Buttons
function renderAttendanceUI(att) {
    const status = att.status; // 'NOT_STARTED', 'WORKING', 'ON_BREAK', 'FINISHED'

    // Status Badge & Texts
    const statusPill = document.getElementById('status-badge');
    const statusHint = document.getElementById('status-hint');
    const startValEl = document.getElementById('val-start-time');
    const workValEl = document.getElementById('val-work-time');
    const breakValEl = document.getElementById('val-break-time');

    if (startValEl) {
        startValEl.textContent = att.start_time || '--:--:--';
    }

    // Render Punctuality Status (Delay or Absence)
    const punctualityEl = document.getElementById('val-punctuality');
    if (punctualityEl) {
        if (att.punctuality) {
            const p = att.punctuality;
            if (p.punctuality === 'LATE' || p.punctuality === 'DELAYED_NOT_IN') {
                punctualityEl.innerHTML = `<span class="text-rose-400 font-bold">Delay: ${p.delay_text}</span>`;
            } else if (p.punctuality === 'ABSENT') {
                punctualityEl.innerHTML = `<span class="text-rose-500 font-bold uppercase">Absent</span>`;
            } else if (p.punctuality === 'ON_TIME') {
                punctualityEl.innerHTML = `<span class="text-emerald-400 font-bold">On Time &check;</span>`;
            } else {
                punctualityEl.innerHTML = `<span class="text-gray-400 font-medium">Shift Upcoming</span>`;
            }
        } else {
            punctualityEl.textContent = '--';
        }
    }

    // Set Status text
    if (statusPill && statusHint) {
        if (status === 'NOT_STARTED') {
            statusPill.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-gray-800 text-gray-300 border border-gray-700';
            statusPill.textContent = 'NOT STARTED';
            statusHint.textContent = 'Ready to begin your shift.';
        } else if (status === 'WORKING') {
            statusPill.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-300 border border-emerald-700 animate-pulse';
            statusPill.textContent = 'WORKING';
            statusHint.textContent = 'You are currently logged in and working.';
        } else if (status === 'ON_BREAK') {
            statusPill.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-amber-950 text-amber-300 border border-amber-700';
            statusPill.textContent = 'ON BREAK';
            statusHint.textContent = 'You are currently on break (max 45 min daily allowance).';
        } else if (status === 'FINISHED') {
            statusPill.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-purple-950 text-purple-300 border border-purple-700';
            statusPill.textContent = 'FINISHED';
            statusHint.textContent = 'Your workday is complete. See you tomorrow!';
        }
    }

    // Button states matching screenshot buttons
    const btnStartWork = document.getElementById('btn-start-work');
    const btnEndWork = document.getElementById('btn-end-work');
    const btnStartBreak = document.getElementById('btn-start-break');
    const btnEndBreak = document.getElementById('btn-end-break');

    // Reset all buttons to disabled first
    [btnStartWork, btnEndWork, btnStartBreak, btnEndBreak].forEach(btn => {
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-40', 'cursor-not-allowed');
            btn.classList.remove('hover:scale-[1.02]', 'hover:brightness-110');
        }
    });

    // Enable appropriate buttons according to Attendance State Machine:
    // NOT_STARTED -> [Start Workday]
    // WORKING -> [End Workday], [Start Break] (if total break time < 45 minutes / 2700s)
    // ON_BREAK -> [End Break]
    // FINISHED -> [Start Workday] enabled (allows resuming shift if ended by mistake)
    const maxBreakSeconds = 45 * 60; // 2700s
    const remainingBreak = Math.max(0, maxBreakSeconds - (att.total_break_seconds || 0));

    // Update Start button text if user has already worked today
    const btnStartText = btnStartWork ? btnStartWork.querySelector('span') : null;
    if (btnStartText) {
        btnStartText.textContent = (status === 'FINISHED') ? 'Resume Workday' : 'Start Workday';
    }

    if (status === 'NOT_STARTED') {
        enableButton(btnStartWork);
    } else if (status === 'WORKING') {
        enableButton(btnEndWork);
        if (remainingBreak > 0) {
            enableButton(btnStartBreak);
        }
    } else if (status === 'ON_BREAK') {
        enableButton(btnEndBreak);
    } else if (status === 'FINISHED') {
        enableButton(btnStartWork); // Can resume shift if ended accidentally
    }

    // Setup Live Timers
    if (timerInterval) clearInterval(timerInterval);

    let currentWorkSeconds = att.total_work_seconds || 0;
    let currentBreakSeconds = att.total_break_seconds || 0;
    let lastTick = Date.now();

    timerInterval = setInterval(() => {
        const nowMs = Date.now();
        const delta = (nowMs - lastTick) / 1000;
        lastTick = nowMs;

        if (status === 'WORKING') {
            currentWorkSeconds += delta;
        } else if (status === 'ON_BREAK') {
            currentBreakSeconds += delta;
        }

        if (workValEl) workValEl.textContent = formatDuration(currentWorkSeconds);
        if (breakValEl) breakValEl.textContent = formatDuration(currentBreakSeconds);
    }, 1000);

    // Initial print
    if (workValEl) workValEl.textContent = formatDuration(currentWorkSeconds);
    if (breakValEl) breakValEl.textContent = formatDuration(currentBreakSeconds);

    // Render Today's Actions table
    renderActionsTable(att.actions || []);
}

function enableButton(btn) {
    if (!btn) return;
    btn.disabled = false;
    btn.classList.remove('opacity-40', 'cursor-not-allowed');
    btn.classList.add('hover:scale-[1.02]', 'hover:brightness-110', 'cursor-pointer');
}

function renderActionsTable(actions) {
    const tbody = document.getElementById('today-actions-tbody');
    if (!tbody) return;

    if (actions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="2" class="py-4 text-center text-xs text-gray-500">No actions recorded today yet.</td></tr>`;
        return;
    }

    const actionLabels = {
        'START_WORKDAY': { label: 'Start Workday', color: 'text-emerald-400', badge: 'WORKING', badgeColor: 'bg-emerald-950 text-emerald-300 border-emerald-800' },
        'END_WORKDAY':   { label: 'End Workday', color: 'text-rose-400', badge: 'FINISHED', badgeColor: 'bg-purple-950 text-purple-300 border-purple-800' },
        'START_BREAK':   { label: 'Start Break', color: 'text-purple-400', badge: 'ON BREAK', badgeColor: 'bg-amber-950 text-amber-300 border-amber-800' },
        'END_BREAK':     { label: 'End Break', color: 'text-amber-400', badge: 'WORKING', badgeColor: 'bg-emerald-950 text-emerald-300 border-emerald-800' }
    };

    let html = '';
    actions.forEach(a => {
        const meta = actionLabels[a.action_type] || { label: a.action_type, color: 'text-gray-300', badge: 'EVENT', badgeColor: 'bg-gray-800 text-gray-300 border-gray-700' };
        html += `
            <tr class="border-b border-[#231733] hover:bg-[#1a1226]/50 transition">
                <td class="py-2.5 px-4 font-mono text-xs text-gray-300">${a.action_time}</td>
                <td class="py-2.5 px-4 text-xs font-semibold ${meta.color}">${meta.label}</td>
                <td class="py-2.5 px-4 text-right">
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border ${meta.badgeColor}">
                        ${meta.badge}
                    </span>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// Bind attendance action triggers
function bindActionButtons() {
    const bindBtn = (id, endpoint) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', async () => {
            el.disabled = true;
            const res = await apiRequest(endpoint, 'POST', {});
            if (res.success) {
                showToast(res.message, 'success');
                loadTodayAttendance();
            } else {
                showToast(res.message, 'error');
                el.disabled = false;
            }
        });
    };

    bindBtn('btn-start-work',  '/virtual-time-clock/api/attendance/start-workday.php');
    bindBtn('btn-end-work',    '/virtual-time-clock/api/attendance/end-workday.php');
    bindBtn('btn-start-break', '/virtual-time-clock/api/attendance/start-break.php');
    bindBtn('btn-end-break',   '/virtual-time-clock/api/attendance/end-break.php');
}
