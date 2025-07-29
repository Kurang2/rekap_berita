<?php
// report.php

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

// Include Composer's autoloader for Dompdf and PhpSpreadsheet
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$report_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_kampus_report = isset($_GET['kampus']) ? $_GET['kampus'] : '';
$filter_bagian_report = isset($_GET['bagian']) ? $_GET['bagian'] : '';

$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$sql_report = "SELECT id, kategori, topik, tanggal_terbit, link_publikasi, media, kampus, bagian FROM berita WHERE 1=1 ";
$param_types_report = "";
$param_values_report = [];

// Filter berdasarkan Kampus
if (!empty($filter_kampus_report)) {
    $sql_report .= " AND kampus = ?";
    $param_types_report .= "s";
    $param_values_report[] = $filter_kampus_report;
}

// Filter berdasarkan Bagian
if (!empty($filter_bagian_report)) {
    $sql_report .= " AND bagian = ?";
    $param_types_report .= "s";
    $param_values_report[] = $filter_bagian_report;
}

// Filter berdasarkan Range Tanggal yang dipilih pengguna
if (!empty($start_date_filter) && !empty($end_date_filter)) {
    $sql_report .= " AND tanggal_terbit BETWEEN ? AND ?";
    $param_types_report .= "ss";
    $param_values_report[] = $start_date_filter . ' 00:00:00';
    $param_values_report[] = $end_date_filter . ' 23:59:59';
}

$sql_report .= " ORDER BY tanggal_terbit ASC";

$stmt_report = mysqli_prepare($link, $sql_report);
if (!empty($param_values_report)) {
    mysqli_stmt_bind_param($stmt_report, $param_types_report, ...$param_values_report);
}
mysqli_stmt_execute($stmt_report);
$result_report = mysqli_stmt_get_result($stmt_report);
$data_report = [];
while ($row = mysqli_fetch_assoc($result_report)) {
    $data_report[] = $row;
}
mysqli_stmt_close($stmt_report);

// Logika untuk Generate PDF atau Excel
if ($report_type == 'pdf') {
    // Inisialisasi Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    // Konten HTML untuk PDF
    $html = '<h1 style="text-align: center;">Laporan Berita</h1>';
    if (!empty($filter_kampus_report)) {
        $html .= '<p><strong>Kampus:</strong> ' . htmlspecialchars($filter_kampus_report) . '</p>';
    }
    if (!empty($filter_bagian_report)) {
        $html .= '<p><strong>Bagian:</strong> ' . htmlspecialchars($filter_bagian_report) . '</p>';
    }
    if (!empty($start_date_filter) && !empty($end_date_filter)) {
        $html .= '<p><strong>Periode Tanggal:</strong> ' . date('d M Y', strtotime($start_date_filter)) . ' - ' . date('d M Y', strtotime($end_date_filter)) . '</p>';
    }
    $html .= '<table border="1" cellspacing="0" cellpadding="5" width="100%">';
    $html .= '<thead><tr><th>ID</th><th>Kategori</th><th>Topik</th><th>Tanggal Terbit</th><th>Link Publikasi</th><th>Media</th><th>Kampus</th><th>Bagian</th></tr></thead>';
    $html .= '<tbody>';
    foreach ($data_report as $row) {
        $html .= '<tr>';
        $html .= '<td>' . $row['id'] . '</td>';
        $html .= '<td>' . htmlspecialchars($row['kategori']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['topik']) . '</td>';
        $html .= '<td>' . $row['tanggal_terbit'] . '</td>';
        $html .= '<td><a href="' . htmlspecialchars($row['link_publikasi']) . '">' . htmlspecialchars($row['link_publikasi']) . '</a></td>';
        $html .= '<td>' . htmlspecialchars($row['media']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['kampus']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['bagian']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $filename_suffix = "";
    if (!empty($start_date_filter) && !empty($end_date_filter)) {
        $filename_suffix .= "_" . $start_date_filter . "_to_" . $end_date_filter;
    }
    if (!empty($filter_kampus_report)) {
        $filename_suffix .= "_" . str_replace(" ", "_", $filter_kampus_report);
    }
    if (!empty($filter_bagian_report)) {
        $filename_suffix .= "_" . str_replace(" ", "_", $filter_bagian_report);
    }
    $dompdf->stream("laporan_berita" . $filename_suffix . ".pdf", array("Attachment" => true));
    exit;

} elseif ($report_type == 'excel') {
    // Inisialisasi PhpSpreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = ['ID', 'Kategori', 'Topik', 'Tanggal Terbit', 'Link Publikasi', 'Media', 'Kampus', 'Bagian'];
    $sheet->fromArray($headers, NULL, 'A1');

    $row_num = 2;
    foreach ($data_report as $row) {
        $sheet->setCellValue('A' . $row_num, $row['id']);
        $sheet->setCellValue('B' . $row_num, $row['kategori']);
        $sheet->setCellValue('C' . $row_num, $row['topik']);
        $sheet->setCellValue('D' . $row_num, $row['tanggal_terbit']);
        $sheet->setCellValue('E' . $row_num, $row['link_publikasi']);
        $sheet->setCellValue('F' . $row_num, $row['media']);
        $sheet->setCellValue('G' . $row_num, $row['kampus']);
        $sheet->setCellValue('H' . $row_num, $row['bagian']);
        $row_num++;
    }

    $writer = new Xlsx($spreadsheet);
    $filename_suffix = "";
    if (!empty($start_date_filter) && !empty($end_date_filter)) {
        $filename_suffix .= "_" . $start_date_filter . "_to_" . $end_date_filter;
    }
    if (!empty($filter_kampus_report)) {
        $filename_suffix .= "_" . str_replace(" ", "_", $filter_kampus_report);
    }
    if (!empty($filter_bagian_report)) {
        $filename_suffix .= "_" . str_replace(" ", "_", $filter_bagian_report);
    }
    $filename = "laporan_berita" . $filename_suffix . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
    $writer->save('php://output');
    exit;
}

// Dapatkan daftar kampus unik untuk dropdown filter laporan
$kampus_options = [];
$sql_kampus = "SELECT DISTINCT kampus FROM berita ORDER BY kampus ASC";
$result_kampus = mysqli_query($link, $sql_kampus);
while ($row = mysqli_fetch_assoc($result_kampus)) {
    $kampus_options[] = $row['kampus'];
}

// Dapatkan daftar bagian unik untuk dropdown filter laporan
$bagian_options = [];
$sql_bagian_report = "SELECT DISTINCT bagian FROM berita WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC";
$result_bagian_report = mysqli_query($link, $sql_bagian_report);
while ($row = mysqli_fetch_assoc($result_bagian_report)) {
    $bagian_options[] = $row['bagian'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Aplikasi Rekap Berita</title>
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
    </style>
</head>

<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-4xl mx-auto bg-white p-8 my-8 rounded-lg shadow-xl">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Laporan Berita</h2>
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

        <form action="" method="GET" class="space-y-4 bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                    <input type="date" name="start_date" id="start_date" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($start_date_filter); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir:</label>
                    <input type="date" name="end_date" id="end_date" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($end_date_filter); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="kampus" class="block text-gray-700 text-sm font-bold mb-2">Filter Kampus:</label>
                    <select name="kampus" id="kampus" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Kampus</option>
                        <?php foreach ($kampus_options as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filter_kampus_report == $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="bagian" class="block text-gray-700 text-sm font-bold mb-2">Filter Bagian:</label>
                    <select name="bagian" id="bagian" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Bagian</option>
                        <?php foreach ($bagian_options as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filter_bagian_report == $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="submit" name="type" value="pdf" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Cetak PDF
                </button>
                <button type="submit" name="type" value="excel" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Cetak Excel
                </button>
            </div>
        </form>
    </div>
</body>

</html>
<?php
// Tutup koneksi database
mysqli_close($link);
?>
