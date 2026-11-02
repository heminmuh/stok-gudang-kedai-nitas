-- ============================================================
-- Sistem Informasi Manajemen Stok Gudang - Kedai Nita's Rembang
-- Capstone Project 139 (B) | MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4)
-- Impor file ini ke database KOSONG (phpMyAdmin > Import), atau jalankan install.php
-- ============================================================

CREATE TABLE pengguna (
  id_pengguna INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_lengkap VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  peran ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uq_pengguna_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lokasi_rak (
  id_rak INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_rak VARCHAR(20) NOT NULL,
  nama_rak VARCHAR(100) NOT NULL,
  jenis VARCHAR(30) DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_rak),
  UNIQUE KEY uq_rak_kode (kode_rak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier (
  id_supplier INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_supplier VARCHAR(120) NOT NULL,
  kontak VARCHAR(100) DEFAULT NULL,
  telepon VARCHAR(30) DEFAULT NULL,
  alamat VARCHAR(255) DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_supplier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE barang (
  id_barang INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_barang VARCHAR(30) NOT NULL,
  nama_barang VARCHAR(120) NOT NULL,
  kategori VARCHAR(60) DEFAULT NULL,
  satuan VARCHAR(20) NOT NULL,
  stok_minimum DECIMAL(12,2) NOT NULL DEFAULT 0,
  stok_saat_ini DECIMAL(12,2) NOT NULL DEFAULT 0,
  harga_beli_terakhir DECIMAL(14,2) NOT NULL DEFAULT 0,
  id_rak INT UNSIGNED DEFAULT NULL,
  id_supplier_utama INT UNSIGNED DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_barang),
  UNIQUE KEY uq_barang_kode (kode_barang),
  KEY idx_barang_rak (id_rak),
  KEY idx_barang_supplier (id_supplier_utama),
  CONSTRAINT fk_barang_rak FOREIGN KEY (id_rak) REFERENCES lokasi_rak (id_rak),
  CONSTRAINT fk_barang_supplier FOREIGN KEY (id_supplier_utama) REFERENCES supplier (id_supplier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inbound (
  id_inbound INT UNSIGNED NOT NULL AUTO_INCREMENT,
  no_transaksi VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  id_supplier INT UNSIGNED DEFAULT NULL,
  jenis ENUM('pembelian','adjustment') NOT NULL DEFAULT 'pembelian',
  foto_nota VARCHAR(255) DEFAULT NULL,
  catatan VARCHAR(255) DEFAULT NULL,
  id_pengguna INT UNSIGNED NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_inbound),
  UNIQUE KEY uq_inbound_no (no_transaksi),
  KEY idx_inbound_tanggal (tanggal),
  KEY idx_inbound_supplier (id_supplier),
  KEY idx_inbound_pengguna (id_pengguna),
  CONSTRAINT fk_inbound_supplier FOREIGN KEY (id_supplier) REFERENCES supplier (id_supplier),
  CONSTRAINT fk_inbound_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inbound_detail (
  id_inbound_detail INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_inbound INT UNSIGNED NOT NULL,
  id_barang INT UNSIGNED NOT NULL,
  jumlah DECIMAL(12,2) NOT NULL,
  sisa_jumlah DECIMAL(12,2) NOT NULL,
  harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0,
  tgl_kedaluwarsa DATE DEFAULT NULL,
  PRIMARY KEY (id_inbound_detail),
  KEY idx_ind_inbound (id_inbound),
  KEY idx_ind_fefo (id_barang, tgl_kedaluwarsa),
  CONSTRAINT fk_ind_inbound FOREIGN KEY (id_inbound) REFERENCES inbound (id_inbound),
  CONSTRAINT fk_ind_barang FOREIGN KEY (id_barang) REFERENCES barang (id_barang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbound (
  id_outbound INT UNSIGNED NOT NULL AUTO_INCREMENT,
  no_transaksi VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  tujuan VARCHAR(40) NOT NULL,
  catatan VARCHAR(255) DEFAULT NULL,
  id_pengguna INT UNSIGNED NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_outbound),
  UNIQUE KEY uq_outbound_no (no_transaksi),
  KEY idx_outbound_tanggal (tanggal),
  KEY idx_outbound_pengguna (id_pengguna),
  CONSTRAINT fk_outbound_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbound_detail (
  id_outbound_detail INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_outbound INT UNSIGNED NOT NULL,
  id_barang INT UNSIGNED NOT NULL,
  id_inbound_detail INT UNSIGNED NOT NULL,
  jumlah DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id_outbound_detail),
  KEY idx_outd_outbound (id_outbound),
  KEY idx_outd_barang (id_barang),
  KEY idx_outd_batch (id_inbound_detail),
  CONSTRAINT fk_outd_outbound FOREIGN KEY (id_outbound) REFERENCES outbound (id_outbound),
  CONSTRAINT fk_outd_barang FOREIGN KEY (id_barang) REFERENCES barang (id_barang),
  CONSTRAINT fk_outd_batch FOREIGN KEY (id_inbound_detail) REFERENCES inbound_detail (id_inbound_detail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_opname (
  id_opname INT UNSIGNED NOT NULL AUTO_INCREMENT,
  no_transaksi VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan VARCHAR(255) DEFAULT NULL,
  id_pengguna INT UNSIGNED NOT NULL,
  id_penyetuju INT UNSIGNED DEFAULT NULL,
  tgl_persetujuan DATETIME DEFAULT NULL,
  catatan_penyetuju VARCHAR(255) DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_opname),
  UNIQUE KEY uq_opname_no (no_transaksi),
  KEY idx_opname_pengguna (id_pengguna),
  KEY idx_opname_penyetuju (id_penyetuju),
  CONSTRAINT fk_opname_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna),
  CONSTRAINT fk_opname_penyetuju FOREIGN KEY (id_penyetuju) REFERENCES pengguna (id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_opname_detail (
  id_opname_detail INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_opname INT UNSIGNED NOT NULL,
  id_barang INT UNSIGNED NOT NULL,
  stok_sistem DECIMAL(12,2) NOT NULL,
  stok_fisik DECIMAL(12,2) NOT NULL,
  selisih DECIMAL(12,2) NOT NULL,
  alasan VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_opname_detail),
  KEY idx_opd_opname (id_opname),
  KEY idx_opd_barang (id_barang),
  CONSTRAINT fk_opd_opname FOREIGN KEY (id_opname) REFERENCES stock_opname (id_opname),
  CONSTRAINT fk_opd_barang FOREIGN KEY (id_barang) REFERENCES barang (id_barang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kartu_stok (
  id_kartu INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_barang INT UNSIGNED NOT NULL,
  waktu DATETIME NOT NULL,
  jenis ENUM('masuk','keluar','adjustment') NOT NULL,
  referensi VARCHAR(30) NOT NULL,
  jumlah DECIMAL(12,2) NOT NULL,
  saldo DECIMAL(12,2) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  id_pengguna INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_kartu),
  KEY idx_kartu_barang (id_barang, waktu),
  KEY idx_kartu_pengguna (id_pengguna),
  CONSTRAINT fk_kartu_barang FOREIGN KEY (id_barang) REFERENCES barang (id_barang),
  CONSTRAINT fk_kartu_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ DATA AWAL ============

-- Akun Admin/Owner: username nita / kata sandi nita123 (segera ganti setelah login pertama)
INSERT INTO pengguna (id_pengguna, nama_lengkap, username, password_hash, peran, aktif) VALUES
(1, 'Nita Widiastuti', 'nita', '$2y$12$3oNy8DQJyYGHiYaNlgyDmuffo8XD88Ck2f0nlBQgUD1qkbdyqc4pO', 'admin', 1);

INSERT INTO lokasi_rak (id_rak, kode_rak, nama_rak, jenis, keterangan) VALUES
(1, 'RK-01', 'Rak Bahan Kering', 'Rak', 'Tepung, gula, minyak, beras'),
(2, 'CH-01', 'Chiller Utama', 'Chiller', 'Produk susu, telur, sayur segar'),
(3, 'FZ-01', 'Freezer', 'Freezer', 'Daging dan olahan beku'),
(4, 'RK-02', 'Rak Bumbu dan Kopi', 'Rak', 'Bumbu kering dan kopi');

-- Contoh supplier: ganti dengan pemasok sebenarnya
INSERT INTO supplier (id_supplier, nama_supplier, kontak, telepon, alamat, aktif) VALUES
(1, 'UD Sumber Pangan Rembang', 'Bapak Slamet', '081200000001', 'Rembang, Jawa Tengah', 1),
(2, 'CV Segar Dairy Jateng', 'Ibu Rina', '081200000002', 'Rembang, Jawa Tengah', 1),
(3, 'Pasar Rembang - Pak Joko', 'Pak Joko', '081200000003', 'Pasar Rembang', 1);

-- Contoh barang & stok awal (data simulasi, silakan ganti sesuai kondisi gudang)
INSERT INTO barang (id_barang, kode_barang, nama_barang, kategori, satuan, stok_minimum, stok_saat_ini, harga_beli_terakhir, id_rak, id_supplier_utama, aktif) VALUES
(1, 'BB-001', 'Tepung Terigu', 'Bahan Kering', 'kg', 10, 32.00, 12000.00, 1, 1, 1),
(2, 'BB-002', 'Gula Pasir', 'Bahan Kering', 'kg', 8, 20.00, 16000.00, 1, 1, 1),
(3, 'BB-003', 'Minyak Goreng', 'Bahan Kering', 'liter', 10, 28.00, 18000.00, 1, 1, 1),
(4, 'BB-004', 'Beras', 'Bahan Kering', 'kg', 20, 70.00, 14000.00, 1, 1, 1),
(5, 'BB-005', 'Telur Ayam', 'Produk Segar', 'kg', 10, 28.00, 29000.00, 2, 2, 1),
(6, 'BB-006', 'Susu UHT', 'Produk Susu', 'liter', 12, 42.00, 17500.00, 2, 2, 1),
(7, 'BB-007', 'Daging Ayam Fillet', 'Daging & Unggas', 'kg', 10, 15.00, 38000.00, 3, 3, 1),
(8, 'BB-008', 'Keju Mozzarella', 'Produk Susu', 'kg', 3, 7.00, 120000.00, 2, 2, 1),
(9, 'BB-009', 'Bawang Putih', 'Sayur & Bumbu', 'kg', 3, 2.00, 32000.00, 4, 3, 1),
(10, 'BB-010', 'Cabai Merah', 'Sayur & Bumbu', 'kg', 3, 2.00, 45000.00, 2, 3, 1),
(11, 'BB-011', 'Kopi Bubuk', 'Minuman', 'kg', 4, 3.00, 90000.00, 4, 1, 1),
(12, 'BB-012', 'Mentega', 'Produk Susu', 'kg', 4, 7.00, 95000.00, 2, 2, 1);

INSERT INTO inbound (id_inbound, no_transaksi, tanggal, id_supplier, jenis, catatan, id_pengguna, dibuat_pada) VALUES
(1, CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 21 DAY), 1, 'pembelian', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00')),
(2, CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 2, 'pembelian', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00')),
(3, CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 3, 'pembelian', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00')),
(4, CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1, 'pembelian', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00'));

INSERT INTO inbound_detail (id_inbound_detail, id_inbound, id_barang, jumlah, sisa_jumlah, harga_beli, tgl_kedaluwarsa) VALUES
(1, 1, 1, 50.00, 32.00, 12000.00, DATE_ADD(CURDATE(), INTERVAL 180 DAY)),
(2, 1, 2, 25.00, 20.00, 16000.00, DATE_ADD(CURDATE(), INTERVAL 300 DAY)),
(3, 1, 3, 40.00, 28.00, 18000.00, DATE_ADD(CURDATE(), INTERVAL 240 DAY)),
(4, 1, 4, 100.00, 70.00, 14000.00, DATE_ADD(CURDATE(), INTERVAL 365 DAY)),
(5, 1, 11, 10.00, 3.00, 90000.00, DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
(6, 2, 5, 30.00, 8.00, 28000.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(7, 2, 6, 48.00, 18.00, 17000.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY)),
(8, 2, 8, 10.00, 7.00, 120000.00, DATE_ADD(CURDATE(), INTERVAL 6 DAY)),
(9, 2, 12, 12.00, 7.00, 95000.00, DATE_ADD(CURDATE(), INTERVAL 40 DAY)),
(10, 3, 7, 40.00, 15.00, 38000.00, DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
(11, 3, 9, 8.00, 2.00, 32000.00, DATE_ADD(CURDATE(), INTERVAL 25 DAY)),
(12, 3, 10, 6.00, 2.00, 45000.00, DATE_ADD(CURDATE(), INTERVAL 4 DAY)),
(13, 4, 5, 20.00, 20.00, 29000.00, DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
(14, 4, 6, 24.00, 24.00, 17500.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY));

INSERT INTO outbound (id_outbound, no_transaksi, tanggal, tujuan, catatan, id_pengguna, dibuat_pada) VALUES
(1, CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'produksi_dapur', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00')),
(2, CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'produksi_dapur', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00')),
(3, CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'produksi_dapur', 'Data contoh', 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'));

INSERT INTO outbound_detail (id_outbound_detail, id_outbound, id_barang, id_inbound_detail, jumlah) VALUES
(1, 1, 1, 1, 10.00),
(2, 1, 2, 2, 3.00),
(3, 1, 3, 3, 6.00),
(4, 1, 4, 4, 15.00),
(5, 1, 11, 5, 4.00),
(6, 2, 1, 1, 8.00),
(7, 2, 2, 2, 2.00),
(8, 2, 3, 3, 6.00),
(9, 2, 4, 4, 15.00),
(10, 2, 11, 5, 3.00),
(11, 2, 5, 6, 12.00),
(12, 2, 6, 7, 15.00),
(13, 2, 8, 8, 3.00),
(14, 3, 5, 6, 10.00),
(15, 3, 6, 7, 15.00),
(16, 3, 12, 9, 5.00),
(17, 3, 7, 10, 25.00),
(18, 3, 9, 11, 6.00),
(19, 3, 10, 12, 4.00);

INSERT INTO kartu_stok (id_barang, waktu, jenis, referensi, jumlah, saldo, keterangan, id_pengguna) VALUES
(1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), 50.00, 50.00, 'Barang masuk dari supplier', 1),
(1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), -10.00, 40.00, 'Barang keluar - Produksi dapur', 1),
(1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -8.00, 32.00, 'Barang keluar - Produksi dapur', 1),
(2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), 25.00, 25.00, 'Barang masuk dari supplier', 1),
(2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), -3.00, 22.00, 'Barang keluar - Produksi dapur', 1),
(2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -2.00, 20.00, 'Barang keluar - Produksi dapur', 1),
(3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), 40.00, 40.00, 'Barang masuk dari supplier', 1),
(3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), -6.00, 34.00, 'Barang keluar - Produksi dapur', 1),
(3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -6.00, 28.00, 'Barang keluar - Produksi dapur', 1),
(4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), 100.00, 100.00, 'Barang masuk dari supplier', 1),
(4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), -15.00, 85.00, 'Barang keluar - Produksi dapur', 1),
(4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -15.00, 70.00, 'Barang keluar - Produksi dapur', 1),
(5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 30.00, 30.00, 'Barang masuk dari supplier', 1),
(5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -12.00, 18.00, 'Barang keluar - Produksi dapur', 1),
(5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -10.00, 8.00, 'Barang keluar - Produksi dapur', 1),
(5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '-0001'), 20.00, 28.00, 'Barang masuk dari supplier', 1),
(6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 48.00, 48.00, 'Barang masuk dari supplier', 1),
(6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -15.00, 33.00, 'Barang keluar - Produksi dapur', 1),
(6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -15.00, 18.00, 'Barang keluar - Produksi dapur', 1),
(6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '-0001'), 24.00, 42.00, 'Barang masuk dari supplier', 1),
(7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '-0001'), 40.00, 40.00, 'Barang masuk dari supplier', 1),
(7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -25.00, 15.00, 'Barang keluar - Produksi dapur', 1),
(8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 10.00, 10.00, 'Barang masuk dari supplier', 1),
(8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -3.00, 7.00, 'Barang keluar - Produksi dapur', 1),
(9, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '-0001'), 8.00, 8.00, 'Barang masuk dari supplier', 1),
(9, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -6.00, 2.00, 'Barang keluar - Produksi dapur', 1),
(10, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '-0001'), 6.00, 6.00, 'Barang masuk dari supplier', 1),
(10, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -4.00, 2.00, 'Barang keluar - Produksi dapur', 1),
(11, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '%Y%m%d'), '-0001'), 10.00, 10.00, 'Barang masuk dari supplier', 1),
(11, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '%Y%m%d'), '-0001'), -4.00, 6.00, 'Barang keluar - Produksi dapur', 1),
(11, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%Y%m%d'), '-0001'), -3.00, 3.00, 'Barang keluar - Produksi dapur', 1),
(12, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), 'masuk', CONCAT('IN-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 12.00, 12.00, 'Barang masuk dari supplier', 1),
(12, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), 'keluar', CONCAT('OUT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), -5.00, 7.00, 'Barang keluar - Produksi dapur', 1);
