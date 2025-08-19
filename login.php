<?php
session_start();
require 'config.php';

$erro = '';
$email = ''; // Inicializa a variável de email

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        $erro = 'Preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, senha_hash, ativo FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            $erro = 'E-mail ou senha incorretos.';
        } 
        elseif ($user['ativo'] == 0) { 
            $erro = 'Você precisa confirmar seu e-mail antes de fazer login. Verifique sua caixa de entrada.';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Revisa | Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@700&family=Ubuntu:wght@400;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { height: 100vh; background-color: #000; font-family: 'Ubuntu', sans-serif; color: white; overflow: hidden; }

        @keyframes heartbeat { 0%, 100% { transform: scale(1); } 10% { transform: scale(1.15); } 20% { transform: scale(1); } 30% { transform: scale(1.1); } 40% { transform: scale(1); } }
        @keyframes move-particle-1 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: -50vh; left: 50vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-2 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: 50vh; left: -50vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-3 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: 60vh; left: 60vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-4 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: -60vh; left: -60vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-5 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: 10vh; left: -60vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-6 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: 20vh; left: 60vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-7 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: -60vh; left: 20vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        @keyframes move-particle-8 { 0%, 9.9% { opacity: 0; transform: translate(-50%, -50%) scale(0); } 10% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.2); } 100% { top: 60vh; left: -20vw; opacity: 0; transform: translate(-50%, -50%) scale(0.5); } }
        
        #cad { position: absolute; top: 20px; right: 20px; font-weight: bold; z-index: 3; }
        #cad a { background-color: #D54F72; padding: 12px 20px; border-radius: 10px; color: #fff; text-decoration: none; font-size: 16px; }
        #cad a:hover { background-color: #c84c6d; }
        .container { height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 40px; }
        .logo-container { position: relative; display: flex; justify-content: center; align-items: center; }
        #logo { display: flex; flex-direction: column; align-items: center; gap: 20px; position: relative; z-index: 2; }
        #img1 { width: 200px; height: auto; animation: heartbeat 3s infinite linear; }
        #img2 { width: 150px; height: auto; }

        .particle { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0); background-color: #D54F72; border-radius: 50%; z-index: 1; filter: blur(2px); opacity: 0; animation-timing-function: linear; animation-iteration-count: infinite; }
        .particle:nth-child(1) { width: 10px; height: 10px; animation-name: move-particle-1; animation-duration: 3s; }
        .particle:nth-child(2) { width: 15px; height: 15px; animation-name: move-particle-2; animation-duration: 3s; }
        .particle:nth-child(3) { width: 8px;  height: 8px;  animation-name: move-particle-3; animation-duration: 3s; }
        .particle:nth-child(4) { width: 12px; height: 12px; animation-name: move-particle-4; animation-duration: 3s; }
        .particle:nth-child(5) { width: 10px; height: 10px; animation-name: move-particle-5; animation-duration: 3s; }
        .particle:nth-child(6) { width: 18px; height: 18px; animation-name: move-particle-6; animation-duration: 3s; }
        .particle:nth-child(7) { width: 7px;  height: 7px;  animation-name: move-particle-7; animation-duration: 3s; }
        .particle:nth-child(8) { width: 14px; height: 14px; animation-name: move-particle-8; animation-duration: 3s; }

        form { display: flex; flex-direction: column; gap: 20px; align-items: center; position: relative; z-index: 3; }
        form input { width: 350px; height: 55px; padding: 15px; font-size: 18px; border-radius: 8px; border: none; outline: none; font-weight: bold; }
        form button { width: 200px; height: 55px; background-color: #D54F72; border: none; border-radius: 10px; font-size: 20px; font-weight: bold; color: white; cursor: pointer; transition: background-color 0.3s; }
        form button:hover { background-color: #c84c6d; }
        
        /* DESTAQUE: Estilos para o container da senha e o ícone */
        .password-container { position: relative; width: 350px; }
        .password-container input { width: 100%; padding-right: 45px; /* Espaço para o ícone */ }
        #togglePassword { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: #333; }

        #linkesqueceusenha{ text-decoration: none; color: #fff; }
        #linkesqueceusenha:hover{ text-decoration: underline; }
        .erro { background-color: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; color: #d9534f; font-size: 14px; padding: 10px; border-radius: 8px; text-align: center; width: 350px; }
        .input-erro { border: 2px solid #d9534f !important; }
    </style>
</head>
<body>

    <div id="cad">
        <p>Ainda não tem conta? <a href="register.php">cadastre-se</a></p>
    </div>

    <div class="container">
        <div class="logo-container">
            <div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div>
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
                   value="<?= htmlspecialchars($email) ?>" 
                   class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>

            <div class="password-container">
                <input type="password" id="senha" name="senha" placeholder="Senha" 
                       class="<?= !empty($erro) ? 'input-erro' : '' ?>" required>
                <i class="fas fa-eye" id="togglePassword"></i>
            </div>

            <button type="submit">Login</button>
        </form>

        <p><a href="forgot_password.php" id="linkesqueceusenha">Esqueceu a senha?</a></p>
    </div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#senha');

    togglePassword.addEventListener('click', function () {
        // Alterna o tipo do atributo do input
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Alterna a classe do ícone
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>