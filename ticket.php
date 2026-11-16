<?php
// halaman e-ticket peserta, tinggal klik cetak (window.print)

require_once __DIR__ . '/config/database.php';

$participantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($participantId <= 0) {
    setFlash('error', 'ID tiket peserta tidak valid.');
    header('Location: participants.php');
    exit;
}

// ambil data peserta sekalian detail event-nya (JOIN) buat ditampilin di tiket
$stmtTicket = $pdo->prepare('
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
        e.category AS event_category,
        e.event_date,
        e.location
    FROM participants p
    INNER JOIN events e ON p.event_id = e.id
    WHERE p.id = :id
    LIMIT 1
');
$stmtTicket->execute(['id' => $participantId]);
$ticket = $stmtTicket->fetch();

if (!$ticket) {
    setFlash('error', 'Data E-Ticket peserta tidak ditemukan.');
    header('Location: participants.php');
    exit;
}

$pageTitle = 'E-Ticket ' . $ticket['ticket_code'];
$isHadir = ($ticket['attendance_status'] === 'Hadir');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Tombol Kembali & Cetak (hilang pas print) -->
<div class="max-w-3xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <a href="participants.php?event_id=<?= (int) $ticket['event_id'] ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-medium transition-colors">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Kembali ke peserta</span>
    </a>

    <div class="flex flex-wrap items-center gap-2">
        <?php if (!$isHadir): ?>
            <a href="checkin.php?code=<?= urlencode($ticket['ticket_code'] ?? '') ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-medium transition-colors">
                <i class="fa-solid fa-user-check text-slate-500"></i>
                <span>Proses check-in</span>
            </a>
        <?php endif; ?>

        <button type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-medium transition-colors">
            <i class="fa-solid fa-print"></i>
            <span>Cetak E-Ticket</span>
        </button>
    </div>
</div>

<!-- Kartu E-Ticket -->
<article class="max-w-3xl mx-auto bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <!-- Header E-Ticket -->
    <div class="bg-slate-900 px-6 py-5 text-white border-b border-slate-300">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-slate-300">
                    Kartu bukti pendaftaran resmi (E-Ticket)
                </p>
                <h1 class="mt-1.5 text-xl sm:text-2xl font-bold leading-snug">
                    <?= htmlspecialchars($ticket['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="mt-1 text-xs font-medium text-slate-300">
                    Kategori: <?= htmlspecialchars($ticket['event_category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <!-- Status Kehadiran -->
            <div class="shrink-0 text-left sm:text-right">
                <span class="block text-xs text-slate-300 font-medium mb-1">
                    Status tiket
                </span>
                <span class="inline-block rounded border px-2.5 py-0.5 text-xs font-semibold <?= $isHadir
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                    : 'border-amber-300 bg-amber-50 text-amber-800' ?>">
                    <?= htmlspecialchars($ticket['attendance_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Isi Tiket -->
    <div class="p-6">
        <!-- Kode Tiket -->
        <div class="mb-6 p-4 rounded-md bg-slate-50 border border-slate-300 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold text-slate-600">Kode tiket unik</p>
                <p class="mt-1 font-mono text-2xl sm:text-3xl font-bold tracking-wider text-slate-900">
                    <?= htmlspecialchars($ticket['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="mt-1 text-xs text-slate-600">
                    Tunjukkan kode tiket atau NIM ini pada meja registrasi saat hari pelaksanaan.
                </p>
            </div>
            <div class="w-14 h-14 rounded-md bg-white border border-slate-300 flex items-center justify-center text-slate-700 shrink-0">
                <i class="fa-solid fa-qrcode text-2xl"></i>
            </div>
        </div>

        <!-- Biodata & Jadwal -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="p-4 rounded-md bg-slate-50 border border-slate-200">
                <p class="text-xs font-semibold text-slate-600 mb-3">
                    Informasi peserta
                </p>
                <dl class="space-y-2.5">
                    <div>
                        <dt class="text-xs text-slate-600">Nama lengkap</dt>
                        <dd class="font-bold text-slate-900 text-base capitalize">
                            <?= htmlspecialchars($ticket['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">NIM / nomor identitas</dt>
                        <dd class="font-semibold text-slate-800 font-mono">
                            <?= htmlspecialchars($ticket['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">Email</dt>
                        <dd class="text-slate-700">
                            <?= htmlspecialchars($ticket['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">No. handphone</dt>
                        <dd class="text-slate-700">
                            <?= htmlspecialchars($ticket['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="p-4 rounded-md bg-slate-50 border border-slate-200">
                <p class="text-xs font-semibold text-slate-600 mb-3">
                    Jadwal &amp; lokasi event
                </p>
                <dl class="space-y-2.5">
                    <div>
                        <dt class="text-xs text-slate-600">Waktu pelaksanaan</dt>
                        <dd class="font-semibold text-slate-900">
                            <i class="fa-regular fa-clock text-slate-600 mr-1"></i>
                            <?= htmlspecialchars(formatTanggalIndo($ticket['event_date'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">Lokasi kegiatan</dt>
                        <dd class="font-semibold text-slate-900">
                            <i class="fa-solid fa-location-dot text-slate-600 mr-1"></i>
                            <?= htmlspecialchars($ticket['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">Tanggal pendaftaran</dt>
                        <dd class="text-slate-700">
                            <?= htmlspecialchars(formatTanggalIndo($ticket['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">Waktu check-in</dt>
                        <dd class="font-semibold <?= $isHadir ? 'text-emerald-700' : 'text-slate-600' ?>">
                            <?= $isHadir && !empty($ticket['check_in_at'])
                                ? htmlspecialchars(formatTanggalIndo($ticket['check_in_at']), ENT_QUOTES, 'UTF-8')
                                : 'Belum melakukan check-in' ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Footer Tiket -->
        <div class="mt-6 pt-4 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-600">
            <span>
                Bukti pendaftaran sah diterbitkan oleh Sistem Informasi EventKu (UTS MMPRPL).
            </span>
            <span class="font-mono">ID #<?= (int) $ticket['id'] ?></span>
        </div>
    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
