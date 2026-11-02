# Sistem Informasi Manajemen Stok Gudang - Kedai Nita's Rembang

Aplikasi web (PHP + MySQL) untuk mencatat barang masuk (inbound), barang keluar (outbound),
stock control, stock opname, kartu stok, laporan, dan dashboard, sesuai proposal Capstone Project 139 (B).

**Login awal:** username `nita` / kata sandi `nita123` (Admin/Owner). Segera ganti kata sandi lewat menu Pengguna.

## Kebutuhan hosting
- PHP 7.4 atau lebih baru (disarankan 8.x) dengan ekstensi PDO MySQL
- MySQL 5.7+ / MariaDB 10.3+
- Apache (cPanel/shared hosting biasa sudah cukup). Browser pengguna perlu internet (Bootstrap, ikon, Chart.js dimuat dari CDN).

## Cara publikasi (cPanel / shared hosting)
1. **Buat database** di cPanel > *MySQL Databases*: buat database, buat user, lalu beri user hak *All Privileges* pada database tersebut.
2. **Unggah** seluruh isi folder ini ke `public_html` (atau sub-folder) lewat File Manager, lalu ekstrak.
3. **Edit `config.php`**: isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. **Pasang database** dengan salah satu cara:
   - buka `https://domain-anda/install.php` lalu klik *Pasang database*; atau
   - impor `database.sql` lewat phpMyAdmin (*Import*).
5. **Hapus `install.php`** setelah selesai, lalu buka `https://domain-anda/` dan login.
6. Pastikan folder `uploads/` dapat ditulis (izin 755, atau 775 bila perlu) agar foto nota bisa diunggah.

## Menjalankan di komputer sendiri (XAMPP/Laragon)
Salin folder ke `htdocs`, buat database `stok_gudang` (phpMyAdmin), impor `database.sql`, lalu buka `http://localhost/stok-gudang/`.

## Fitur
| Modul | Isi |
|---|---|
| Login & hak akses | Admin/Owner dan Petugas Gudang, sesi otomatis berakhir 2 jam, pembatasan percobaan login |
| Data master | Barang, supplier, lokasi rak, pengguna (kelola hanya Admin) |
| Barang masuk | Nomor otomatis `IN-YYYYMMDD-NNNN`, batch + tanggal kedaluwarsa, foto nota, stok & kartu stok otomatis |
| Barang keluar | Nomor `OUT-...`, **FEFO** otomatis (tanpa tanggal kedaluwarsa memakai FIFO), tolak jika melebihi stok, low stock alert |
| Stock opname | Input hitung fisik, selisih otomatis, alasan wajib, adjustment baru berlaku setelah disetujui Admin |
| Stok & peringatan | Low stock, expiring soon (N = `EXPIRY_DAYS`, default 7 hari), rekomendasi restock |
| Kartu stok | Riwayat mutasi per barang beserta saldo |
| Laporan | Posisi stok, masuk, keluar, kedaluwarsa, opname, pembelian & pemakaian (Admin); ekspor Excel (CSV) dan cetak/PDF |
| Dashboard | Ringkasan, grafik transaksi 7 hari, fast/slow moving |

## Aturan bisnis yang diterapkan
BR-01 nomor transaksi otomatis - BR-02 stok tidak boleh negatif - BR-04 transaksi tersimpan tidak dapat diubah (koreksi lewat adjustment) -
BR-05 pengeluaran FEFO/FIFO - BR-06/07 peringatan stok minimum & kedaluwarsa - BR-08 adjustment beralasan dan disetujui Admin.

## Keamanan
Kata sandi di-hash (bcrypt), semua query memakai prepared statement, token CSRF pada setiap form, output di-escape (anti XSS),
validasi unggahan (hanya JPG/PNG/WebP maks. 2 MB) dan folder `uploads/` tidak mengeksekusi skrip, cookie sesi HttpOnly/SameSite.
Aktifkan HTTPS pada domain Anda. Hapus `install.php` setelah pemasangan.

## Catatan
Data barang, supplier, dan transaksi pada `database.sql` adalah **contoh simulasi**; ganti dengan data Kedai Nita's.
Struktur 11 tabel mengikuti ERD pada proposal (ditambah kolom `jenis` pada `inbound` untuk adjustment opname).
