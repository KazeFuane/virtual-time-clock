<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0a0710] text-gray-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; Virtual Time Clock</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo / Brand Card -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white shadow-lg shadow-purple-900/40 mb-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Virtual Time Clock</h1>
            <p class="text-sm text-purple-300/70 mt-1">ByteDance Sahara Employee Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-[#140c1d] border border-[#281b3b] rounded-2xl p-6 sm:p-8 shadow-2xl">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-white">Sign In to Your Workspace</h2>
                <p class="text-xs text-gray-400 mt-0.5">Enter your credentials to record and manage your hours.</p>
            </div>

            <!-- Error Banner -->
            <div id="login-error-box" class="hidden mb-4 p-3 rounded-lg bg-red-950/60 border border-red-800 text-xs text-red-200">
                <span id="login-error-text"></span>
            </div>

            <form id="login-form" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-medium text-gray-300 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3.5 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 transition"
                           placeholder="e.g. employee or admin">
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-gray-300 mb-1.5">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3.5 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 transition"
                           placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                </div>

                <div class="pt-2">
                    <button type="submit" id="btn-login-submit"
                            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-semibold py-2.5 px-4 rounded-lg text-sm shadow-md shadow-purple-900/30 transition disabled:opacity-50">
                        Sign In
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-[#261738] text-center text-xs text-gray-400">
                <p class="font-medium text-gray-300 mb-1">Development Credentials:</p>
                <div class="text-[11px] space-y-0.5 text-gray-400 font-mono">
                    <p>admin / Admin2024!</p>
                    <p>manager / Manager2024!</p>
                    <p>employee / Employee2024!</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-gray-500">
            Virtual Time Clock v1.0.0 &bull; Africa/Casablanca
        </div>
    </div>

    <script>
    document.getElementById('login-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errBox = document.getElementById('login-error-box');
        const errText = document.getElementById('login-error-text');
        const submitBtn = document.getElementById('btn-login-submit');

        errBox.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Signing in...';

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const resp = await fetch('/virtual-time-clock/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ username, password })
            });

            const data = await resp.json();

            if (data.success) {
                window.location.href = '/virtual-time-clock/public/dashboard.php';
            } else {
                errText.textContent = data.message || 'Login failed.';
                errBox.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
            }
        } catch (err) {
            errText.textContent = 'Network or server error. Please try again.';
            errBox.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
        }
    });
    </script>
</body>
</html>
