<?php
$host = '127.0.0.1';
$db   = 'sistema_revisao';
$user = 'root'; // Ou seu usuário do XAMPP, geralmente 'root'
$pass = '';     // Ou sua senha do XAMPP, geralmente em branco
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    // A linha mágica que força a exibição de erros do banco de dados
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Em caso de falha na conexão, mostra o erro e para tudo.
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>