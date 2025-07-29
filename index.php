<?php
// index.php (Halaman Login)

require_once 'config.php'; // Pastikan session_start() ada di config.php

// Cek apakah user sudah login, jika ya, redirect ke dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

$username = $password = "";
$username_err = $password_err = "";

// Ambil pesan error dari session jika ada (misal dari manage_users.php jika tidak ada akses)
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']); // Hapus pesan setelah ditampilkan

// Proses form saat data dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Mohon masukkan username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Validasi password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Mohon masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Jika tidak ada error validasi input
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Cek jika username ada, lalu verifikasi password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            // Password benar, mulai session
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;

                            // Redirect ke halaman dashboard
                            header("location: dashboard.php");
                            exit();
                        } else {
                            // Password tidak valid
                            $password_err = "Password yang Anda masukkan salah.";
                        }
                    }
                } else {
                    // Username tidak ditemukan
                    $username_err = "Tidak ada akun ditemukan dengan username tersebut.";
                }
            } else {
                $error_message = "Terjadi kesalahan. Mohon coba lagi nanti.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Terjadi kesalahan pada persiapan query. Mohon coba lagi.";
        }
    }
    // Jika ada error validasi, tampilkan pesan error umum
    if (!empty($username_err) || !empty($password_err)) {
        $error_message = "Mohon periksa kembali input Anda.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Rekap Berita</title>
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
        /* Custom styles to override or add specific details */
        .help-block {
            color: #dc3545; /* Red for errors */
            font-size: 0.875em;
            margin-top: 5px;
            display: block;
        }
        .form-group.has-error input {
            border-color: #dc3545;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">
    <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-xl">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-6">Login</h2>
        <p class="text-center text-gray-600 mb-6">Masukkan Username dan Password untuk login</p>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" name="username" id="username" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($username); ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" name="password" id="password" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Login
                </button>
            </div>
        </form>
    </div>
</body>
</html>
<?php
// Tutup koneksi database (tidak diperlukan di sini karena tidak ada koneksi yang dibuka secara eksplisit)
// mysqli_close($link); // Ini akan menyebabkan error jika $link belum didefinisikan
?>
