<?php
// user/register.php
require_once __DIR__ . '/../config/db.php';
session_start();
$message = '';
$message_class = ''; // To control alert color

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if(!$name || !$email || !$password){
        $message = "ERROR: ALL_FIELDS_ARE_REQUIRED.";
        $message_class = 'alert-danger';
    } else {
        // Check if email already exists before hashing
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $check_res = $stmt_check->get_result();
        $stmt_check->close();

        if ($check_res->num_rows > 0) {
            $message = "ERROR: EMAIL_ADDRESS_ALREADY_IN_DATABASE.";
            $message_class = 'alert-danger';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Use prepared statement for insertion
            $stmt = $conn->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
            $stmt->bind_param("sss",$name,$email,$hash);
            
            if($stmt->execute()){
                $message = "SUCCESS: ACCOUNT_CREATED. INITIALIZING_SESSION...";
                $message_class = 'alert-success';
                
                // Immediate session start and redirect
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['user_name'] = $name;
                // Since wallet_balance is likely defaulted to 0 in DB
                $_SESSION['user_wallet'] = 0.00; 
                header('Location: wallet.php');
                exit;
            } else {
                // Generic database insert error
                $message = "FATAL_ERROR: DATABASE_WRITE_FAILURE.";
                $message_class = 'alert-danger';
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register • SYSTEM ACCESS REQUEST</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    /* Custom Styles for Hacker Terminal UI - Consistent Theme */
    :root {
        --color-background: #000000;
        --color-primary: #00FF41; /* Neon Green */
        --color-secondary: #00C740; /* Darker Green */
        --color-text-dim: #777;
        --color-terminal-red: #FF4000; /* Reddish Orange for errors */
        --color-terminal-yellow: #FFFF00; /* Yellow for warnings/prompts */
        --shadow-glow: 0 0 8px rgba(0, 255, 65, 0.6);
        --shadow-subtle: 0 0 3px rgba(0, 255, 65, 0.3);
    }
    body {
        background-color: var(--color-background) !important;
        color: var(--color-primary) !important;
        font-family: 'Consolas', 'Courier New', monospace; 
        line-height: 1.4;
    }
    h1, h2, h3, h4 {
        color: var(--color-primary) !important;
        text-shadow: var(--shadow-subtle);
    }
    .text-muted-hacker {
        color: var(--color-text-dim) !important;
        font-size: 0.85rem;
    }

    /* Card Styling (Terminal Block) */
    .modern-card {
        background-color: #0A0A0A;
        border: 2px solid var(--color-primary);
        border-radius: 4px; /* Boxy look */
        box-shadow: 0 0 15px var(--color-primary); /* Stronger glow for the main panel */
        padding: 2rem;
    }
    .card-title {
        text-transform: uppercase;
        letter-spacing: 2px;
        border-bottom: 1px dashed var(--color-secondary);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    /* Form Elements */
    .form-control {
        background-color: #1A1A1A !important;
        color: var(--color-primary) !important;
        border: 1px solid var(--color-secondary) !important;
        border-radius: 2px !important;
    }
    .form-control:focus {
        background-color: #1A1A1A !important;
        border-color: var(--color-primary) !important;
        box-shadow: 0 0 5px var(--color-primary) !important;
    }
    .form-label {
        color: var(--color-terminal-yellow) !important;
        text-transform: uppercase;
        font-size: 0.9rem;
    }

    /* Alert Styles (Terminal Style) */
    .alert {
        font-weight: bold;
        border-radius: 2px;
        font-family: 'Consolas', monospace;
    }
    .alert-danger { 
        background-color: rgba(255, 64, 0, 0.1) !important; 
        border: 1px solid var(--color-terminal-red) !important;
        color: var(--color-terminal-red) !important; 
    }
    .alert-warning {
        background-color: rgba(255, 255, 0, 0.1) !important; 
        border: 1px solid var(--color-terminal-yellow) !important;
        color: var(--color-terminal-yellow) !important; 
    }
    .alert-success {
        background-color: rgba(0, 255, 65, 0.1) !important; 
        border: 1px solid var(--color-primary) !important;
        color: var(--color-primary) !important; 
    }
    
    /* Button Styles */
    .btn-hacker {
        border-radius: 2px;
        font-weight: bold;
        text-transform: uppercase;
        transition: all 0.2s;
        border: 1px solid;
    }
    .btn-primary-hacker {
        background-color: var(--color-primary) !important;
        border-color: var(--color-primary) !important;
        color: #000 !important;
        box-shadow: 0 0 10px var(--color-primary);
    }
    .btn-primary-hacker:hover {
        background-color: var(--color-secondary) !important;
        box-shadow: 0 0 15px var(--color-primary);
    }
    
    /* Login Link */
    .link-hacker {
        color: var(--color-terminal-yellow) !important;
        text-decoration: none;
    }
    .link-hacker:hover {
        text-decoration: underline;
        color: var(--color-primary) !important;
    }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card modern-card shadow">
        <div class="card-body">
          <h3 class="card-title">ACCESS: NEW USER PROFILE CREATION</h3>
          
          <?php if($message): ?>
            <div class="alert <?php echo $message_class; ?>" role="alert">
                <?php 
                if ($message_class === 'alert-danger') {
                    echo '<i class="fas fa-exclamation-triangle"></i> ';
                } elseif ($message_class === 'alert-success') {
                     echo '<i class="fas fa-check-circle"></i> ';
                }
                echo htmlspecialchars($message); 
                ?>
            </div>
          <?php endif; ?>
          
          <form method="post" novalidate class="d-grid gap-3">
            <div class="mb-3">
              <label class="form-label" for="nameInput">USER ALIAS / NAME</label>
              <input name="name" required class="form-control" id="nameInput" placeholder="ENTER DESIRED NAME" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="emailInput">EMAIL ADDRESS (USER ID)</label>
              <input name="email" type="email" required class="form-control" id="emailInput" placeholder="SYSTEM EMAIL ADDRESS" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="passwordInput">PASSWORD HASH (ENCRYPT)</label>
              <input name="password" type="password" required class="form-control" id="passwordInput" placeholder="SET ENCRYPTED PASSWORD" />
            </div>
            <button class="btn btn-primary-hacker w-100 btn-lg btn-hacker mt-2">CREATE & INITIATE ACCOUNT</button>
          </form>
          
          <hr style="border-color: #333;" class="my-4">
          
          <div class="text-center small text-muted-hacker">
            <span style="color: var(--color-text-dim);">ALREADY HAVE AN ACCOUNT?</span> 
            <a href="login.php" class="link-hacker fw-bold">INITIATE LOGIN</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>