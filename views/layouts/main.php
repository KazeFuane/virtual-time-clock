<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0914] text-gray-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        sidebar: {
                            DEFAULT: '#140c1d',
                            hover: '#221530',
                            active: '#2e1c42',
                            text: '#9f9bb0'
                        },
                        app: {
                            bg: '#0d0914',
                            card: '#161022',
                            border: '#281d38'
                        },
                        brand: {
                            DEFAULT: '#7c3aed',
                            hover: '#6d28d9'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Courier New', 'monospace']
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0d0914; }
        ::-webkit-scrollbar-thumb { background: #281d38; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #3b2a54; }
    </style>
</head>
<body class="h-full flex overflow-hidden antialiased">
    <!-- Toast notifications -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Sidebar -->
    <?php include ROOT_PATH . '/views/partials/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#0d0914]">
        <!-- Header -->
        <?php include ROOT_PATH . '/views/partials/header.php'; ?>

        <!-- Content scrollable view -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <?= $pageContent ?? '' ?>
        </main>
    </div>

    <!-- Core App JS -->
    <script src="/virtual-time-clock/public/assets/js/app.js"></script>
    <?= $pageScripts ?? '' ?>
</body>
</html>
