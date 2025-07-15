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

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisa | Cadastro</title>
    <link rel="stylesheet" href="css/cadastro.css">
</head>
<body>

    <div id="voltar">
        <a href="login.php">Voltar</a>
    </div>

    <div class="container">
        <div id="logo">
            <img src="images/logo.png" alt="Logo" id="img1">
            <img src="images/nome.png" alt="Nome Revisa" id="img2">
        </div>

        <form method="post" action="">
            <input type="text" name="nome" placeholder="Nome" required>
            <input type="email" name="email" placeholder="E-mail" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <input type="password" name="senha_confirm" placeholder="Confirmar Senha" required>
            <button type="submit">Cadastrar</button>
        </form>
    </div>

</body>
</html>
