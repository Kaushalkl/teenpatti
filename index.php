<?php
session_start();

// If user not logged in → redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: user/login.php");
    exit;
}

$user_name = $_SESSION['user_name'];
$wallet = $_SESSION['wallet'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teen Patti – Premium Lobby</title>

    <!-- BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">

    <!-- GOOGLE FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>

        body {
            background: radial-gradient(circle at top, #202020, #000000);
            font-family: 'Poppins', sans-serif;
            color: white;
            overflow-x: hidden;
        }

        /* NAVBAR */
        .navbar {
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(12px);
            padding: 15px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .wallet-btn {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            border: none;
            padding: 6px 15px;
            border-radius: 10px;
            font-weight: 600;
        }

        .logout-btn {
            background: #ff3b3b;
            border: none;
            padding: 6px 15px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
        }

        /* GAME CARD */
        .game-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(15px);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            transition: 0.4s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .game-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0px 0px 25px rgba(255, 204, 0, 0.7);
            border-color: #ffcc00;
        }

        .game-card img {
            width: 100%;
            border-radius: 15px;
            margin-bottom: 15px;
        }

        .play-btn {
            background: linear-gradient(45deg, #ffcc00, #ff8800);
            padding: 12px;
            border-radius: 12px;
            border: none;
            width: 100%;
            font-weight: 700;
            color: #000;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .play-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px #ffcc00;
        }

        /* Glowing heading */
        h2 {
            text-shadow: 0px 0px 15px #ffcc00;
        }

    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar d-flex justify-content-between">
    <div class="fs-3 text-warning fw-bold">Teen Patti</div>

    <div class="text-white fs-6">
        Welcome, <b><?php echo $user_name; ?></b>
    </div>

    <div>
        <a href="user/wallet.php" class="wallet-btn">
            Wallet: ₹<?php echo number_format($wallet); ?>
        </a>
        <a href="user/logout.php" class="logout-btn ms-2">
            Logout
        </a>
    </div>
</nav>

<!-- MAIN LOBBY -->
<div class="container py-5">

    <h2 class="text-center mb-5 fw-bold">Select Your Game Mode</h2>

    <div class="row g-4">

        <!-- Teen Patti Classic -->
        <div class="col-md-4">
            <div class="game-card">
                <img src="https://i.imgur.com/qTqWd9i.jpeg">
                <h4 class="mt-2">Teen Patti Classic</h4>
                <p>Play with smart AI bots. Fast & smooth gameplay.</p>
                <a href="user/bet.php">
                    <button class="play-btn">Play Now</button>
                </a>
            </div>
        </div>

        <!-- Teen Patti Joker -->
        <div class="col-md-4">
            <div class="game-card" onclick="comingSoon()">
                <img src="https://i.imgur.com/OF1q661.jpeg">
                <h4 class="mt-2">Teen Patti Joker</h4>
                <p>Joker twist for high power gameplay.</p>
                <button class="play-btn">Coming Soon</button>
            </div>
        </div>

        <!-- 3 Card Poker -->
        <div class="col-md-4">
            <div class="game-card" onclick="comingSoon()">
                <img src="https://i.imgur.com/JTf3fM7.jpeg">
                <h4 class="mt-2">3 Card Poker</h4>
                <p>Casino-style real poker experience.</p>
                <button class="play-btn">Coming Soon</button>
            </div>
        </div>

    </div>
</div>

<script>
function comingSoon() {
    alert("🚀 This game mode will be added soon!");
}
</script>

</body>
</html>
