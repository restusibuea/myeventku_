-- ============================================================================
-- SQL Dump Database: db_event_uts
-- Proyek UTS Mata Kuliah Metode Model Pengembangan Perangkat Lunak (MMPRPL)
-- Tema: Sistem Informasi Manajemen Event dan Pendaftaran Peserta
-- Catatan: File ini dapat diimpor manual via phpMyAdmin jika diperlukan.
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `db_event_uts`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `db_event_uts`;

-- hapus tabel lama dulu; participants duluan karena dia yang punya foreign key ke events
DROP TABLE IF EXISTS `participants`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- Tabel 1: events (Master Data Event Kampus)
-- ----------------------------------------------------------------------------
CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` ENUM('Seminar', 'Workshop', 'Kompetisi', 'Bootcamp') NOT NULL DEFAULT 'Seminar',
  `event_date` DATETIME NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `quota` INT NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `status` ENUM('Terbuka', 'Penuh', 'Selesai') NOT NULL DEFAULT 'Terbuka',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tabel 2: participants (Data Pendaftaran & Kehadiran Peserta)
-- Dilengkapi UNIQUE KEY (event_id, identity_no) untuk mencegah pendaftaran ganda
-- di tingkat database dan ON DELETE CASCADE agar peserta terhapus saat event dihapus.
-- ----------------------------------------------------------------------------
CREATE TABLE `participants` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Seed Data Awal: 4 Event Kampus Teknologi
-- ----------------------------------------------------------------------------
INSERT INTO `events` (`id`, `title`, `category`, `event_date`, `location`, `quota`, `description`, `status`) VALUES
(1, 'Seminar Nasional AI & Software Engineering 2026', 'Seminar', '2026-11-25 09:00:00', 'Auditorium Gedung Rektorat Lt. 3', 50, 'Seminar nasional membahas penerapan kecerdasan buatan (AI) dan arsitektur perangkat lunak modern bersama praktisi industri teknologi.', 'Terbuka'),
(2, 'Workshop PHP Native & Arsitektur Database MySQL', 'Workshop', '2026-12-02 13:00:00', 'Lab Komputer Rekayasa Perangkat Lunak 1', 30, 'Pelatihan langsung membangun sistem web aman menggunakan PHP 8 PDO Prepared Statements dan desain relasi tabel MySQL.', 'Terbuka'),
(3, 'Kompetisi Inovasi Prototype Mahasiswa TRPL', 'Kompetisi', '2026-12-10 08:30:00', 'Hall Inovasi Kampus Teknologi', 3, 'Kompetisi unjuk karya prototype aplikasi mahasiswa TRPL tingkat program studi.', 'Terbuka'),
(4, 'Bootcamp UI/UX Design & Prototyping', 'Bootcamp', '2026-12-18 09:00:00', 'Ruang Studio Desain Digital Lt. 2', 25, 'Bootcamp intensif merancang antarmuka modern yang ramah pengguna mulai dari riset UX, wireframe, hingga prototipe interaktif.', 'Terbuka');

-- ----------------------------------------------------------------------------
-- Seed Data Awal: 9 Peserta (Event #3 sengaja diisi 2 dari 3 kuota)
-- ----------------------------------------------------------------------------
INSERT INTO `participants` (`event_id`, `ticket_code`, `name`, `identity_no`, `email`, `phone`, `attendance_status`, `check_in_at`) VALUES
(1, 'EVT-2026-1001', 'Yogi Pratama', '240103001', 'yogimo@wbi.ac.id', '081265432109', 'Hadir', '2026-11-15 08:45:00'),
(1, 'EVT-2026-1002', 'Nikola Tesla', '240103002', 'nikolatesla@usu.ac.id', '081377889900', 'Hadir', '2026-11-15 08:52:00'),
(1, 'EVT-2026-1003', 'Yuji Itadori', '240103003', 'yujiitadori@jujutsukaisen.ac.id', '085211223344', 'Terdaftar', NULL),
(2, 'EVT-2026-2001', 'Restu Bajawa Erlintua Sibuea', '2505010007', 'bajawa@unimed.ac.id', '082155667788', 'Hadir', '2026-11-15 12:50:00'),
(2, 'EVT-2026-2002', 'Wisnu Wardana', '250501005', 'wisnu@umsu.ac.id', '081299887766', 'Terdaftar', NULL),
(3, 'EVT-2026-3001', 'Simo Hayha', '240103006', 'simohayha@itb.ac.id', '085277665544', 'Hadir', '2026-11-15 08:15:00'),
(3, 'EVT-2026-3002', 'Gloo', '240103007', 'gloo@telkomuniversity.ac.id', '082133445566', 'Terdaftar', NULL),
(4, 'EVT-2026-4001', 'Grace', '250501008', 'grace@wbi.ac.id', '081311223355', 'Hadir', '2026-11-15 09:05:00'),
(4, 'EVT-2026-4002', 'Dolor Sitamet', '250501009', 'dolorsitamet@usu.ac.id', '085299001122', 'Terdaftar', NULL);

-- ----------------------------------------------------------------------------
-- Tabel 3: users (Akun Login Admin & Pengguna)
-- Password default akun admin: admin123 (hash password_hash PHP)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES
('admin', '$2y$10$BrDXJY2gsqldg1gBpivkEeZh4KHGng69/XVq0r8CAEeC1KsBiFB6C', 'admin');