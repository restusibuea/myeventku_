<?php
// koneksi PDO + auto-installer: kalau database/tabel belum ada, langsung dibuatin sekalian isi data contoh

// session dipakai buat nyimpen flash message (notif sukses/gagal habis CRUD)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// set ke WIB biar jam check-in yang kesimpan nggak meleset
date_default_timezone_set('Asia/Jakarta');

$dbHost    = '127.0.0.1';
$dbName    = 'db_event_uts';
$dbUser    = 'root';
$dbPass    = '';
$dbCharset = 'utf8mb4';

// setting PDO yang dipakai di semua query
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error SQL langsung jadi PDOException, gampang ditangkap try-catch
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // hasil query bisa diakses pakai $row['nama_kolom']
    PDO::ATTR_EMULATE_PREPARES   => false,                  // pakai prepared statement asli MySQL, lebih aman dari SQL injection
];

try {
    // konek dulu ke server MySQL tanpa pilih database, soalnya database-nya bisa jadi belum ada
    $serverPdo = new PDO(
        "mysql:host={$dbHost};charset={$dbCharset}",
        $dbUser,
        $dbPass,
        $pdoOptions
    );

    // bikin database kalau belum ada
    $serverPdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}`
         CHARACTER SET utf8mb4
         COLLATE utf8mb4_unicode_ci"
    );

    // baru konek beneran ke db_event_uts, koneksi $pdo ini yang dipakai di semua halaman
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}",
        $dbUser,
        $dbPass,
        $pdoOptions
    );

    // tabel events, cuma dibuat kalau belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `category` ENUM('Seminar', 'Workshop', 'Kompetisi', 'Bootcamp') NOT NULL DEFAULT 'Seminar',
            `event_date` DATETIME NOT NULL,
            `location` VARCHAR(150) NOT NULL,
            `quota` INT NOT NULL DEFAULT 0,
            `description` TEXT NULL,
            `status` ENUM('Terbuka', 'Penuh', 'Selesai') NOT NULL DEFAULT 'Terbuka',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // tabel participants: UNIQUE (event_id, identity_no) biar NIM nggak dobel di event yang sama, ON DELETE CASCADE biar peserta ikut kehapus kalau event-nya dihapus
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `participants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `event_id` INT NOT NULL,
            `ticket_code` VARCHAR(25) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `identity_no` VARCHAR(30) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(25) NOT NULL,
            `attendance_status` ENUM('Terdaftar', 'Hadir') NOT NULL DEFAULT 'Terdaftar',
            `check_in_at` DATETIME NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_ticket_code` (`ticket_code`),
            UNIQUE KEY `unique_participant` (`event_id`, `identity_no`),
            CONSTRAINT `fk_participants_event`
                FOREIGN KEY (`event_id`) REFERENCES `events`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // seed cuma jalan kalau tabel events masih kosong, jadi data yang sudah ada nggak ketimpa
    $stmtCheckSeed = $pdo->prepare('SELECT COUNT(*) FROM events');
    $stmtCheckSeed->execute();
    $existingEventCount = (int) $stmtCheckSeed->fetchColumn();

    if ($existingEventCount === 0) {
        // pakai transaksi: kalau gagal di tengah jalan, semua dibatalin biar datanya nggak setengah-setengah
        $pdo->beginTransaction();

        $seedEvents = [
            [
                'title'       => 'Seminar Nasional AI & Software Engineering 2026',
                'category'    => 'Seminar',
                'event_date'  => '2026-11-25 09:00:00',
                'location'    => 'Auditorium Gedung Rektorat Lt. 3',
                'quota'       => 50,
                'description' => 'Seminar nasional membahas penerapan kecerdasan buatan (AI) dan arsitektur perangkat lunak modern bersama praktisi industri teknologi.',
                'status'      => 'Terbuka',
            ],
            [
                'title'       => 'Workshop PHP Native & Arsitektur Database MySQL',
                'category'    => 'Workshop',
                'event_date'  => '2026-12-02 13:00:00',
                'location'    => 'Lab Komputer Rekayasa Perangkat Lunak 1',
                'quota'       => 30,
                'description' => 'Pelatihan langsung membangun sistem web aman menggunakan PHP 8 PDO Prepared Statements dan desain relasi tabel MySQL.',
                'status'      => 'Terbuka',
            ],
            [
                'title'       => 'Kompetisi Inovasi Prototype Mahasiswa TRPL',
                'category'    => 'Kompetisi',
                'event_date'  => '2026-12-10 08:30:00',
                'location'    => 'Hall Inovasi Kampus Teknologi',
                'quota'       => 3,
                'description' => 'Kompetisi unjuk karya prototype aplikasi mahasiswa TRPL tingkat program studi.',
                'status'      => 'Terbuka',
            ],
            [
                'title'       => 'Bootcamp UI/UX Design & Prototyping',
                'category'    => 'Bootcamp',
                'event_date'  => '2026-12-18 09:00:00',
                'location'    => 'Ruang Studio Desain Digital Lt. 2',
                'quota'       => 25,
                'description' => 'Bootcamp intensif merancang antarmuka modern yang ramah pengguna mulai dari riset UX, wireframe, hingga prototipe interaktif.',
                'status'      => 'Terbuka',
            ],
        ];

        // masukin 4 event contoh
        $stmtInsertEvent = $pdo->prepare('
            INSERT INTO events (title, category, event_date, location, quota, description, status)
            VALUES (:title, :category, :event_date, :location, :quota, :description, :status)
        ');

        $insertedEventIds = [];
        foreach ($seedEvents as $index => $eventRow) {
            $stmtInsertEvent->execute($eventRow);
            $insertedEventIds[$index + 1] = (int) $pdo->lastInsertId();
        }

        $seedParticipants = [
            // event 1: 3 peserta (2 hadir, 1 terdaftar)
            [
                'event_id'          => $insertedEventIds[1],
                'ticket_code'       => 'EVT-2026-1001',
                'name'              => 'Yogi Pratama',
                'identity_no'       => '240103001',
                'email'             => 'yogimo@wbi.ac.id',
                'phone'             => '081265432109',
                'attendance_status' => 'Hadir',
                'check_in_at'       => '2026-11-15 08:45:00',
            ],
            [
                'event_id'          => $insertedEventIds[1],
                'ticket_code'       => 'EVT-2026-1002',
                'name'              => 'Nikola Tesla',
                'identity_no'       => '240103002',
                'email'             => 'nikolatesla@usu.ac.id',
                'phone'             => '081377889900',
                'attendance_status' => 'Hadir',
                'check_in_at'       => '2026-11-15 08:52:00',
            ],
            [
                'event_id'          => $insertedEventIds[1],
                'ticket_code'       => 'EVT-2026-1003',
                'name'              => 'Yuji Itadori',
                'identity_no'       => '240103003',
                'email'             => 'yujiitadori@jujutsukaisen.ac.id',
                'phone'             => '085211223344',
                'attendance_status' => 'Terdaftar',
                'check_in_at'       => null,
            ],
            // event 2: 2 peserta (1 hadir, 1 terdaftar)
            [
                'event_id'          => $insertedEventIds[2],
                'ticket_code'       => 'EVT-2026-2001',
                'name'              => 'Restu Bajawa Erlintua Sibuea',
                'identity_no'       => '2505010007',
                'email'             => 'bajawa@unimed.ac.id',
                'phone'             => '082155667788',
                'attendance_status' => 'Hadir',
                'check_in_at'       => '2026-11-15 12:50:00',
            ],
            [
                'event_id'          => $insertedEventIds[2],
                'ticket_code'       => 'EVT-2026-2002',
                'name'              => 'Wisnu Wardana',
                'identity_no'       => '250501005',
                'email'             => 'wisnu@umsu.ac.id',
                'phone'             => '081299887766',
                'attendance_status' => 'Terdaftar',
                'check_in_at'       => null,
            ],
            // event 3 kuotanya cuma 3, sengaja diisi 2 biar pas demo tinggal daftarin 1 orang langsung Penuh
            [
                'event_id'          => $insertedEventIds[3],
                'ticket_code'       => 'EVT-2026-3001',
                'name'              => 'Simo Hayha',
                'identity_no'       => '240103006',
                'email'             => 'simohayha@itb.ac.id',
                'phone'             => '085277665544',
                'attendance_status' => 'Hadir',
                'check_in_at'       => '2026-11-15 08:15:00',
            ],
            [
                'event_id'          => $insertedEventIds[3],
                'ticket_code'       => 'EVT-2026-3002',
                'name'              => 'Gloo',
                'identity_no'       => '240103007',
                'email'             => 'gloo@telkomuniversity.ac.id',
                'phone'             => '082133445566',
                'attendance_status' => 'Terdaftar',
                'check_in_at'       => null,
            ],
            // event 4: 2 peserta (1 hadir, 1 terdaftar)
            [
                'event_id'          => $insertedEventIds[4],
                'ticket_code'       => 'EVT-2026-4001',
                'name'              => 'Grace',
                'identity_no'       => '250501008',
                'email'             => 'grace@wbi.ac.id',
                'phone'             => '081311223355',
                'attendance_status' => 'Hadir',
                'check_in_at'       => '2026-11-15 09:05:00',
            ],
            [
                'event_id'          => $insertedEventIds[4],
                'ticket_code'       => 'EVT-2026-4002',
                'name'              => 'Dolor Sitamet',
                'identity_no'       => '250501009',
                'email'             => 'dolorsitamet@usu.ac.id',
                'phone'             => '085299001122',
                'attendance_status' => 'Terdaftar',
                'check_in_at'       => null,
            ],
        ];

        // masukin 9 peserta contoh
        $stmtInsertParticipant = $pdo->prepare('
            INSERT INTO participants (event_id, ticket_code, name, identity_no, email, phone, attendance_status, check_in_at)
            VALUES (:event_id, :ticket_code, :name, :identity_no, :email, :phone, :attendance_status, :check_in_at)
        ');

        foreach ($seedParticipants as $participantRow) {
            $stmtInsertParticipant->execute($participantRow);
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    // ada yang gagal pas install/seed? rollback biar database nggak setengah jadi
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // tampilkan pesan error yang jelas, biasanya gara-gara MySQL di XAMPP belum dinyalain
    http_response_code(500);
    echo '<div style="font-family:sans-serif;max-width:600px;margin:40px auto;padding:20px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:6px;">';
    echo '<h2 style="margin-top:0;">Koneksi Database Gagal</h2>';
    echo '<p>Pastikan layanan <strong>MySQL</strong> di XAMPP/Laragon sudah aktif (port 3306).</p>';
    echo '<p style="font-size:13px;color:#7f1d1d;">Detail teknis: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</div>';
    exit;
}

// simpan notif sementara ke session, nanti ditampilin di halaman tujuan setelah redirect
function setFlash($type, $message)
{
    $_SESSION['flash'] = [
        'type'    => $type, // 'success' atau 'error'
        'message' => $message,
    ];
}

// ambil notif dari session lalu langsung hapus, jadi cuma muncul sekali (pas di-refresh nggak muncul lagi)
function getFlash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// update status event otomatis: peserta >= kuota berarti Penuh, kalau masih ada sisa ya Terbuka
function syncEventStatus($pdo, $eventId)
{
    // ambil kuota & status event sekarang (event yang sudah Selesai nggak usah diutak-atik)
    $stmtEvent = $pdo->prepare('SELECT quota, status FROM events WHERE id = :id');
    $stmtEvent->execute(['id' => $eventId]);
    $event = $stmtEvent->fetch();

    if (!$event || $event['status'] === 'Selesai') {
        return;
    }

    // hitung sudah berapa orang yang daftar di event ini
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE event_id = :event_id');
    $stmtCount->execute(['event_id' => $eventId]);
    $registeredCount = (int) $stmtCount->fetchColumn();

    $newStatus = ($registeredCount >= (int) $event['quota']) ? 'Penuh' : 'Terbuka';

    if ($newStatus !== $event['status']) {
        // cuma update kalau statusnya memang berubah, biar nggak query sia-sia
        $stmtUpdate = $pdo->prepare('UPDATE events SET status = :status WHERE id = :id');
        $stmtUpdate->execute([
            'status' => $newStatus,
            'id'     => $eventId,
        ]);
    }
}

// ubah datetime MySQL (2026-12-10 08:30:00) jadi format Indonesia '10 Desember 2026, 08:30 WIB'
function formatTanggalIndo($datetime)
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $hari  = date('d', $timestamp);
    $bulan = $bulanIndo[(int) date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam   = date('H:i', $timestamp);

    return "{$hari} {$bulan} {$tahun}, {$jam} WIB";
}
