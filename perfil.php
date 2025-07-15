<?php
session_start();
require 'config.php';

// Garante que só usuários logados acessem
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Carrega dados atuais do usuário
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nome, email, senha_hash FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die('Usuário não encontrado.');
}

// Atualizar nome/email
if (isset($_POST['atualizar_info'])) {
    $novo_nome = trim($_POST['nome'] ?? '');
    $novo_email = trim($_POST['email'] ?? '');

    if (!$novo_nome || !$novo_email) {
        $msg = "Nome e email não podem ficar vazios.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nome = ?, email = ? WHERE id = ?");
        $stmt->execute([$novo_nome, $novo_email, $user_id]);
        $_SESSION['user_nome'] = $novo_nome;
        $msg = "Informações atualizadas com sucesso!";
    }
}

// Atualizar senha
if (isset($_POST['atualizar_senha'])) {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if (!password_verify($senha_atual, $usuario['senha_hash'])) {
        $msg = "Senha atual incorreta.";
    } elseif ($nova_senha !== $confirmar) {
        $msg = "Nova senha e confirmação não conferem.";
    } else {
        $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET senha_hash = ? WHERE id = ?");
        $stmt->execute([$nova_hash, $user_id]);
        $msg = "Senha atualizada com sucesso!";
    }
}

// Excluir conta
if (isset($_POST['excluir_conta'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    session_destroy();
    header('Location: login.php?msg=Conta excluída');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Perfil do Usuário</title>
</head>
<body>
    <h2>Perfil de <?= htmlspecialchars($_SESSION['user_nome']) ?></h2>

    <?php if (isset($msg)) echo "<p><strong>$msg</strong></p>"; ?>

    <form method="post">
        <h3>Atualizar Nome/Email</h3>
        Nome: <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required><br>
        Email: <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required><br>
        <button type="submit" name="atualizar_info">Salvar Alterações</button>
    </form>

    <hr>

    <form method="post">
        <h3>Alterar Senha</h3>
        Senha atual: <input type="password" name="senha_atual" required><br>
        Nova senha: <input type="password" name="nova_senha" required><br>
        Confirmar nova senha: <input type="password" name="confirmar_senha" required><br>
        <button type="submit" name="atualizar_senha">Alterar Senha</button>
    </form>

    <hr>

    <form method="post" onsubmit="return confirm('Tem certeza que deseja excluir sua conta? Isso não poderá ser desfeito!');">
        <h3>Excluir Conta</h3>
        <button type="submit" name="excluir_conta" style="color:red;">Excluir minha conta</button>
    </form>

    <hr>
    <p><a href="dashboard.php">← Voltar para Dashboard</a> | <a href="logout.php">Sair</a></p>
</body>
</html>
