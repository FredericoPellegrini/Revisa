<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];

$resultados = [];
$termo_pesquisa = '';
$busca_realizada = isset($_GET['q']);

if ($busca_realizada && !empty(trim($_GET['q']))) {
    $termo_pesquisa = trim($_GET['q']);
    $termo_para_busca = "%" . $termo_pesquisa . "%";

    $sql_busca = "
        SELECT 
            a.id, 
            a.titulo, 
            GROUP_CONCAT(
                CASE 
                    WHEN r.feita = 0 AND r.data_revisao >= CURDATE() THEN DATE_FORMAT(r.data_revisao, '%d/%m/%Y') 
                    ELSE NULL 
                END
                ORDER BY r.data_revisao ASC
                SEPARATOR ','
            ) as proximas_revisoes
        FROM assuntos a
        LEFT JOIN revisoes r ON a.id = r.assunto_id
        WHERE 
            a.user_id = ? AND a.titulo LIKE ?
        GROUP BY
            a.id, a.titulo
        ORDER BY 
            a.titulo
    ";
    $stmt = $pdo->prepare($sql_busca);
    $stmt->execute([$usuario_id, $termo_para_busca]);
    $resultados_banco = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($resultados_banco as $resultado) {
        $revisoes = [];
        if (!empty($resultado['proximas_revisoes'])) {
            $revisoes = explode(',', $resultado['proximas_revisoes']);
        }
        $resultados[$resultado['id']] = [
            'titulo' => $resultado['titulo'],
            'revisoes' => $revisoes
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/busca.css">
    <title>Busca - Revisa</title>
</head>
<body>
    <aside class="nav-icons">
        <div class="nav-icons-top">
            <a href="dashboard.php" title="Dashboard"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></a>
            <a href="calendario.php" title="Calendário"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></a>
            <a href="perfil.php" title="Perfil"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></a>
            <a href="#" class="active" title="Busca"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></a>
        </div>
        <div class="nav-icons-bottom">
            <a href="logout.php" title="Sair"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l-3-3m0 0l-3-3m3 3H9" /></svg></a>
        </div>
    </aside>

    <main class="search-main-content">
        <div class="search-box">
            <h1>Buscar Assuntos</h1>
            <form method="get" class="search-form">
                <input type="text" id="search-input" name="q" placeholder="Digite o nome de um assunto..." value="<?= htmlspecialchars($termo_pesquisa) ?>" autofocus>
                <button type="submit" id="search-button">Buscar</button>
            </form>

            <div class="search-results-container">
                <?php if ($busca_realizada): ?>
                    <?php if (!empty($resultados)): ?>
                        <?php foreach ($resultados as $assunto_id => $assunto): ?>
                            <div class="result-item" data-assunto-id="<?= $assunto_id ?>" data-titulo="<?= htmlspecialchars($assunto['titulo']) ?>">
                                <div class="result-info">
                                    <h3><?= htmlspecialchars($assunto['titulo']) ?></h3>
                                    <?php if (!empty($assunto['revisoes'])): ?>
                                        <p>Próximas revisões agendadas:</p>
                                        <ul class="revisoes-list">
                                            <?php foreach($assunto['revisoes'] as $data_rev): ?>
                                                <li><?= $data_rev ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Nenhuma revisão futura agendada para este assunto.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="result-menu-container">
                                    <button class="menu-btn">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
                                    </button>
                                    <div class="menu-dropdown">
                                        <a href="#" class="edit-btn">Editar Assunto</a>
                                        <a href="#" class="delete-btn">Apagar Assunto</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="status-message">
                            <p>Nenhum resultado encontrado para "<?= htmlspecialchars($termo_pesquisa) ?>".</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="status-message">
                        <p>Use a barra de busca para encontrar seus assuntos e ver as datas das próximas revisões.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
<script src="js/busca.js"></script>
</body>
</html>