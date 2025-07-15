<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        die('Preencha todos os campos.');
    }

    $stmt = $pdo->prepare("SELECT id, nome, senha_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha, $user['senha_hash'])) {
        die('Email ou senha incorretos.');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nome'] = $user['nome'];

    header('Location: dashboard.php'); // página principal do sistema
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <title>Revisa | Login</title>
</head>
<body>

    <div id="cad">
        <p>Ainda não tem conta? <a href="#">cadastre-se</a></p>
    </div>

    <div class="container">
        <div id="logo">
            <img src="images/logo.png" alt="Logo" id="img1">
            <img src="images/nome.png" alt="Nome Revisa" id="img2">
        </div>

        <form method="post" action="">
            <input type="email" name="email" placeholder="E-mail" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">Login</button>
        </form>
    </div>

</body>
</html>

