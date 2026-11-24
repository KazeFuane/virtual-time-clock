<?php
/**
 * Administrator Overview & Attendance Dashboard
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = 'Admin Console &mdash; ClockWise';
$breadcrumbs = [
    ['label' => 'Time Clock', 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => 'Administration', 'url' => '#'],
    ['label' => 'Console', 'url' => '']
];

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide">Administration Console</h1>
            <p class="text-xs text-gray-400 mt-1">Monitor organization staff status, register new employees, and export records.</p>
        </div>

        <div class="flex items-center gap-2">
            <input type="date" id="admin-date-picker" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 transition">
            <button id="btn-export-csv" class="px-3 py-1.5 rounded-lg bg-emerald-700/50 hover:bg-emerald-600/60 border border-emerald-600 text-emerald-200 text-xs font-semibold flex items-center gap-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </button>
            <?php if ($currentUser['role'] === 'administrator'): ?>
                <button onclick="document.getElementById('modal-add-employee').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Employee
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance table -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Attendance Register</h3>
            <input type="text" id="admin-search-input" placeholder="Search staff name or username..." class="bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#160e22] text-gray-400 font-semibold border-b border-[#251738]">
                    <tr>
                        <th class="py-2.5 px-4">Employee</th>
                        <th class="py-2.5 px-4">Role</th>
                        <th class="py-2.5 px-4">Status</th>
                        <th class="py-2.5 px-4">Clock In</th>
                        <th class="py-2.5 px-4">Clock Out</th>
                        <th class="py-2.5 px-4">Work Hours</th>
                        <th class="py-2.5 px-4">Break Hours</th>
                    </tr>
                </thead>
                <tbody id="admin-attendance-tbody" class="divide-y divide-[#231733]">
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">Loading team attendance...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($currentUser['role'] === 'administrator'): ?>
    <!-- User accounts (Admins only) -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">User Accounts</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Passwords are stored encrypted (hashed) and cannot be viewed. Use Reset to set a new one.</p>
            </div>
            <input type="text" id="users-search-input" placeholder="Filter accounts..." class="bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#160e22] text-gray-400 font-semibold border-b border-[#251738]">
                    <tr>
                        <th class="py-2.5 px-4">Name</th>
                        <th class="py-2.5 px-4">Email</th>
                        <th class="py-2.5 px-4">Role</th>
                        <th class="py-2.5 px-4">Status</th>
                        <th class="py-2.5 px-4">Password</th>
                        <th class="py-2.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-tbody" data-current-user-id="<?= (int) $currentUser['id'] ?>" class="divide-y divide-[#231733]">
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Loading accounts...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($currentUser['role'] === 'administrator'): ?>
<!-- Modal: Reset Password -->
<div id="modal-reset-password" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#140c1d] border border-[#2e1d44] rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[#281b3d]">
            <h3 class="text-sm font-bold text-white">Reset Password &mdash; <span id="reset-target-name" class="text-purple-300"></span></h3>
            <button type="button" data-close="modal-reset-password" class="text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="form-reset-password" class="space-y-3">
            <input type="hidden" name="user_id">
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">New Password</label>
                <div class="flex gap-2">
                    <input type="text" name="password" minlength="8" autocomplete="off" class="flex-1 bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-purple-500" placeholder="Leave empty to auto-generate">
                    <button type="button" id="btn-reset-generate" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Generate</button>
                </div>
                <p class="text-[11px] text-gray-500 mt-1">Minimum 8 characters. The user will need this to sign in.</p>
            </div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" data-close="modal-reset-password" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Cancel</button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white shadow">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Account -->
<div id="modal-edit-user" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#140c1d] border border-[#2e1d44] rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[#281b3d]">
            <h3 class="text-sm font-bold text-white">Edit Account</h3>
            <button type="button" data-close="modal-edit-user" class="text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="form-edit-user" class="space-y-3">
            <input type="hidden" name="user_id">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Username</label>
                <input type="text" name="username" required pattern="[A-Za-z0-9_]{3,50}" title="3-50 letters, numbers or underscores" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-purple-500">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Email</label>
                <input type="email" name="email" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Role</label>
                    <select name="role_id" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white capitalize focus:outline-none focus:border-purple-500 disabled:opacity-50"></select>
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Status</label>
                    <select name="is_active" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 disabled:opacity-50">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <p id="edit-self-note" class="hidden text-[11px] text-gray-500">You can't change your own role or status.</p>
            <div class="pt-2 flex justify-between gap-2">
                <button type="button" id="btn-edit-to-reset" class="px-3 py-1.5 rounded-lg bg-amber-900/40 hover:bg-amber-800/60 border border-amber-800 text-amber-200 text-xs">Reset password&hellip;</button>
                <div class="flex gap-2">
                    <button type="button" data-close="modal-edit-user" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white shadow">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Credentials (shown once after create / reset) -->
<div id="modal-credentials" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#140c1d] border border-emerald-800 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <h3 class="text-sm font-bold text-white" id="credentials-title">Account credentials</h3>
        <div class="bg-[#0f0a17] border border-[#2d1e44] rounded-lg p-3 space-y-2 font-mono text-xs">
            <div class="flex justify-between gap-3"><span class="text-gray-500">Username</span><span id="credentials-username" class="text-white"></span></div>
            <div class="flex justify-between gap-3 items-center"><span class="text-gray-500">Password</span><span id="credentials-password" class="text-emerald-300 select-all break-all"></span></div>
        </div>
        <p class="text-[11px] text-amber-300">Copy this password now and give it to the user. For security it is not stored in readable form and will not be shown again.</p>
        <div class="flex justify-end gap-2">
            <button type="button" id="btn-copy-credentials" class="px-3 py-1.5 rounded-lg bg-emerald-700/60 hover:bg-emerald-600/70 border border-emerald-600 text-xs font-semibold text-emerald-100">Copy</button>
            <button type="button" data-close="modal-credentials" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white">Done</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Add Employee (Admins only) -->
<div id="modal-add-employee" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#140c1d] border border-[#2e1d44] rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[#281b3d]">
            <h3 class="text-sm font-bold text-white">Create New Employee Account</h3>
            <button onclick="document.getElementById('modal-add-employee').classList.add('hidden')" class="text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="form-create-employee" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Username</label>
                <input type="text" name="username" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Email</label>
                <input type="email" name="email" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Password</label>
                <div class="flex gap-2">
                    <input type="text" name="password" minlength="8" autocomplete="off" class="flex-1 bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-purple-500" placeholder="Leave empty to auto-generate">
                    <button type="button" id="btn-create-generate" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Generate</button>
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Role</label>
                <select name="role_id" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                    <option value="1">Employee</option>
                    <option value="2">Manager</option>
                    <option value="3">Administrator</option>
                </select>
            </div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-employee').classList.add('hidden')" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Cancel</button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white shadow">Register Employee</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = '
<script>
let adminSelectedDate = new Date().toISOString().split("T")[0];

document.addEventListener("DOMContentLoaded", () => {
    const picker = document.getElementById("admin-date-picker");
    picker.value = adminSelectedDate;

    picker.addEventListener("change", (e) => {
        adminSelectedDate = e.target.value;
        loadAdminAttendance();
    });

    const searchInput = document.getElementById("admin-search-input");
    let debounceTimer;
    searchInput.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadAdminAttendance, 300);
    });

    document.getElementById("btn-export-csv").addEventListener("click", () => {
        const q = encodeURIComponent(searchInput.value.trim());
        window.location.href = `/virtual-time-clock/api/admin/attendance.php?date=${adminSelectedDate}&search=${q}&format=csv`;
    });

    const form = document.getElementById("form-create-employee");
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            data.role_id = parseInt(data.role_id, 10);

            if (!data.password) {
                delete data.password;
                data.generate_password = true;
            }

            const res = await apiRequest("/virtual-time-clock/api/admin/users.php", "POST", data);
            if (res.success) {
                showToast("Account created successfully", "success");
                document.getElementById("modal-add-employee").classList.add("hidden");
                form.reset();
                showCredentials("New account created", res.data.username, res.data.password);
                loadAdminAttendance();
                loadUsers();
            } else {
                showToast(res.message, "error");
            }
        });
    }

    initUserManagement();
    loadAdminAttendance();
});

/* ---------- User account management (admins only) ---------- */

let allUsers = [];

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"\x27]/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "\x27": "&#39;"}[c]));
}

function generateClientPassword(length = 12) {
    const sets = ["ABCDEFGHJKLMNPQRSTUVWXYZ", "abcdefghijkmnpqrstuvwxyz", "23456789", "!@#$%&*?"];
    const all = sets.join("");
    const rand = (max) => crypto.getRandomValues(new Uint32Array(1))[0] % max;
    const chars = sets.map(s => s[rand(s.length)]);
    while (chars.length < length) chars.push(all[rand(all.length)]);
    for (let i = chars.length - 1; i > 0; i--) {
        const j = rand(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join("");
}

function showCredentials(title, username, password) {
    document.getElementById("credentials-title").textContent = title;
    document.getElementById("credentials-username").textContent = username;
    document.getElementById("credentials-password").textContent = password;
    document.getElementById("modal-credentials").classList.remove("hidden");
}

function initUserManagement() {
    const tbody = document.getElementById("users-tbody");
    if (!tbody) return; // not an administrator

    document.querySelectorAll("[data-close]").forEach(btn => {
        btn.addEventListener("click", () => document.getElementById(btn.dataset.close).classList.add("hidden"));
    });

    document.getElementById("btn-create-generate").addEventListener("click", () => {
        document.querySelector("#form-create-employee [name=password]").value = generateClientPassword();
    });
    document.getElementById("btn-reset-generate").addEventListener("click", () => {
        document.querySelector("#form-reset-password [name=password]").value = generateClientPassword();
    });

    document.getElementById("btn-copy-credentials").addEventListener("click", async () => {
        const u = document.getElementById("credentials-username").textContent;
        const p = document.getElementById("credentials-password").textContent;
        try {
            await navigator.clipboard.writeText(`Username: ${u}\nPassword: ${p}`);
            showToast("Credentials copied to clipboard", "success");
        } catch (e) {
            showToast("Copy failed - select the password and copy it manually", "warning");
        }
    });

    document.getElementById("users-search-input").addEventListener("input", renderUsers);

    tbody.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;
        const user = allUsers.find(u => String(u.id) === btn.dataset.id);
        if (!user) return;

        if (btn.dataset.action === "edit") {
            openEditModal(user);
        } else if (btn.dataset.action === "reset") {
            openResetModal(user);
        } else if (btn.dataset.action === "toggle") {
            const activate = !Number(user.is_active);
            if (!activate && !confirm(`Deactivate @${user.username}? They will no longer be able to sign in.`)) return;
            const res = await apiRequest("/virtual-time-clock/api/admin/users.php", "PATCH", {id: user.id, is_active: activate ? 1 : 0});
            showToast(res.success ? (activate ? "Account activated" : "Account deactivated") : res.message, res.success ? "success" : "error");
            if (res.success) loadUsers();
        }
    });

    document.getElementById("btn-edit-to-reset").addEventListener("click", () => {
        const id = document.getElementById("form-edit-user").user_id.value;
        const user = allUsers.find(u => String(u.id) === id);
        document.getElementById("modal-edit-user").classList.add("hidden");
        if (user) openResetModal(user);
    });

    document.getElementById("form-edit-user").addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.target;
        const payload = {
            id: parseInt(form.user_id.value, 10),
            first_name: form.first_name.value.trim(),
            last_name: form.last_name.value.trim(),
            username: form.username.value.trim(),
            email: form.email.value.trim(),
        };
        if (!form.role_id.disabled) payload.role_id = parseInt(form.role_id.value, 10);
        if (!form.is_active.disabled) payload.is_active = parseInt(form.is_active.value, 10);

        const res = await apiRequest("/virtual-time-clock/api/admin/users.php", "PATCH", payload);
        if (res.success) {
            showToast("Account updated", "success");
            document.getElementById("modal-edit-user").classList.add("hidden");
            loadUsers();
            loadAdminAttendance();
        } else {
            showToast(res.message, "error");
        }
    });

    document.getElementById("form-reset-password").addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.target;
        const payload = {id: parseInt(form.user_id.value, 10), action: "reset_password"};
        if (form.password.value) payload.password = form.password.value;

        const res = await apiRequest("/virtual-time-clock/api/admin/users.php", "PATCH", payload);
        if (res.success) {
            const user = allUsers.find(u => u.id == payload.id);
            document.getElementById("modal-reset-password").classList.add("hidden");
            showCredentials("Password reset", user ? user.username : "", res.data.password);
        } else {
            showToast(res.message, "error");
        }
    });

    loadUsers();
}

let allRoles = [];

function openResetModal(user) {
    const form = document.getElementById("form-reset-password");
    form.reset();
    form.user_id.value = user.id;
    document.getElementById("reset-target-name").textContent = `${user.first_name} ${user.last_name} (@${user.username})`;
    document.getElementById("modal-reset-password").classList.remove("hidden");
}

function openEditModal(user) {
    const form = document.getElementById("form-edit-user");
    const isSelf = String(user.id) === document.getElementById("users-tbody").dataset.currentUserId;

    form.role_id.innerHTML = allRoles.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join("");
    form.user_id.value = user.id;
    form.first_name.value = user.first_name;
    form.last_name.value = user.last_name;
    form.username.value = user.username;
    form.email.value = user.email;
    form.role_id.value = user.role_id;
    form.is_active.value = Number(user.is_active) ? "1" : "0";
    form.role_id.disabled = isSelf;
    form.is_active.disabled = isSelf;
    document.getElementById("edit-self-note").classList.toggle("hidden", !isSelf);
    document.getElementById("modal-edit-user").classList.remove("hidden");
}

async function loadUsers() {
    const tbody = document.getElementById("users-tbody");
    if (!tbody) return;
    const res = await apiRequest("/virtual-time-clock/api/admin/users.php");
    if (!res.success) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-rose-400">${escapeHtml(res.message)}</td></tr>`;
        return;
    }
    allUsers = res.data.users || [];
    allRoles = res.data.roles || [];
    renderUsers();
}

function renderUsers() {
    const tbody = document.getElementById("users-tbody");
    const q = document.getElementById("users-search-input").value.trim().toLowerCase();
    const list = allUsers.filter(u =>
        !q || `${u.first_name} ${u.last_name} ${u.username} ${u.email} ${u.role}`.toLowerCase().includes(q)
    );

    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-gray-500">No accounts found.</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(u => {
        const active = Number(u.is_active) === 1;
        const status = active
            ? `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">ACTIVE</span>`
            : `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-800 text-gray-400">INACTIVE</span>`;
        return `
            <tr class="hover:bg-[#1a1226]/50">
                <td class="py-2.5 px-4">
                    <div class="font-semibold text-white">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</div>
                    <div class="text-[11px] text-gray-500 font-mono">@${escapeHtml(u.username)}</div>
                </td>
                <td class="py-2.5 px-4 text-gray-300">${escapeHtml(u.email)}</td>
                <td class="py-2.5 px-4 text-gray-400 capitalize">${escapeHtml(u.role)}</td>
                <td class="py-2.5 px-4">${status}</td>
                <td class="py-2.5 px-4 font-mono text-gray-500" title="Stored as a secure hash - cannot be displayed">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</td>
                <td class="py-2.5 px-4 text-right whitespace-nowrap">
                    <button data-action="edit" data-id="${u.id}" class="px-2.5 py-1 rounded-md bg-purple-900/40 hover:bg-purple-800/60 border border-purple-800 text-purple-200 text-[11px] font-semibold">Edit</button>
                    <button data-action="reset" data-id="${u.id}" class="ml-1 px-2.5 py-1 rounded-md bg-amber-900/40 hover:bg-amber-800/60 border border-amber-800 text-amber-200 text-[11px] font-semibold">Reset password</button>
                    <button data-action="toggle" data-id="${u.id}" class="ml-1 px-2.5 py-1 rounded-md bg-[#221532] hover:bg-[#2c1c3f] text-gray-300 text-[11px]">${active ? "Deactivate" : "Activate"}</button>
                </td>
            </tr>`;
    }).join("");
}

async function loadAdminAttendance() {
    const q = encodeURIComponent(document.getElementById("admin-search-input").value.trim());
    const res = await apiRequest(`/virtual-time-clock/api/admin/attendance.php?date=${adminSelectedDate}&search=${q}`);
    const tbody = document.getElementById("admin-attendance-tbody");

    if (!res.success || !res.data || !res.data.records || res.data.records.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-xs text-gray-500">No staff records found for this date.</td></tr>`;
        return;
    }

    let rows = "";
    res.data.records.forEach(r => {
        const name = `${r.first_name} ${r.last_name}`;
        const workHrs = (r.total_work_seconds ? (r.total_work_seconds / 3600).toFixed(2) : "0.00") + " h";
        const breakHrs = (r.total_break_seconds ? (r.total_break_seconds / 3600).toFixed(2) : "0.00") + " h";

        let statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-800 text-gray-400">NOT STARTED</span>`;
        if (r.status === "WORKING") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">WORKING</span>`;
        } else if (r.status === "ON_BREAK") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">ON BREAK</span>`;
        } else if (r.status === "FINISHED") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-950 text-purple-300 border border-purple-800">FINISHED</span>`;
        }

        rows += `
            <tr class="border-b border-[#231733] hover:bg-[#1a1226]/50">
                <td class="py-2.5 px-4">
                    <div class="font-semibold text-white">${name}</div>
                    <div class="text-[11px] text-gray-500 font-mono">@${r.username}</div>
                </td>
                <td class="py-2.5 px-4 text-gray-400 capitalize">${r.role}</td>
                <td class="py-2.5 px-4">${statusBadge}</td>
                <td class="py-2.5 px-4 font-mono text-gray-300">${r.start_time || "--:--"}</td>
                <td class="py-2.5 px-4 font-mono text-gray-300">${r.end_time || "--:--"}</td>
                <td class="py-2.5 px-4 font-mono font-semibold text-emerald-400">${workHrs}</td>
                <td class="py-2.5 px-4 font-mono text-amber-400">${breakHrs}</td>
            </tr>
        `;
    });
    tbody.innerHTML = rows;
}
</script>
';

require_once ROOT_PATH . '/views/layouts/main.php';
