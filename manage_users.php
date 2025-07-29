<?php
// manage_users.php

require_once 'config.php'; // Mengandung koneksi database ($link) dan session_start()

// Pastikan user sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Pastikan ID pengguna yang sedang login disimpan di sesi.
// Ini penting untuk mencegah admin menghapus akunnya sendiri.
if (!isset($_SESSION['id'])) {
    // Fallback: Jika ID belum disimpan di sesi, ambil dari database
    $sql_get_id = "SELECT id FROM users WHERE username = ?";
    if ($stmt_get_id = mysqli_prepare($link, $sql_get_id)) {
        mysqli_stmt_bind_param($stmt_get_id, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt_get_id);
        mysqli_stmt_bind_result($stmt_get_id, $session_user_id);
        mysqli_stmt_fetch($stmt_get_id);
        mysqli_stmt_close($stmt_get_id);
        $_SESSION['id'] = $session_user_id;
    }
}

// Hanya izinkan role 'admin' untuk mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman manajemen pengguna.";
    header("location: dashboard.php"); // Redirect jika bukan admin
    exit;
}

$username_err = $password_err = $role_err = "";
$username = $password = $role = "";

// Ambil pesan sukses/error dari session jika ada
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']); // Hapus pesan setelah ditampilkan
unset($_SESSION['error_message']);   // Hapus pesan setelah ditampilkan

// --- LOGIKA HAPUS PENGGUNA ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = filter_var($_GET['id'], FILTER_VALIDATE_INT); // Validasi ID sebagai integer

    if ($user_id_to_delete === false) {
        $_SESSION['error_message'] = "ID pengguna tidak valid.";
    } elseif ($user_id_to_delete == $_SESSION['id']) {
        $_SESSION['error_message'] = "Anda tidak bisa menghapus akun Anda sendiri.";
    } else {
        // Cek apakah pengguna dengan ID tersebut ada
        $sql_check_user = "SELECT id FROM users WHERE id = ?";
        if ($stmt_check_user = mysqli_prepare($link, $sql_check_user)) {
            mysqli_stmt_bind_param($stmt_check_user, "i", $user_id_to_delete);
            mysqli_stmt_execute($stmt_check_user);
            mysqli_stmt_store_result($stmt_check_user);

            if (mysqli_stmt_num_rows($stmt_check_user) == 1) {
                // Pengguna ditemukan, lanjutkan hapus
                $sql_delete = "DELETE FROM users WHERE id = ?";
                if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $user_id_to_delete);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $_SESSION['success_message'] = "Pengguna berhasil dihapus.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menghapus pengguna. Mohon coba lagi.";
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query hapus.";
                }
            } else {
                $_SESSION['error_message'] = "Pengguna yang ingin dihapus tidak ditemukan.";
            }
            mysqli_stmt_close($stmt_check_user);
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan saat memeriksa pengguna.";
        }
    }
    header("Location: manage_users.php");
    exit();
}

// --- PROSES FORM (TAMBAH & UPDATE PENGGUNA) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Logika untuk TAMBAH PENGGUNA BARU
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        // Validasi username
        if (empty(trim($_POST["username"]))) {
            $username_err = "Mohon masukkan username.";
        } else {
            $sql = "SELECT id FROM users WHERE username = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $param_username);
                $param_username = trim($_POST["username"]);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        $username_err = "Username ini sudah terdaftar.";
                    } else {
                        $username = trim($_POST["username"]);
                    }
                } else {
                    $error_message = "Terjadi kesalahan saat memeriksa username. Mohon coba lagi nanti.";
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Validasi password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Mohon masukkan password.";
        } elseif (strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password harus memiliki minimal 6 karakter.";
        } else {
            $password = trim($_POST["password"]);
        }

        // Validasi role
        if (empty(trim($_POST["role"]))) {
            $role_err = "Mohon pilih peran (role).";
        } else {
            $role = trim($_POST["role"]);
            if ($role !== 'admin' && $role !== 'user') {
                $role_err = "Peran tidak valid.";
            }
        }

        // Jika tidak ada error, masukkan user baru ke database
        if (empty($username_err) && empty($password_err) && empty($role_err)) {
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sss", $param_username, $param_password, $param_role);
                $param_username = $username;
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $param_role = $role;

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Pengguna baru berhasil ditambahkan.";
                    header("Location: manage_users.php");
                    exit();
                } else {
                    $_SESSION['error_message'] = "Gagal menambahkan pengguna. Mohon coba lagi.";
                    header("Location: manage_users.php");
                    exit();
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query tambah.";
                header("Location: manage_users.php");
                exit();
            }
        } else {
            $error_message = "Mohon periksa kembali input Anda untuk menambahkan pengguna baru. Ada kesalahan.";
        }
    }
    // Logika untuk UPDATE PENGGUNA
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_user') {
        $update_id = filter_var(trim($_POST['user_id_edit']), FILTER_VALIDATE_INT);
        $update_username = trim($_POST['username_edit']);
        $update_role = trim($_POST['role_edit']);
        $update_password = trim($_POST['password_edit']); // Opsional: untuk perubahan password

        if ($update_id === false) {
            $_SESSION['error_message'] = "ID pengguna untuk update tidak valid.";
        } elseif (empty($update_username)) {
            $error_message = "Username tidak boleh kosong.";
        } elseif (empty($update_role)) {
            $error_message = "Peran tidak boleh kosong.";
        } elseif ($update_role !== 'admin' && $update_role !== 'user') {
            $error_message = "Peran tidak valid.";
        } else {
            // Cek apakah username sudah ada untuk pengguna lain (kecuali diri sendiri)
            $sql_check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check_username)) {
                mysqli_stmt_bind_param($stmt_check, "si", $update_username, $update_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $error_message = "Username ini sudah digunakan oleh pengguna lain.";
                }
                mysqli_stmt_close($stmt_check);
            }

            if (empty($error_message)) {
                $sql_update = "UPDATE users SET username = ?, role = ? WHERE id = ?";
                $param_types_update = "ssi";
                $param_values_update = [$update_username, $update_role, $update_id];

                // Jika password baru disediakan, update juga passwordnya
                if (!empty($update_password)) {
                    if (strlen($update_password) < 6) {
                        $error_message = "Password baru harus memiliki minimal 6 karakter.";
                    } else {
                        $sql_update = "UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?";
                        $param_types_update = "sssi";
                        $param_values_update = [$update_username, password_hash($update_password, PASSWORD_DEFAULT), $update_role, $update_id];
                    }
                }

                if (empty($error_message)) {
                    if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                        mysqli_stmt_bind_param($stmt_update, $param_types_update, ...$param_values_update);
                        if (mysqli_stmt_execute($stmt_update)) {
                            $_SESSION['success_message'] = "Pengguna berhasil diperbarui.";
                            header("Location: manage_users.php");
                            exit();
                        } else {
                            $_SESSION['error_message'] = "Gagal memperbarui pengguna. Mohon coba lagi.";
                            header("Location: manage_users.php");
                            exit();
                        }
                        mysqli_stmt_close($stmt_update);
                    } else {
                        $_SESSION['error_message'] = "Terjadi kesalahan pada persiapan query update.";
                        header("Location: manage_users.php");
                        exit();
                    }
                } else {
                     $_SESSION['error_message'] = $error_message; // Set error message to session for redirect
                     header("Location: manage_users.php");
                     exit();
                }
            } else {
                $_SESSION['error_message'] = $error_message; // Set error message to session for redirect
                header("Location: manage_users.php");
                exit();
            }
        }
    }
}

// Ambil daftar semua user untuk ditampilkan
$users_list = [];
$sql_users = "SELECT id, username, role FROM users ORDER BY username ASC";
$result_users = mysqli_query($link, $sql_users);
if ($result_users) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $users_list[] = $row;
    }
} else {
    $error_message = "Gagal mengambil daftar pengguna."; // Ini adalah error jika gagal fetch list
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Aplikasi Rekap Berita</title>
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
        /* Style for modal */
        .modal {
          display: none; /* Hidden by default */
          position: fixed; /* Stay in place */
          z-index: 1000; /* Sit on top */
          left: 0;
          top: 0;
          width: 100%; /* Full width */
          height: 100%; /* Full height */
          overflow: auto; /* Enable scroll if needed */
          background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
          padding-top: 60px;
        }

        .modal-content {
          background-color: #fefefe;
          margin: 5% auto; /* 5% from top and centered */
          padding: 20px;
          border: 1px solid #888;
          width: 90%; /* Responsive width */
          max-width: 500px; /* Max width */
          border-radius: 8px;
          position: relative;
          box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
          animation-name: animatetop;
          animation-duration: 0.4s
        }

        @keyframes animatetop {
          from {top: -300px; opacity: 0}
          to {top: 0; opacity: 1}
        }

        .close-button {
          color: #aaa;
          float: right;
          font-size: 28px;
          font-weight: bold;
          position: absolute;
          top: 10px;
          right: 20px;
        }

        .close-button:hover,
        .close-button:focus {
          color: black;
          text-decoration: none;
          cursor: pointer;
        }
        /* Responsive table for small screens */
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
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-4xl mx-auto bg-white p-8 my-8 rounded-lg shadow-xl">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Manajemen Pengguna</h2>
        <nav class="mb-8">
            <ul class="flex justify-center space-x-4 p-4 bg-blue-600 text-white rounded-lg shadow-md">
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

        <?php
        if (!empty($success_message)) {
            echo '<div class="bg-green-100 text-green-700 border border-green-200 p-4 mb-4 rounded-md font-medium">' . $success_message . '</div>';
        }
        if (!empty($error_message)) {
            echo '<div class="bg-red-100 text-red-700 border border-red-200 p-4 mb-4 rounded-md font-medium">' . $error_message . '</div>';
        }
        ?>
        <h3 class="text-2xl font-semibold text-gray-800 mb-4">Tambah Pengguna Baru</h3>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" name="username" id="username" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" name="password" id="password" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($password); ?>" required>
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($role_err)) ? 'has-error' : ''; ?>">
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Peran (Role):</label>
                <select name="role" id="role" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Pilih Peran</option>
                    <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?php echo ($role == 'user') ? 'selected' : ''; ?>>User</option>
                </select>
                <span class="help-block"><?php echo $role_err; ?></span>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Tambah Pengguna
                </button>
            </div>
        </form>

        <h3 class="text-2xl font-semibold text-gray-800 mb-4 mt-8">Daftar Pengguna</h3>
        <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200">
            <?php if (!empty($users_list)): ?>
            <table class="w-full whitespace-nowrap">
                <thead>
                    <tr class="bg-blue-600 text-white">
                        <th class="px-4 py-3 text-left font-semibold rounded-tl-lg">ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Username</th>
                        <th class="px-4 py-3 text-left font-semibold">Peran</th>
                        <th class="px-4 py-3 text-left font-semibold rounded-tr-lg">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users_list as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-800" data-label="ID"><?php echo $user['id']; ?></td>
                        <td class="px-4 py-3 text-gray-800" data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="px-4 py-3 text-gray-800" data-label="Peran"><?php echo htmlspecialchars($user['role']); ?></td>
                        <td class="px-4 py-3 space-x-2">
                            <button class="bg-green-500 hover:bg-green-600 text-white text-sm py-1 px-3 rounded-md transition duration-300 btn-edit"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>">Edit</button>
                            <a href="manage_users.php?action=delete&id=<?php echo $user['id']; ?>"
                               onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna \'<?php echo htmlspecialchars($user['username']); ?>\'?');"
                               class="bg-red-500 hover:bg-red-600 text-white text-sm py-1 px-3 rounded-md transition duration-300">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="p-4 text-gray-500 text-center">Tidak ada pengguna terdaftar.</p>
            <?php endif; ?>
        </div>

        <!-- The Modal Structure for Edit User -->
        <div id="editUserModal" class="modal">
            <div class="modal-content">
                <span class="close-button">&times;</span>
                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Edit Pengguna</h3>
                <form id="editUserForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id_edit" id="user_id_edit">

                    <div class="form-group">
                        <label for="username_edit" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                        <input type="text" name="username_edit" id="username_edit" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="form-group">
                        <label for="password_edit" class="block text-gray-700 text-sm font-bold mb-2">Password Baru (kosongkan jika tidak ingin diubah):</label>
                        <input type="password" name="password_edit" id="password_edit" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="role_edit" class="block text-gray-700 text-sm font-bold mb-2">Peran (Role):</label>
                        <select name="role_edit" id="role_edit" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById("editUserModal");
            var span = document.getElementsByClassName("close-button")[0];
            var editButtons = document.querySelectorAll(".btn-edit");

            // Ketika user mengklik <span> (x), tutup modal
            span.onclick = function() {
              modal.style.display = "none";
            }

            // Ketika user mengklik di luar modal, tutup modal
            window.onclick = function(event) {
              if (event.target == modal) {
                modal.style.display = "none";
              }
            }

            // Ketika tombol edit diklik, buka modal dan isi form
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    var userId = this.getAttribute('data-id');
                    var username = this.getAttribute('data-username');
                    var role = this.getAttribute('data-role');

                    document.getElementById('user_id_edit').value = userId;
                    document.getElementById('username_edit').value = username;
                    document.getElementById('role_edit').value = role;
                    document.getElementById('password_edit').value = ''; // Kosongkan field password untuk keamanan

                    modal.style.display = "block";
                });
            });
        });
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($link);
?>
