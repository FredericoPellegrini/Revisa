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

    // Organiza os resultados
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
    <title>Busca - Revisa</title>
    <style>
        :root { 
            --bg-darkest: #282A2C;
            --bg-darker: #1B1C1D;
            --bg-dark: #282A2C;
            --text-light: #E5E7EB; 
            --text-normal: #9CA3AF; 
            --text-dark: #6B7280; 
            --accent-yellow: #FBBF24; 
            --accent-red: #F87171; 
            --accent-green: #22C55E; 
            --accent-blue: #3B82F6; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-darker); color: var(--text-light); display: flex; }
        a { color: inherit; text-decoration: none; }

        .nav-icons { background-color: var(--bg-dark); border-right: 1px solid var(--bg-dark); padding: 24px 0; width: 80px; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-icons-top { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .nav-icons a { padding: 12px; border-radius: 8px; line-height: 0; transition: background-color 0.2s; }
        .nav-icons a:hover { background-color: #374151; }
        .nav-icons a.active { background-color: var(--bg-darker); }
        .nav-icons svg { width: 28px; height: 28px; color: var(--text-light); }
        .nav-icons .active svg { color: var(--accent-green); }

        .search-main-content { width: 100%; padding: 40px; overflow-y: auto; }
        .search-box { max-width: 800px; margin: 0 auto; }
        .search-box h1 { font-size: 1.8rem; margin-bottom: 20px; color: white; }
        
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 15px; border: 1px solid var(--bg-dark); border-radius: 8px; background-color: var(--bg-dark); color: var(--text-light); font-size: 1rem; }
        .search-form input:focus { outline: none; border-color: var(--accent-blue); }
        .search-form button { background-color: var(--accent-blue); color: white; border: none; padding: 0 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: bold; }

        .search-results-container { margin-top: 40px; }
        .result-item { background-color: var(--bg-dark); padding: 20px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .result-item h3 { color: var(--accent-yellow); margin-bottom: 10px; font-size: 1.2rem; }
        .result-item p { color: var(--text-normal); font-size: 0.9rem; line-height: 1.5; }
        .revisoes-list { list-style: none; display: flex; flex-wrap: wrap; gap: 10px; padding-top: 5px; }
        .revisoes-list li { background-color: var(--bg-darker); padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; }
        
        .status-message { color: var(--text-normal); text-align: center; padding: 40px; background-color: var(--bg-dark); border-radius: 8px; }

        /* DESTAQUE: Estilos para o menu CRUD */
        .result-menu-container { position: relative; }
        .menu-btn { padding: 4px; border-radius: 4px; line-height: 0; cursor: pointer; background: none; border: none; color: var(--text-normal); }
        .menu-btn:hover { background-color: #374151; }
        .menu-dropdown { display: none; position: absolute; right: 0; top: 30px; background-color: #374151; border-radius: 8px; z-index: 10; width: 140px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .menu-dropdown a { display: block; padding: 10px 12px; font-size: 0.9rem; color: var(--text-light); }
        .menu-dropdown a:hover { background-color: var(--accent-blue); color: white; }
        .menu-dropdown a.delete-btn { color: var(--accent-red); }
        .menu-dropdown a.delete-btn:hover { background-color: var(--accent-red); color: white; }
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resultsContainer = document.querySelector('.search-results-container');

    resultsContainer.addEventListener('click', function(e) {
        const menuBtn = e.target.closest('.menu-btn');
        
        // Fecha todos os outros menus antes de abrir um novo
        document.querySelectorAll('.menu-dropdown').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) {
                menu.style.display = 'none';
            }
        });

        if (menuBtn) {
            e.preventDefault();
            const dropdown = menuBtn.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.preventDefault();
            const resultItem = editBtn.closest('.result-item');
            const assuntoId = resultItem.dataset.assuntoId;
            const tituloAtual = resultItem.dataset.titulo;
            const novoTitulo = prompt("Digite o novo nome para o assunto:", tituloAtual);

            if (novoTitulo && novoTitulo.trim() !== '' && novoTitulo.trim() !== tituloAtual) {
                const formData = new FormData();
                formData.append('action', 'edit_assunto');
                formData.append('assunto_id', assuntoId);
                formData.append('novo_titulo', novoTitulo.trim());

                // Reutiliza a API do dashboard.php para editar
                fetch('dashboard.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            resultItem.querySelector('h3').textContent = novoTitulo.trim();
                            resultItem.dataset.titulo = novoTitulo.trim();
                        } else {
                            alert('Erro ao editar: ' + (data.message || 'Tente novamente.'));
                        }
                    });
            }
        }

        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja apagar este assunto? Todas as suas revisões serão removidas permanentemente.')) {
                const resultItem = deleteBtn.closest('.result-item');
                const assuntoId = resultItem.dataset.assuntoId;
                
                const formData = new FormData();
                formData.append('action', 'delete_assunto');
                formData.append('assunto_id', assuntoId);
                
                // Reutiliza a API do dashboard.php para apagar
                fetch('dashboard.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            resultItem.style.opacity = '0';
                            setTimeout(() => resultItem.remove(), 300);
                        } else {
                            alert('Erro ao apagar: ' + (data.message || 'Tente novamente.'));
                        }
                    });
            }
        }
    });

    // Fecha os menus se clicar fora deles
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.result-menu-container')) {
            document.querySelectorAll('.menu-dropdown').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
});
</script>

</body>
</html>