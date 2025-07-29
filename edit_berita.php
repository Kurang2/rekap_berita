<?php
// edit_berita.php

require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Hanya izinkan role 'admin' untuk mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki akses untuk mengedit berita.";
    header("location: dashboard.php");
    exit;
}

// Definisikan variabel dan inisialisasi dengan nilai kosong
$id = $kategori = $topik = $tanggal_terbit = $link_publikasi = $media = $kampus = $bagian = "";
$kategori_err = $topik_err = $tanggal_terbit_err = $link_publikasi_err = $media_err = $kampus_err = $bagian_err = "";

// Ambil pesan sukses/error dari session jika ada
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']); // Hapus pesan setelah ditampilkan
unset($_SESSION['error_message']);   // Hapus pesan setelah ditampilkan

// Opsi untuk dropdown kategori
$kategori_options = ["Berita", "Artikel", "Opini"];

// Proses form saat data dikirim (POST request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil ID berita dari hidden field
    $id = $_POST["id"];

    // Validasi Kategori (sekarang dari dropdown)
    if (empty(trim($_POST["kategori"]))) {
        $kategori_err = "Mohon pilih kategori.";
    } elseif (!in_array(trim($_POST["kategori"]), $kategori_options)) { // Validasi apakah pilihan ada di daftar opsi
        $kategori_err = "Kategori yang dipilih tidak valid.";
    } else {
        $kategori = trim($_POST["kategori"]);
    }

    // Validasi Topik
    if (empty(trim($_POST["topik"]))) {
        $topik_err = "Mohon masukkan topik berita.";
    } else {
        $topik = trim($_POST["topik"]);
    }

    // Validasi Tanggal Terbit
    if (empty(trim($_POST["tanggal_terbit"]))) {
        $tanggal_terbit_err = "Mohon masukkan tanggal terbit.";
    } else {
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", trim($_POST["tanggal_terbit"]))) {
            $tanggal_terbit_err = "Format tanggal tidak valid (YYYY-MM-DD).";
        } else {
            $tanggal_terbit = trim($_POST["tanggal_terbit"]);
        }
    }

    // Validasi Link Publikasi
    if (empty(trim($_POST["link_publikasi"]))) {
        $link_publikasi_err = "Mohon masukkan link publikasi.";
    } else {
        $link_publikasi_temp = trim($_POST["link_publikasi"]);
        if (!filter_var($link_publikasi_temp, FILTER_VALIDATE_URL)) {
            $link_publikasi_err = "Format link publikasi tidak valid.";
        } else {
            $link_publikasi = $link_publikasi_temp;
        }
    }

    // Validasi Media
    if (empty(trim($_POST["media"]))) {
        $media_err = "Mohon masukkan media publikasi.";
    } else {
        $media = trim($_POST["media"]);
    }

    // Validasi Kampus
    if (empty(trim($_POST["kampus"]))) {
        $kampus_err = "Mohon masukkan kampus.";
    } else {
        $kampus = trim($_POST["kampus"]);
    }

    // Validasi Bagian
    $bagian = trim($_POST["bagian"]);

    // Jika tidak ada error validasi input
    if (empty($kategori_err) && empty($topik_err) && empty($tanggal_terbit_err) && empty($link_publikasi_err) && empty($media_err) && empty($kampus_err) && empty($bagian_err)) {
        $sql = "UPDATE berita SET kategori = ?, topik = ?, tanggal_terbit = ?, link_publikasi = ?, media = ?, kampus = ?, bagian = ? WHERE id = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssi", $param_kategori, $param_topik, $param_tanggal_terbit, $param_link_publikasi, $param_media, $param_kampus, $param_bagian, $param_id);

            $param_kategori = $kategori;
            $param_topik = $topik;
            $param_tanggal_terbit = $tanggal_terbit;
            $param_link_publikasi = $link_publikasi;
            $param_media = $media;
            $param_kampus = $kampus;
            $param_bagian = $bagian;
            $param_id = $id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Berita berhasil diperbarui.";
                header("location: list_berita.php"); // Redirect ke daftar berita setelah update
                exit();
            } else {
                $_SESSION['error_message'] = "Terjadi kesalahan saat memperbarui berita. Mohon coba lagi.";
                header("location: edit_berita.php?id=" . $id); // Redirect kembali ke halaman edit dengan pesan error
                exit();
            }

            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query. Mohon coba lagi.";
            header("location: edit_berita.php?id=" . $id);
            exit();
        }
    } else {
        $error_message = "Mohon periksa kembali input Anda. Ada kesalahan.";
        // Jika ada error, data form akan tetap terisi dari POST
    }
} else { // Jika halaman diakses melalui GET (untuk menampilkan form edit)
    // Cek apakah parameter id ada di URL dan valid (numerik)
    if (isset($_GET["id"]) && is_numeric(trim($_GET["id"]))) {
        $id = trim($_GET["id"]);

        $sql = "SELECT kategori, topik, tanggal_terbit, link_publikasi, media, kampus, bagian FROM berita WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $id;

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $kategori, $topik, $tanggal_terbit, $link_publikasi, $media, $kampus, $bagian);
                    mysqli_stmt_fetch($stmt);
                } else {
                    // ID tidak ditemukan di database
                    $_SESSION['error_message'] = "Berita tidak ditemukan.";
                    header("location: list_berita.php");
                    exit();
                }
            } else {
                $_SESSION['error_message'] = "Terjadi kesalahan saat mengambil data berita. Mohon coba lagi.";
                header("location: list_berita.php");
                exit();
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query. Mohon coba lagi.";
            header("location: list_berita.php");
            exit();
        }
    } else {
        // ID tidak ada atau tidak valid di URL
        $_SESSION['error_message'] = "ID berita tidak valid.";
        header("location: list_berita.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Berita - Aplikasi Rekap Berita</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        /* Custom styles for specific overrides or details */
        body {
            font-family: 'Inter', sans-serif;
        }
        .help-block {
            color: #dc3545; /* Red for errors */
            font-size: 0.875em;
            margin-top: 5px;
            display: block;
        }
        .form-group.has-error input, .form-group.has-error select {
            border-color: #dc3545;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-xl mx-auto bg-white p-8 my-8 rounded-lg shadow-xl">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Edit Berita</h2>
        <nav class="mb-8">
            <ul class="flex justify-center space-x-4 p-4 bg-blue-600 text-white rounded-lg shadow-md">
                <li><a href="dashboard.php" class="hover:text-blue-200">Dashboard</a></li>
                <li><a href="list_berita.php" class="hover:text-blue-200">Daftar Berita</a></li>
                <li><a href="report.php" class="hover:text-blue-200">Laporan</a></li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li><a href="add_berita.php" class="hover:text-blue-200">Tambah Berita</a></li>
                    <li><a href="manage_users.php" class="hover:text-blue-200">Tambah Akun</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="hover:text-blue-200">Logout</a></li>
            </ul>
        </nav>

        <hr class="my-8 border-gray-300">

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 text-green-700 border border-green-200 p-4 mb-4 rounded-md font-medium"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 text-red-700 border border-red-200 p-4 mb-4 rounded-md font-medium"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <div class="form-group <?php echo (!empty($kategori_err)) ? 'has-error' : ''; ?>">
                <label for="kategori" class="block text-gray-700 text-sm font-bold mb-2">Kategori:</label>
                <select name="kategori" id="kategori" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($kategori_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($kategori == $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="help-block"><?php echo $kategori_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($topik_err)) ? 'has-error' : ''; ?>">
                <label for="topik" class="block text-gray-700 text-sm font-bold mb-2">Topik:</label>
                <input type="text" name="topik" id="topik" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($topik); ?>" required>
                <span class="help-block"><?php echo $topik_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($tanggal_terbit_err)) ? 'has-error' : ''; ?>">
                <label for="tanggal_terbit" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Terbit:</label>
                <input type="date" name="tanggal_terbit" id="tanggal_terbit" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($tanggal_terbit); ?>" required>
                <span class="help-block"><?php echo $tanggal_terbit_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($link_publikasi_err)) ? 'has-error' : ''; ?>">
                <label for="link_publikasi" class="block text-gray-700 text-sm font-bold mb-2">Link Publikasi:</label>
                <input type="text" name="link_publikasi" id="link_publikasi" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($link_publikasi); ?>" required>
                <span class="help-block"><?php echo $link_publikasi_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($media_err)) ? 'has-error' : ''; ?>">
                <label for="media" class="block text-gray-700 text-sm font-bold mb-2">Media:</label>
                <input type="text" name="media" id="media" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($media); ?>" required>
                <span class="help-block"><?php echo $media_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($kampus_err)) ? 'has-error' : ''; ?>">
                <label for="kampus" class="block text-gray-700 text-sm font-bold mb-2">Kampus:</label>
                <input type="text" name="kampus" id="kampus" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($kampus); ?>" required>
                <span class="help-block"><?php echo $kampus_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($bagian_err)) ? 'has-error' : ''; ?>">
                <label for="bagian" class="block text-gray-700 text-sm font-bold mb-2">Bagian:</label>
                <input type="text" name="bagian" id="bagian" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($bagian); ?>">
                <span class="help-block"><?php echo $bagian_err; ?></span>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Simpan Perubahan
                </button>
                <a href="list_berita.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md transition duration-300">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($link);
?>
