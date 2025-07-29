<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u461274508_root'); // Username default XAMPP
define('DB_PASSWORD', '@Rifqi23');     // Password default XAMPP (kosong)
define('DB_NAME', 'u461274508_db_berita');

// Buat koneksi database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Cek koneksi
if ($link === false) {
    die("ERROR: Tidak dapat terhubung. " . mysqli_connect_error());
}
