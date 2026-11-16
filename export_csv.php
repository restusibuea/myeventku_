<?php
// export daftar hadir ke CSV, bisa per event (?event_id=) atau semua sekaligus

require_once __DIR__ . '/config/database.php';

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$fileLabel = 'semua_event';

if ($eventId > 0) {
    // pastiin event-nya ada, kalau nggak balikin ke halaman peserta
    $stmtEvent = $pdo->prepare('SELECT id, title FROM events WHERE id = :id LIMIT 1');
    $stmtEvent->execute(['id' => $eventId]);
    $event = $stmtEvent->fetch();

    if (!$event) {
        setFlash('error', 'Event yang dipilih untuk export CSV tidak ditemukan.');
        header('Location: participants.php');
        exit;
    }

    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $event['title'] ?? 'event'), '_'));
    $fileLabel = $slug !== '' ? $slug : ('event_' . $eventId);

    // peserta di event yang dipilih aja
    $stmtRows = $pdo->prepare('
        SELECT
            p.ticket_code,
            p.name,
            p.identity_no,
            p.email,
            p.phone,
            p.attendance_status,
            p.check_in_at,
            p.created_at,
            e.title AS event_title,
            e.category AS event_category,
            e.event_date,
            e.location
        FROM participants p
        INNER JOIN events e ON p.event_id = e.id
        WHERE p.event_id = :event_id
        ORDER BY p.id ASC
    ');
    $stmtRows->execute(['event_id' => $eventId]);
} else {
    // nggak ada event_id, berarti ambil semua peserta
    $stmtRows = $pdo->prepare('
        SELECT
            p.ticket_code,
            p.name,
            p.identity_no,
            p.email,
            p.phone,
            p.attendance_status,
            p.check_in_at,
            p.created_at,
            e.title AS event_title,
            e.category AS event_category,
            e.event_date,
            e.location
        FROM participants p
        INNER JOIN events e ON p.event_id = e.id
        ORDER BY e.id ASC, p.id ASC
    ');
    $stmtRows->execute();
}

$rows = $stmtRows->fetchAll();

$filename = 'daftar_hadir_' . $fileLabel . '_' . date('Ymd_His') . '.csv';

// header ini yang bikin browser langsung download file, bukan nampilin teks
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8, tanpa ini Excel suka salah baca karakter
fwrite($output, "\xEF\xBB\xBF");

// baris pertama: nama-nama kolom
fputcsv($output, [
    'No',
    'Kode Tiket',
    'Nama Peserta',
    'NIM',
    'Email',
    'No HP',
    'Nama Event',
    'Kategori',
    'Waktu Pelaksanaan',
    'Lokasi',
    'Status Kehadiran',
    'Waktu Check-In',
    'Tanggal Daftar',
]);

$no = 1;
foreach ($rows as $row) {
    fputcsv($output, [
        $no++,
        $row['ticket_code'] ?? '',
        $row['name'] ?? '',
        $row['identity_no'] ?? '',
        $row['email'] ?? '',
        $row['phone'] ?? '',
        $row['event_title'] ?? '',
        $row['event_category'] ?? '',
        $row['event_date'] ?? '',
        $row['location'] ?? '',
        $row['attendance_status'] ?? '',
        $row['check_in_at'] ?? '-',
        $row['created_at'] ?? '',
    ]);
}

fclose($output);
exit;
