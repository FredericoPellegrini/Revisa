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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil do Usuário</title>
    <link rel="stylesheet" href="css/perfil.css">
</head>
<body>

    <div class="container">
        <div class="perfil">
            <div class="foto"></div>
            <h2><?= htmlspecialchars($_SESSION['user_nome']) ?></h2>
        </div>

        <?php if (isset($msg)) echo "<p class='msg'>$msg</p>"; ?>

        <!-- Atualizar nome/email -->
        <form method="post" class="bloco">
            <div class="linha">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            </div>
            <div class="linha">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
            </div>
            <button type="submit" name="atualizar_info">Salvar Alterações</button>
        </form>

        <!-- Atualizar senha -->
        <form method="post" class="bloco">
            <div class="linha">
                <label for="senha_atual">Senha Atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required>
            </div>
            <div class="linha">
                <label for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" required>
            </div>
            <div class="linha">
                <label for="confirmar_senha">Confirmar Nova</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required>
            </div>
            <button type="submit" name="atualizar_senha">Alterar Senha</button>
        </form>

        <!-- 2 Etapas -->
        <div class="bloco">
            <div class="linha">
                <label>2 Etapas</label>
                <span><a href="#">Configuração</a></span>
            </div>
        </div>

        <!-- Excluir Conta -->
        <form method="post" class="bloco" onsubmit="return confirm('Deseja mesmo excluir sua conta?');">
            <div class="linha">
                <label>Gerenciar Conta</label>
                <span><button type="submit" name="excluir_conta" class="excluir">Excluir Conta</button></span>
            </div>
        </form>

        <div class="voltar">
            <a href="dashboard.php">← Voltar</a>
        </div>
    </div>

</body>
</html>
