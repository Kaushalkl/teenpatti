<?php
session_start();
require_once "../config/db.php";

// पहले से लॉगिन है?
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$redirect_url = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === "" || $password === "") {
        $error = "CRITICAL_ERROR: USERNAME_AND_PASSWORD_REQUIRED";
    } else {

        $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username=? LIMIT 1");
        if (!$stmt) {
            error_log("DB prepare failed: " . $conn->error);
            $error = "SERVER_ERROR: DATABASE_INIT_FAILURE";
        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($admin_id, $user, $hashed_pass);
                $stmt->fetch();

                if (password_verify($password, $hashed_pass)) {

                    if (password_needs_rehash($hashed_pass, PASSWORD_BCRYPT)) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $u = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
                        if ($u) {
                            $u->bind_param("si", $newHash, $admin_id);
                            $u->execute();
                            $u->close();
                        }
                    }

                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['admin_username'] = $user;

                    $redirect_url = 'dashboard.php';

                } else {
                    $error = "AUTHENTICATION_FAILED: INVALID_CREDENTIALS_HASH_MISMATCH";
                }

            } else {
                $error = "AUTHENTICATION_FAILED: INVALID_CREDENTIALS_USER_NOT_FOUND";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<style>
    body {
        background: radial-gradient(circle at center, #001100, #000);
        font-family: 'Orbitron', sans-serif;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }

    /* Moving Matrix Background */
    .matrix {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        opacity: 0.15;
        background: url('https://i.imgur.com/1e73JZP.gif') repeat;
        background-size: cover;
        z-index: 0;
    }

    .login-card {
        width: 420px;
        padding: 35px;
        background: rgba(0, 20, 0, 0.75);
        border: 2px solid #0f0;
        border-radius: 12px;
        box-shadow: 0 0 25px #00ff00;
        z-index: 2;
        animation: pulse 3s infinite alternate;
        backdrop-filter: blur(4px);
    }

    @keyframes pulse {
        from { box-shadow: 0 0 15px #00ff00; }
        to { box-shadow: 0 0 35px #00ff99; }
    }

    h3 {
        color: #0f0;
        text-align: center;
        margin-bottom: 20px;
        letter-spacing: 3px;
        text-shadow: 0 0 10px #0f0;
        font-weight: bold;
    }

    label {
        color: #00ff99;
    }

    .form-control {
        background: #000;
        border: 1px solid #00ff66;
        color: #00ff99;
        box-shadow: inset 0 0 10px #003300;
    }

    .form-control:focus {
        border-color: #00ff00;
        box-shadow: 0 0 12px #00ff00;
        background: #000;
        color: #0f0;
    }

    .btn-login {
        background: linear-gradient(90deg, #00ff00, #00cc66);
        border: none;
        padding: 10px;
        width: 100%;
        font-size: 16px;
        font-weight: bold;
        border-radius: 6px;
        color: #000;
        box-shadow: 0 0 20px #00ff00;
    }

    .btn-login:hover {
        box-shadow: 0 0 35px #00ff66;
        background: #00ff33;
    }

    .switch-login {
        margin-top: 18px;
        text-align: center;
    }

    .switch-login a {
        color: #00ff99;
        border: 1px solid #00ff99;
        padding: 8px 15px;
        border-radius: 6px;
        text-decoration: none;
        box-shadow: 0 0 12px #00ff99;
    }

    .switch-login a:hover {
        box-shadow: 0 0 22px #00ffaa;
    }

    .footer {
        margin-top: 12px;
        text-align: center;
        color: #0f0;
        font-size: 11px;
        opacity: 0.8;
    }

</style>
</head>

<body>

<div class="matrix"></div>

<div class="login-card">

    <h3>SECURE ADMIN ACCESS</h3>

    <?php if ($error != ""): ?>
        <p style="color:#ff4444; text-align:center;">ERROR: <?= $error ?></p>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">
            <label>USERNAME</label>
            <input type="text" name="username" class="form-control" required autocomplete="off">
        </div>

        <div class="mb-3">
            <label>PASSWORD</label>
            <input type="password" name="password" class="form-control" required autocomplete="off">
        </div>

        <button class="btn btn-login">LOGIN</button>
    </form>

    <div class="switch-login">
        <a href="../user/login.php">⬅ USER LOGIN</a>
    </div>

    <div class="footer">
        © 2025 SECURE ADMIN SYSTEM
    </div>

</div>

<?php if ($redirect_url != ""): ?>
<script>
    setTimeout(()=>{ window.location.href = "<?= $redirect_url ?>"; }, 900);
</script>
<?php endif; ?>

</body>
</html>
