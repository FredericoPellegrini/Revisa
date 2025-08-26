<?php
require 'config.php';

$msg = '';
$token = $_GET['token'] ?? ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? ''; 
    $nova_senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['senha_confirm'] ?? ''; 


    if (empty($nova_senha) || empty($confirma_senha)) {
        $msg = 'Por favor, preencha ambos os campos de senha.';
    } elseif ($nova_senha !== $confirma_senha) {
        $msg = 'As senhas não conferem.';
    } elseif (strlen($nova_senha) < 8 || !preg_match('/[A-Za-z]/', $nova_senha) || !preg_match('/[0-9]/', $nova_senha)) {
        $msg = 'A senha deve ter no mínimo 8 caracteres, incluindo letras e números.';
    } else {
        if ($token) {
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expira > NOW()");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if ($reset) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET senha_hash = ? WHERE id = ?");
                $stmt->execute([$senha_hash, $reset['user_id']]);

                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);

                $msg = "Senha redefinida com sucesso! <a href='login.php'>Login</a>";
            } else {
                $msg = "Token inválido ou expirado.";
            }
        } else {
            $msg = "Token de redefinição não fornecido.";
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
    <link rel="stylesheet" href="css/resetpassword.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Redefinir senha</title>
</head>
<body>



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

    <h2>Redefinir senha</h2>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div class="password-field">
            <input type="password" name="senha" placeholder="Nova senha" 
                   class="<?= !empty($msg) && (strpos($msg, 'Senhas não conferem') !== false || strpos($msg, 'A senha deve ter') !== false) ? 'input-erro' : '' ?>"
                   onpaste="return false;" required>
            <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                <i class="fas fa-eye"></i> 
            </span>
        </div>

        <div class="password-field">
            <input type="password" name="senha_confirm" placeholder="Confirmar nova senha" 
                   class="<?= !empty($msg) && strpos($msg, 'Senhas não conferem') !== false ? 'input-erro' : '' ?>"
                   onpaste="return false;" required>
            <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                <i class="fas fa-eye"></i> 
            </span>
        </div>

        <button type="submit">Salvar</button>
    </form>
    <p class="msg"><?= $msg ?></p> 

<script src="js/reset_password.js"></script>

</body>
</html> 