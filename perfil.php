<?php
session_start();
require 'config.php';

// Garante que só usuários logados acessem
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = null;

// Carrega dados atuais do usuário para exibir no formulário
$stmt_load = $pdo->prepare("SELECT nome, email, senha_hash FROM users WHERE id = ?");
$stmt_load->execute([$user_id]);
$usuario = $stmt_load->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ... (toda a lógica PHP para atualizar dados, senha, etc., permanece a mesma)
// Atualizar nome/email
if (isset($_POST['atualizar_info'])) {
    $novo_nome = trim($_POST['nome'] ?? '');
    $novo_email = trim($_POST['email'] ?? '');
    if (empty($novo_nome) || empty($novo_email)) {
        $msg = ['texto' => 'Nome e email não podem ficar vazios.', 'tipo' => 'erro'];
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$novo_nome, $novo_email, $user_id]);
            $usuario['nome'] = $novo_nome;
            $usuario['email'] = $novo_email;
            $msg = ['texto' => 'Informações atualizadas com sucesso!', 'tipo' => 'sucesso'];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 $msg = ['texto' => 'Este email já está em uso por outra conta.', 'tipo' => 'erro'];
            } else {
                 $msg = ['texto' => 'Ocorreu um erro ao atualizar as informações.', 'tipo' => 'erro'];
            }
        }
    }
}
// Atualizar senha
if (isset($_POST['atualizar_senha'])) {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
    if (!password_verify($senha_atual, $usuario['senha_hash'])) {
        $msg = ['texto' => 'Senha atual incorreta.', 'tipo' => 'erro'];
    } elseif ($nova_senha !== $confirmar) {
        $msg = ['texto' => 'Nova senha e confirmação não conferem.', 'tipo' => 'erro'];
    } elseif (strlen($nova_senha) < 6) {
        $msg = ['texto' => 'A nova senha deve ter no mínimo 6 caracteres.', 'tipo' => 'erro'];
    } else {
        $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET senha_hash = ? WHERE id = ?");
        $stmt->execute([$nova_hash, $user_id]);
        $msg = ['texto' => 'Senha atualizada com sucesso!', 'tipo' => 'sucesso'];
        $stmt_load->execute([$user_id]);
        $usuario = $stmt_load->fetch();
    }
}
// Limpar dados de estudo
if (isset($_POST['limpar_dados'])) {
    $senha_confirmacao = $_POST['senha_confirmacao'] ?? '';
    if (password_verify($senha_confirmacao, $usuario['senha_hash'])) {
        try {
            $pdo->beginTransaction();
            $stmt_delete_assuntos = $pdo->prepare("DELETE FROM assuntos WHERE user_id = ?");
            $stmt_delete_assuntos->execute([$user_id]);
            $stmt_delete_tags = $pdo->prepare("DELETE FROM tags WHERE id NOT IN (SELECT DISTINCT tag_id FROM assunto_tag)");
            $stmt_delete_tags->execute();
            $pdo->commit();
            $msg = ['texto' => 'Todos os seus dados de estudo foram apagados com sucesso!', 'tipo' => 'sucesso'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = ['texto' => 'Ocorreu um erro ao limpar seus dados. Tente novamente.', 'tipo' => 'erro'];
        }
    } else {
        $msg = ['texto' => 'Senha incorreta. Seus dados não foram apagados.', 'tipo' => 'erro'];
    }
}
// Excluir Conta com verificação de senha
if (isset($_POST['excluir_conta'])) {
    $senha_excluir = $_POST['senha_excluir'] ?? '';
    if (password_verify($senha_excluir, $usuario['senha_hash'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        session_destroy();
        header('Location: login.php?msg=Conta+excluída+com+sucesso');
        exit;
    } else {
        $msg = ['texto' => 'Senha incorreta. A conta não foi excluída.', 'tipo' => 'erro'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meu Perfil - Revisa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { 
            --bg-darkest: #282A2C; --bg-darker: #1B1C1D; --bg-dark: #282A2C;
            --text-light: #E5E7EB; --text-normal: #9CA3AF; --text-dark: #6B7280; 
            --accent-yellow: #FBBF24; --accent-red: #F87171; --accent-green: #22C55E; --accent-blue: #3B82F6; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-darker); color: var(--text-light); }
        a { color: inherit; text-decoration: none; }
        
        .main-grid { display: grid; grid-template-columns: 80px 1fr; height: 100vh; }
        .nav-icons { background-color: var(--bg-dark); border-right: 1px solid var(--bg-dark); padding: 24px 0; display: flex; flex-direction: column; align-items: center; justify-content: space-between; }
        .nav-icons-top { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .nav-icons a { padding: 12px; border-radius: 8px; line-height: 0; transition: background-color 0.2s; }
        .nav-icons a:hover { background-color: #374151; }
        .nav-icons a.active { background-color: var(--bg-darker); }
        .nav-icons svg { width: 28px; height: 28px; }
        .nav-icons .active svg { color: var(--accent-green); }

        .profile-main-content { padding: 40px; background-color: var(--bg-darker); height: 100vh; overflow-y: auto; }
        .profile-box { max-width: 600px; margin: 0 auto; }
        .profile-box h1 { font-size: 1.8rem; margin-bottom: 30px; color: white; }
        
        .form-block { background-color: var(--bg-dark); padding: 25px; border-radius: 8px; margin-bottom: 20px; }
        .form-block h2 { font-size: 1.2rem; margin-bottom: 20px; color: var(--text-light); border-bottom: 1px solid var(--bg-darker); padding-bottom: 10px; }
        .form-block h3 { font-size: 1rem; margin-top: 25px; margin-bottom: 15px; color: var(--text-light); }
        .form-block p { font-size: 0.9rem; color: var(--text-normal); margin-bottom: 15px; line-height: 1.5; }
        .form-row { margin-bottom: 15px; }
        .form-row label { display: block; font-size: 0.9rem; font-weight: 500; color: var(--text-normal); margin-bottom: 8px; }
        .form-row input { width: 100%; background-color: var(--bg-darker); border: 1px solid var(--text-dark); color: var(--text-light); padding: 12px; border-radius: 8px; font-size: 1rem; }
        
        button[type="submit"] { background-color: var(--accent-blue); color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: background-color 0.2s; }
        button[type="submit"]:hover { background-color: #2563EB; }

        .delete-block { border: 1px solid rgba(248, 113, 113, 0.3); }
        .delete-btn { background-color: var(--accent-red); }
        .delete-btn:hover { background-color: #DC2626; }
        .warning-btn { background-color: var(--accent-yellow); color: var(--bg-darkest); }
        .warning-btn:hover { background-color: #D97706; }
        
        .feedback-msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
        .feedback-msg.sucesso { background-color: #166534; color: #DCFCE7; }
        .feedback-msg.erro { background-color: #991B1B; color: #FEE2E2; }

        /* DESTAQUE: Estilos para o container da senha e o ícone */
        .password-container { position: relative; }
        .password-container input { padding-right: 45px; /* Espaço para o ícone */ }
        .password-container .toggle-password { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: var(--text-normal); }

    </style>
</head>
<body>
    <div class="main-grid">
        <aside class="nav-icons">
            <div class="nav-icons-top">
                <a href="dashboard.php" title="Dashboard"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></a>
                <a href="calendario.php" title="Calendário"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></a>
                <a href="#" class="active" title="Perfil"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></a>
                <a href="busca.php" title="Busca"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></a>
            </div>
            <div class="nav-icons-bottom">
                <a href="logout.php" title="Sair"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l-3-3m0 0l-3-3m3 3H9" /></svg></a>
            </div>
        </aside>
        <main class="profile-main-content">
            <div class="profile-box">
                <h1>Meu Perfil</h1>
                <?php if ($msg): ?>
                    <div class="feedback-msg <?= $msg['tipo'] ?>">
                        <?= htmlspecialchars($msg['texto']) ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="form-block">
                    <h2>Informações da Conta</h2>
                    <div class="form-row">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                    </div>
                    <div class="form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>
                    <button type="submit" name="atualizar_info">Salvar Alterações</button>
                </form>
                <form method="post" class="form-block">
                    <h2>Alterar Senha</h2>
                    <div class="form-row">
                        <label for="senha_atual">Senha Atual</label>
                        <div class="password-container">
                            <input type="password" id="senha_atual" name="senha_atual" required>
                            <i class="fas fa-eye toggle-password" data-target="senha_atual"></i>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="nova_senha">Nova Senha (mínimo 6 caracteres)</label>
                        <div class="password-container">
                            <input type="password" id="nova_senha" name="nova_senha" required>
                            <i class="fas fa-eye toggle-password" data-target="nova_senha"></i>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="confirmar_senha">Confirmar Nova Senha</label>
                        <div class="password-container">
                            <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                            <i class="fas fa-eye toggle-password" data-target="confirmar_senha"></i>
                        </div>
                    </div>
                    <button type="submit" name="atualizar_senha">Alterar Senha</button>
                </form>

                <div class="form-block delete-block">
                    <h2>Gerenciar Conta</h2>
                    
                    <form method="post" onsubmit="return confirm('ATENÇÃO: Isso apagará TODOS os seus assuntos e revisões. Deseja continuar?');">
                        <h3>Limpar Dados de Estudo</h3>
                        <p>Esta ação removerá todos os seus assuntos e o histórico de revisões. Sua conta e login serão mantidos.</p>
                        <div class="form-row">
                            <label for="senha_confirmacao">Digite sua senha para confirmar</label>
                            <div class="password-container">
                                <input type="password" id="senha_confirmacao" name="senha_confirmacao" required>
                                <i class="fas fa-eye toggle-password" data-target="senha_confirmacao"></i>
                            </div>
                        </div>
                        <button type="submit" name="limpar_dados" class="warning-btn">Limpar Todos os Dados</button>
                    </form>

                    <hr style="border-color: var(--bg-darker); margin: 30px 0;">

                    <form method="post" onsubmit="return confirm('ATENÇÃO: Ação IRREVERSÍVEL. Deseja mesmo excluir sua conta?');">
                        <h3>Excluir Conta</h3>
                        <p>Esta ação removerá permanentemente sua conta, seu login e todos os seus dados associados.</p>
                        <div class="form-row">
                            <label for="senha_excluir">Digite sua senha para confirmar a exclusão</label>
                            <div class="password-container">
                                <input type="password" id="senha_excluir" name="senha_excluir" required>
                                <i class="fas fa-eye toggle-password" data-target="senha_excluir"></i>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; justify-content: flex-end;">
                             <button type="submit" name="excluir_conta" class="delete-btn">Excluir Conta Permanentemente</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePasswordIcons = document.querySelectorAll('.toggle-password');

        togglePasswordIcons.forEach(icon => {
            icon.addEventListener('click', function() {
                const targetInputId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetInputId);

                if (passwordInput) {
                    // Alterna o tipo do atributo do input
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Alterna a classe do ícone
                    this.classList.toggle('fa-eye-slash');
                }
            });
        });
    });
</script>

</body>
</html>