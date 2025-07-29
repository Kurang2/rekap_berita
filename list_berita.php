<?php
// list_berita.php

require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Ambil pesan sukses/error dari session jika ada
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']); // Hapus pesan setelah ditampilkan
unset($_SESSION['error_message']);   // Hapus pesan setelah ditampilkan

// Variabel untuk pencarian dan pagination
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_kampus = isset($_GET['kampus']) ? $_GET['kampus'] : '';
$filter_bagian = isset($_GET['bagian']) ? $_GET['bagian'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'tanggal_terbit';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10; // Jumlah rekaman per halaman

// Tentukan offset untuk pagination
$offset = ($page - 1) * $records_per_page;

// Query dasar untuk mengambil data berita
$sql = "SELECT id, kategori, topik, tanggal_terbit, link_publikasi, media, kampus, bagian FROM berita WHERE 1=1";
$param_types = "";
$param_values = [];

// Tambahkan kondisi pencarian jika ada
if (!empty($search_query)) {
    $sql .= " AND (kategori LIKE ? OR topik LIKE ? OR link_publikasi LIKE ? OR media LIKE ? OR kampus LIKE ? OR bagian LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $param_types .= "ssssss";
    $param_values[] = $search_param;
    $param_values[] = $search_param;
    $param_values[] = $search_param;
    $param_values[] = $search_param;
    $param_values[] = $search_param;
    $param_values[] = $search_param;
}

// Tambahkan filter kampus jika ada
if (!empty($filter_kampus)) {
    $sql .= " AND kampus = ?";
    $param_types .= "s";
    $param_values[] = $filter_kampus;
}

// Tambahkan filter bagian jika ada
if (!empty($filter_bagian)) {
    $sql .= " AND bagian = ?";
    $param_types .= "s";
    $param_values[] = $filter_bagian;
}

// Hitung total rekaman untuk pagination
$sql_count = "SELECT COUNT(*) FROM berita WHERE 1=1";
$param_types_count = "";
$param_values_count = [];

if (!empty($search_query)) {
    $sql_count .= " AND (kategori LIKE ? OR topik LIKE ? OR link_publikasi LIKE ? OR media LIKE ? OR kampus LIKE ? OR bagian LIKE ?)";
    $search_param_count = "%" . $search_query . "%";
    $param_types_count .= "ssssss";
    $param_values_count[] = $search_param_count;
    $param_values_count[] = $search_param_count;
    $param_values_count[] = $search_param_count;
    $param_values_count[] = $search_param_count;
    $param_values_count[] = $search_param_count;
    $param_values_count[] = $search_param_count;
}
if (!empty($filter_kampus)) {
    $sql_count .= " AND kampus = ?";
    $param_types_count .= "s";
    $param_values_count[] = $filter_kampus;
}
if (!empty($filter_bagian)) {
    $sql_count .= " AND bagian = ?";
    $param_types_count .= "s";
    $param_values_count[] = $filter_bagian;
}


$stmt_count = mysqli_prepare($link, $sql_count);
if (!empty($param_values_count)) {
    mysqli_stmt_bind_param($stmt_count, $param_types_count, ...$param_values_count);
}
mysqli_stmt_execute($stmt_count);
mysqli_stmt_bind_result($stmt_count, $total_records);
mysqli_stmt_fetch($stmt_count);
mysqli_stmt_close($stmt_count);

$total_pages = ceil($total_records / $records_per_page);

// Tambahkan pengurutan
$sql .= " ORDER BY " . $sort_by . " " . $sort_order;

// Tambahkan limit dan offset untuk pagination
$sql .= " LIMIT ? OFFSET ?";
$param_types .= "ii";
$param_values[] = $records_per_page;
$param_values[] = $offset;

$stmt = mysqli_prepare($link, $sql);
if (!empty($param_values)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$param_values);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$isAdmin = ($_SESSION['role'] == 'admin');

// Dapatkan daftar kampus unik untuk dropdown filter
$kampus_options = [];
$sql_kampus = "SELECT DISTINCT kampus FROM berita ORDER BY kampus ASC";
$result_kampus = mysqli_query($link, $sql_kampus);
while ($row = mysqli_fetch_assoc($result_kampus)) {
    $kampus_options[] = $row['kampus'];
}

// Dapatkan daftar bagian unik untuk dropdown filter
$bagian_options = [];
$sql_bagian = "SELECT DISTINCT bagian FROM berita WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC";
$result_bagian = mysqli_query($link, $sql_bagian);
while ($row = mysqli_fetch_assoc($result_bagian)) {
    $bagian_options[] = $row['bagian'];
}

// Tentukan halaman aktif saat ini untuk highlight menu
$current_page_name = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Berita - Aplikasi Rekap Berita</title>
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
        body.no-scroll {
            overflow: hidden; /* Sembunyikan scrollbar saat menu terbuka */
        }
        .help-block {
            color: #dc3545; /* Merah untuk error */
            font-size: 0.875em;
            margin-top: 5px;
            display: block;
        }
        .form-group.has-error input, .form-group.has-error select {
            border-color: #dc3545;
        }
        /* Responsive table */
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr { border: 1px solid #ccc; margin-bottom: 10px; }
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td:before {
                position: absolute;
                top: 6px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                content: attr(data-label);
                text-align: left;
                font-weight: bold;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form .form-group {
                min-width: unset;
                width: 100%;
            }
        }

        /* Gaya untuk word wrap pada kolom topik */
        .word-wrap-topic {
            word-break: break-words; /* Memaksa teks untuk pindah baris */
            white-space: normal; /* Memastikan white-space normal */
        }

        /* Custom CSS for Off-Canvas Menu */
        .off-canvas-menu {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px; /* Lebar sidebar */
            background-color: #1a202c; /* Warna latar belakang sidebar */
            transform: translateX(-100%); /* Sembunyikan di luar layar */
            transition: transform 0.3s ease-in-out;
            z-index: 2000; /* Pastikan di atas konten lain */
            box-shadow: 2px 0 5px rgba(0,0,0,0.5);
            padding-top: 60px; /* Ruang untuk header/logo jika ada */
        }

        .off-canvas-menu.open {
            transform: translateX(0); /* Tampilkan menu */
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1500;
            display: none; /* Sembunyikan overlay secara default */
        }

        .overlay.open {
            display: block; /* Tampilkan overlay saat menu terbuka */
        }

        /* Gaya untuk tombol hamburger (hanya terlihat di mobile) */
        .hamburger-button {
            display: block; /* Default: terlihat di mobile */
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            z-index: 2001; /* Pastikan di atas menu dan konten */
            position: fixed;
            top: 15px;
            left: 15px;
            color: white; /* Warna ikon hamburger */
        }
        .hamburger-button .line {
            width: 25px;
            height: 3px;
            background-color: white;
            margin: 5px 0;
            transition: all 0.3s ease;
        }

        /* Sembunyikan navigasi default di mobile */
        @media (max-width: 767px) {
            .main-nav-desktop {
                display: none;
            }
        }

        /* Tampilkan navigasi default di desktop */
        @media (min-width: 768px) {
            .hamburger-button {
                display: none; /* Sembunyikan hamburger di desktop */
            }
            .main-nav-desktop {
                display: flex; /* Tampilkan navigasi biasa di desktop */
            }
            .off-canvas-menu {
                display: none; /* Sembunyikan off-canvas di desktop */
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <!-- Overlay untuk menutup menu -->
    <div id="overlay" class="overlay"></div>

    <!-- Off-Canvas Menu (Sidebar) -->
    <div id="offCanvasMenu" class="off-canvas-menu">
        <ul class="flex flex-col space-y-2 p-4 text-white">
            <li><a href="dashboard.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300 <?php echo ($current_page_name == 'dashboard.php') ? 'bg-blue-700 font-bold' : ''; ?>">Dashboard</a></li>
            <li><a href="list_berita.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300 <?php echo ($current_page_name == 'list_berita.php') ? 'bg-blue-700 font-bold' : ''; ?>">Daftar Berita</a></li>
            <li><a href="report.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300 <?php echo ($current_page_name == 'report.php') ? 'bg-blue-700 font-bold' : ''; ?>">Laporan</a></li>
            <?php if ($isAdmin): ?>
                <li><a href="add_berita.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300 <?php echo ($current_page_name == 'add_berita.php') ? 'bg-blue-700 font-bold' : ''; ?>">Tambah Berita</a></li>
                <li><a href="manage_users.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300 <?php echo ($current_page_name == 'manage_users.php') ? 'bg-blue-700 font-bold' : ''; ?>">Tambah Akun</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="block py-2 px-4 hover:bg-blue-700 rounded-md transition duration-300">Logout</a></li>
        </ul>
    </div>

    <!-- Header dengan Tombol Hamburger (Hanya di Mobile) -->
    <header class="bg-blue-600 text-white p-4 flex justify-between items-center md:hidden fixed w-full top-0 left-0 z-10">
        <button id="hamburgerButton" class="hamburger-button">
            <div class="line"></div>
            <div class="line"></div>
            <div class="line"></div>
        </button>
        <h1 class="text-xl font-bold mx-auto">Aplikasi Rekap Berita</h1>
    </header>

    <div class="max-w-7xl mx-auto bg-white p-8 my-8 rounded-lg shadow-xl md:mt-8 mt-20"> <!-- Tambah mt-20 untuk ruang header mobile -->
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Daftar Berita</h2>
        
        <!-- Navigasi Desktop (Disembunyikan di Mobile) -->
        <nav class="mb-8 main-nav-desktop">
            <ul class="flex justify-center space-x-4 p-4 bg-blue-600 text-white rounded-lg shadow-md w-full">
                <li><a href="dashboard.php" class="hover:text-blue-200 <?php echo ($current_page_name == 'dashboard.php') ? 'font-bold' : ''; ?>">Dashboard</a></li>
                <li><a href="list_berita.php" class="hover:text-blue-200 <?php echo ($current_page_name == 'list_berita.php') ? 'font-bold' : ''; ?>">Daftar Berita</a></li>
                <li><a href="report.php" class="hover:text-blue-200 <?php echo ($current_page_name == 'report.php') ? 'font-bold' : ''; ?>">Laporan</a></li>
                <?php if ($isAdmin): ?>
                    <li><a href="add_berita.php" class="hover:text-blue-200 <?php echo ($current_page_name == 'add_berita.php') ? 'font-bold' : ''; ?>">Tambah Berita</a></li>
                    <li><a href="manage_users.php" class="hover:text-blue-200 <?php echo ($current_page_name == 'manage_users.php') ? 'font-bold' : ''; ?>">Tambah Akun</a></li>
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

        <!-- Filter dan Pencarian -->
        <form action="" method="GET" class="filter-form bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm mb-8">
            <div class="form-group flex-1 min-w-[180px]">
                <label for="search" class="block text-gray-700 text-sm font-bold mb-2">Cari:</label>
                <input type="text" name="search" id="search" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Cari berita..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group flex-1 min-w-[180px]">
                <label for="kampus" class="block text-gray-700 text-sm font-bold mb-2">Filter Kampus:</label>
                <select name="kampus" id="kampus" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Kampus</option>
                    <?php foreach ($kampus_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filter_kampus == $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-1 min-w-[180px]">
                <label for="bagian" class="block text-gray-700 text-sm font-bold mb-2">Filter Bagian:</label>
                <select name="bagian" id="bagian" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Bagian</option>
                    <?php foreach ($bagian_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filter_bagian == $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-1 min-w-[180px]">
                <label for="sort_by" class="block text-gray-700 text-sm font-bold mb-2">Urutkan Berdasarkan:</label>
                <select name="sort_by" id="sort_by" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="tanggal_terbit" <?php echo ($sort_by == 'tanggal_terbit') ? 'selected' : ''; ?>>Tanggal Terbit</option>
                    <option value="kategori" <?php echo ($sort_by == 'kategori') ? 'selected' : ''; ?>>Kategori</option>
                    <option value="topik" <?php echo ($sort_by == 'topik') ? 'selected' : ''; ?>>Topik</option>
                    <option value="kampus" <?php echo ($sort_by == 'kampus') ? 'selected' : ''; ?>>Kampus</option>
                    <option value="bagian" <?php echo ($sort_by == 'bagian') ? 'selected' : ''; ?>>Bagian</option>
                </select>
            </div>
            <div class="form-group flex-1 min-w-[180px]">
                <label for="sort_order" class="block text-gray-700 text-sm font-bold mb-2">Urutan:</label>
                <select name="sort_order" id="sort_order" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>Terlama</option>
                </select>
            </div>
            <div class="flex gap-2 mt-auto">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">Terapkan Filter</button>
                <a href="list_berita.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md transition duration-300">Reset Filter</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200">
            <table class="w-full">
                <thead>
                    <tr class="bg-blue-600 text-white">
                        <th class="px-4 py-3 text-left font-semibold rounded-tl-lg">No</th> <!-- Diubah dari ID ke No -->
                        <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-left font-semibold">Topik</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal Terbit</th>
                        <th class="px-4 py-3 text-left font-semibold">Link Publikasi</th>
                        <th class="px-4 py-3 text-left font-semibold">Media</th>
                        <th class="px-4 py-3 text-left font-semibold">Kampus</th>
                        <th class="px-4 py-3 text-left font-semibold">Bagian</th>
                        <?php if ($isAdmin): ?>
                            <th class="px-4 py-3 text-left font-semibold rounded-tr-lg">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php
                        $row_number = $offset + 1; // Inisialisasi nomor urut
                        while ($row = mysqli_fetch_assoc($result)):
                            // Logika untuk membatasi topik menjadi 3 kata pertama
                            $original_topik = htmlspecialchars($row['topik']);
                            $words = explode(' ', $original_topik);
                            $display_topik = implode(' ', array_slice($words, 0, 3));
                            if (count($words) > 3) {
                                $display_topik .= '...';
                            }
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-800" data-label="No"><?php echo $row_number++; ?></td> <!-- Menampilkan nomor urut -->
                                <td class="px-4 py-3 text-gray-800" data-label="Kategori"><?php echo htmlspecialchars($row['kategori']); ?></td>
                                <td class="px-4 py-3 text-gray-800 word-wrap-topic" data-label="Topik"><?php echo $display_topik; ?></td>
                                <td class="px-4 py-3 text-gray-800" data-label="Tanggal Terbit"><?php echo $row['tanggal_terbit']; ?></td>
                                <td class="px-4 py-3" data-label="Link Publikasi"><a href="<?php echo htmlspecialchars($row['link_publikasi']); ?>" target="_blank" class="text-blue-500 hover:underline">Lihat Link</a></td>
                                <td class="px-4 py-3 text-gray-800" data-label="Media"><?php echo htmlspecialchars($row['media']); ?></td>
                                <td class="px-4 py-3 text-gray-800" data-label="Kampus"><?php echo htmlspecialchars($row['kampus']); ?></td>
                                <td class="px-4 py-3 text-gray-800" data-label="Bagian"><?php echo htmlspecialchars($row['bagian']); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td class="px-4 py-3 space-x-2">
                                        <a href="edit_berita.php?id=<?php echo $row['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white text-sm py-1 px-3 rounded-md transition duration-300">Edit</a>
                                        <a href="delete_berita.php?id=<?php echo $row['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white text-sm py-1 px-3 rounded-md transition duration-300" onclick="return confirm('Apakah Anda yakin ingin menghapus berita ini?');">Hapus</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($isAdmin) ? '9' : '8'; ?>" class="px-4 py-3 text-center text-gray-500">Tidak ada berita ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Responsif -->
        <div class="flex flex-wrap justify-center items-center gap-2 mt-8">
            <?php
            if ($total_pages > 1) {
                $query_params = $_GET;
                unset($query_params['page']); // Hapus parameter halaman saat ini

                // Link ke halaman pertama (<<)
                if ($page > 1) {
                    $query_params['page'] = 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border border-gray-300 rounded-md text-blue-600 hover:bg-blue-50 transition duration-300">&laquo;</a>';
                }

                // Link ke halaman sebelumnya (<)
                if ($page > 1) {
                    $query_params['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border border-gray-300 rounded-md text-blue-600 hover:bg-blue-50 transition duration-300">&lt;</a>';
                }

                $num_boundary_pages = 2; // Jumlah halaman di awal dan akhir yang selalu ditampilkan
                $num_surrounding_pages = 2; // Jumlah halaman di sekitar halaman aktif

                // Tentukan rentang halaman yang akan ditampilkan
                $start_page = max(1, $page - $num_surrounding_pages);
                $end_page = min($total_pages, $page + $num_surrounding_pages);

                // Jika halaman aktif terlalu dekat ke awal, perlebar rentang akhir
                if ($start_page <= $num_boundary_pages) {
                    $end_page = min($total_pages, $end_page + ($num_boundary_pages - $start_page + 1));
                }
                // Jika halaman aktif terlalu dekat ke akhir, perlebar rentang awal
                if ($end_page >= ($total_pages - $num_boundary_pages + 1)) {
                    $start_page = max(1, $start_page - ($end_page - ($total_pages - $num_boundary_pages)));
                }


                $pages_to_show = [];
                // Tambahkan halaman awal
                for ($i = 1; $i <= min($total_pages, $num_boundary_pages); $i++) {
                    $pages_to_show[$i] = true;
                }
                // Tambahkan halaman di sekitar halaman aktif
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $pages_to_show[$i] = true;
                }
                // Tambahkan halaman akhir
                for ($i = max(1, $total_pages - $num_boundary_pages + 1); $i <= $total_pages; $i++) {
                    $pages_to_show[$i] = true;
                }

                ksort($pages_to_show); // Urutkan nomor halaman secara numerik

                $prev_page_displayed = 0;
                foreach ($pages_to_show as $p_num => $is_visible) {
                    // Tampilkan ellipsis jika ada lompatan halaman
                    if ($p_num > $prev_page_displayed + 1) {
                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                    }
                    $query_params['page'] = $p_num;
                    $class = ($p_num == $page) ? 'bg-blue-600 text-white border-blue-600' : 'border border-gray-300 text-blue-600 hover:bg-blue-50';
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 rounded-md transition duration-300 ' . $class . '">' . $p_num . '</a>';
                    $prev_page_displayed = $p_num;
                }

                // Link ke halaman berikutnya (>)
                if ($page < $total_pages) {
                    $query_params['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border border-gray-300 rounded-md text-blue-600 hover:bg-blue-50 transition duration-300">&gt;</a>';
                }

                // Link ke halaman terakhir (>>)
                if ($page < $total_pages) {
                    $query_params['page'] = $total_pages;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border border-gray-300 rounded-md text-blue-600 hover:bg-blue-50 transition duration-300">&raquo;</a>';
                }
            }
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var hamburgerButton = document.getElementById('hamburgerButton');
            var offCanvasMenu = document.getElementById('offCanvasMenu');
            var overlay = document.getElementById('overlay');
            var body = document.body; // Get the body element

            if (hamburgerButton && offCanvasMenu && overlay) {
                hamburgerButton.addEventListener('click', function() {
                    offCanvasMenu.classList.toggle('open');
                    overlay.classList.toggle('open');
                    body.classList.toggle('no-scroll'); // Add/remove no-scroll class
                });

                overlay.addEventListener('click', function() {
                    offCanvasMenu.classList.remove('open');
                    overlay.classList.remove('open');
                    body.classList.remove('no-scroll'); // Remove no-scroll class
                });

                // Close menu when a link is clicked
                document.querySelectorAll('#offCanvasMenu a').forEach(link => {
                    link.addEventListener('click', function() {
                        offCanvasMenu.classList.remove('open');
                        overlay.classList.remove('open');
                        body.classList.remove('no-scroll'); // Remove no-scroll class
                    });
                });
            }
        });
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($link);
?>
