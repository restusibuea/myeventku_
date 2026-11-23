<?php
// halaman check-in: cari peserta pakai kode tiket/NIM, terus tandai Hadir

// pastikan user sudah login sebelum akses halaman check-in
require_once 'includes/auth.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Check-in cepat';

// ada 2 cara check-in: klik tombol di tabel (checkin_id) atau ketik kode/NIM (quick_scan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'checkin_id') {
            $participantId = (int) ($_POST['participant_id'] ?? 0);

            if ($participantId <= 0) {
                setFlash('error', 'ID peserta tidak valid untuk proses check-in.');
                header('Location: checkin.php');
                exit;
            }

            // ambil pesertanya dulu, buat cek datanya ada dan belum check-in
            $stmtFind = $pdo->prepare('
                SELECT p.id, p.ticket_code, p.name, p.identity_no, p.attendance_status, p.check_in_at, e.title AS event_title
                FROM participants p
                INNER JOIN events e ON p.event_id = e.id
                WHERE p.id = :id
                LIMIT 1
            ');
            $stmtFind->execute(['id' => $participantId]);
            $participant = $stmtFind->fetch();

            if (!$participant) {
                setFlash('error', 'Data peserta tidak ditemukan di sistem.');
                header('Location: checkin.php');
                exit;
            }

            if ($participant['attendance_status'] === 'Hadir') {
                setFlash('error', 'Peserta "' . $participant['name'] . '" (' . $participant['ticket_code'] . ') sudah melakukan check-in pada ' . formatTanggalIndo($participant['check_in_at'] ?? null) . '.');
                header('Location: checkin.php');
                exit;
            }

            // tandai Hadir + catat jam check-in-nya
            $stmtUpdate = $pdo->prepare('
                UPDATE participants
                SET attendance_status = :status,
                    check_in_at = :check_in_at
                WHERE id = :id
            ');
            $stmtUpdate->execute([
                'status'      => 'Hadir',
                'check_in_at' => date('Y-m-d H:i:s'),
                'id'          => $participantId,
            ]);

            setFlash('success', 'Check-in berhasil! "' . $participant['name'] . '" (' . $participant['ticket_code'] . ') tercatat HADIR pada "' . $participant['event_title'] . '".');
            header('Location: checkin.php');
            exit;
        }

        if ($action === 'quick_scan') {
            $keyword = trim((string) ($_POST['keyword'] ?? ''));
            $eventId = (int) ($_POST['event_id'] ?? 0);

            if ($keyword === '') {
                setFlash('error', 'Masukkan Kode Tiket (EVT-2026-XXXX) atau NIM peserta terlebih dahulu.');
                header('Location: checkin.php');
                exit;
            }

            // kalau dropdown event dipilih, pencarian dibatasi ke event itu aja
            $eventCondition = ($eventId > 0) ? ' AND p.event_id = :event_id' : '';

            // cari pakai kode tiket atau NIM, mana yang cocok
            $stmtLookup = $pdo->prepare("
                SELECT p.id, p.ticket_code, p.name, p.identity_no, p.attendance_status, p.check_in_at, e.title AS event_title
                FROM participants p
                INNER JOIN events e ON p.event_id = e.id
                WHERE (p.ticket_code = :ticket_code OR p.identity_no = :identity_no) {$eventCondition}
                ORDER BY p.id DESC
            ");

            $params = [
                'ticket_code' => $keyword,
                'identity_no' => $keyword,
            ];
            if ($eventId > 0) {
                $params['event_id'] = $eventId;
            }

            $stmtLookup->execute($params);
            $matches = $stmtLookup->fetchAll();

            if (empty($matches)) {
                setFlash('error', 'Peserta dengan Kode Tiket atau NIM "' . $keyword . '" tidak ditemukan' . ($eventId > 0 ? ' pada event yang dipilih.' : '.'));
                header('Location: checkin.php?code=' . urlencode($keyword) . ($eventId > 0 ? '&event_id=' . $eventId : ''));
                exit;
            }

            // NIM yang sama bisa daftar di beberapa event, jadi kalau hasilnya lebih dari 1 tampilin list biar panitia pilih sendiri
            if (count($matches) > 1) {
                setFlash('error', 'Ditemukan ' . count($matches) . ' pendaftaran untuk NIM "' . $keyword . '". Silakan klik tombol "Konfirmasi hadir" pada event yang sesuai di bawah.');
                header('Location: checkin.php?code=' . urlencode($keyword));
                exit;
            }

            $target = $matches[0];

            if ($target['attendance_status'] === 'Hadir') {
                setFlash('error', 'Peserta "' . $target['name'] . '" (' . $target['ticket_code'] . ') sudah berstatus HADIR sejak ' . formatTanggalIndo($target['check_in_at'] ?? null) . '.');
                header('Location: checkin.php');
                exit;
            }

            // hasilnya cuma 1 dan belum hadir, langsung check-in aja
            $stmtCheckIn = $pdo->prepare('
                UPDATE participants
                SET attendance_status = :status,
                    check_in_at = :check_in_at
                WHERE id = :id
            ');
            $stmtCheckIn->execute([
                'status'      => 'Hadir',
                'check_in_at' => date('Y-m-d H:i:s'),
                'id'          => (int) $target['id'],
            ]);

            setFlash('success', 'Check-in cepat berhasil! "' . $target['name'] . '" (' . $target['ticket_code'] . ') tercatat HADIR di "' . $target['event_title'] . '".');
            header('Location: checkin.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('error', 'Terjadi kesalahan database saat memproses check-in peserta.');
        header('Location: checkin.php');
        exit;
    }
}

// bagian GET: dropdown event, antrean check-in, sama riwayat yang sudah hadir
$prefillCode   = trim((string) ($_GET['code'] ?? ''));
$filterEventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

// event buat dropdown filter
$stmtEvents = $pdo->prepare('SELECT id, title FROM events ORDER BY event_date ASC');
$stmtEvents->execute();
$eventsList = $stmtEvents->fetchAll();

// angka sudah hadir vs belum, ditampilin di pojok kanan atas
$stmtCountHadir = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE attendance_status = :status');
$stmtCountHadir->execute(['status' => 'Hadir']);
$totalHadir = (int) $stmtCountHadir->fetchColumn();

$stmtCountPending = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE attendance_status = :status');
$stmtCountPending->execute(['status' => 'Terdaftar']);
$totalPending = (int) $stmtCountPending->fetchColumn();

// default tampilin yang belum check-in; kalau ada keyword, tampilin hasil pencariannya
$pendingConditions = [];
$pendingParams     = [];

if ($prefillCode !== '') {
    $pendingConditions[] = '(p.ticket_code LIKE :code_ticket OR p.identity_no LIKE :code_nim OR p.name LIKE :code_name)';
    $pendingParams['code_ticket'] = '%' . $prefillCode . '%';
    $pendingParams['code_nim']    = '%' . $prefillCode . '%';
    $pendingParams['code_name']   = '%' . $prefillCode . '%';
} else {
    $pendingConditions[] = 'p.attendance_status = :pending_status';
    $pendingParams['pending_status'] = 'Terdaftar';
}

if ($filterEventId > 0) {
    $pendingConditions[] = 'p.event_id = :event_id';
    $pendingParams['event_id'] = $filterEventId;
}

$pendingWhereSql = 'WHERE ' . implode(' AND ', $pendingConditions);

// peserta yang tinggal diklik buat check-in
$stmtPending = $pdo->prepare("
    SELECT p.id, p.ticket_code, p.name, p.identity_no, p.attendance_status, p.check_in_at, e.title AS event_title
    FROM participants p
    INNER JOIN events e ON p.event_id = e.id
    {$pendingWhereSql}
    ORDER BY p.id DESC
");
$stmtPending->execute($pendingParams);
$pendingParticipants = $stmtPending->fetchAll();

// riwayat yang sudah hadir, yang terbaru di atas
$historyWhereSql = ($filterEventId > 0)
    ? 'WHERE p.attendance_status = :status AND p.event_id = :event_id'
    : 'WHERE p.attendance_status = :status';

$historyParams = ['status' => 'Hadir'];
if ($filterEventId > 0) {
    $historyParams['event_id'] = $filterEventId;
}

$stmtHistory = $pdo->prepare("
    SELECT p.id, p.ticket_code, p.name, p.identity_no, p.attendance_status, p.check_in_at, e.title AS event_title
    FROM participants p
    INNER JOIN events e ON p.event_id = e.id
    {$historyWhereSql}
    ORDER BY p.check_in_at DESC, p.id DESC
");
$stmtHistory->execute($historyParams);
$checkedInHistory = $stmtHistory->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Judul Halaman -->
<section class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            Check-in kehadiran cepat
        </h1>
        <p class="mt-1 text-sm text-slate-600">
            Masukkan kode tiket (<span class="font-mono font-semibold">EVT-2026-XXXX</span>) atau NIM untuk konfirmasi kehadiran peserta.
        </p>
    </div>

    <div class="flex items-center gap-2">
        <div class="px-3.5 py-2 rounded-md border border-emerald-300 bg-emerald-50 text-emerald-800 text-xs font-semibold">
            Sudah hadir: <?= $totalHadir ?>
        </div>
        <div class="px-3.5 py-2 rounded-md border border-amber-300 bg-amber-50 text-amber-800 text-xs font-semibold">
            Belum hadir: <?= $totalPending ?>
        </div>
    </div>
</section>

<!-- Form Scan -->
<section class="mb-6 bg-white border border-slate-300 rounded-md shadow-none p-5">
    <div class="mb-4">
        <h2 class="text-base font-bold text-slate-900">Scan / input cepat check-in</h2>
        <p class="text-xs text-slate-600 mt-0.5">
            Ketik kode tiket (contoh: <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">EVT-2026-1003</span>) atau NIM peserta lalu tekan tombol check-in.
        </p>
    </div>

    <form method="POST" action="checkin.php" class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <input type="hidden" name="action" value="quick_scan">

        <div class="md:col-span-5">
            <label for="keyword" class="sr-only">Kode tiket atau NIM</label>
            <input type="text"
                   id="keyword"
                   name="keyword"
                   required
                   autofocus
                   value="<?= htmlspecialchars($prefillCode, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Masukkan kode tiket (EVT-2026-XXXX) atau NIM..."
                   class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-900 placeholder-slate-400 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
        </div>

        <div class="md:col-span-4">
            <label for="scan_event_id" class="sr-only">Filter event opsional</label>
            <select id="scan_event_id"
                    name="event_id"
                    class="w-full px-3.5 py-2.5 rounded-md border border-slate-300 bg-white text-slate-800 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                <option value="0">Semua event (deteksi otomatis)</option>
                <?php foreach ($eventsList as $ev): ?>
                    <option value="<?= (int) $ev['id'] ?>" <?= $filterEventId === (int) $ev['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ev['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-3">
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid fa-circle-check"></i>
                <span>Check-in sekarang</span>
            </button>
        </div>
    </form>
</section>

<!-- Antrean Check-In -->
<section class="mb-6 bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                <?= $prefillCode !== ''
                    ? 'Hasil pencarian tiket / NIM: "' . htmlspecialchars($prefillCode, ENT_QUOTES, 'UTF-8') . '"'
                    : 'Peserta siap check-in (status: Terdaftar)' ?>
            </h2>
            <p class="text-xs text-slate-600">
                Klik tombol <strong>Konfirmasi hadir</strong> untuk langsung mengubah status menjadi Hadir.
            </p>
        </div>
        <?php if ($prefillCode !== '' || $filterEventId > 0): ?>
            <a href="checkin.php"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-medium">
                <i class="fa-solid fa-rotate-left"></i> Tampilkan semua
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($pendingParticipants)): ?>
        <div class="p-8 text-center text-sm text-slate-600">
            <p class="font-semibold text-slate-700">
                <?= $prefillCode !== '' ? 'Tidak ditemukan peserta yang cocok dengan kata kunci tersebut.' : 'Seluruh peserta terdaftar sudah melakukan check-in.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                        <th class="px-5 py-3">Kode tiket</th>
                        <th class="px-4 py-3">Nama &amp; NIM</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi 1 klik</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <?php foreach ($pendingParticipants as $row): ?>
                        <?php $rowHadir = ($row['attendance_status'] === 'Hadir'); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                                <?= htmlspecialchars($row['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-900 capitalize"><?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-600">NIM: <?= htmlspecialchars($row['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-4 py-3.5 text-xs sm:text-sm text-slate-700">
                                <?= htmlspecialchars($row['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $rowHadir
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                    : 'border-amber-300 bg-amber-50 text-amber-800' ?>">
                                    <?= htmlspecialchars($row['attendance_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <?php if ($rowHadir): ?>
                                    <span class="text-xs font-semibold text-emerald-700">
                                        Sudah hadir
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="checkin.php" class="inline">
                                        <input type="hidden" name="action" value="checkin_id">
                                        <input type="hidden" name="participant_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition-colors">
                                            <i class="fa-solid fa-user-check"></i>
                                            <span>Konfirmasi hadir</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Riwayat Hadir -->
<section class="bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="text-base font-bold text-slate-900">Riwayat peserta hadir (check-in)</h2>
            <p class="text-xs text-slate-600">Daftar peserta yang telah terkonfirmasi hadir di lokasi acara.</p>
        </div>
        <a href="export_csv.php<?= $filterEventId > 0 ? '?event_id=' . $filterEventId : '' ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-medium transition-colors">
            <i class="fa-solid fa-file-csv text-slate-500"></i> Unduh rekap CSV
        </a>
    </div>

    <?php if (empty($checkedInHistory)): ?>
        <div class="p-8 text-center text-sm text-slate-600">Belum ada peserta yang tercatat hadir.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                        <th class="px-5 py-3">Kode tiket</th>
                        <th class="px-4 py-3">Peserta &amp; NIM</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Waktu check-in</th>
                        <th class="px-5 py-3 text-right">Bukti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <?php foreach ($checkedInHistory as $hist): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                                <?= htmlspecialchars($hist['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-900 capitalize"><?= htmlspecialchars($hist['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-600">NIM: <?= htmlspecialchars($hist['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-4 py-3.5 text-xs sm:text-sm text-slate-700">
                                <?= htmlspecialchars($hist['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-700 whitespace-nowrap">
                                <?= htmlspecialchars(formatTanggalIndo($hist['check_in_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <a href="ticket.php?id=<?= (int) $hist['id'] ?>"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-medium transition-colors">
                                    <i class="fa-solid fa-ticket"></i> E-Ticket
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
