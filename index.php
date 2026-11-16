<?php
// dashboard: ringkasan angka event & peserta, progres kuota tiap event, sama pendaftar terbaru

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard';

// total event + total kursi semua event (COALESCE biar hasilnya 0, bukan NULL, kalau tabel kosong)
$stmtStatEvents = $pdo->prepare('
    SELECT COUNT(*) AS total_events, COALESCE(SUM(quota), 0) AS total_quota
    FROM events
');
$stmtStatEvents->execute();
$eventStats = $stmtStatEvents->fetch();

$totalEvents = (int) ($eventStats['total_events'] ?? 0);
$totalQuota  = (int) ($eventStats['total_quota'] ?? 0);

// total peserta yang sudah daftar
$stmtStatParticipants = $pdo->prepare('SELECT COUNT(*) FROM participants');
$stmtStatParticipants->execute();
$totalParticipants = (int) $stmtStatParticipants->fetchColumn();

// yang sudah check-in (status Hadir)
$stmtStatCheckin = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE attendance_status = :status');
$stmtStatCheckin->execute(['status' => 'Hadir']);
$totalHadir = (int) $stmtStatCheckin->fetchColumn();

// sisa kursi yang masih bisa diisi, pakai max(0) biar nggak minus
$sisaKuotaTersedia = max(0, $totalQuota - $totalParticipants);
$persenTerisiGlobal = ($totalQuota > 0) ? min(100, round(($totalParticipants / $totalQuota) * 100)) : 0;

// list event + jumlah pendaftar & yang hadir; pakai LEFT JOIN biar event yang belum ada pesertanya tetap muncul
$stmtEvents = $pdo->prepare("
    SELECT
        e.id,
        e.title,
        e.category,
        e.event_date,
        e.location,
        e.quota,
        e.description,
        e.status,
        e.created_at,
        COUNT(p.id) AS terisi,
        COALESCE(SUM(CASE WHEN p.attendance_status = 'Hadir' THEN 1 ELSE 0 END), 0) AS hadir
    FROM events e
    LEFT JOIN participants p ON e.id = p.event_id
    GROUP BY e.id, e.title, e.category, e.event_date, e.location, e.quota, e.description, e.status, e.created_at
    ORDER BY e.event_date ASC
");
$stmtEvents->execute();
$events = $stmtEvents->fetchAll();

// 5 pendaftar paling baru, sekalian ambil judul event-nya
$stmtRecentParticipants = $pdo->prepare('
    SELECT p.id, p.ticket_code, p.name, p.identity_no, p.attendance_status, p.created_at, e.title AS event_title
    FROM participants p
    INNER JOIN events e ON p.event_id = e.id
    ORDER BY p.id DESC
    LIMIT 5
');
$stmtRecentParticipants->execute();
$recentParticipants = $stmtRecentParticipants->fetchAll();

$statusStyles = [
    'Terbuka' => 'border-emerald-300 bg-emerald-50 text-emerald-800',
    'Penuh'   => 'border-rose-300 bg-rose-50 text-rose-800',
    'Selesai' => 'border-slate-300 bg-slate-100 text-slate-700',
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Judul Halaman -->
<section class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            Dashboard manajemen event
        </h1>
        <p class="mt-1 text-sm text-slate-600">
            Ringkasan kuota event aktif, data pendaftar, dan status kehadiran peserta.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="events.php?action=new"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-medium transition-colors">
            <i class="fa-solid fa-calendar-plus text-slate-500"></i>
            <span>Tambah event</span>
        </a>
        <a href="participants.php?action=new"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-medium transition-colors">
            <i class="fa-solid fa-user-plus"></i>
            <span>Daftarkan peserta</span>
        </a>
    </div>
</section>

<!-- Kartu Statistik -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-slate-300 rounded-md shadow-none p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-600">Total event</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?= $totalEvents ?></p>
                <p class="mt-1 text-xs text-slate-600">Total kapasitas <?= $totalQuota ?> kursi</p>
            </div>
            <i class="fa-solid fa-calendar-days text-lg text-slate-500"></i>
        </div>
    </div>

    <div class="bg-white border border-slate-300 rounded-md shadow-none p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-600">Peserta terdaftar</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?= $totalParticipants ?></p>
                <p class="mt-1 text-xs text-slate-600"><?= $persenTerisiGlobal ?>% dari total kuota terisi</p>
            </div>
            <i class="fa-solid fa-users text-lg text-slate-500"></i>
        </div>
    </div>

    <div class="bg-white border border-slate-300 rounded-md shadow-none p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-600">Peserta hadir (check-in)</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?= $totalHadir ?></p>
                <p class="mt-1 text-xs text-slate-600">
                    <?= max(0, $totalParticipants - $totalHadir) ?> peserta belum check-in
                </p>
            </div>
            <i class="fa-solid fa-user-check text-lg text-slate-500"></i>
        </div>
    </div>

    <div class="bg-white border border-slate-300 rounded-md shadow-none p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-600">Sisa kuota tersedia</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?= $sisaKuotaTersedia ?></p>
                <p class="mt-1 text-xs text-slate-600">Kursi siap didaftarkan</p>
            </div>
            <i class="fa-solid fa-chair text-lg text-slate-500"></i>
        </div>
    </div>
</section>

<!-- Daftar Event -->
<section class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Daftar event &amp; keterisian kuota</h2>
            <p class="text-xs sm:text-sm text-slate-600">Status kuota diperbarui otomatis setiap ada pendaftaran atau pembatalan.</p>
        </div>
        <a href="events.php" class="text-xs sm:text-sm font-medium text-slate-700 hover:text-slate-900 inline-flex items-center gap-1">
            <span>Lihat semua</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
        </a>
    </div>

    <?php if (empty($events)): ?>
        <div class="bg-white border border-slate-300 rounded-md shadow-none p-10 text-center">
            <p class="font-semibold text-slate-700">Belum ada event yang tersedia.</p>
            <p class="text-sm text-slate-500 mt-1">Tambahkan event baru melalui menu manajemen event.</p>
            <a href="events.php?action=new" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 text-white text-xs font-medium">
                <i class="fa-solid fa-plus"></i> Tambah event baru
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($events as $event): ?>
                <?php
                    $quota     = (int) $event['quota'];
                    $terisi    = (int) $event['terisi'];
                    $hadir     = (int) $event['hadir'];
                    $sisaSlot  = max(0, $quota - $terisi);
                    $persen    = ($quota > 0) ? min(100, (int) round(($terisi / $quota) * 100)) : 0;
                    $isPenuh   = ($event['status'] === 'Penuh' || $terisi >= $quota);

                    $statBadge = $statusStyles[$event['status']] ?? 'border-slate-300 bg-slate-100 text-slate-700';

                    // warna bar: merah kalau sudah penuh, kuning kalau >= 75%, sisanya gelap
                    if ($persen >= 100) {
                        $barColor = 'bg-rose-600';
                    } elseif ($persen >= 75) {
                        $barColor = 'bg-amber-500';
                    } else {
                        $barColor = 'bg-slate-800';
                    }
                ?>
                <article class="bg-white border border-slate-300 rounded-md shadow-none p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2.5">
                            <span class="text-xs font-semibold tracking-wider text-slate-500">
                                <?= htmlspecialchars($event['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $statBadge ?>">
                                <?= htmlspecialchars($event['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-snug">
                            <?= htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="mt-1.5 text-xs sm:text-sm text-slate-600 line-clamp-2">
                            <?= htmlspecialchars($event['description'] ?? 'Tidak ada deskripsi tambahan.', ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="mt-4 space-y-1.5 text-xs text-slate-600">
                            <div class="flex items-center gap-2">
                                <i class="fa-regular fa-clock text-slate-600 w-4 text-center"></i>
                                <span><?= htmlspecialchars(formatTanggalIndo($event['event_date'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-location-dot text-slate-600 w-4 text-center"></i>
                                <span><?= htmlspecialchars($event['location'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>

                        <!-- Progress Kuota -->
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <div class="flex items-center justify-between text-xs font-medium mb-1.5">
                                <span class="text-slate-600">
                                    Kuota terisi:
                                    <strong class="text-slate-900"><?= $terisi ?> / <?= $quota ?></strong>
                                </span>
                                <span class="<?= $isPenuh ? 'text-rose-700 font-semibold' : 'text-slate-700 font-semibold' ?>">
                                    <?= $persen ?>% (Sisa <?= $sisaSlot ?>)
                                </span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded overflow-hidden">
                                <div class="h-full <?= $barColor ?>" style="width: <?= $persen ?>%"></div>
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-600">
                                <?= $hadir ?> peserta sudah melakukan check-in
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="mt-4 pt-4 border-t border-slate-200 flex items-center gap-2">
                        <?php if ($isPenuh || $event['status'] === 'Selesai'): ?>
                            <span class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-slate-100 border border-slate-200 text-slate-500 text-xs font-medium cursor-not-allowed">
                                <i class="fa-solid fa-ban"></i>
                                <span><?= $event['status'] === 'Selesai' ? 'Event selesai' : 'Kuota penuh' ?></span>
                            </span>
                        <?php else: ?>
                            <a href="participants.php?event_id=<?= (int) $event['id'] ?>#form-daftar"
                               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition-colors">
                                <i class="fa-solid fa-user-plus"></i>
                                <span>Daftar peserta</span>
                            </a>
                        <?php endif; ?>

                        <a href="events.php?edit=<?= (int) $event['id'] ?>"
                           class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md text-slate-700 hover:bg-slate-100 text-xs font-medium transition-colors">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Kelola event</span>
                        </a>

                        <a href="export_csv.php?event_id=<?= (int) $event['id'] ?>"
                           title="Export CSV"
                           aria-label="Export CSV"
                           class="w-9 h-9 flex items-center justify-center border border-slate-300 rounded-md bg-white hover:bg-slate-50 text-slate-700 transition-colors shrink-0">
                            <i class="fa-solid fa-file-csv"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Pendaftar Terbaru -->
<section class="bg-white border border-slate-300 rounded-md shadow-none overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="text-base font-bold text-slate-900">Pendaftar terbaru</h2>
            <p class="text-xs text-slate-600">5 pendaftaran terakhir yang masuk ke sistem.</p>
        </div>
        <a href="participants.php" class="text-xs font-medium text-slate-700 hover:text-slate-900 inline-flex items-center gap-1">
            <span>Kelola semua peserta</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($recentParticipants)): ?>
        <div class="p-8 text-center text-sm text-slate-500">Belum ada peserta yang terdaftar.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                        <th class="px-5 py-3">Kode tiket</th>
                        <th class="px-5 py-3">Nama &amp; NIM</th>
                        <th class="px-5 py-3">Event diikuti</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <?php foreach ($recentParticipants as $peserta): ?>
                        <?php $isHadir = ($peserta['attendance_status'] === 'Hadir'); ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                                <?= htmlspecialchars($peserta['ticket_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-900 capitalize"><?= htmlspecialchars($peserta['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-600">NIM: <?= htmlspecialchars($peserta['identity_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-5 py-3.5 text-xs sm:text-sm text-slate-700">
                                <?= htmlspecialchars($peserta['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $isHadir
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                    : 'border-amber-300 bg-amber-50 text-amber-800' ?>">
                                    <?= htmlspecialchars($peserta['attendance_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <a href="ticket.php?id=<?= (int) $peserta['id'] ?>"
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
