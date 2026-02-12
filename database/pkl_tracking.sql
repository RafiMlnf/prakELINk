-- ============================================
-- PKL Tracking System - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS pkl_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pkl_tracking;

-- ============================================
-- Table: users
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'pembimbing', 'siswa') NOT NULL DEFAULT 'siswa',
    foto VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Table: penempatan (Placement Locations)
-- ============================================
CREATE TABLE IF NOT EXISTS penempatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_perusahaan VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    radius_meter INT NOT NULL DEFAULT 100,
    kontak_perusahaan VARCHAR(100),
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Table: siswa (Student Profiles)
-- ============================================
CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nisn VARCHAR(20) NOT NULL UNIQUE,
    kelas VARCHAR(20) NOT NULL,
    jurusan VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20),
    penempatan_id INT DEFAULT NULL,
    pembimbing_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (penempatan_id) REFERENCES penempatan(id) ON DELETE SET NULL,
    FOREIGN KEY (pembimbing_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Table: presensi (Attendance)
-- ============================================
CREATE TABLE IF NOT EXISTS presensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME DEFAULT NULL,
    jam_keluar TIME DEFAULT NULL,
    lat_masuk DECIMAL(10, 8) DEFAULT NULL,
    lng_masuk DECIMAL(11, 8) DEFAULT NULL,
    lat_keluar DECIMAL(10, 8) DEFAULT NULL,
    lng_keluar DECIMAL(11, 8) DEFAULT NULL,
    jarak_masuk DECIMAL(10, 2) DEFAULT NULL,
    jarak_keluar DECIMAL(10, 2) DEFAULT NULL,
    status ENUM('hadir', 'izin', 'sakit', 'alpha') NOT NULL DEFAULT 'hadir',
    keterangan TEXT,
    foto_masuk VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_presensi (siswa_id, tanggal)
) ENGINE=InnoDB;

-- ============================================
-- Table: jurnal (Daily Journal)
-- ============================================
CREATE TABLE IF NOT EXISTS jurnal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    judul_kegiatan VARCHAR(200) NOT NULL,
    deskripsi_kegiatan TEXT NOT NULL,
    foto_kegiatan VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'disetujui', 'revisi') NOT NULL DEFAULT 'pending',
    catatan_pembimbing TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: pengajuan_pkl (Placement Requests)
-- ============================================
CREATE TABLE IF NOT EXISTS pengajuan_pkl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    nama_perusahaan VARCHAR(150) NOT NULL,
    alamat_perusahaan TEXT NOT NULL,
    bidang_usaha VARCHAR(100),
    nama_pimpinan VARCHAR(100),
    no_telp_perusahaan VARCHAR(30),
    email_perusahaan VARCHAR(100),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    surat_permohonan VARCHAR(255) DEFAULT NULL,
    surat_balasan VARCHAR(255) DEFAULT NULL,
    dokumen_pendukung VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'disetujui', 'ditolak', 'revisi') NOT NULL DEFAULT 'pending',
    catatan_admin TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Table: notifikasi (Notifications)
-- ============================================
CREATE TABLE IF NOT EXISTS notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipe VARCHAR(50) NOT NULL,
    pesan TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Seed Data: Default Admin
-- ============================================
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@pkltracker.com', 'admin');
-- Default password: password

-- ============================================
-- Seed Data: Sample Pembimbing
-- ============================================
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('pembimbing1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso, S.Pd', 'budi@pkltracker.com', 'pembimbing');

-- ============================================
-- Seed Data: Sample Penempatan
-- ============================================
INSERT INTO penempatan (nama_perusahaan, alamat, latitude, longitude, radius_meter, kontak_perusahaan, tanggal_mulai, tanggal_selesai) VALUES
('PT Teknologi Indonesia', 'Jl. Sudirman No. 123, Jakarta', -6.20876340, 106.84559900, 150, '021-5551234', '2026-01-15', '2026-06-15'),
('CV Maju Bersama', 'Jl. Ahmad Yani No. 45, Surabaya', -7.29050000, 112.73780000, 100, '031-5559876', '2026-01-15', '2026-06-15');

-- ============================================
-- Seed Data: Sample Siswa
-- ============================================
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('siswa1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmad Fauzi', 'ahmad@siswa.com', 'siswa'),
('siswa2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Nurhaliza', 'siti@siswa.com', 'siswa');

INSERT INTO siswa (user_id, nisn, kelas, jurusan, no_hp, penempatan_id, pembimbing_id) VALUES
(3, '1234567890', 'XII RPL 1', 'Rekayasa Perangkat Lunak', '081234567890', 1, 2),
(4, '0987654321', 'XII RPL 2', 'Rekayasa Perangkat Lunak', '089876543210', 2, 2);
