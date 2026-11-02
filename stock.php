<?php
/*
 * Logika inti persediaan:
 *  - penomoran transaksi otomatis (BR-01)
 *  - pencatatan kartu stok untuk setiap mutasi
 *  - pengeluaran FEFO; batch tanpa tanggal kedaluwarsa memakai FIFO (BR-05)
 * Semua fungsi harus dipanggil di dalam transaksi basis data.
 */
require_once __DIR__ . '/db.php';

/** Nomor transaksi: PREFIX-YYYYMMDD-NNNN. $table hanya diisi konstanta internal. */
function next_no(string $prefix, string $table, string $date): string
{
    $ymd = date('Ymd', strtotime($date));
    $max = val('SELECT MAX(no_transaksi) FROM ' . $table . ' WHERE no_transaksi LIKE ? FOR UPDATE', [$prefix . '-' . $ymd . '-%']);
    $n = $max ? ((int)substr($max, -4)) + 1 : 1;
    return $prefix . '-' . $ymd . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Catat mutasi ke kartu stok. Panggil SETELAH barang.stok_saat_ini diperbarui. */
function kartu(int $idBarang, string $jenis, string $ref, float $jumlahBertanda, string $ket, int $uid): void
{
    $saldo = (float)val('SELECT stok_saat_ini FROM barang WHERE id_barang = ?', [$idBarang]);
    q(
        'INSERT INTO kartu_stok (id_barang, waktu, jenis, referensi, jumlah, saldo, keterangan, id_pengguna) VALUES (?,?,?,?,?,?,?,?)',
        [$idBarang, date('Y-m-d H:i:s'), $jenis, $ref, round($jumlahBertanda, 2), round($saldo, 2), $ket, $uid]
    );
}

/** Tambah / kurangi stok total barang (atomik di sisi database). */
function ubah_stok(int $idBarang, float $delta): void
{
    q('UPDATE barang SET stok_saat_ini = stok_saat_ini + ? WHERE id_barang = ?', [round($delta, 2), $idBarang]);
}

/**
 * Ambil $qty dari batch dengan urutan FEFO (kedaluwarsa terdekat lebih dulu).
 * Batch tanpa tanggal kedaluwarsa dipakai terakhir dengan urutan FIFO.
 * Mengurangi sisa_jumlah tiap batch dan mengembalikan rincian alokasi.
 */
function fefo_take(int $idBarang, float $qty): array
{
    $batches = rows(
        'SELECT id_inbound_detail, sisa_jumlah, tgl_kedaluwarsa FROM inbound_detail
         WHERE id_barang = ? AND sisa_jumlah > 0
         ORDER BY (tgl_kedaluwarsa IS NULL), tgl_kedaluwarsa, id_inbound_detail
         FOR UPDATE',
        [$idBarang]
    );
    $left = round($qty, 2);
    $out = [];
    foreach ($batches as $b) {
        if ($left <= 0.00001) {
            break;
        }
        $sisa = (float)$b['sisa_jumlah'];
        $take = round(min($sisa, $left), 2);
        q('UPDATE inbound_detail SET sisa_jumlah = sisa_jumlah - ? WHERE id_inbound_detail = ?', [$take, $b['id_inbound_detail']]);
        $out[] = ['id' => (int)$b['id_inbound_detail'], 'jumlah' => $take, 'exp' => $b['tgl_kedaluwarsa']];
        $left = round($left - $take, 2);
    }
    if ($left > 0.00001) {
        throw new RuntimeException('Sisa stok pada batch tidak mencukupi (barang ID ' . $idBarang . ').');
    }
    return $out;
}

/** Galat yang pesannya aman ditampilkan kepada pengguna (mis. stok tidak cukup). */
class UserError extends RuntimeException
{
}
