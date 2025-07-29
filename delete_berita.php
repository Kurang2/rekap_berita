<?php
// delete_berita.php

require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Hanya izinkan role 'admin' untuk mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menghapus berita.";
    header("location: dashboard.php");
    exit;
}

// Cek apakah parameter ID ada dan valid
if (isset($_GET["id"]) && is_numeric(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Cek apakah berita dengan ID tersebut ada sebelum mencoba menghapus
    $sql_check = "SELECT id FROM berita WHERE id = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $param_id_check);
        $param_id_check = $id;
        if (mysqli_stmt_execute($stmt_check)) {
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) == 1) {
                // Berita ditemukan, lanjutkan proses hapus
                $sql_delete = "DELETE FROM berita WHERE id = ?";
                if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $param_id_delete);
                    $param_id_delete = $id;
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $_SESSION['success_message'] = "Berita berhasil dihapus.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menghapus berita. Mohon coba lagi.";
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query hapus.";
                }
            } else {
                // Berita tidak ditemukan
                $_SESSION['error_message'] = "Berita yang ingin dihapus tidak ditemukan.";
            }
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan saat memeriksa berita.";
        }
        mysqli_stmt_close($stmt_check);
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query cek berita.";
    }
} else {
    // ID tidak ada atau tidak valid
    $_SESSION['error_message'] = "ID berita tidak valid untuk dihapus.";
}

// Redirect kembali ke halaman daftar berita
header("location: list_berita.php");
exit();

// Tutup koneksi database (ini tidak akan dieksekusi karena ada exit() di atas)
// mysqli_close($link);
?>
