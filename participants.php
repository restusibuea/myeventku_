<?php
// halaman pendaftaran peserta: daftar, edit, batal, plus validasi kuota & anti dobel

// pastikan user sudah login sebelum akses halaman peserta
require_once 'includes/auth.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Pendaftaran peserta';
$allowedAttendance = ['Terdaftar', 'Hadir'];

// aksi tambah/edit/batal peserta lewat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $eventId    = (int) ($_POST['event_id'] ?? 0);
            $name       = trim((string) ($_POST['name'] ?? ''));
            $identityNo = trim((string) ($_POST['identity_no'] ?? ''));
            $email      = trim((string) ($_POST['email'] ?? ''));
            $phone      = trim((string) ($_POST['phone'] ?? ''));

            // semua field wajib diisi, terus format email-nya juga dicek
            if ($eventId <= 0 || $name === '' || $identityNo === '' || $email === '' || $phone === '') {
                setFlash('error', 'Semua kolom pendaftaran (Event, Nama, NIM, Email, No. HP) wajib diisi.');
                header('Location: participants.php' . ($eventId > 0 ? '?event_id=' . $eventId : '') . '#form-daftar');
                exit;
            }

            // cek format email pakai fungsi bawaan PHP filter_var
            // kalau formatnya salah (misal tidak ada @ atau domain), pendaftaran ditolak
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Format alamat email tidak valid. Contoh: nama@kampus.ac.id');
                header('Location: participants.php?event_id=' . $eventId . '#form-daftar');
                exit;
            }

            // cek nomor HP pakai ctype_digit: pastikan semua karakter hanya angka 0-9
            // kalau ada huruf, spasi, tanda minus, atau simbol lain, langsung ditolak
            if (!ctype_digit($phone)) {
                setFlash('error', 'Nomor HP tidak valid! Hanya boleh diisi angka saja (contoh: 081234567890).');
                header('Location: participants.php?event_id=' . $eventId . '#form-daftar');
                exit;
            }

            // ambil event tujuannya dulu, kuotanya perlu buat dicek
            $stmtEvent = $pdo->prepare('SELECT id, title, quota, status FROM events WHERE id = :id');
            $stmtEvent->execute(['id' => $eventId]);
            $targetEvent = $stmtEvent->fetch();

            if (!$targetEvent) {
                setFlash('error', 'Event yang dipilih tidak ditemukan di database.');
                header('Location: participants.php');
                exit;
            }

            if ($targetEvent['status'] === 'Selesai') {
                setFlash('error', 'Pendaftaran ditolak karena event "' . $targetEvent['title'] . '" sudah selesai.');
                header('Location: participants.php?event_id=' . $eventId);
                exit;
            }

            // cek kuota dulu, kalau sudah full ya tolak pendaftarannya
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE event_id = :event_id');
            $stmtCount->execute(['event_id' => $eventId]);
            $registeredCount = (int) $stmtCount->fetchColumn();

            if ($registeredCount >= (int) $targetEvent['quota'] || $targetEvent['status'] === 'Penuh') {
                // sekalian pastiin status event-nya Penuh, jaga-jaga kalau belum ke-update
                syncEventStatus($pdo, $eventId);
                setFlash('error', 'Pendaftaran ditolak! Kuota untuk event "' . $targetEvent['title'] . '" sudah penuh (' . $registeredCount . '/' . (int) $targetEvent['quota'] . ' peserta).');
                header('Location: participants.php?event_id=' . $eventId . '#form-daftar');
                exit;
            }

            // satu NIM/email cuma boleh daftar sekali per event, jadi cek dulu sudah ada atau belum
            $stmtDuplicate = $pdo->prepare('
                SELECT identity_no, email
                FROM participants
                WHERE event_id = :event_id
                  AND (identity_no = :identity_no OR email = :email)
                LIMIT 1
            ');
            $stmtDuplicate->execute([
                'event_id'    => $eventId,
                'identity_no' => $identityNo,
                'email'       => $email,
            ]);
            $duplicate = $stmtDuplicate->fetch();

            if ($duplicate) {
                if (strcasecmp((string) $duplicate['identity_no'], $identityNo) === 0) {
                    setFlash('error', 'Pendaftaran ganda ditolak! NIM "' . $identityNo . '" sudah terdaftar pada event "' . $targetEvent['title'] . '".');
                } else {
                    setFlash('error', 'Pendaftaran ganda ditolak! Email "' . $email . '" sudah terdaftar pada event "' . $targetEvent['title'] . '".');
                }
                header('Location: participants.php?event_id=' . $eventId . '#form-daftar');
                exit;
            }

            // generate kode tiket EVT-2026-XXXX, kalau ternyata sudah kepakai ya acak ulang
            $stmtCheckCode = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE ticket_code = :ticket_code');
            do {
                $randomDigits = (string) random_int(1000, 9999);
                $ticketCode   = 'EVT-2026-' . $randomDigits;
                $stmtCheckCode->execute(['ticket_code' => $ticketCode]);
                $codeExists = (int) $stmtCheckCode->fetchColumn() > 0;
            } while ($codeExists);

            // semua lolos, simpan pesertanya
            $stmtInsert = $pdo->prepare('
                INSERT INTO participants (event_id, ticket_code, name, identity_no, email, phone, attendance_status)
                VALUES (:event_id, :ticket_code, :name, :identity_no, :email, :phone, :attendance_status)
            ');
            $stmtInsert->execute([
                'event_id'          => $eventId,
                'ticket_code'       => $ticketCode,
                'name'              => $name,
                'identity_no'       => $identityNo,
                'email'             => $email,
                'phone'             => $phone,
                'attendance_status' => 'Terdaftar',
            ]);

            // update status event, siapa tahu peserta ini yang bikin kuotanya penuh
            syncEventStatus($pdo, $eventId);

            setFlash('success', 'Pendaftaran berhasil untuk "' . $name . '"! Kode Tiket: ' . $ticketCode . '.');
            header('Location: participants.php?event_id=' . $eventId);
            exit;
        }

        if ($action === 'update') {
            $participantId    = (int) ($_POST['id'] ?? 0);
            $name             = trim((string) ($_POST['name'] ?? ''));
            $identityNo       = trim((string) ($_POST['identity_no'] ?? ''));
            $email            = trim((string) ($_POST['email'] ?? ''));
            $phone            = trim((string) ($_POST['phone'] ?? ''));
            $attendanceStatus = trim((string) ($_POST['attendance_status'] ?? 'Terdaftar'));

            if ($participantId <= 0 || $name === '' || $identityNo === '' || $email === '' || $phone === '') {
                setFlash('error', 'Data biodata peserta tidak lengkap untuk diperbarui.');
                header('Location: participants.php');
                exit;
            }

            // cek ulang format email saat edit biodata pakai filter_var
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Format email peserta tidak valid.');
                header('Location: participants.php?edit=' . $participantId);
                exit;
            }

            // cek nomor HP pakai ctype_digit agar saat edit tetap hanya angka 0-9
            if (!ctype_digit($phone)) {
                setFlash('error', 'Nomor HP tidak valid! Hanya boleh diisi angka saja (contoh: 081234567890).');
                header('Location: participants.php?edit=' . $participantId);
                exit;
            }

            if (!in_array($attendanceStatus, $allowedAttendance, true)) {
                setFlash('error', 'Status kehadiran peserta tidak valid.');
                header('Location: participants.php?edit=' . $participantId);
                exit;
            }

            // ambil data lama: event_id buat cek dobel, check_in_at biar jam check-in yang lama nggak hilang
            $stmtCurrent = $pdo->prepare('SELECT id, event_id, check_in_at FROM participants WHERE id = :id');
            $stmtCurrent->execute(['id' => $participantId]);
            $currentParticipant = $stmtCurrent->fetch();

            if (!$currentParticipant) {
                setFlash('error', 'Data peserta tidak ditemukan.');
                header('Location: participants.php');
                exit;
            }

            $eventId = (int) $currentParticipant['event_id'];

            // cek dobel lagi, tapi peserta yang lagi diedit nggak ikut dihitung
            $stmtDupEdit = $pdo->prepare('
                SELECT identity_no, email
                FROM participants
                WHERE event_id = :event_id
                  AND (identity_no = :identity_no OR email = :email)
                  AND id != :id
                LIMIT 1
            ');
            $stmtDupEdit->execute([
                'event_id'    => $eventId,
                'identity_no' => $identityNo,
                'email'       => $email,
                'id'          => $participantId,
            ]);
            $dupEdit = $stmtDupEdit->fetch();

            if ($dupEdit) {
                setFlash('error', 'Gagal memperbarui! NIM atau Email tersebut sudah dipakai oleh peserta lain pada event yang sama.');
                header('Location: participants.php?edit=' . $participantId);
                exit;
            }

            // status Hadir: pakai jam check-in lama kalau ada, kalau belum ada isi jam sekarang; selain itu dikosongin
            $checkInAt = null;
            if ($attendanceStatus === 'Hadir') {
                $checkInAt = !empty($currentParticipant['check_in_at'])
                    ? $currentParticipant['check_in_at']
                    : date('Y-m-d H:i:s');
            }

            // simpan perubahan biodata & status kehadiran
            $stmtUpdate = $pdo->prepare('
                UPDATE participants
                SET name = :name,
                    identity_no = :identity_no,
                    email = :email,
                    phone = :phone,
                    attendance_status = :attendance_status,
                    check_in_at = :check_in_at
                WHERE id = :id
            ');
            $stmtUpdate->execute([
                'name'              => $name,
                'identity_no'       => $identityNo,
                'email'             => $email,
                'phone'             => $phone,
                'attendance_status' => $attendanceStatus,
                'check_in_at'       => $checkInAt,
                'id'                => $participantId,
            ]);

            setFlash('success', 'Biodata peserta "' . $name . '" berhasil diperbarui.');
            header('Location: participants.php?event_id=' . $eventId);
            exit;
        }

        if ($action === 'delete') {
            $participantId = (int) ($_POST['id'] ?? 0);

            if ($participantId <= 0) {
                setFlash('error', 'ID peserta yang akan dihapus tidak valid.');
                header('Location: participants.php');
                exit;
            }

            // ambil data peserta dulu, event_id-nya dibutuhin buat update status event setelah dihapus
            $stmtFind = $pdo->prepare('SELECT id, event_id, name, ticket_code FROM participants WHERE id = :id');
            $stmtFind->execute(['id' => $participantId]);
            $participantRow = $stmtFind->fetch();

            if (!$participantRow) {
                setFlash('error', 'Data peserta tidak ditemukan atau sudah dibatalkan sebelumnya.');
                header('Location: participants.php');
                exit;
            }

            $eventId = (int) $participantRow['event_id'];

            // hapus pendaftarannya
            $stmtDelete = $pdo->prepare('DELETE FROM participants WHERE id = :id');
            $stmtDelete->execute(['id' => $participantId]);

            // ada kursi kosong lagi, jadi event yang tadinya Penuh bisa balik Terbuka
            syncEventStatus($pdo, $eventId);

            setFlash('success', 'Pendaftaran "' . $participantRow['name'] . '" (' . $participantRow['ticket_code'] . ') berhasil dibatalkan dan kuota event telah diperbarui.');
            header('Location: participants.php');
            exit;
        }
    } catch (PDOException $e) {
        // lapis kedua: kalau lolos cek PHP tapi kena UNIQUE di database, tetap ketangkep di sini
        if ($e->getCode() === '23000') {
            setFlash('error', 'Pendaftaran gagal karena data NIM atau Kode Tiket sudah terdaftar pada event ini.');
        } else {
            setFlash('error', 'Terjadi kesalahan database saat memproses data peserta. Silakan coba lagi.');
        }
        header('Location: participants.php');
        exit;
    }
}

// bagian GET: mode edit, dropdown event, filter, dan pencarian
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editParticipant = null;

if ($editId > 0) {
    // data peserta yang mau diedit + judul event-nya
    $stmtEdit = $pdo->prepare('
        SELECT p.*, e.title AS event_title
        FROM participants p
        INNER JOIN events e ON p.event_id = e.id
        WHERE p.id = :id
    ');
    $stmtEdit->execute(['id' => $editId]);
    $editParticipant = $stmtEdit->fetch();

    if (!$editParticipant) {
        setFlash('error', 'Data peserta yang ingin diedit tidak ditemukan.');
        header('Location: participants.php');
        exit;
    }
}

// semua event + jumlah terisi, buat dropdown pilihan event
$stmtAllEvents = $pdo->prepare('
    SELECT
        e.id,
        e.title,
        e.category,
        e.quota,
        e.status,
        COUNT(p.id) AS terisi
    FROM events e
    LEFT JOIN participants p ON e.id = p.event_id
    GROUP BY e.id, e.title, e.category, e.quota, e.status
    ORDER BY e.event_date ASC
');
$stmtAllEvents->execute();
$allEvents = $stmtAllEvents->fetchAll();

$selectedEventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$searchQuery     = trim((string) ($_GET['q'] ?? ''));

$whereClauses = [];
$queryParams  = [];

if ($selectedEventId > 0) {
    $whereClauses[] = 'p.event_id = :event_id';
    $queryParams['event_id'] = $selectedEventId;
}

if ($searchQuery !== '') {
    $whereClauses[] = '(p.name LIKE :q_name OR p.identity_no LIKE :q_nim OR p.ticket_code LIKE :q_ticket)';
    $queryParams['q_name']   = '%' . $searchQuery . '%';
    $queryParams['q_nim']    = '%' . $searchQuery . '%';
    $queryParams['q_ticket'] = '%' . $searchQuery . '%';
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// list peserta, bisa difilter per event dan dicari pakai nama/NIM/kode tiket
$stmtParticipants = $pdo->prepare("
    SELECT
        p.id,
        p.event_id,
        p.ticket_code,
        p.name,
        p.identity_no,
        p.email,
        p.phone,
        p.attendance_status,
        p.check_in_at,
        p.created_at,
        e.title AS event_title,
        e.category AS event_category
    FROM participants p
    INNER JOIN events e ON p.event_id = e.id
    {$whereSql}
    ORDER BY p.id DESC
");
$stmtParticipants->execute($queryParams);
$participantsList = $stmtParticipants->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Judul Halaman -->
<section class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            Pendaftaran &amp; data peserta
        </h1>
        <p class="mt-1 text-sm text-slate-600">
            Daftarkan peserta dengan validasi kuota otomatis, pencegahan duplikasi NIM/email, dan cetak E-Ticket.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="export_csv.php<?= $selectedEventId > 0 ? '?event_id=' . $selectedEventId : '' ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-medium transition-colors">
            <i class="fa-solid fa-file-csv text-slate-500"></i>
            <span>Export daftar hadir CSV</span>
        </a>
    </div>
</section>

<!-- Form Daftar/Edit Peserta -->
<section id="form-daftar" class="mb-6 bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                <?= $editParticipant ? 'Edit biodata peserta' : 'Formulir pendaftaran peserta baru' ?>
            </h2>
            <p class="text-xs text-slate-600">
                <?= $editParticipant
                    ? 'Perbarui data biodata atau status kehadiran peserta (' . htmlspecialchars($editParticipant['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') . ').'
                    : 'Sistem memeriksa sisa kuota event, menolak NIM/email ganda, dan membuat kode tiket EVT-2026-XXXX.' ?>
            </p>
        </div>
        <?php if ($editParticipant): ?>
            <a href="participants.php"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-medium">
                <i class="fa-solid fa-xmark"></i> Batal edit
            </a>
        <?php endif; ?>
    </div>

    <form method="POST" action="participants.php" class="p-5 space-y-4">
        <input type="hidden" name="action" value="<?= $editParticipant ? 'update' : 'create' ?>">
        <?php if ($editParticipant): ?>
            <input type="hidden" name="id" value="<?= (int) $editParticipant['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Pilihan Event -->
            <div class="md:col-span-2">
                <label for="event_id" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Pilih event tujuan <span class="text-rose-600">*</span>
                </label>
                <?php if ($editParticipant): ?>
                    <input type="text"
                           readonly
                           value="<?= htmlspecialchars($editParticipant['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-3.5 py-2 rounded-md border border-slate-300 bg-slate-100 text-slate-700 text-sm font-medium cursor-not-allowed">
                <?php else: ?>
                    <select id="event_id"
                            name="event_id"
                            required
                            class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                        <option value="">-- Pilih event yang akan diikuti --</option>
                        <?php foreach ($allEvents as $ev): ?>
                            <?php
                                $evQuota  = (int) $ev['quota'];
                                $evTerisi = (int) $ev['terisi'];
                                $evFull   = ($evTerisi >= $evQuota || $ev['status'] === 'Penuh');
                                $evLabel  = $ev['title'] . ' [' . $ev['category'] . '] — Terisi ' . $evTerisi . '/' . $evQuota . ($evFull ? ' (Penuh)' : ' (Terbuka)');
                            ?>
                            <option value="<?= (int) $ev['id'] ?>" <?= ($selectedEventId === (int) $ev['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($evLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-600">
                        Event berlabel <strong>Penuh</strong> tetap dapat dipilih untuk menguji penolakan kuota di sisi server.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Nama Lengkap -->
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Nama lengkap <span class="text-rose-600">*</span>
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       required
                       maxlength="100"
                       placeholder="Masukkan nama lengkap..."
                       value="<?= htmlspecialchars($editParticipant['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- NIM -->
            <div>
                <label for="identity_no" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    NIM / nomor identitas <span class="text-rose-600">*</span>
                </label>
                <input type="text"
                       id="identity_no"
                       name="identity_no"
                       required
                       maxlength="30"
                       placeholder="Masukkan NIM..."
                       value="<?= htmlspecialchars($editParticipant['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Alamat email <span class="text-rose-600">*</span>
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       required
                       maxlength="100"
                       placeholder="Masukkan alamat email..."
                       value="<?= htmlspecialchars($editParticipant['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- Nomor HP -->
            <div>
                <label for="phone" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    No. handphone / WhatsApp <span class="text-rose-600">*</span>
                </label>
                <input type="text"
                       id="phone"
                       name="phone"
                       inputmode="numeric"
                       required
                       maxlength="25"
                       placeholder="Contoh: 081234567890 (hanya angka)"
                       value="<?= htmlspecialchars($editParticipant['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <?php if ($editParticipant): ?>
                <!-- Status Kehadiran (cuma pas edit) -->
                <div class="md:col-span-2">
                    <label for="attendance_status" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Status kehadiran <span class="text-rose-600">*</span>
                    </label>
                    <select id="attendance_status"
                            name="attendance_status"
                            class="w-full md:w-72 px-3.5 py-2 rounded-md border border-slate-300 text-sm bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                        <?php $curStatus = $editParticipant['attendance_status'] ?? 'Terdaftar'; ?>
                        <?php foreach ($allowedAttendance as $att): ?>
                            <option value="<?= htmlspecialchars($att, ENT_QUOTES, 'UTF-8') ?>" <?= $curStatus === $att ? 'selected' : '' ?>>
                                <?= htmlspecialchars($att, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="pt-3 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200">
            <?php if ($editParticipant): ?>
                <a href="participants.php"
                   class="px-4 py-2 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium">
                    Batal
                </a>
            <?php endif; ?>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid <?= $editParticipant ? 'fa-floppy-disk' : 'fa-paper-plane' ?>"></i>
                <span><?= $editParticipant ? 'Simpan perubahan biodata' : 'Daftarkan peserta & buat tiket' ?></span>
            </button>
        </div>
    </form>
</section>

<!-- Filter & Pencarian -->
<section class="mb-6 bg-white border border-slate-300 rounded-md shadow-none p-4">
    <form method="GET" action="participants.php" class="flex flex-col md:flex-row items-stretch md:items-center gap-3">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text"
                   name="q"
                   value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Cari nama, NIM, atau kode tiket..."
                   class="w-full pl-10 pr-4 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
        </div>

        <div class="w-full md:w-72">
            <select name="event_id"
                    class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                <option value="0">Semua event</option>
                <?php foreach ($allEvents as $ev): ?>
                    <option value="<?= (int) $ev['id'] ?>" <?= $selectedEventId === (int) $ev['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ev['title'] ?? '', ENT_QUOTES, 'UTF-8') ?> (<?= (int) $ev['terisi'] ?>/<?= (int) $ev['quota'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid fa-filter text-xs"></i>
                <span>Filter</span>
            </button>

            <?php if ($selectedEventId > 0 || $searchQuery !== ''): ?>
                <a href="participants.php"
                   class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium transition-colors">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Tabel Peserta -->
<section class="bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-900">Daftar peserta terdaftar</h2>
            <p class="text-xs text-slate-600">Menampilkan <?= count($participantsList) ?> peserta.</p>
        </div>
    </div>

    <?php if (empty($participantsList)): ?>
        <div class="p-10 text-center">
            <p class="font-semibold text-slate-700">Belum ada data peserta yang cocok dengan filter.</p>
            <p class="text-sm text-slate-600 mt-1">Gunakan formulir di atas untuk mendaftarkan peserta baru.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                        <th class="px-5 py-3">Kode tiket</th>
                        <th class="px-4 py-3">Peserta &amp; NIM</th>
                        <th class="px-4 py-3">Kontak</th>
                        <th class="px-4 py-3">Event diikuti</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <?php foreach ($participantsList as $row): ?>
                        <?php $isHadir = ($row['attendance_status'] === 'Hadir'); ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-800">
                                    <?= htmlspecialchars($row['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-900 capitalize">
                                    <?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-xs text-slate-600">
                                    NIM: <?= htmlspecialchars($row['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-600">
                                <p><i class="fa-regular fa-envelope mr-1 text-slate-600"></i><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5"><i class="fa-solid fa-phone mr-1 text-slate-600"></i><?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-4 py-3.5 text-xs sm:text-sm text-slate-700 max-w-xs">
                                <p class="font-medium text-slate-800 leading-snug">
                                    <?= htmlspecialchars($row['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <span class="text-xs text-slate-600">
                                    <?= htmlspecialchars($row['event_category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $isHadir
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                    : 'border-amber-300 bg-amber-50 text-amber-800' ?>">
                                    <?= htmlspecialchars($row['attendance_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($isHadir && !empty($row['check_in_at'])): ?>
                                    <p class="mt-1 text-[11px] text-slate-600">
                                        <?= htmlspecialchars(formatTanggalIndo($row['check_in_at']), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="ticket.php?id=<?= (int) $row['id'] ?>"
                                       title="Lihat & cetak E-Ticket"
                                       class="px-2.5 py-1.5 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-medium transition-colors">
                                        <i class="fa-solid fa-ticket"></i> E-Ticket
                                    </a>

                                    <a href="participants.php?edit=<?= (int) $row['id'] ?>#form-daftar"
                                       title="Edit biodata peserta"
                                       class="px-2.5 py-1.5 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-medium transition-colors">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>

                                    <form method="POST"
                                          action="participants.php"
                                          class="inline"
                                          onsubmit="return confirm('Batalkan pendaftaran peserta ini? Kuota event akan otomatis bertambah kembali.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit"
                                                title="Batalkan / hapus peserta"
                                                class="px-2.5 py-1.5 rounded-md bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 text-xs font-medium transition-colors">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
