<?php
// dashboard.php

require_once 'config.php'; // Mengandung koneksi database ($link) dan session_start()

// Pastikan user sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// --- Ambil Jumlah Berita/Artikel berdasarkan Kategori ---
$kategori_counts = [];
$sql_kategori = "SELECT kategori, COUNT(*) as total FROM berita GROUP BY kategori ORDER BY kategori ASC";
$result_kategori = mysqli_query($link, $sql_kategori);

if ($result_kategori) {
    while ($row = mysqli_fetch_assoc($result_kategori)) {
        $kategori_counts[] = $row;
    }
} else {
    $error_message_kategori = "Gagal mengambil data kategori: " . mysqli_error($link);
}

// --- Ambil Jumlah Rekapan per Kampus ---
$kampus_counts = [];
$sql_kampus_dashboard = "SELECT kampus, COUNT(*) as total FROM berita GROUP BY kampus ORDER BY kampus ASC";
$result_kampus_dashboard = mysqli_query($link, $sql_kampus_dashboard);

if ($result_kampus_dashboard) {
    while ($row = mysqli_fetch_assoc($result_kampus_dashboard)) {
        $kampus_counts[] = $row;
    }
} else {
    $error_message_kampus = "Gagal mengambil data kampus: " . mysqli_error($link);
}

// --- Data untuk Grafik Berita Per Bulan Per Kampus ---
$monthly_news_data = [];
$unique_months = [];
$unique_campuses = [];

$sql_chart_data = "SELECT tanggal_terbit, kampus FROM berita ORDER BY tanggal_terbit ASC";
$result_chart_data = mysqli_query($link, $sql_chart_data);

if ($result_chart_data) {
    while ($row = mysqli_fetch_assoc($result_chart_data)) {
        $date_obj = new DateTime($row['tanggal_terbit']);
        $month_year = $date_obj->format('Y-m'); // Format YYYY-MM
        $campus = $row['kampus'];

        // Inisialisasi jika belum ada
        if (!isset($monthly_news_data[$month_year])) {
            $monthly_news_data[$month_year] = [];
        }
        if (!isset($monthly_news_data[$month_year][$campus])) {
            $monthly_news_data[$month_year][$campus] = 0;
        }

        $monthly_news_data[$month_year][$campus]++;

        // Kumpulkan bulan dan kampus unik
        if (!in_array($month_year, $unique_months)) {
            $unique_months[] = $month_year;
        }
        if (!in_array($campus, $unique_campuses)) {
            $unique_campuses[] = $campus;
        }
    }
    sort($unique_months); // Urutkan bulan secara kronologis
    sort($unique_campuses); // Urutkan kampus secara alfabetis
} else {
    $error_message_chart = "Gagal mengambil data untuk grafik: " . mysqli_error($link);
}

// Siapkan data dalam format yang bisa dibaca Chart.js
$chart_labels = [];
foreach ($unique_months as $month) {
    // Ubah format YYYY-MM menjadi nama bulan yang lebih mudah dibaca
    $chart_labels[] = (new DateTime($month . '-01'))->format('M Y');
}

$chart_datasets = [];
$colors = [ // Beberapa warna dasar untuk grafik
    '#4CAF50', '#2196F3', '#FFC107', '#E91E63', '#9C27B0',
    '#00BCD4', '#FF5722', '#795548', '#607D8B', '#CDDC39'
];
$color_index = 0;

foreach ($unique_campuses as $campus) {
    $data_for_campus = [];
    foreach ($unique_months as $month) {
        $data_for_campus[] = isset($monthly_news_data[$month][$campus]) ? $monthly_news_data[$month][$campus] : 0;
    }
    $chart_datasets[] = [
        'label' => $campus,
        'data' => $data_for_campus,
        'backgroundColor' => $colors[$color_index % count($colors)],
        'borderColor' => $colors[$color_index % count($colors)],
        'borderWidth' => 1
    ];
    $color_index++;
}


// Sesuaikan navigasi berdasarkan peran
$isAdmin = ($_SESSION['role'] == 'admin');

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Rekap Berita</title>
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
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css"> <!-- Pastikan ini tetap ada jika ada gaya kustom -->
    <style>
        /* Gaya kustom tambahan jika diperlukan, atau ganti dengan Tailwind */
        body {
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        /* Override beberapa gaya default jika style.css Anda terlalu kuat */
        .stats-list li {
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container bg-white shadow-lg rounded-lg p-6 my-8">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Dashboard</h2>
        <nav class="mb-8">
            <ul class="flex justify-center space-x-4 p-3 bg-blue-600 text-white rounded-md shadow-md">
                <li><a href="dashboard.php" class="hover:text-blue-200">Dashboard</a></li>
                <li><a href="list_berita.php" class="hover:text-blue-200">Daftar Berita</a></li>
                <li><a href="report.php" class="hover:text-blue-200">Laporan</a></li>
                <?php if ($isAdmin): ?>
                    <li><a href="add_berita.php" class="hover:text-blue-200">Tambah Berita</a></li>
                    <li><a href="manage_users.php" class="hover:text-blue-200">Tambah Akun</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="hover:text-blue-200">Logout</a></li>
            </ul>
        </nav>

        <hr class="my-8 border-gray-300">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="stats-section bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm">
                <h3 class="text-xl font-semibold text-gray-700 mb-4 pb-2 border-b-2 border-green-500">Jumlah Berita/Artikel Berdasarkan Kategori</h3>
                <?php if (!empty($error_message_kategori)): ?>
                    <p class="text-red-500 font-semibold"><?php echo $error_message_kategori; ?></p>
                <?php elseif (!empty($kategori_counts)): ?>
                    <ul class="stats-list space-y-3">
                        <?php foreach ($kategori_counts as $count): ?>
                            <li class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                                <span class="stats-label text-gray-600 font-medium"><?php echo htmlspecialchars($count['kategori']); ?>:</span>
                                <span class="stats-value bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold"><?php echo $count['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray-500">Tidak ada data kategori berita yang tersedia.</p>
                <?php endif; ?>
            </div>

            <div class="stats-section bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm">
                <h3 class="text-xl font-semibold text-gray-700 mb-4 pb-2 border-b-2 border-green-500">Jumlah Rekapan Berita per Kampus</h3>
                <?php if (!empty($error_message_kampus)): ?>
                    <p class="text-red-500 font-semibold"><?php echo $error_message_kampus; ?></p>
                <?php elseif (!empty($kampus_counts)): ?>
                    <ul class="stats-list space-y-3">
                        <?php foreach ($kampus_counts as $count): ?>
                            <li class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                                <span class="stats-label text-gray-600 font-medium"><?php echo htmlspecialchars($count['kampus']); ?>:</span>
                                <span class="stats-value bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold"><?php echo $count['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray-500">Tidak ada data rekapan per kampus yang tersedia.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-section bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 pb-2 border-b-2 border-blue-500">Grafik Berita Terbit per Bulan per Kampus</h3>
            <?php if (!empty($error_message_chart)): ?>
                <p class="text-red-500 font-semibold"><?php echo $error_message_chart; ?></p>
            <?php elseif (empty($unique_months) || empty($unique_campuses)): ?>
                <p class="text-gray-500">Tidak ada data yang cukup untuk membuat grafik.</p>
            <?php else: ?>
                <div class="relative h-96"> <!-- Tinggi responsif untuk grafik -->
                    <canvas id="monthlyNewsChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data dari PHP untuk Chart.js
            const chartLabels = <?php echo json_encode($chart_labels); ?>;
            const chartDatasets = <?php echo json_encode($chart_datasets); ?>;

            if (chartLabels.length > 0 && chartDatasets.length > 0) {
                const ctx = document.getElementById('monthlyNewsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar', // Bisa juga 'line'
                    data: {
                        labels: chartLabels,
                        datasets: chartDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Penting untuk tinggi responsif
                        scales: {
                            x: {
                                stacked: true, // Untuk bar chart: tumpuk bar per kampus
                                title: {
                                    display: true,
                                    text: 'Bulan'
                                }
                            },
                            y: {
                                stacked: true, // Untuk bar chart: tumpuk bar per kampus
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Jumlah Berita'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Jumlah Berita per Bulan per Kampus'
                            }
                        }
                    }
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
