<?php
require 'config.php';

$token = $_GET['token'] ?? '';

if ($token) {
    $stmt = $pdo->prepare("SELECT id, data_criacao FROM users WHERE token_verificacao = ? AND ativo = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $agora = new DateTime();
        $criado = new DateTime($user['data_criacao']);
        $intervalo = $criado->diff($agora);

        if ($intervalo->days >= 1) { // passou 24h
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            echo "O tempo para ativar a conta expirou. Cadastre-se novamente.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET ativo = 1, token_verificacao = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            echo "Conta confirmada com sucesso! <a href='login.php'>Fazer login</a>";
        }
    } else {
        echo "Token inválido ou conta já confirmada.";
    }
} else {
    echo "Token não informado.";
}
?>
