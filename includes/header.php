<?php
// header ini di-include di semua halaman: sekalian load koneksi db, navbar, sama flash message

require_once __DIR__ . '/../config/database.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$activeTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . 'MyEventKu'
    : 'MyEventKu - Sistem Informasi Manajemen Event & Peserta';

$flashMessage = getFlash();

$navItems = [
    ['file' => 'index.php',        'label' => 'Dashboard',           'icon' => 'fa-chart-pie'],
    ['file' => 'events.php',       'label' => 'Manajemen event',     'icon' => 'fa-calendar-days'],
    ['file' => 'participants.php', 'label' => 'Pendaftaran peserta', 'icon' => 'fa-users'],
    ['file' => 'checkin.php',      'label' => 'Check-in cepat',      'icon' => 'fa-qrcode'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($activeTitle ?? '', ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    }
                }
            }
        };
    </script>

    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800 antialiased">

    <!-- Navbar (hilang pas print) -->
    <header class="sticky top-0 z-40 bg-white border-b border-slate-300 print:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-md bg-slate-900 text-white flex items-center justify-center">
                        <i class="fa-solid fa-ticket text-base"></i>
                    </div>
                    <div>
                        <span class="block text-base font-bold tracking-tight text-slate-900 leading-tight">
                            MyEventKu
                        </span>
                        <span class="block text-[11px] font-medium text-slate-600">
                            Sistem Informasi Manajemen Event &amp; Pendaftaran Peserta
                        </span>
                    </div>
                </a>

                <!-- Navigasi Desktop -->
                <nav class="hidden md:flex items-center gap-1" aria-label="Menu Utama">
                    <?php foreach ($navItems as $item): ?>
                        <?php $isActive = ($currentPage === $item['file']); ?>
                        <a href="<?= htmlspecialchars($item['file'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $isActive
                               ? 'bg-slate-900 text-white'
                               : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <i class="fa-solid <?= htmlspecialchars($item['icon'] ?? '', ENT_QUOTES, 'UTF-8') ?> text-xs"></i>
                            <span><?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Tombol Menu Mobile -->
                <div class="flex items-center md:hidden">
                    <button type="button"
                            id="mobileMenuBtn"
                            onclick="toggleMobileMenu()"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100"
                            aria-label="Buka menu navigasi">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Menu Mobile -->
            <div id="mobileMenu" class="hidden md:hidden pb-4 pt-2 border-t border-slate-200 space-y-1">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($currentPage === $item['file']); ?>
                    <a href="<?= htmlspecialchars($item['file'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium <?= $isActive
                           ? 'bg-slate-900 text-white'
                           : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="fa-solid <?= htmlspecialchars($item['icon'] ?? '', ENT_QUOTES, 'UTF-8') ?> w-4 text-center"></i>
                        <span><?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <!-- Konten -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <?php if ($flashMessage !== null): ?>
            <?php
                $isSuccess = ($flashMessage['type'] ?? '') === 'success';
                $alertClass = $isSuccess
                    ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                    : 'bg-rose-50 border-rose-300 text-rose-800';
                $iconClass = $isSuccess
                    ? 'fa-circle-check text-emerald-700'
                    : 'fa-circle-exclamation text-rose-700';
                $titleText = $isSuccess ? 'Berhasil' : 'Perhatian';
            ?>
            <!-- Flash Message -->
            <div id="flashBanner"
                 class="mb-6 flex items-start justify-between gap-3 p-4 rounded-md border print:hidden <?= $alertClass ?>"
                 role="alert">
                <div class="flex items-start gap-3">
                    <i class="fa-solid <?= $iconClass ?> text-base mt-0.5"></i>
                    <div class="text-sm">
                        <p class="font-semibold"><?= htmlspecialchars($titleText ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-0.5"><?= htmlspecialchars($flashMessage['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('flashBanner').remove()"
                        class="p-1 rounded-md opacity-70 hover:opacity-100 transition-opacity"
                        aria-label="Tutup notifikasi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        <?php endif; ?>
