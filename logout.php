<?php
// proses logout: bersihkan semua data session dan arahkan kembali ke halaman login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// kosongkan seluruh array session
$_SESSION = [];

// hapus cookie session di browser apabila diaktifkan
if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

// hancurkan session di server lalu redirect ke login.php
session_destroy();
header('Location: login.php');
exit;
