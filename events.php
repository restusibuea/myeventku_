<?php
// halaman CRUD event: tambah, cari/filter, edit, hapus

// pastikan user sudah login sebelum akses halaman event
require_once 'includes/auth.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Manajemen event';
$allowedCategories = ['Seminar', 'Workshop', 'Kompetisi', 'Bootcamp'];

// semua aksi yang ngubah data (tambah/edit/hapus) lewat POST, dibedain dari input 'action'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $title       = trim((string) ($_POST['title'] ?? ''));
            $category    = trim((string) ($_POST['category'] ?? ''));
            $rawDate     = trim((string) ($_POST['event_date'] ?? ''));
            $location    = trim((string) ($_POST['location'] ?? ''));
            $quota       = (int) ($_POST['quota'] ?? 0);
            $description = trim((string) ($_POST['description'] ?? ''));

            // cek input dulu sebelum disimpan, jangan langsung percaya data dari form
            if ($title === '' || $location === '' || $rawDate === '') {
                setFlash('error', 'Judul event, tanggal pelaksanaan, dan lokasi wajib diisi.');
                header('Location: events.php?action=new');
                exit;
            }

            if (!in_array($category, $allowedCategories, true)) {
                setFlash('error', 'Kategori event tidak valid.');
                header('Location: events.php?action=new');
                exit;
            }

            if ($quota < 1) {
                setFlash('error', 'Kuota peserta minimal harus 1 orang.');
                header('Location: events.php?action=new');
                exit;
            }

            $timestamp = strtotime($rawDate);
            if ($timestamp === false) {
                setFlash('error', 'Format tanggal dan waktu pelaksanaan tidak valid.');
                header('Location: events.php?action=new');
                exit;
            }
            $formattedDate = date('Y-m-d H:i:s', $timestamp);

            // event baru pasti belum ada pesertanya, jadi statusnya langsung Terbuka
            $stmtInsert = $pdo->prepare('
                INSERT INTO events (title, category, event_date, location, quota, description, status)
                VALUES (:title, :category, :event_date, :location, :quota, :description, :status)
            ');
            $stmtInsert->execute([
                'title'       => $title,
                'category'    => $category,
                'event_date'  => $formattedDate,
                'location'    => $location,
                'quota'       => $quota,
                'description' => $description,
                'status'      => 'Terbuka',
            ]);

            setFlash('success', 'Event "' . $title . '" berhasil ditambahkan dengan kuota ' . $quota . ' peserta.');
            header('Location: events.php');
            exit;
        }

        if ($action === 'update') {
            $eventId     = (int) ($_POST['id'] ?? 0);
            $title       = trim((string) ($_POST['title'] ?? ''));
            $category    = trim((string) ($_POST['category'] ?? ''));
            $rawDate     = trim((string) ($_POST['event_date'] ?? ''));
            $location    = trim((string) ($_POST['location'] ?? ''));
            $quota       = (int) ($_POST['quota'] ?? 0);
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($eventId <= 0 || $title === '' || $location === '' || $rawDate === '') {
                setFlash('error', 'Data event tidak lengkap untuk diperbarui.');
                header('Location: events.php');
                exit;
            }

            if (!in_array($category, $allowedCategories, true)) {
                setFlash('error', 'Kategori event tidak valid.');
                header('Location: events.php?edit=' . $eventId);
                exit;
            }

            if ($quota < 1) {
                setFlash('error', 'Kuota peserta minimal harus 1 orang.');
                header('Location: events.php?edit=' . $eventId);
                exit;
            }

            $timestamp = strtotime($rawDate);
            if ($timestamp === false) {
                setFlash('error', 'Format tanggal dan waktu pelaksanaan tidak valid.');
                header('Location: events.php?edit=' . $eventId);
                exit;
            }
            $formattedDate = date('Y-m-d H:i:s', $timestamp);

            // cek dulu sudah berapa yang daftar, buat validasi kuota baru
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE event_id = :event_id');
            $stmtCount->execute(['event_id' => $eventId]);
            $registeredCount = (int) $stmtCount->fetchColumn();

            // kuota nggak boleh dikecilin di bawah jumlah peserta yang sudah daftar, nanti datanya nggak masuk akal
            if ($quota < $registeredCount) {
                setFlash('error', "Gagal memperbarui! Kuota baru ({$quota}) tidak boleh lebih kecil dari peserta yang sudah terdaftar ({$registeredCount} orang).");
                header('Location: events.php?edit=' . $eventId);
                exit;
            }

            // kuota dinaikin melebihi peserta -> Terbuka lagi, kalau sama persis -> Penuh
            $newStatus = ($quota > $registeredCount) ? 'Terbuka' : 'Penuh';

            // simpan perubahan event sekalian status barunya
            $stmtUpdate = $pdo->prepare('
                UPDATE events
                SET title = :title,
                    category = :category,
                    event_date = :event_date,
                    location = :location,
                    quota = :quota,
                    description = :description,
                    status = :status
                WHERE id = :id
            ');
            $stmtUpdate->execute([
                'title'       => $title,
                'category'    => $category,
                'event_date'  => $formattedDate,
                'location'    => $location,
                'quota'       => $quota,
                'description' => $description,
                'status'      => $newStatus,
                'id'          => $eventId,
            ]);

            setFlash('success', 'Event "' . $title . '" berhasil diperbarui (Status otomatis: ' . $newStatus . ').');
            header('Location: events.php');
            exit;
        }

        if ($action === 'delete') {
            $eventId = (int) ($_POST['id'] ?? 0);

            if ($eventId <= 0) {
                setFlash('error', 'ID event yang akan dihapus tidak valid.');
                header('Location: events.php');
                exit;
            }

            // ambil judulnya dulu buat ditampilin di notif setelah dihapus
            $stmtFind = $pdo->prepare('SELECT title FROM events WHERE id = :id');
            $stmtFind->execute(['id' => $eventId]);
            $eventRow = $stmtFind->fetch();

            if (!$eventRow) {
                setFlash('error', 'Event tidak ditemukan atau sudah dihapus sebelumnya.');
                header('Location: events.php');
                exit;
            }

            // cukup hapus event-nya, peserta di event itu otomatis ikut kehapus karena ON DELETE CASCADE
            $stmtDelete = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $stmtDelete->execute(['id' => $eventId]);

            setFlash('success', 'Event "' . $eventRow['title'] . '" beserta seluruh data pesertanya berhasil dihapus.');
            header('Location: events.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('error', 'Terjadi kesalahan database saat memproses data event. Silakan periksa kembali input Anda.');
        header('Location: events.php');
        exit;
    }
}

// bagian GET: mode edit, pencarian, dan filter kategori
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editEvent = null;
$editRegisteredCount = 0;

if ($editId > 0) {
    // ambil data event yang mau diedit buat ngisi form
    $stmtEdit = $pdo->prepare('SELECT * FROM events WHERE id = :id');
    $stmtEdit->execute(['id' => $editId]);
    $editEvent = $stmtEdit->fetch();

    if ($editEvent) {
        // jumlah peserta event ini, dipakai jadi batas minimal kuota di form
        $stmtEditCount = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE event_id = :event_id');
        $stmtEditCount->execute(['event_id' => $editId]);
        $editRegisteredCount = (int) $stmtEditCount->fetchColumn();
    } else {
        setFlash('error', 'Event yang ingin Anda edit tidak ditemukan.');
        header('Location: events.php');
        exit;
    }
}

$showForm = ($editEvent !== null) || (($_GET['action'] ?? '') === 'new');

$searchQuery    = trim((string) ($_GET['q'] ?? ''));
$filterCategory = trim((string) ($_GET['category'] ?? ''));

$whereClauses = [];
$queryParams  = [];

if ($searchQuery !== '') {
    $whereClauses[] = '(e.title LIKE :search_title OR e.location LIKE :search_location)';
    $queryParams['search_title']    = '%' . $searchQuery . '%';
    $queryParams['search_location'] = '%' . $searchQuery . '%';
}

if ($filterCategory !== '' && in_array($filterCategory, $allowedCategories, true)) {
    $whereClauses[] = 'e.category = :category';
    $queryParams['category'] = $filterCategory;
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// list event sesuai keyword & kategori (keyword tetap lewat parameter, nggak ditempel langsung ke SQL)
$stmtList = $pdo->prepare("
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
        COUNT(p.id) AS terisi
    FROM events e
    LEFT JOIN participants p ON e.id = p.event_id
    {$whereSql}
    GROUP BY e.id, e.title, e.category, e.event_date, e.location, e.quota, e.description, e.status, e.created_at
    ORDER BY e.event_date ASC
");
$stmtList->execute($queryParams);
$eventsList = $stmtList->fetchAll();

$statusStyles = [
    'Terbuka' => 'border-emerald-300 bg-emerald-50 text-emerald-800',
    'Penuh'   => 'border-rose-300 bg-rose-50 text-rose-800',
    'Selesai' => 'border-slate-300 bg-slate-100 text-slate-700',
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Judul Halaman -->
<section class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            Manajemen event kampus
        </h1>
        <p class="mt-1 text-sm text-slate-600">
            Kelola jadwal kegiatan, kapasitas kuota, dan status pendaftaran peserta.
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($editEvent !== null): ?>
            <a href="events.php"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium transition-colors">
                <i class="fa-solid fa-xmark"></i>
                <span>Batal edit</span>
            </a>
        <?php else: ?>
            <button type="button"
                    onclick="toggleEventForm()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid fa-plus"></i>
                <span>Tambah event baru</span>
            </button>
        <?php endif; ?>
    </div>
</section>

<!-- Form Tambah/Edit Event -->
<section id="eventFormSection"
         class="mb-6 bg-white border border-slate-300 rounded-md shadow-none overflow-hidden <?= $showForm ? '' : 'hidden' ?>">
    <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                <?= $editEvent ? 'Edit data event' : 'Tambah event baru' ?>
            </h2>
            <p class="text-xs text-slate-600">
                <?= $editEvent
                    ? 'Perubahan kuota akan menyinkronkan status terbuka atau penuh secara otomatis.'
                    : 'Isi data event untuk membuka pendaftaran peserta.' ?>
            </p>
        </div>
        <button type="button"
                onclick="toggleEventForm(false)"
                class="text-slate-400 hover:text-slate-600 p-1.5 rounded-md"
                title="Tutup form">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <form method="POST" action="events.php" class="p-5 space-y-4">
        <input type="hidden" name="action" value="<?= $editEvent ? 'update' : 'create' ?>">
        <?php if ($editEvent): ?>
            <input type="hidden" name="id" value="<?= (int) $editEvent['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Judul Event -->
            <div class="md:col-span-2">
                <label for="title" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Judul event <span class="text-rose-600">*</span>
                </label>
                <input type="text"
                       id="title"
                       name="title"
                       required
                       maxlength="150"
                       placeholder="Masukkan nama event..."
                       value="<?= htmlspecialchars($editEvent['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- Kategori -->
            <div>
                <label for="category" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Kategori <span class="text-rose-600">*</span>
                </label>
                <select id="category"
                        name="category"
                        required
                        class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                    <?php $selectedCat = $editEvent['category'] ?? 'Seminar'; ?>
                    <?php foreach ($allowedCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedCat === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tanggal & Waktu -->
            <div>
                <label for="event_date" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Tanggal &amp; waktu pelaksanaan <span class="text-rose-600">*</span>
                </label>
                <?php
                    $dateInputValue = '';
                    if (!empty($editEvent['event_date'])) {
                        $ts = strtotime($editEvent['event_date']);
                        if ($ts !== false) {
                            $dateInputValue = date('Y-m-d\TH:i', $ts);
                        }
                    }
                ?>
                <input type="datetime-local"
                       id="event_date"
                       name="event_date"
                       required
                       value="<?= htmlspecialchars($dateInputValue, ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- Lokasi -->
            <div>
                <label for="location" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Lokasi kegiatan <span class="text-rose-600">*</span>
                </label>
                <input type="text"
                       id="location"
                       name="location"
                       required
                       maxlength="150"
                       placeholder="Masukkan lokasi acara..."
                       value="<?= htmlspecialchars($editEvent['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
            </div>

            <!-- Kuota -->
            <div>
                <label for="quota" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Kuota maksimal peserta <span class="text-rose-600">*</span>
                </label>
                <input type="number"
                       id="quota"
                       name="quota"
                       required
                       min="<?= $editEvent ? max(1, $editRegisteredCount) : 1 ?>"
                       placeholder="Masukkan kuota peserta..."
                       value="<?= htmlspecialchars((string) ($editEvent['quota'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                <?php if ($editEvent): ?>
                    <p class="mt-1 text-xs text-slate-600">
                        Terdaftar saat ini: <strong><?= $editRegisteredCount ?> peserta</strong>.
                        Jika kuota &gt; <?= $editRegisteredCount ?>, status menjadi <strong>Terbuka</strong>; jika sama, menjadi <strong>Penuh</strong>.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Deskripsi -->
            <div class="md:col-span-2">
                <label for="description" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Deskripsi singkat event
                </label>
                <textarea id="description"
                          name="description"
                          rows="3"
                          placeholder="Tulis deskripsi singkat acara (opsional)..."
                          class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none"><?= htmlspecialchars($editEvent['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <div class="pt-3 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200">
            <?php if ($editEvent): ?>
                <a href="events.php"
                   class="px-4 py-2 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium transition-colors">
                    Batal
                </a>
            <?php endif; ?>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid <?= $editEvent ? 'fa-floppy-disk' : 'fa-circle-plus' ?>"></i>
                <span><?= $editEvent ? 'Simpan perubahan' : 'Simpan event baru' ?></span>
            </button>
        </div>
    </form>
</section>

<!-- Pencarian & Filter -->
<section class="mb-6 bg-white border border-slate-300 rounded-md shadow-none p-4">
    <form method="GET" action="events.php" class="flex flex-col md:flex-row items-stretch md:items-center gap-3">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text"
                   name="q"
                   value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Cari nama event atau lokasi..."
                   class="w-full pl-10 pr-4 py-2 rounded-md border border-slate-300 text-sm focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
        </div>

        <div class="w-full md:w-56">
            <select name="category"
                    class="w-full px-3.5 py-2 rounded-md border border-slate-300 text-sm bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none">
                <option value="">Semua kategori</option>
                <?php foreach ($allowedCategories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition-colors">
                <i class="fa-solid fa-filter text-xs"></i>
                <span>Terapkan</span>
            </button>

            <?php if ($searchQuery !== '' || $filterCategory !== ''): ?>
                <a href="events.php"
                   class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-md bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium transition-colors">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Daftar Event -->
<?php if (empty($eventsList)): ?>
    <div class="bg-white border border-slate-300 rounded-md shadow-none p-10 text-center">
        <p class="font-semibold text-slate-700">Tidak ada event yang sesuai dengan pencarian atau filter.</p>
        <p class="text-sm text-slate-600 mt-1">Ubah kata kunci pencarian atau tambah event baru.</p>
    </div>
<?php else: ?>

    <!-- Tabel (desktop) -->
    <section class="hidden lg:block bg-white border border-slate-300 rounded-md shadow-none overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                        <th class="px-5 py-3">Event &amp; lokasi</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Jadwal</th>
                        <th class="px-4 py-3 w-48">Kuota terisi</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <?php foreach ($eventsList as $event): ?>
                        <?php
                            $quota   = (int) $event['quota'];
                            $terisi  = (int) $event['terisi'];
                            $persen  = ($quota > 0) ? min(100, (int) round(($terisi / $quota) * 100)) : 0;
                            $isPenuh = ($event['status'] === 'Penuh' || $terisi >= $quota);

                            $statBadge = $statusStyles[$event['status']] ?? 'border-slate-300 bg-slate-100 text-slate-700';
                            $barColor  = ($persen >= 100) ? 'bg-rose-600' : (($persen >= 75) ? 'bg-amber-500' : 'bg-slate-800');
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4 max-w-xs">
                                <p class="font-semibold text-slate-900 leading-snug">
                                    <?= htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="mt-1 text-xs text-slate-600 flex items-center gap-1.5">
                                    <i class="fa-solid fa-location-dot text-slate-600"></i>
                                    <span><?= htmlspecialchars($event['location'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </p>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-xs font-semibold tracking-wider text-slate-600">
                                    <?= htmlspecialchars($event['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-600 whitespace-nowrap">
                                <?= htmlspecialchars(formatTanggalIndo($event['event_date'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-between text-xs font-medium mb-1">
                                    <span class="text-slate-800"><?= $terisi ?> / <?= $quota ?></span>
                                    <span class="text-slate-600"><?= $persen ?>%</span>
                                </div>
                                <div class="w-full h-2 bg-slate-200 rounded overflow-hidden">
                                    <div class="h-full <?= $barColor ?>" style="width: <?= $persen ?>%"></div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $statBadge ?>">
                                    <?= htmlspecialchars($event['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <?php if (!$isPenuh && $event['status'] !== 'Selesai'): ?>
                                        <a href="participants.php?event_id=<?= (int) $event['id'] ?>#form-daftar"
                                           title="Daftarkan peserta ke event ini"
                                           class="px-2.5 py-1.5 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition-colors">
                                            <i class="fa-solid fa-user-plus"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="export_csv.php?event_id=<?= (int) $event['id'] ?>"
                                       title="Export CSV"
                                       aria-label="Export CSV"
                                       class="w-9 h-9 flex items-center justify-center border border-slate-300 rounded-md bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium transition-colors">
                                        <i class="fa-solid fa-file-csv"></i>
                                    </a>

                                    <a href="events.php?edit=<?= (int) $event['id'] ?>"
                                       title="Kelola event"
                                       class="px-2.5 py-1.5 rounded-md text-slate-700 hover:bg-slate-100 text-xs font-medium transition-colors">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </a>

                                    <form method="POST"
                                          action="events.php"
                                          class="inline"
                                          onsubmit="return confirm('Yakin ingin menghapus event ini? Seluruh peserta yang terdaftar di event ini juga akan ikut terhapus secara otomatis.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                                        <button type="submit"
                                                title="Hapus event"
                                                class="w-9 h-9 flex items-center justify-center rounded-md bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 text-xs font-medium transition-colors">
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
    </section>

    <!-- Kartu (mobile) -->
    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:hidden">
        <?php foreach ($eventsList as $event): ?>
            <?php
                $quota   = (int) $event['quota'];
                $terisi  = (int) $event['terisi'];
                $persen  = ($quota > 0) ? min(100, (int) round(($terisi / $quota) * 100)) : 0;
                $isPenuh = ($event['status'] === 'Penuh' || $terisi >= $quota);

                $statBadge = $statusStyles[$event['status']] ?? 'border-slate-300 bg-slate-100 text-slate-700';
                $barColor  = ($persen >= 100) ? 'bg-rose-600' : (($persen >= 75) ? 'bg-amber-500' : 'bg-slate-800');
            ?>
            <article class="bg-white border border-slate-300 rounded-md shadow-none p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <span class="text-xs font-semibold tracking-wider text-slate-600">
                            <?= htmlspecialchars($event['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="rounded border px-2 py-0.5 text-xs font-semibold <?= $statBadge ?>">
                            <?= htmlspecialchars($event['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <h3 class="font-bold text-slate-900">
                        <?= htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <p class="mt-2 text-xs text-slate-600">
                        <i class="fa-regular fa-clock mr-1 text-slate-600"></i>
                        <?= htmlspecialchars(formatTanggalIndo($event['event_date'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mt-1 text-xs text-slate-600">
                        <i class="fa-solid fa-location-dot mr-1 text-slate-600"></i>
                        <?= htmlspecialchars($event['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div class="mt-4">
                        <div class="flex justify-between text-xs font-medium mb-1">
                            <span>Kuota: <?= $terisi ?> / <?= $quota ?></span>
                            <span><?= $persen ?>%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-200 rounded overflow-hidden">
                            <div class="h-full <?= $barColor ?>" style="width: <?= $persen ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200 flex flex-wrap items-center gap-2">
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

                    <form method="POST"
                          action="events.php"
                          class="inline shrink-0"
                          onsubmit="return confirm('Yakin ingin menghapus event ini beserta seluruh pesertanya?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                        <button type="submit"
                                title="Hapus event"
                                aria-label="Hapus event"
                                class="w-9 h-9 flex items-center justify-center rounded-md bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 text-xs font-medium transition-colors">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

<?php endif; ?>

<script>
    function toggleEventForm(forceOpen) {
        const section = document.getElementById('eventFormSection');
        if (!section) return;

        if (typeof forceOpen === 'boolean') {
            section.classList.toggle('hidden', !forceOpen);
        } else {
            section.classList.toggle('hidden');
        }

        if (!section.classList.contains('hidden')) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
