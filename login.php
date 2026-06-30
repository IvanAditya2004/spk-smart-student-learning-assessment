<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* BODY BACKGROUND */
body {
    font-family: 'Inter', sans-serif;
    height: 100vh;

    background: url('assets/images/login.png') no-repeat center;
    background-size: cover;

    display: flex;
    align-items: center;
    justify-content: center;
}

/* LOGIN CARD */
.login-box {
    width: 100%;
    max-width: 380px;
    padding: 40px;
    border-radius: 20px;

    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(10px);

    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.43);

    text-align: center;
}

/* HEADER */
.login-header i {
    font-size: 40px;
    color: #2a6df4;
    margin-bottom: 10px;
}

.login-header h2 {
    font-weight: 600;
    color: #333;
}

.login-header p {
    font-size: 13px;
    color: #777;
}

/* INPUT */
.input-group {
    position: relative;
    margin-top: 20px;
}

.input-group input {
    width: 100%;
    padding: 14px 40px;
    border-radius: 12px;

    border: 1px solid #606060;
    background: #f7f9fc;

    font-size: 14px;
    outline: none;
}

/* LABEL */
.input-group label {
    position: absolute;
    left: 40px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    color: #888;
    transition: 0.3s;
}

/* FLOAT */
.input-group input:focus + label,
.input-group input:not(:placeholder-shown) + label {
    top: -8px;
    left: 35px;
    font-size: 11px;
    background: white;
    padding: 2px 6px;
    border-radius: 5px;
    color: #2a6df4;
}

/* ICON */
.input-group i {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.left-icon { left: 12px; }
.right-icon { right: 12px; cursor: pointer; }

/* BUTTON */
.btn-login {
    width: 100%;
    margin-top: 25px;
    padding: 14px;
    border-radius: 12px;

    border: none;
    background: #2a6df4;
    color: white;
    font-weight: 600;

    cursor: pointer;
    transition: 0.3s;
}

.btn-login:hover {
    background: #0d3380;
}

/* TEXT BAWAH */
.footer-text {
    margin-top: 15px;
    font-size: 12px;
    color: #3e3e3e;
}

/* ALERT */
.alert {
    margin-top: 15px;
    padding: 10px;
    border-radius: 8px;

    background: #ffe5e5;
    color: #d60000;
    font-size: 13px;
}

</style>
</head>

<body>

<div class="login-box">

    <div class="login-header">
        <i class="fas fa-graduation-cap"></i>
        <h2>Login</h2>
        <p>Masuk ke sistem</p>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">

        <div class="input-group">
            <i class="fas fa-user left-icon"></i>
            <input type="text" name="username" required placeholder=" ">
            <label>Username</label>
        </div>

        <div class="input-group">
            <i class="fas fa-lock left-icon"></i>
            <input type="password" name="password" required placeholder=" ">
            <label>Password</label>
            <i class="fas fa-eye right-icon toggle-pass"></i>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            Masuk
        </button>

    </form>

    <div class="footer-text">
       SMP NEGERI 1 JOGOROTO 
    </div>

</div>

<script>

// TOGGLE PASSWORD
document.querySelector('.toggle-pass').addEventListener('click', function() {
    const input = document.querySelector('input[name="password"]');
    input.type = input.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye-slash');
});

// LOADING
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.innerText = 'Memproses...';
});

</script>

</body>
</html>