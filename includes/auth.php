<?php
// middleware proteksi halaman: cek apakah user sudah login di session

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// kalau user_id belum ada di session, paksa kembali ke halaman login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
