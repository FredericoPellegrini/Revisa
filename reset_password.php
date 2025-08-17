<?php
require 'config.php';

$msg = '';
$token = $_GET['token'] ?? ''; // Pega o token da URL inicialmente

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? ''; // Pega o token do POST quando o formulário é enviado
    $nova_senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['senha_confirm'] ?? ''; // Adicionado para confirmação de senha

    // Validação da senha
    if (empty($nova_senha) || empty($confirma_senha)) {
        $msg = 'Por favor, preencha ambos os campos de senha.';
    } elseif ($nova_senha !== $confirma_senha) {
        $msg = 'As senhas não conferem.';
    } elseif (strlen($nova_senha) < 8 || !preg_match('/[A-Za-z]/', $nova_senha) || !preg_match('/[0-9]/', $nova_senha)) {
        $msg = 'A senha deve ter no mínimo 8 caracteres, incluindo letras e números.';
    } else {
        // Se as validações passarem, processa o token
        if ($token) {
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
    <!-- Font Awesome para ícones (visualizar senha) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Redefinir senha</title>
    <style>
        .msg { color: #d9534f; text-align: center; margin-bottom: 10px; } /* Estilo para mensagens de erro/sucesso */
        .input-erro { border: 2px solid #d9534f !important; }

        /* Estilos para o campo de senha e ícone, copiados do register.php para consistência */
        .password-field {
            position: relative;
            width: 350px; 
        }
        .password-field input {
            padding-right: 40px; 
            width: 100%; 
            box-sizing: border-box; 
            height: 55px; 
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 1.1em;
        }

        /* --- FORMULÁRIO --- Copiado do register.php para consistência */
        form { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
            align-items: center; 
            position: relative; 
            z-index: 3; 
        } 

        /* Garante que os inputs diretos tenham a mesma largura e altura dos campos de senha */
        form > input {
            width: 350px;
            height: 55px;
        }
        
        /* Remover a margem inferior para o último item do formulário para evitar espaço extra */
        form > *:last-child {
            margin-bottom: 0 !important; 
        }

        /* Ajuste fino para os campos de input dentro do formulário */
        form input { /* Aplica-se a todos os inputs, incluindo os dentro de .password-field */
            padding: 15px; 
            font-size: 18px; 
            border-radius: 8px; 
            border: none; 
            outline: none; 
            font-weight: bold; 
        }

        form button { 
            width: 200px; 
            height: 55px; 
            background-color: #D54F72; 
            border: none; 
            border-radius: 10px; 
            font-size: 20px; 
            font-weight: bold; 
            color: white; 
            cursor: pointer; 
            transition: background-color 0.3s; 
        }
        form button:hover { background-color: #c84c6d; }

        #linkesqueceusenha{
            text-decoration: none;
            color: #fff;
        }

        #linkesqueceusenha:hover{
            text-decoration: underline;
        }

        a{
            text-decoration: none;
            color: white;
        }

    </style>
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
        
        <!-- Campo Nova Senha -->
        <div class="password-field">
            <input type="password" name="senha" placeholder="Nova senha" 
                   class="<?= !empty($msg) && (strpos($msg, 'Senhas não conferem') !== false || strpos($msg, 'A senha deve ter') !== false) ? 'input-erro' : '' ?>"
                   onpaste="return false;" required>
            <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                <i class="fas fa-eye"></i> <!-- Ícone de olho -->
            </span>
        </div>

        <!-- Campo Confirmar Nova Senha -->
        <div class="password-field">
            <input type="password" name="senha_confirm" placeholder="Confirmar nova senha" 
                   class="<?= !empty($msg) && strpos($msg, 'Senhas não conferem') !== false ? 'input-erro' : '' ?>"
                   onpaste="return false;" required>
            <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                <i class="fas fa-eye"></i> <!-- Ícone de olho -->
            </span>
        </div>

        <button type="submit">Salvar</button>
    </form>
    <p class="msg"><?= $msg ?></p> <!-- Adicionada classe 'msg' para estilização -->

    <script>
        // Função para alternar a visibilidade da senha
        function togglePasswordVisibility(iconElement) {
            const input = iconElement.previousElementSibling; // Pega o input adjacente
            const icon = iconElement.querySelector('i'); // Pega o ícone dentro do span

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash'); // Altera para ícone de olho cortado
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye'); // Altera de volta para ícone de olho
            }
        }
    </script>
</body>
</html> 