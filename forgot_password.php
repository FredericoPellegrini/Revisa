<?php
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Gera token único
            $token = bin2hex(random_bytes(50));
            $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Salva no banco
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expira) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expira]);

            // Link para redefinir senha
            $link = "http://localhost/Revisa/reset_password.php?token=" . $token;

            // === Enviar e-mail com PHPMailer ===
            $mail = new PHPMailer(true);

            try {

                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';

                // Configuração SMTP (exemplo Gmail)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'fredericopellegrini1996@gmail.com';   // seu Gmail
                $mail->Password   = 'ixcj bdvo ulhf pcne';        // senha de app do Gmail
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Remetente e destinatário
                $mail->setFrom('seuemail@gmail.com', 'Revisa');
                $mail->addAddress($email);

                // Conteúdo
                $mail->isHTML(true);
                $mail->Subject = 'Redefinição de senha - Revisa';
                $mail->Body    = "Olá!<br><br>Clique no link para redefinir sua senha:<br><a href='$link'>$link</a>";

                $mail->send();
                $msg = "Enviamos um link para seu e-mail!";
            } catch (Exception $e) {
                $msg = "Erro ao enviar e-mail: {$mail->ErrorInfo}";
            }
        } else {
            $msg = "E-mail não encontrado.";
        }
    } else {
        $msg = "Informe seu e-mail.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Esqueci a senha</title>
</head>
<body>
    <h2>Esqueci a senha</h2>
    <form method="post">
        <input type="email" name="email" placeholder="Digite seu e-mail" required>
        <button type="submit">Enviar link</button>
    </form>
    <p><?= $msg ?></p>
</body>
</html>
