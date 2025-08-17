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
        // CORREÇÃO AQUI: Mudado 'email_verificado' para 'ativo'
        $stmt = $pdo->prepare("SELECT id, nome, senha_hash, ativo FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            $erro = 'E-mail ou senha incorretos.';
        } 
        // CORREÇÃO AQUI: Mudado '$user['email_verificado']' para '$user['ativo']'
        elseif ($user['ativo'] == 0) { 
            $erro = 'Você precisa confirmar seu e-mail antes de fazer login. Verifique sua caixa de entrada.';
        } else {
            // Login ok
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
    <link rel="stylesheet" href="css/telasinicias.css">
    <title>Revisa | Login</title>
</head>
<body>

    <div id="cad">
        <p>Ainda não tem conta? <a href="register.php">cadastre-se</a></p>
    </div>

    <div class="container">
        <div class="logo-container">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>

            <div id="logo">
                <img src="images/logo.png" alt="Logo" id="img1">
                <img src="images/nome.png" alt="Nome Revisa" id="img2">
            </div>
        </div>

        <form method="post" action="">
            <?php if (!empty($erro)): ?>
                <div class="erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <input type="email" name="email" placeholder="E-mail" 
                    value="<?= htmlspecialchars($email ?? '') ?>" 
                    class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>

            <input type="password" name="senha" placeholder="Senha" 
                    class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>

            <button type="submit">Login</button>
        </form>

        <p><a href="forgot_password.php" id="linkesqueceusenha">Esqueceu a senha?</a></p>
    </div>

</body>
</html>
