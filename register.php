<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$erro = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $senha_confirm = trim($_POST['senha_confirm'] ?? '');

    if (!$nome || !$email || !$senha) {
        $erro = 'Preencha todos os campos.';
    } elseif ($senha !== $senha_confirm) {
        $erro = 'As senhas não conferem.'; // Mensagem aprimorada
    } 
    // Nova regra para senhas: mínimo 8 caracteres, com letras e números
    elseif (strlen($senha) < 8 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
        $erro = 'A senha deve ter no mínimo 8 caracteres, incluindo letras e números.';
    } 
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = 'E-mail já cadastrado. Tente outro ou <a href="login.php">faça login</a>.'; // Mensagem aprimorada
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(50));

            // Insere no banco (usuário inativo até confirmar)
            $stmt = $pdo->prepare("INSERT INTO users (nome, email, senha_hash, ativo, token_verificacao, data_criacao) VALUES (?, ?, ?, 0, ?, NOW())");
            $stmt->execute([$nome, $email, $senha_hash, $token]);

            // Enviar e-mail
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'fredericopellegrini1996@gmail.com'; // Use seu e-mail do Gmail
                $mail->Password = 'ixcj bdvo ulhf pcne'; // Use sua Senha de App do Gmail
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->CharSet = "UTF-8";

                $mail->setFrom('fredericopellegrini1996@gmail.com', 'Revisa'); // Use seu e-mail do Gmail
                $mail->addAddress($email, $nome);
                $mail->isHTML(true);

                $link = "http://localhost/revisa/verificar.php?token=" . $token;

                $mail->Subject = "Confirme seu cadastro - Revisa";
                $mail->Body    = "<p>Olá <b>$nome</b>,</p>
                                   <p>Obrigado por se cadastrar no <b>Revisa</b>! Para ativar sua conta, clique no link abaixo:</p>
                                   <p><a href='$link'>$link</a></p>"; 

                $mail->send();
                $msg = "Cadastro realizado! Verifique seu e-mail para confirmar a conta. O e-mail pode demorar alguns minutos.";
            } catch (Exception $e) {
                $erro = "Erro ao enviar e-mail: {$mail->ErrorInfo}";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisa | Cadastro</title>
    <link rel="stylesheet" href="css/telasinicias.css">
    <!-- Font Awesome para ícones (visualizar senha) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .erro { color: #d9534f; text-align: center; margin-bottom: 10px; }
        .msg { color: #28a745; text-align: center; margin-bottom: 10px; }
        .input-erro { border: 2px solid #d9534f !important; }

        /* Estilos para o campo de senha e ícone */
        .password-field {
            position: relative;
            width: 350px; /* Definido largura fixa para o container do campo de senha */
        }
        .password-field input {
            padding-right: 40px; /* Cria espaço para o ícone */
            width: 100%; /* O input ocupa 100% da largura do seu container (.password-field) */
            box-sizing: border-box; /* Inclui padding na largura total */
            height: 55px; /* Garante que o input dentro do wrapper tenha a altura desejada */
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

        /* --- FORMULÁRIO --- */
        form { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; /* Definido o gap aqui para garantir espaçamento uniforme entre os filhos diretos */
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
            margin-bottom: 0 !important; /* Usar !important para garantir que sobrescreva */
        }


        /* Ajuste fino para os campos de input dentro do formulário */
        form input { /* Aplica-se a todos os inputs, incluindo os dentro de .password-field */
            padding: 15px; 
            font-size: 18px; 
            border-radius: 8px; 
            border: none; 
            outline: none; 
            font-weight: bold; 
            /* Remover height e width, pois já estão no form > input ou password-field input */
            /* Remover margin-bottom, pois o gap do form cuidará do espaçamento */
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
    </style>
</head>
<body>
    <div id="cad">
        <a href="login.php">Login</a>
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
            <?php elseif (!empty($msg)): ?>
                <div class="msg"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <input type="text" name="nome" placeholder="Nome" 
                    value="<?= htmlspecialchars($nome ?? '') ?>" 
                    class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>

            <input type="email" name="email" placeholder="E-mail" 
                    value="<?= htmlspecialchars($email ?? '') ?>" 
                    class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>

            <!-- Campo de Senha com Anti-Colar e Visualizar -->
            <div class="password-field">
                <input type="password" name="senha" placeholder="Senha" 
                        value="<?= htmlspecialchars($senha ?? '') ?>" 
                        class="<?= !empty($erro) && (strpos($erro, 'Senhas não conferem') !== false || strpos($erro, 'A senha deve ter') !== false) ? 'input-erro' : '' ?>" 
                        onpaste="return false;" required>
                <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                    <i class="fas fa-eye"></i> <!-- Ícone de olho -->
                </span>
            </div>

            <!-- Campo de Confirmar Senha com Anti-Colar e Visualizar -->
            <div class="password-field">
                <input type="password" name="senha_confirm" placeholder="Confirmar Senha" 
                        value="<?= htmlspecialchars($senha_confirm ?? '') ?>" 
                        class="<?= !empty($erro) && (strpos($erro, 'Senhas não conferem') !== false || strpos($erro, 'A senha deve ter') !== false) ? 'input-erro' : '' ?>" 
                        onpaste="return false;" required>
                <span class="password-toggle" onclick="togglePasswordVisibility(this)">
                    <i class="fas fa-eye"></i> <!-- Ícone de olho -->
                </span>
            </div>

            <button type="submit">Cadastrar</button>
        </form>
    </div>

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