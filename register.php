<?php
// halaman daftar akun baru dengan validasi input, cek username ganda, dan password_hash

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

// kalau sudah login sebelumnya, langsung arahkan ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';
$username     = '';

// proses pendaftaran akun baru saat form disubmit lewat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim((string) ($_POST['username'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '' || $confirmPassword === '') {
        $errorMessage = 'Semua kolom pendaftaran akun wajib diisi.';
    } elseif (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errorMessage = 'Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, atau garis bawah (_).';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            // cek dulu apakah username sudah dipakai biar nggak bentrok sama UNIQUE KEY
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $stmtCheck->execute(['username' => $username]);
            $usernameExists = (int) $stmtCheck->fetchColumn() > 0;

            if ($usernameExists) {
                $errorMessage = 'Username sudah digunakan, silakan pilih username lain.';
            } else {
                // hash password pakai password_hash() sebelum disimpan dan kasih role default 'user'
                $stmtInsert = $pdo->prepare('
                    INSERT INTO users (username, password_hash, role)
                    VALUES (:username, :password_hash, :role)
                ');
                $stmtInsert->execute([
                    'username'      => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => 'user',
                ]);

                // simpan notif sukses ke session lalu arahkan ke halaman login
                setFlash('success', 'Pendaftaran akun berhasil! Silakan login dengan akun baru Anda.');
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            // tangani duplicate constraint (SQLSTATE 23000) atau gangguan database dengan aman
            if ($e->getCode() === '23000') {
                $errorMessage = 'Username sudah digunakan, silakan pilih username lain.';
            } else {
                $errorMessage = 'Terjadi gangguan saat menyimpan pendaftaran akun ke database.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - MyEventKu</title>

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

    <!-- Konten Utama Halaman Register -->
    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md bg-white border border-slate-300 rounded-md shadow-none p-6 sm:p-8">
            <!-- Header Identitas Aplikasi -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-md bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-user-plus text-base" aria-hidden="true"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-tight">
                        Daftar Akun MyEventKu
                    </h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Buat akun baru untuk mengakses sistem manajemen event
                    </p>
                </div>
            </div>

            <!-- Alert Error Validasi -->
            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 flex items-start gap-2.5 p-3.5 rounded-md border border-rose-300 bg-rose-50 text-rose-800 text-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation text-rose-700 mt-0.5 shrink-0" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Pendaftaran Akun Baru -->
            <form method="POST" action="register.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Username
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           required
                           autofocus
                           minlength="3"
                           maxlength="50"
                           autocomplete="username"
                           placeholder="Contoh: panitia_trpl"
                           value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                    <p class="mt-1 text-xs text-slate-600">
                        Minimal 3 karakter (huruf, angka, atau garis bawah).
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Password
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           minlength="6"
                           autocomplete="new-password"
                           placeholder="Minimal 6 karakter..."
                           class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                </div>

                <div>
                    <label for="confirm_password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Konfirmasi password
                    </label>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           required
                           minlength="6"
                           autocomplete="new-password"
                           placeholder="Ulangi password..."
                           class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors cursor-pointer">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <span>Daftar sekarang</span>
                </button>
            </form>

            <!-- Link Kembali ke Halaman Login -->
            <div class="mt-5 pt-4 border-t border-slate-200 text-center text-xs text-slate-600">
                Sudah punya akun?
                <a href="login.php" class="font-semibold text-slate-900 hover:underline">Masuk di sini</a>
            </div>
        </div>
    </main>

    <!-- Footer Sederhana -->
    <footer class="bg-white border-t border-slate-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-600">
                <p class="text-center sm:text-left">
                    &copy; 2026 Sistem Informasi Manajemen Event &amp; Pendaftaran Peserta
                </p>
                <p class="text-center sm:text-right font-medium text-slate-700">
                    Proyek UTS MMPRPL
                </p>
            </div>
        </div>
    </footer>

</body>
</html>
