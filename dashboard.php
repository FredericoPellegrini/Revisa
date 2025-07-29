<?php
session_start();
require 'config.php';

// PARTE 1: LÓGICA DE BACKEND (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Ação inválida ou não processada.'];
    $usuario_id = $_SESSION['user_id'] ?? 0;

    if ($usuario_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
        exit;
    }

    try {
        if ($_POST['action'] === 'add_task') {
            $titulo = trim($_POST['assunto'] ?? '');
            if (empty($titulo)) {
                throw new Exception("O campo 'Assunto' é obrigatório.");
            }
            $tag_existente = $_POST['tag_existente'] ?? '';
            $tag_nova = trim($_POST['tag_nova'] ?? '');
            $tag_final = '';
            if (!empty($tag_nova)) {
                $tag_final = $tag_nova;
            } else if (!empty($tag_existente) && $tag_existente !== '--new--') {
                $tag_final = $tag_existente;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO assuntos (user_id, titulo, criado_em) VALUES (?, ?, CURDATE())");
            $stmt->execute([$usuario_id, $titulo]);
            $assunto_id = $pdo->lastInsertId();

            if (!empty($tag_final)) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE nome = ?");
                $stmt->execute([$tag_final]);
                $tag_id = $stmt->fetchColumn();
                if (!$tag_id) {
                    $stmt = $pdo->prepare("INSERT INTO tags (nome) VALUES (?)");
                    $stmt->execute([$tag_final]);
                    $tag_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO assunto_tag (assunto_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$assunto_id, $tag_id]);
            }
            
            // ##### LINHA MODIFICADA AQUI #####
            $dias_revisao = [0, 1, 7, 14, 30, 60, 120]; 
            
            $stmt_revisao = $pdo->prepare("INSERT INTO revisoes (assunto_id, dia_revisao, data_revisao) VALUES (?, ?, ?)");
            foreach ($dias_revisao as $dia) {
                $data_revisao = date('Y-m-d', strtotime("+$dia days"));
                $stmt_revisao->execute([$assunto_id, $dia, $data_revisao]);
            }

            $pdo->commit();
            $response = ['success' => true, 'message' => 'Assunto adicionado com sucesso!'];
        }
        
        else if ($_POST['action'] === 'mark_done' || $_POST['action'] === 'unmark_done') {
            $revisao_id = $_POST['revisao_id'] ?? 0;
            $novo_status = ($_POST['action'] === 'mark_done') ? 1 : 0;
            $sql = "UPDATE revisoes r JOIN assuntos a ON r.assunto_id = a.id SET r.feita = ? WHERE r.id = ? AND a.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_status, $revisao_id, $usuario_id]);
            $response = ['success' => true];
        }
        
        else if ($_POST['action'] === 'delete_assunto') {
            $assunto_id = $_POST['assunto_id'] ?? 0;
            if (empty($assunto_id)) throw new Exception("ID do assunto não fornecido.");
            
            $sql = "DELETE FROM assuntos WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$assunto_id, $usuario_id]);
            
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Assunto apagado com sucesso.'];
            } else {
                throw new Exception("Assunto não encontrado ou você não tem permissão para apagá-lo.");
            }
        }
        
        else if ($_POST['action'] === 'edit_assunto') {
            $assunto_id = $_POST['assunto_id'] ?? 0;
            $novo_titulo = trim($_POST['novo_titulo'] ?? '');

            if (empty($assunto_id)) throw new Exception("ID do assunto não fornecido.");
            if (empty($novo_titulo)) throw new Exception("O novo título não pode ser vazio.");

            $sql = "UPDATE assuntos SET titulo = ? WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_titulo, $assunto_id, $usuario_id]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true];
            } else {
                throw new Exception("Assunto não encontrado ou o título já era o mesmo.");
            }
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $response['message'] = 'Erro: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// PARTE 2: LÓGICA PARA CARREGAMENTO DA PÁGINA
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];
$sql_todas_revisoes = "SELECT r.id, r.dia_revisao, r.data_revisao, a.id as assunto_id, a.titulo, t.nome as tag_nome, r.feita FROM revisoes r JOIN assuntos a ON r.assunto_id = a.id LEFT JOIN assunto_tag at ON a.id = at.assunto_id LEFT JOIN tags t ON at.tag_id = t.id WHERE a.user_id = ? ORDER BY r.data_revisao ASC, r.dia_revisao ASC";
$stmt_todas_revisoes = $pdo->prepare($sql_todas_revisoes);
$stmt_todas_revisoes->execute([$usuario_id]);
$todas_revisoes = $stmt_todas_revisoes->fetchAll();
$sql_materias = "SELECT DISTINCT t.nome FROM tags t JOIN assunto_tag at ON t.id = at.tag_id JOIN assuntos a ON at.assunto_id = a.id WHERE a.user_id = ? ORDER BY t.nome ASC";
$stmt_materias = $pdo->prepare($sql_materias);
$stmt_materias->execute([$usuario_id]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_COLUMN);
$cores_disponiveis = ['#FBBF24', '#60A5FA', '#F87171', '#4ADE80', '#818CF8', '#A78BFA'];
$cores_materias = [];
foreach($materias as $i => $materia){
    $cores_materias[$materia] = $cores_disponiveis[$i % count($cores_disponiveis)];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Revisa</title>
    <style>
        :root { --bg-darkest: #000000; --bg-darker: #111827; --bg-dark: #1F2937; --text-light: #E5E7EB; --text-normal: #9CA3AF; --text-dark: #6B7280; --accent-yellow: #FBBF24; --accent-red: #F87171; --accent-green: #22C55E; --accent-blue: #3B82F6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-darkest); color: var(--text-light); }
        a { color: inherit; text-decoration: none; }
        .dashboard-grid { display: grid; grid-template-columns: 80px 250px 1fr 350px; height: 100vh; }
        .nav-icons, .filters-sidebar, .main-view, .add-task-sidebar { height: 100vh; overflow-y: auto; }
        .nav-icons { background-color: var(--bg-darker); border-right: 1px solid var(--bg-dark); padding: 24px 0; display: flex; flex-direction: column; align-items: center; justify-content: space-between; }
        .nav-icons-top { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .nav-icons a { padding: 12px; border-radius: 8px; line-height: 0; transition: background-color 0.2s; }
        .nav-icons a:hover { background-color: var(--bg-dark); }
        .nav-icons a.active { background-color: var(--bg-dark); }
        .nav-icons svg { width: 28px; height: 28px; }
        .nav-icons .active svg { color: var(--accent-green); }
        .filters-sidebar { background-color: var(--bg-darker); padding: 24px; }
        .section-title { font-size: 0.75rem; font-weight: bold; color: var(--text-dark); text-transform: uppercase; margin-bottom: 16px; margin-top: 30px; }
        .time-filters a { display: block; padding: 10px; border-radius: 8px; font-weight: 500; cursor: pointer; margin-bottom: 4px;}
        .time-filters a:hover { background-color: var(--bg-dark); }
        .time-filters a.active { background-color: var(--bg-dark); color: white; }
        .filters-sidebar .materias-list a { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; font-weight: 500; cursor: pointer; }
        .filters-sidebar .materias-list a:hover, .filters-sidebar .materias-list a.active { background-color: var(--bg-dark); color: white; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .main-view { padding: 32px; }
        .task-card { background-color: var(--bg-dark); padding: 16px; border-radius: 8px; display: flex; align-items: center; gap: 16px; margin-bottom: 12px; transition: opacity 0.3s, transform 0.3s; position: relative; }
        .task-card[style*="display: none"] { display: none !important; }
        .task-card .checkbox { width: 20px; height: 20px; border: 2px solid var(--text-dark); border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .task-card .checkbox:hover { border-color: var(--accent-yellow); }
        .task-card .task-title { font-weight: 500; margin-right: auto; }
        .task-card .task-date { font-size: 0.8rem; color: var(--text-dark); }
        .future-task-icon { width: 20px; height: 20px; color: var(--text-dark); flex-shrink: 0; }
        .task-menu-container { position: relative; }
        .task-menu-btn { padding: 4px; border-radius: 4px; line-height: 0; cursor: pointer; background: none; border: none; color: var(--text-normal); }
        .task-menu-btn:hover { background-color: #374151; }
        .task-menu-dropdown { display: none; position: absolute; right: 0; top: 30px; background-color: #374151; border-radius: 8px; z-index: 10; width: 120px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .task-menu-dropdown a { display: block; padding: 10px 12px; font-size: 0.9rem; }
        .task-menu-dropdown a:hover { background-color: var(--accent-blue); color: white; }
        .task-menu-dropdown a.delete { color: var(--accent-red); }
        .task-menu-dropdown a.delete:hover { background-color: var(--accent-red); color: white; }
        .task-card.completed { opacity: 0.6; }
        .task-card.completed .checkbox { background-color: var(--accent-green); border-color: var(--accent-green); cursor: pointer; }
        .task-card.completed .checkbox:hover { background-color: #16a34a; border-color: #16a34a; }
        .task-card.completed .task-title { text-decoration: line-through; color: var(--text-dark); }
        .add-task-sidebar { padding: 32px; }
        #add-task-form .form-group { margin-bottom: 16px; }
        #add-task-form label { font-size: 0.9rem; font-weight: 500; color: var(--text-normal); display: block; margin-bottom: 8px; }
        #add-task-form input, #add-task-form select { width: 100%; background-color: var(--bg-dark); border: 1px solid var(--bg-dark); color: var(--text-light); padding: 12px; border-radius: 8px; font-size: 1rem; }
        #add-task-form select { appearance: none; -webkit-appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>'); background-repeat: no-repeat; background-position: right 12px center; }
        #add-task-form button[type="submit"] { width: 100%; background-color: var(--accent-blue); color: #fff; border: none; padding: 12px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; }
        #new-tag-group { display: none; align-items: center; gap: 8px; }
        #new-tag-group input { flex-grow: 1; }
        .cancel-new-tag-btn { background-color: var(--bg-dark); border: 1px solid var(--text-dark); color: var(--text-normal); padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; cursor: pointer; }
        .cancel-new-tag-btn:hover { background-color: var(--text-dark); color: white; }
        #form-message { margin-top: 16px; padding: 10px; border-radius: 8px; text-align: center; display: none; word-break: break-word; }
        #form-message.success { background-color: #166534; color: #DCFCE7; }
        #form-message.error { background-color: #991B1B; color: #FEE2E2; }
    </style>
</head>
<body>
    <div class="dashboard-grid">
        <aside class="nav-icons">
            <div class="nav-icons-top">
                <a href="#" class="active" title="Hoje"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></a>
                <a href="calendario.php" title="Calendário"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></a>
                <a href="perfil.php" title="Perfil"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></a>
                <a href="busca.php" title="Busca"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></a>
            </div>
            <div class="nav-icons-bottom">
                <a href="logout.php" title="Sair"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l-3-3m0 0l-3-3m3 3H9" /></svg></a>
            </div>
        </aside>
        
        <aside class="filters-sidebar">
            <h1 style="font-size: 1.5rem; font-weight: bold; color: white; margin-bottom: 40px;">Revisa</h1>
            <div class="time-filters">
                <a class="time-filter-btn active" data-period="today">Hoje</a>
                <a class="time-filter-btn" data-period="week">Próximos 7 dias</a>
                <a class="time-filter-btn" data-period="all">Caixa de Entrada</a>
            </div>
            <div class="section-title">Matérias</div>
            <div class="materias-list">
                <a class="tag-filter-btn active" data-filter="all">Todas as Matérias</a>
                <?php foreach ($materias as $materia): ?>
                    <a class="tag-filter-btn" data-filter="<?= htmlspecialchars($materia) ?>">
                        <span class="color-dot" style="background-color: <?= $cores_materias[$materia] ?? 'var(--text-dark)' ?>;"></span>
                        <span><?= htmlspecialchars($materia) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="main-view">
            <div class="section-title" id="main-view-title">Hoje</div>
            <div id="pending-tasks"></div>
            <div class="section-title">Concluídas</div>
            <div id="completed-tasks"></div>
        </main>
        
        <aside class="add-task-sidebar">
            <div class="section-title">Adicionar Assunto</div>
            <form id="add-task-form">
                <div class="form-group">
                    <label for="task-assunto">Assunto</label>
                    <input type="text" id="task-assunto" name="assunto" placeholder="Ex: Regência Verbal" required>
                </div>
                <div class="form-group">
                    <label for="task-tag-select">Tag (Opcional)</label>
                    <div id="tag-input-wrapper">
                        <select id="task-tag-select" name="tag_existente">
                            <option value="">Nenhuma</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?= htmlspecialchars($materia) ?>"><?= htmlspecialchars($materia) ?></option>
                            <?php endforeach; ?>
                            <option value="--new--">--- Adicionar Nova Tag ---</option>
                        </select>
                        <div id="new-tag-group">
                            <input type="text" id="task-tag-nova" name="tag_nova" placeholder="Nome da Nova Tag">
                            <button type="button" id="cancel-new-tag-btn" class="cancel-new-tag-btn" title="Cancelar">X</button>
                        </div>
                    </div>
                </div>
                <button type="submit">Adicionar</button>
            </form>
            <div id="form-message"></div>
        </aside>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let todasRevisoes = <?= json_encode($todas_revisoes) ?>;
    const coresMaterias = <?= json_encode($cores_materias) ?>;
    
    const pendingTasksContainer = document.getElementById('pending-tasks');
    const completedTasksContainer = document.getElementById('completed-tasks');
    const mainView = document.querySelector('.main-view');
    const mainViewTitle = document.getElementById('main-view-title');
    const addForm = document.getElementById('add-task-form');
    const formMessage = document.getElementById('form-message');
    const tagSelect = document.getElementById('task-tag-select');
    const newTagGroup = document.getElementById('new-tag-group');
    const newTagInput = document.getElementById('task-tag-nova');
    const cancelNewTagBtn = document.getElementById('cancel-new-tag-btn');

    let currentPeriod = 'today';
    let currentTag = 'all';

    function renderTasks() {
        const today = new Date(); today.setHours(0, 0, 0, 0);
        pendingTasksContainer.innerHTML = ''; completedTasksContainer.innerHTML = '';
        const filteredRevisoes = todasRevisoes.filter(rev => {
            const revDate = new Date(rev.data_revisao + 'T00:00:00-03:00');
            const nextWeek = new Date(new Date().setDate(today.getDate() + 7)); nextWeek.setHours(0,0,0,0);
            let periodMatch = false;
            if (currentPeriod === 'today') { if (revDate.getTime() === today.getTime()) periodMatch = true; } 
            else if (currentPeriod === 'week') { if (revDate >= today && revDate <= nextWeek) periodMatch = true; } 
            else if (currentPeriod === 'all') { periodMatch = true; }
            const tagMatch = (currentTag === 'all' || !rev.tag_nome || rev.tag_nome === currentTag);
            return periodMatch && tagMatch;
        });
        let hasPending = false;
        filteredRevisoes.forEach(rev => {
            const taskCard = document.createElement('div');
            taskCard.className = 'task-card';
            taskCard.dataset.id = rev.id;
            taskCard.dataset.materia = rev.tag_nome || '';
            taskCard.dataset.assuntoId = rev.assunto_id;
            taskCard.dataset.titulo = rev.titulo;
            const revDate = new Date(rev.data_revisao + 'T00:00:00-03:00');
            const dataFormatada = revDate.toLocaleDateString('pt-BR', {day: '2-digit', month: 'short'});
            const corBorda = rev.tag_nome ? (coresMaterias[rev.tag_nome] || 'var(--text-dark)') : 'var(--text-dark)';
            let checkboxHtml = '';
            if (revDate <= today) {
                checkboxHtml = `<div class="checkbox" style="border-color: ${corBorda}"></div>`;
            } else {
                checkboxHtml = `<svg class="future-task-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
            }
            taskCard.innerHTML = `
                ${checkboxHtml}
                <span class="task-title">${rev.titulo}</span>
                <span class="task-date">${dataFormatada}</span>
                <div class="task-menu-container">
                    <button class="task-menu-btn"><svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="pointer-events: none;"><path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg></button>
                    <div class="task-menu-dropdown"><a href="#" data-action="edit">Editar</a><a href="#" data-action="delete" class="delete">Apagar Assunto</a></div>
                </div>
            `;
            if (rev.feita == 1) {
                taskCard.classList.add('completed');
                completedTasksContainer.appendChild(taskCard);
            } else {
                hasPending = true;
                pendingTasksContainer.appendChild(taskCard);
            }
        });
        if (!hasPending && filteredRevisoes.length > 0) {
             pendingTasksContainer.innerHTML = '<p style="color: var(--text-dark);">Nenhuma revisão pendente para este período.</p>';
        } else if (filteredRevisoes.length === 0) {
             pendingTasksContainer.innerHTML = '<p style="color: var(--text-dark);">Nenhuma revisão encontrada.</p>';
        }
    }

    mainView.addEventListener('click', function(e) {
        const menuBtn = e.target.closest('.task-menu-btn');
        const deleteBtn = e.target.closest('[data-action="delete"]');
        const editBtn = e.target.closest('[data-action="edit"]');
        const checkbox = e.target.closest('.checkbox');
        document.querySelectorAll('.task-menu-dropdown').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
        if (menuBtn) {
            e.preventDefault();
            const dropdown = menuBtn.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        else if (deleteBtn) {
            e.preventDefault();
            const taskCard = deleteBtn.closest('.task-card');
            const assuntoId = taskCard.dataset.assuntoId;
            if (confirm('Tem certeza que deseja apagar este assunto? Todas as suas revisões (feitas e futuras) serão removidas permanentemente.')) {
                const formData = new FormData();
                formData.append('action', 'delete_assunto');
                formData.append('assunto_id', assuntoId);
                fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    if (data.success) {
                        todasRevisoes = todasRevisoes.filter(rev => rev.assunto_id != assuntoId);
                        renderTasks();
                    } else { alert('Erro ao apagar: ' + data.message); }
                });
            }
        }
        else if (editBtn) {
            e.preventDefault();
            const taskCard = editBtn.closest('.task-card');
            const assuntoId = taskCard.dataset.assuntoId;
            const tituloAtual = taskCard.dataset.titulo;
            const novoTitulo = prompt("Digite o novo nome para o assunto:", tituloAtual);
            if (novoTitulo && novoTitulo.trim() !== '' && novoTitulo !== tituloAtual) {
                const formData = new FormData();
                formData.append('action', 'edit_assunto');
                formData.append('assunto_id', assuntoId);
                formData.append('novo_titulo', novoTitulo.trim());
                fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    if (data.success) {
                        todasRevisoes.forEach(rev => { if (rev.assunto_id == assuntoId) { rev.titulo = novoTitulo.trim(); } });
                        renderTasks();
                    } else { alert('Erro ao editar: ' + data.message); }
                });
            }
        }
        else if (checkbox) {
            const taskCard = checkbox.closest('.task-card');
            const action = taskCard.classList.contains('completed') ? 'unmark_done' : 'mark_done';
            const revisaoId = taskCard.dataset.id;
            const formData = new FormData();
            formData.append('action', action);
            formData.append('revisao_id', revisaoId);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) {
                    const revisaoIndex = todasRevisoes.findIndex(r => r.id == revisaoId);
                    if (revisaoIndex > -1) { todasRevisoes[revisaoIndex].feita = (action === 'mark_done') ? 1 : 0; }
                    renderTasks();
                } else { alert('Falha na operação: ' + data.message); }
            });
        }
    });

    document.querySelectorAll('.time-filter-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.time-filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            currentPeriod = this.dataset.period;
            mainViewTitle.textContent = this.textContent;
            renderTasks();
        });
    });

    document.querySelectorAll('.tag-filter-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tag-filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            currentTag = this.dataset.filter;
            renderTasks();
        });
    });

    tagSelect.addEventListener('change', function() {
        if (this.value === '--new--') {
            tagSelect.style.display = 'none';
            newTagGroup.style.display = 'flex';
            newTagInput.required = true;
            newTagInput.focus();
        }
    });

    cancelNewTagBtn.addEventListener('click', function() {
        newTagGroup.style.display = 'none';
        newTagInput.required = false;
        newTagInput.value = '';
        tagSelect.style.display = 'block';
        tagSelect.value = "";
    });

    addForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(addForm);
        formData.append('action', 'add_task');
        fetch('dashboard.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
            formMessage.textContent = data.message;
            formMessage.className = data.success ? 'success' : 'error';
            formMessage.style.display = 'block';
            if (data.success) {
                addForm.reset();
                newTagGroup.style.display = 'none';
                tagSelect.style.display = 'block';
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    });
    
    renderTasks();
});
</script>
</body>
</html>