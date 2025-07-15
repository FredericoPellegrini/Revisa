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

<!-- Formulário HTML simples para login -->
<form method="post" action="">
    Email: <input type="email" name="email" required><br>
    Senha: <input type="password" name="senha" required><br>
    <button type="submit">Entrar</button>
</form>
