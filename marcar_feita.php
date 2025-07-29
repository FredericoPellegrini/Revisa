<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id']) || !isset($_POST['revisao_id'])) {
    header('Location: dashboard.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$revisao_id = $_POST['revisao_id'];

$sql = "UPDATE revisoes r
        JOIN assuntos a ON r.assunto_id = a.id
        SET r.feita = 1
        WHERE r.id = ? AND a.usuario_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$revisao_id, $usuario_id]);

header('Location: dashboard.php');
exit;
