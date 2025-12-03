<?php
// user/login.php
require_once __DIR__ . '/../config/db.php';
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id,name,password,wallet_balance FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res && $res->num_rows === 1){
        $u = $res->fetch_assoc();

        if(password_verify($password, $u['password'])){
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_wallet'] = (float)$u['wallet_balance'];
            header('Location: wallet.php');
            exit;
        } else $error = "AUTH_FAILED: HASH_MISMATCH";
    } 
    else $error = "AUTH_FAILED: EMAIL_NOT_FOUND";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Login • CYBER ACCESS PANEL</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>

/* ============================================================
   GLOBAL COLOR
============================================================ */
:root {
    --neon: #00ff41;
    --neon2: #00d637;
    --dark: #000;
    --error: #ff0037;
    --yellow: #f6ff00;
}

/* ============================================================
   MATRIX BACKGROUND
============================================================ */
body {
    margin: 0;
    background: black;
    color: var(--neon);
    font-family: Consolas, monospace;
    overflow: hidden;
}

.matrix-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: 100%;
    background: url('https://i.ibb.co/y5FSh0x/matrix.gif');
    opacity: 0.18;
    mix-blend-mode: screen;
    z-index: -1;
}

/* ============================================================
   HACKING TITLE GLITCH EFFECT
============================================================ */
.glitch-title {
    font-size: 30px;
    font-weight: bold;
    letter-spacing: 4px;
    color: var(--neon);
    text-align: center;
    text-shadow: 0 0 10px var(--neon);
    position: relative;
    animation: glitch 1.8s infinite;
}

@keyframes glitch {
    0% { text-shadow: 2px 0 red, -2px 0 blue; }
    20% { text-shadow: -4px 0 red, 4px 0 blue; }
    40% { text-shadow: 2px 0 red, -2px 0 blue; }
    60% { text-shadow: -2px 0 red, 2px 0 blue; }
    100% { text-shadow: 2px 0 red, -2px 0 blue; }
}

/* ============================================================
   LOGIN CARD - CYBER BLOCK 
============================================================ */
.cyber-card {
    background: rgba(0, 0, 0, 0.8);
    border: 1px solid var(--neon);
    padding: 28px;
    border-radius: 10px;
    box-shadow: 0 0 20px var(--neon);
    position: relative;
}

/* SCAN LINE ANIMATION BORDER */
.cyber-card:before {
    content: "";
    position: absolute;
    top: -2px; left: -2px;
    width: calc(100% + 4px);
    height: calc(100% + 4px);
    border: 2px solid var(--neon);
    animation: borderScan 4s linear infinite;
}

@keyframes borderScan {
    0% { clip-path: inset(0 100% 100% 0); }
    25% { clip-path: inset(0 0 100% 0); }
    50% { clip-path: inset(0 0 0 0); }
    75% { clip-path: inset(100% 0 0 0); }
    100% { clip-path: inset(0 100% 100% 0); }
}

/* ============================================================
   INPUTS
============================================================ */
.cyber-input {
    background: #050505;
    color: var(--neon);
    border: 1px solid var(--neon2);
    box-shadow: 0 0 10px var(--neon2);
}

.cyber-input:focus {
    border-color: var(--neon);
    box-shadow: 0 0 15px var(--neon);
    background: black;
}

/* ============================================================
   ERROR BOX WITH FLICKER
============================================================ */
.error-box {
    border: 1px solid var(--error);
    background: rgba(255,0,0,0.12);
    color: var(--error);
    padding: 10px;
    border-radius: 5px;
    animation: flicker 0.22s infinite alternate;
}

@keyframes flicker {
    from { opacity: 1; }
    to { opacity: 0.6; }
}

/* ============================================================
   HACKER LOGIN BUTTON
============================================================ */
.btn-hack {
    background: var(--neon);
    color: black;
    font-weight: bold;
    border: none;
    padding: 12px;
    width: 100%;
    text-transform: uppercase;
    border-radius: 5px;
    box-shadow: 0 0 15px var(--neon);
    transition: 0.2s;
}

.btn-hack:hover {
    background: var(--neon2);
    box-shadow: 0 0 25px var(--neon2);
    transform: scale(1.05);
}

/* ADMIN BUTTON */
.admin-btn {
    background: var(--yellow);
    color: black;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: bold;
    box-shadow: 0 0 10px var(--yellow);
}

.admin-btn:hover {
    box-shadow: 0 0 20px var(--yellow);
}

/* FOOTER */
.foot {
    text-align: center;
    font-size: 12px;
    color: #888;
    margin-top: 10px;
}

</style>
</head>
<body>

<div class="matrix-bg"></div>

<div class="container d-flex justify-content-center align-items-center" style="height:100vh;">
    <div class="col-md-5">

        <div class="cyber-card">

            <h2 class="glitch-title mb-4">USER ACCESS PANEL</h2>

            <?php if($error): ?>
            <div class="error-box mb-3">
                <i class="fa-solid fa-skull-crossbones"></i> SYSTEM ERROR: <?= $error ?>
            </div>
            <?php endif; ?>

            <form method="POST">

                <label>Email Access ID</label>
                <input type="email" name="email" class="form-control cyber-input mb-3"
                       placeholder="enter registered email" required>

                <label>Password Key</label>
                <input type="password" name="password" class="form-control cyber-input mb-4"
                       placeholder="enter secure password" required>

                <button class="btn-hack">INITIATE LOGIN</button>

            </form>

            <hr style="border-color:var(--neon); opacity:0.4;">

            <div class="d-flex justify-content-between align-items-center">

                <a href="../admin/login.php" class="admin-btn">
                    <i class="fa-solid fa-user-shield"></i> ADMIN PANEL
                </a>

                <a href="register.php" style="color:var(--yellow); text-decoration:none;">REQUEST REGISTRATION</a>
            </div>

            <div class="foot">© 2025 CYBER AUTH SYSTEM</div>

        </div>

    </div>
</div>

</body>
</html>
