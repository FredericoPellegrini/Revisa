<?php
session_start();
require 'config.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        $erro = 'Preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, senha_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            $erro = 'Email ou senha incorretos.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            header('Location: dashboard.php');
            exit;
        }
    }
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
        <p>Ainda não tem conta? <a href="register.php">cadastre-se</a></p>
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


            <?php if (!empty($erro)): ?>
        <script>
            window.onload = function() {
                alert("<?= addslashes($erro) ?>");
            };
        </script>
        <?php endif; ?>
</body>
</html>

