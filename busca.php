<?php
session_start();
require 'config.php';

// 1. VERIFICA AUTENTICAÇÃO
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];

$resultados = [];
$termo_pesquisa = '';
$busca_realizada = isset($_GET['q']);

// 2. LÓGICA DE BUSCA
if ($busca_realizada && !empty(trim($_GET['q']))) {
    $termo_pesquisa = trim($_GET['q']);
    $termo_para_busca = "%" . $termo_pesquisa . "%";

    // ##### CONSULTA SQL ATUALIZADA AQUI #####
    $sql_busca = "
        SELECT 
            a.id, 
            a.titulo, 
            r.data_revisao
        FROM assuntos a
        LEFT JOIN revisoes r ON a.id = r.assunto_id 
            AND r.data_revisao >= CURDATE() 
            AND r.feita = 0  -- Adicionada esta linha para ignorar revisões concluídas
        WHERE 
            a.user_id = ? AND a.titulo LIKE ?
        ORDER BY 
            a.titulo, r.data_revisao ASC
    ";
    $stmt = $pdo->prepare($sql_busca);
    $stmt->execute([$usuario_id, $termo_para_busca]);
    $resultados_banco = $stmt->fetchAll();

    // Organizar resultados por assunto
    foreach ($resultados_banco as $resultado) {
        $assunto_id = $resultado['id'];
        if (!isset($resultados[$assunto_id])) {
            $resultados[$assunto_id] = [
                'titulo' => $resultado['titulo'],
                'revisoes' => []
            ];
        }
        if ($resultado['data_revisao']) {
            $data_formatada = date('d/m/Y', strtotime($resultado['data_revisao']));
            if (!in_array($data_formatada, $resultados[$assunto_id]['revisoes'])) {
                $resultados[$assunto_id]['revisoes'][] = $data_formatada;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Busca - Revisa</title>
    <style>
        :root { --bg-darkest: #000000; --bg-darker: #111827; --bg-dark: #1F2937; --text-light: #E5E7EB; --text-normal: #9CA3AF; --text-dark: #6B7280; --accent-yellow: #FBBF24; --accent-red: #F87171; --accent-green: #22C55E; --accent-blue: #3B82F6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-darkest); color: var(--text-light); display: flex; }
        a { color: inherit; text-decoration: none; }

        .nav-icons { background-color: var(--bg-darker); border-right: 1px solid var(--bg-dark); padding: 24px 0; width: 80px; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-icons-top { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .nav-icons a { padding: 12px; border-radius: 8px; line-height: 0; transition: background-color 0.2s; }
        .nav-icons a:hover { background-color: var(--bg-dark); }
        .nav-icons a.active { background-color: var(--bg-dark); }
        .nav-icons svg { width: 28px; height: 28px; }
        .nav-icons .active svg { color: var(--accent-green); }

        .search-main-content { width: 100%; padding: 40px; overflow-y: auto; }
        .search-box { max-width: 800px; margin: 0 auto; }
        .search-box h1 { font-size: 1.8rem; margin-bottom: 20px; color: white; }
        
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 15px; border: 1px solid var(--bg-dark); border-radius: 8px; background-color: var(--bg-dark); color: var(--text-light); font-size: 1rem; }
        .search-form input:focus { outline: none; border-color: var(--accent-blue); }
        .search-form button { background-color: var(--accent-blue); color: white; border: none; padding: 0 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: bold; }

        .search-results-container { margin-top: 40px; }
        .result-item { background-color: var(--bg-dark); padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        .result-item h3 { color: var(--accent-yellow); margin-bottom: 10px; font-size: 1.2rem; }
        .result-item p { color: var(--text-normal); font-size: 0.9rem; line-height: 1.5; }
        .revisoes-list { list-style: none; display: flex; flex-wrap: wrap; gap: 10px; padding-top: 5px; }
        .revisoes-list li { background-color: var(--bg-darker); padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; }
        
        .status-message { color: var(--text-normal); text-align: center; padding: 40px; background-color: var(--bg-dark); border-radius: 8px; }
    </style>
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
                        <?php foreach ($resultados as $assunto): ?>
                            <div class="result-item">
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
</body>
</html>