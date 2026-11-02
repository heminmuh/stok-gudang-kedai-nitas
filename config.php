<?php
/*
 * KONFIGURASI APLIKASI
 * Ubah nilai DB_* sesuai database di hosting Anda (cPanel > MySQL Databases).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'stok_gudang');   // di cPanel biasanya berawalan nama akun, mis. akunanda_stok
define('DB_USER', 'root');          // user database
define('DB_PASS', '');              // kata sandi database

define('APP_NAME', "Stok Gudang Kedai Nita's");
define('EXPIRY_DAYS', 7);           // N hari peringatan "segera kedaluwarsa" (aturan BR-07)
define('SESSION_TIMEOUT', 7200);    // detik tanpa aktivitas sebelum otomatis logout (2 jam)
define('TIMEZONE', 'Asia/Jakarta');
define('DEBUG', false);             // true hanya saat memeriksa galat koneksi
