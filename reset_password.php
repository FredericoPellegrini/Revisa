<?php
require 'config.php';

$msg = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $nova_senha = $_POST['senha'] ?? '';

    if ($token && $nova_senha) {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expira > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {
            // Atualiza a senha
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $reset['user_id']]);

            // Apaga o token usado
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $msg = "Senha redefinida com sucesso! <a href='login.php'>Login</a>";
        } else {
            $msg = "Token inválido ou expirado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir senha</title>
</head>
<body>
    <h2>Redefinir senha</h2>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="senha" placeholder="Nova senha" required>
        <button type="submit">Salvar</button>
    </form>
    <p><?= $msg ?></p>
</body>
</html>
