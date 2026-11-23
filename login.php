<?php
// halaman login untuk autentikasi admin/panitia menggunakan PDO dan password_verify

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

// kalau sudah login sebelumnya, langsung arahkan ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$flashMessage = getFlash();
$errorMessage = '';
$username     = '';

// proses autentikasi saat form login disubmit lewat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errorMessage = 'Username dan password wajib diisi.';
    } else {
        try {
            // ambil data user berdasarkan username pakai prepared statement biar aman dari SQL injection
            $stmtUser = $pdo->prepare('
                SELECT id, username, password_hash, role
                FROM users
                WHERE username = :username
                LIMIT 1
            ');
            $stmtUser->execute(['username' => $username]);
            $user = $stmtUser->fetch();

            // verifikasi kecocokan password dengan hash bcrypt yang tersimpan di database
            if ($user && password_verify($password, $user['password_hash'])) {
                // regenerasi ID session setelah login berhasil untuk mencegah session fixation
                session_regenerate_id(true);

                // simpan identitas login ke dalam session
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                header('Location: index.php');
                exit;
            }

            // tampilkan pesan error umum agar tidak membocorkan field mana yang salah
            $errorMessage = 'Username atau password yang Anda masukkan salah.';
        } catch (PDOException $e) {
            $errorMessage = 'Terjadi gangguan saat memeriksa data login di database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MyEventKu</title>

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

    <!-- Konten Utama Halaman Login -->
    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md bg-white border border-slate-300 rounded-md shadow-none p-6 sm:p-8">
            <!-- Header Identitas Aplikasi -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-md bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-ticket text-base" aria-hidden="true"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-tight">
                        Login MyEventKu
                    </h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Sistem Informasi Manajemen Event &amp; Pendaftaran Peserta
                    </p>
                </div>
            </div>

            <!-- Flash Message Sukses/Notif dari Session -->
            <?php if ($flashMessage !== null): ?>
                <?php
                    $isFlashSuccess = ($flashMessage['type'] ?? '') === 'success';
                    $flashClasses   = $isFlashSuccess
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                        : 'border-rose-300 bg-rose-50 text-rose-800';
                    $flashIcon      = $isFlashSuccess
                        ? 'fa-circle-check text-emerald-700'
                        : 'fa-circle-exclamation text-rose-700';
                ?>
                <div class="mb-5 flex items-start gap-2.5 p-3.5 rounded-md border <?= $flashClasses ?> text-sm" role="alert">
                    <i class="fa-solid <?= $flashIcon ?> mt-0.5 shrink-0" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($flashMessage['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Alert Error Autentikasi -->
            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 flex items-start gap-2.5 p-3.5 rounded-md border border-rose-300 bg-rose-50 text-rose-800 text-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation text-rose-700 mt-0.5 shrink-0" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Login Pengguna -->
            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Username
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           required
                           autofocus
                           autocomplete="username"
                           placeholder="Masukkan username..."
                           value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Password
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           placeholder="Masukkan password..."
                           class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors cursor-pointer">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Masuk</span>
                </button>
            </form>

            <!-- Link ke Halaman Daftar Akun -->
            <div class="mt-5 pt-4 border-t border-slate-200 text-center text-xs text-slate-600">
                Belum punya akun?
                <a href="register.php" class="font-semibold text-slate-900 hover:underline">Daftar di sini</a>
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
