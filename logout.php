<?php
/** Keluar: kosongkan sesi, ganti ID sesi, kembali ke halaman awal. */
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
session_regenerate_id(true);
flash('ok', 'Anda sudah keluar. Sampai jumpa lagi!');
redirect('index.php');
