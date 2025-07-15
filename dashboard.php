<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Adicionar novo assunto
if (isset($_POST['criar'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if ($titulo) {
        $stmt = $pdo->prepare("INSERT INTO assuntos (user_id, titulo, descricao) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $titulo, $descricao]);

        $assunto_id = $pdo->lastInsertId();
        $hoje = new DateTime();

        $dias = [1, 2, 4, 8, 16, 32, 64];
        foreach ($dias as $dia) {
            $data_revisao = $hoje->modify("+$dia days")->format('Y-m-d');
            $stmt = $pdo->prepare("INSERT INTO revisoes (assunto_id, dia_revisao, data_revisao) VALUES (?, ?, ?)");
            $stmt->execute([$assunto_id, $dia, $data_revisao]);
            $hoje->modify("-$dia days"); // volta pro hoje original
        }
    }
}

// Atualizar assunto
if (isset($_POST['editar'])) {
    $id = $_POST['id'];
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $stmt = $pdo->prepare("UPDATE assuntos SET titulo = ?, descricao = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$titulo, $descricao, $id, $user_id]);
}

// Deletar assunto
if (isset($_POST['excluir'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM assuntos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}

// Listar assuntos do usuário com revisões feitas
$stmt = $pdo->prepare("
    SELECT a.id, a.titulo, a.descricao,
        (SELECT COUNT(*) FROM revisoes r WHERE r.assunto_id = a.id AND feita = TRUE) AS feitas
    FROM assuntos a
    WHERE a.user_id = ?
    ORDER BY a.criado_em DESC
");
$stmt->execute([$user_id]);
$assuntos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>
    <h2>Olá, <?= htmlspecialchars($_SESSION['user_nome']) ?>!</h2>

    <h3>Adicionar novo assunto</h3>
    <form method="post">
        <input type="text" name="titulo" placeholder="Título" required><br>
        <textarea name="descricao" placeholder="Descrição" rows="3"></textarea><br>
        <button type="submit" name="criar">Cadastrar Assunto</button>
    </form>

    <hr>

<h3>Meus Assuntos</h3>
<?php foreach ($assuntos as $a): ?>
    <form method="post" style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">
        <input type="hidden" name="id" value="<?= $a['id'] ?>">
        <strong>Título:</strong><br>
        <input type="text" name="titulo" value="<?= htmlspecialchars($a['titulo']) ?>" required><br>
        <strong>Descrição:</strong><br>
        <textarea name="descricao" rows="2"><?= htmlspecialchars($a['descricao']) ?></textarea><br>
        Revisões feitas: <?= $a['feitas'] ?><br>

    <details>
        <summary><strong>📅 Revisões</strong></summary>
        <ul style="margin-top: 5px;">
            <?php
            $rev_stmt = $pdo->prepare("SELECT dia_revisao, data_revisao, feita FROM revisoes WHERE assunto_id = ? ORDER BY dia_revisao");
            $rev_stmt->execute([$a['id']]);
            $revisoes = $rev_stmt->fetchAll();
            foreach ($revisoes as $rev):
                $data_br = date("d-m-Y", strtotime($rev['data_revisao']));
            ?>
                <li>
                    Dia <?= $rev['dia_revisao'] ?> — <?= $data_br ?> — 
                    <?= $rev['feita'] ? '✅' : '❌' ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>

        <button type="submit" name="editar">Salvar</button>
        <button type="submit" name="excluir" onclick="return confirm('Tem certeza que deseja excluir este assunto?')">Excluir</button>
    </form>
<?php endforeach; ?>

    <p><a href="perfil.php">Perfil</a> | <a href="logout.php">Sair</a></p>
</body>
</html>
