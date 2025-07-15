<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';

    if (!$nome || !$email || !$senha) {
        die('Preencha todos os campos.');
    }
    if ($senha !== $senha_confirm) {
        die('Senhas não conferem.');
    }

    // Verifica se email já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die('Email já cadastrado.');
    }

    // Hash da senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Insere no banco
    $stmt = $pdo->prepare("INSERT INTO users (nome, email, senha_hash) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $email, $senha_hash]);

    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['user_nome'] = $nome;

    header('Location: dashboard.php'); // ou página principal
    exit;
}
?>

<!-- Formulário HTML simples para cadastro -->
<form method="post" action="">
    Nome: <input type="text" name="nome" required><br>
    Email: <input type="email" name="email" required><br>
    Senha: <input type="password" name="senha" required><br>
    Confirmar Senha: <input type="password" name="senha_confirm" required><br>
    <button type="submit">Cadastrar</button>
</form>
