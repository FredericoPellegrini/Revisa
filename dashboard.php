<?php
session_start();
require 'config.php';

// PARTE 1: LÓGICA DE BACKEND (API)

// Endpoint para fornecer dados atualizados via GET para o JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
        exit;
    }
    $usuario_id = $_SESSION['user_id'];
    
    // Busca todas as revisões
    $sql_revisoes = "SELECT r.id, r.dia_revisao, r.data_revisao, a.id as assunto_id, a.titulo, t.nome as tag_nome, r.feita FROM revisoes r JOIN assuntos a ON r.assunto_id = a.id LEFT JOIN assunto_tag at ON a.id = at.assunto_id LEFT JOIN tags t ON at.tag_id = t.id WHERE a.user_id = ? ORDER BY r.data_revisao ASC, a.id ASC, r.dia_revisao ASC";
    $stmt_revisoes = $pdo->prepare($sql_revisoes);
    $stmt_revisoes->execute([$usuario_id]);
    $todas_revisoes = $stmt_revisoes->fetchAll(PDO::FETCH_ASSOC);

    // Busca todas as tags do usuário
    $sql_tags = "SELECT DISTINCT t.id, t.nome FROM tags t JOIN assunto_tag at ON t.id = at.tag_id JOIN assuntos a ON at.assunto_id = a.id WHERE a.user_id = ? ORDER BY t.nome ASC";
    $stmt_tags = $pdo->prepare($sql_tags);
    $stmt_tags->execute([$usuario_id]);
    $tags_com_id = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'todas_revisoes' => $todas_revisoes,
        'tags_list' => $tags_com_id
    ]);
    exit;
}


// Lógica para processar ações via POST
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
            if (empty($titulo)) throw new Exception("O campo 'Assunto' é obrigatório.");
            $tag_existente = $_POST['tag_existente'] ?? '';
            $tag_nova = trim($_POST['tag_nova'] ?? '');
            $tag_id = null;
            if (!empty($tag_nova)) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(nome) = LOWER(?)");
                $stmt->execute([$tag_nova]);
                $existing_tag = $stmt->fetch();
                if ($existing_tag) { $tag_id = $existing_tag['id']; }
                else {
                    $stmt = $pdo->prepare("INSERT INTO tags (nome) VALUES (?)");
                    $stmt->execute([$tag_nova]);
                    $tag_id = $pdo->lastInsertId();
                }
            } else if (!empty($tag_existente)) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE nome = ?");
                $stmt->execute([$tag_existente]);
                $tag_id = $stmt->fetchColumn();
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO assuntos (user_id, titulo, criado_em) VALUES (?, ?, CURDATE())");
            $stmt->execute([$usuario_id, $titulo]);
            $assunto_id = $pdo->lastInsertId();
            if ($tag_id) {
                $stmt = $pdo->prepare("INSERT INTO assunto_tag (assunto_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$assunto_id, $tag_id]);
            }
            $dias_revisao = [0, 1, 7, 14, 30, 60, 120]; 
            $stmt_revisao = $pdo->prepare("INSERT INTO revisoes (assunto_id, dia_revisao, data_revisao) VALUES (?, ?, ?)");
            foreach ($dias_revisao as $dia) {
                $data_revisao = date('Y-m-d', strtotime("+$dia days"));
                $stmt_revisao->execute([$assunto_id, $dia, $data_revisao]);
            }
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Assunto adicionado com sucesso!'];
        }
        
        else if ($_POST['action'] === 'unmark_done') {
            $revisao_id = $_POST['revisao_id'] ?? 0;
            $sql = "UPDATE revisoes r JOIN assuntos a ON r.assunto_id = a.id SET r.feita = 0 WHERE r.id = ? AND a.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$revisao_id, $usuario_id]);
            $response = ['success' => true];
        }

        else if ($_POST['action'] === 'mark_done') {
            $revisao_id = $_POST['revisao_id'] ?? 0;
            if (empty($revisao_id)) throw new Exception("ID da revisão não fornecido.");
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT assunto_id, data_revisao FROM revisoes WHERE id = ?");
            $stmt->execute([$revisao_id]);
            $revisao = $stmt->fetch();
            if (!$revisao) throw new Exception("Revisão não encontrada.");
            $assunto_id = $revisao['assunto_id'];
            $data_revisao_original = new DateTime($revisao['data_revisao']);
            $hoje = new DateTime(date('Y-m-d'));
            $atraso = 0;
            if ($hoje > $data_revisao_original) {
                $atraso = $hoje->diff($data_revisao_original)->days;
            }
            if ($atraso >= 14) {
                $stmt = $pdo->prepare("DELETE FROM revisoes WHERE assunto_id = ? AND feita = 0");
                $stmt->execute([$assunto_id]);
                $stmt_dia = $pdo->prepare("SELECT dia_revisao FROM revisoes WHERE id = ?");
                $stmt_dia->execute([$revisao_id]);
                $dia_original = $stmt_dia->fetchColumn();
                $stmt_insert_done = $pdo->prepare("INSERT INTO revisoes (assunto_id, dia_revisao, data_revisao, feita) VALUES (?, ?, CURDATE(), 1)");
                $stmt_insert_done->execute([$assunto_id, $dia_original]);
                $dias_revisao_futura = [1, 7, 14, 30, 60, 120];
                $stmt_insert_future = $pdo->prepare("INSERT INTO revisoes (assunto_id, dia_revisao, data_revisao) VALUES (?, ?, ?)");
                foreach ($dias_revisao_futura as $dia) {
                    $nova_data = date('Y-m-d', strtotime("+$dia days"));
                    $stmt_insert_future->execute([$assunto_id, $dia, $nova_data]);
                }
            } else {
                $sql = "UPDATE revisoes r JOIN assuntos a ON r.assunto_id = a.id SET r.feita = 1 WHERE r.id = ? AND a.user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$revisao_id, $usuario_id]);
            }
            $pdo->commit();
            $response = ['success' => true];
        }
        
        else if ($_POST['action'] === 'delete_assunto') {
            $assunto_id = $_POST['assunto_id'] ?? 0;
            if (empty($assunto_id)) throw new Exception("ID do assunto não fornecido.");
            $sql = "DELETE FROM assuntos WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$assunto_id, $usuario_id]);
            if ($stmt->rowCount() > 0) $response = ['success' => true, 'message' => 'Assunto apagado com sucesso.'];
            else throw new Exception("Assunto não encontrado ou você não tem permissão para apagá-lo.");
        }
        else if ($_POST['action'] === 'edit_assunto') {
            $assunto_id = $_POST['assunto_id'] ?? 0;
            $novo_titulo = trim($_POST['novo_titulo'] ?? '');
            if (empty($assunto_id)) throw new Exception("ID do assunto não fornecido.");
            if (empty($novo_titulo)) throw new Exception("O novo título não pode ser vazio.");
            $sql = "UPDATE assuntos SET titulo = ? WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_titulo, $assunto_id, $usuario_id]);
            if ($stmt->rowCount() > 0) $response = ['success' => true];
            else throw new Exception("Assunto não encontrado ou o título já era o mesmo.");
        }
        else if ($_POST['action'] === 'edit_tag') {
            $tag_id = $_POST['tag_id'] ?? 0;
            $novo_nome_tag = trim($_POST['novo_nome_tag'] ?? '');
            if (empty($tag_id) || empty($novo_nome_tag)) throw new Exception("ID da tag e novo nome são obrigatórios.");
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(nome) = LOWER(?) AND id != ?");
            $stmt->execute([$novo_nome_tag, $tag_id]);
            if ($stmt->fetch()) throw new Exception("Já existe outra tag com este nome.");
            $stmt = $pdo->prepare("UPDATE tags SET nome = ? WHERE id = ?");
            $stmt->execute([$novo_nome_tag, $tag_id]);
            if ($stmt->rowCount() > 0) $response = ['success' => true, 'message' => 'Tag editada com sucesso!'];
            else throw new Exception("Tag não encontrada ou nome não foi alterado.");
        }
        else if ($_POST['action'] === 'delete_tag') {
            $tag_id = $_POST['tag_id'] ?? 0;
            if (empty($tag_id)) throw new Exception("ID da tag é obrigatório.");
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT at.assunto_id FROM assunto_tag at JOIN assuntos a ON at.assunto_id = a.id WHERE at.tag_id = ? AND a.user_id = ?");
            $stmt->execute([$tag_id, $usuario_id]);
            $user_subjects_with_tag = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($user_subjects_with_tag) > 0) {
                $placeholders = implode(',', array_fill(0, count($user_subjects_with_tag), '?'));
                $sql_disassociate = "DELETE FROM assunto_tag WHERE tag_id = ? AND assunto_id IN ($placeholders)";
                $stmt_disassociate = $pdo->prepare($sql_disassociate);
                $stmt_disassociate->execute(array_merge([$tag_id], $user_subjects_with_tag));
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assunto_tag WHERE tag_id = ?");
            $stmt->execute([$tag_id]);
            $global_associations = $stmt->fetchColumn();
            if ($global_associations == 0) {
                $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
                $stmt->execute([$tag_id]);
                $response = ['success' => true, 'message' => 'Tag e suas associações removidas com sucesso!'];
            } else {
                $response = ['success' => true, 'message' => 'Associações da tag para seus assuntos foram removidas. A tag ainda é usada por outros e não foi apagada.'];
            }
            $pdo->commit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        $response['message'] = 'Erro: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// PARTE 2: LÓGICA PARA CARREGAMENTO INICIAL DA PÁGINA
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];
$sql_todas_revisoes = "SELECT r.id, r.dia_revisao, r.data_revisao, a.id as assunto_id, a.titulo, t.nome as tag_nome, r.feita FROM revisoes r JOIN assuntos a ON r.assunto_id = a.id LEFT JOIN assunto_tag at ON a.id = at.assunto_id LEFT JOIN tags t ON at.tag_id = t.id WHERE a.user_id = ? ORDER BY r.data_revisao ASC, a.id ASC, r.dia_revisao ASC";
$stmt_todas_revisoes = $pdo->prepare($sql_todas_revisoes);
$stmt_todas_revisoes->execute([$usuario_id]);
$todas_revisoes = $stmt_todas_revisoes->fetchAll(PDO::FETCH_ASSOC);
$sql_tags = "SELECT DISTINCT t.id, t.nome FROM tags t JOIN assunto_tag at ON t.id = at.tag_id JOIN assuntos a ON at.assunto_id = a.id WHERE a.user_id = ? ORDER BY t.nome ASC";
$stmt_tags = $pdo->prepare($sql_tags);
$stmt_tags->execute([$usuario_id]);
$tags_com_id = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);
$cores_disponiveis = ['#FBBF24', '#60A5FA', '#F87171', '#4ADE80', '#818CF8', '#A78BFA'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Revisa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
        :root { 
            --bg-darkest: #282A2C; /* Preto puro para a área mais escura */
            --bg-darker: #1B1C1D;  /* Tom "preto clarinho" para as 3 áreas principais */
            --bg-dark: #282A2C;   /* Cor dos cards e das linhas divisórias */
            --text-light: #E5E7EB; 
            --text-normal: #9CA3AF; 
            --text-dark: #6B7280; 
            --accent-yellow: #FBBF24; 
            --accent-red: #F87171; 
            --accent-green: #22C55E; 
            --accent-blue: #3B82F6; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-darker); color: var(--text-light); }
        a { color: inherit; text-decoration: none; }
        .dashboard-grid { display: grid; grid-template-columns: 80px 250px 1fr 350px; height: 100vh; }
        
        .nav-icons, .filters-sidebar, .main-view, .add-task-sidebar { height: 100vh; overflow-y: auto; }
        .nav-icons { 
            background-color: var(--bg-darkest);
            border-right: 1px solid var(--bg-dark);
            padding: 24px 0; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: space-between; 
        }
        .filters-sidebar { 
            background-color: var(--bg-darker);
            border-right: 1px solid var(--bg-dark);
            padding: 24px;
        }
        .main-view {
            background-color: var(--bg-darker);
            border-right: 1px solid var(--bg-dark);
            padding: 32px;
        }
        .add-task-sidebar {
            background-color: var(--bg-darker);
            padding: 32px;
        }

        .nav-icons-top { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .nav-icons a { padding: 12px; border-radius: 8px; line-height: 0; transition: background-color 0.2s; }
        .nav-icons a:hover { background-color: var(--bg-dark); }
        .nav-icons a.active { background-color: var(--bg-dark); }
        .nav-icons .active svg { color: var(--accent-green); }
        .nav-icons svg { width: 28px; height: 28px; }

        .section-title { font-size: 0.75rem; font-weight: bold; color: var(--text-dark); text-transform: uppercase; margin-bottom: 16px; margin-top: 30px; }
        .time-filters a { display: block; padding: 10px; border-radius: 8px; font-weight: 500; cursor: pointer; margin-bottom: 4px;}
        .time-filters a:hover { background-color: var(--bg-dark); }
        .time-filters a.active { background-color: var(--bg-dark); color: white; }

        .filters-sidebar .tags-list a { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; font-weight: 500; cursor: pointer; justify-content: space-between; }
        .filters-sidebar .tags-list a:hover, .filters-sidebar .tags-list a.active { background-color: var(--bg-dark); color: white; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .tag-name-wrapper { display: flex; align-items: center; gap: 12px; flex-grow: 1; }
        .tag-actions { display: flex; gap: 5px; opacity: 0; transition: opacity 0.2s; }
        .filters-sidebar .tags-list a:hover .tag-actions { opacity: 1; }
        .tag-action-btn { background: none; border: none; color: var(--text-normal); cursor: pointer; padding: 4px; border-radius: 4px; line-height: 0; }
        .tag-action-btn:hover { background-color: #374151; color: white; }
        .tag-action-btn.delete-tag-btn:hover { color: var(--accent-red); }

        .edit-tag-modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); justify-content: center; align-items: center; }
        .edit-tag-modal-content { background-color: var(--bg-darker); margin: auto; padding: 20px; border: 1px solid var(--bg-dark); border-radius: 10px; width: 80%; max-width: 400px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .edit-tag-modal-content h3 { margin-bottom: 20px; color: var(--text-light); }
        .edit-tag-modal-content input { margin-bottom: 15px; width:100%; background-color: var(--bg-dark); border: 1px solid var(--bg-dark); color: var(--text-light); padding: 12px; border-radius: 8px; font-size: 1rem; }
        .edit-tag-modal-actions { display: flex; justify-content: space-around; gap: 10px; }
        .edit-tag-modal-actions button { flex-grow: 1; padding: 10px 15px; border-radius: 8px; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: background-color 0.3s; border: none; color: #fff;}
        .edit-tag-modal-actions #save-edited-tag-btn { background-color: var(--accent-green); }
        .edit-tag-modal-actions .cancel-btn { background-color: var(--text-dark); }
        .edit-tag-modal-actions .cancel-btn:hover { background-color: #555; }
        .msg { padding: 10px; border-radius: 5px; margin-top: 10px; color: white; }
        .msg.success { background-color: #166534; }
        .msg.error { background-color: #991B1B; }
        
        .main-view { padding: 32px; }
        .task-card { background-color: var(--bg-dark); padding: 16px; border-radius: 8px; display: flex; align-items: center; gap: 16px; margin-bottom: 12px; transition: opacity 0.3s, transform 0.3s; position: relative; }
        .task-card .checkbox { width: 20px; height: 20px; border: 2px solid var(--text-dark); border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .task-card .checkbox:hover { border-color: var(--accent-yellow); }
        .task-card .task-title { font-weight: 500; margin-right: auto; }
        .task-card .task-date { font-size: 0.8rem; color: var(--text-dark); transition: color 0.2s; }
        .task-card.overdue .task-date { color: var(--accent-red); font-weight: bold; }
        .future-task-icon { width: 20px; height: 20px; color: var(--text-dark); flex-shrink: 0; }
        .task-menu-container { position: relative; }
        .task-menu-btn { padding: 4px; border-radius: 4px; line-height: 0; cursor: pointer; background: none; border: none; color: var(--text-normal); }
        .task-menu-btn:hover { background-color: #374151; }
        .task-menu-dropdown { display: none; position: absolute; right: 0; top: 30px; background-color: #374151; border-radius: 8px; z-index: 10; width: 140px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .task-menu-dropdown a { display: block; padding: 10px 12px; font-size: 0.9rem; }
        .task-menu-dropdown a:hover { background-color: var(--accent-blue); color: white; }
        .task-menu-dropdown a.delete { color: var(--accent-red); }
        .task-menu-dropdown a.delete:hover { background-color: var(--accent-red); color: white; }
        .task-card.completed { opacity: 0.6; }
        .task-card.completed .checkbox { background-color: var(--accent-green); border-color: var(--accent-green); }
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
            <div class="section-title">Tags</div>
            <div class="tags-list" id="tags-list-container"></div>
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
                        <select id="task-tag-select" name="tag_existente"></select>
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

    <div id="edit-tag-modal" class="edit-tag-modal">
        <div class="edit-tag-modal-content">
            <h3>Editar Tag</h3>
            <input type="hidden" id="edit-tag-id">
            <input type="text" id="edit-tag-name-input" placeholder="Novo nome da tag">
            <div class="edit-tag-modal-actions">
                <button id="save-edited-tag-btn">Salvar</button>
                <button id="cancel-edit-tag-btn" class="cancel-btn">Cancelar</button>
            </div>
            <div id="edit-tag-message" class="msg" style="display: none;"></div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let todasRevisoes = <?= json_encode($todas_revisoes) ?>;
    let tagsList = <?= json_encode($tags_com_id) ?>;
    const coresDisponiveis = <?= json_encode($cores_disponiveis) ?>;
    const todayServerFormatted = '<?= date("Y-m-d") ?>';

    let coresTagsMap = {};
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
    const tagsListContainer = document.getElementById('tags-list-container');
    const editTagModal = document.getElementById('edit-tag-modal');
    const editTagIdInput = document.getElementById('edit-tag-id');
    const editTagNameInput = document.getElementById('edit-tag-name-input');
    const saveEditedTagBtn = document.getElementById('save-edited-tag-btn');
    const cancelEditTagBtn = document.getElementById('cancel-edit-tag-btn');
    const editTagMessage = document.getElementById('edit-tag-message');
    let currentPeriod = 'today';
    let currentTag = 'all';

    function updateCoresTagsMap() { /* ... */ }
    function renderTagsList() { /* ... */ }

    function renderTasks() {
        pendingTasksContainer.innerHTML = ''; 
        completedTasksContainer.innerHTML = '';

        const filteredRevisoes = todasRevisoes.filter(rev => {
            let periodMatch = false;
            if (currentPeriod === 'today') {
                if (rev.data_revisao <= todayServerFormatted) periodMatch = true;
            } else if (currentPeriod === 'week') {
                const today = new Date(todayServerFormatted + 'T00:00:00');
                const revDate = new Date(rev.data_revisao + 'T00:00:00');
                const nextWeek = new Date(today);
                nextWeek.setDate(today.getDate() + 7);
                if (revDate >= today && revDate < nextWeek) periodMatch = true;
            } else if (currentPeriod === 'all') {
                periodMatch = true;
            }
            const tagMatch = (currentTag === 'all' || (rev.tag_nome || 'none') === currentTag);
            return periodMatch && tagMatch;
        });

        let hasPending = false;
        todasRevisoes.forEach(rev => {
            const isVisible = filteredRevisoes.some(filteredRev => filteredRev.id === rev.id);
            const taskCard = document.createElement('div');
            taskCard.className = 'task-card';
            taskCard.dataset.id = rev.id;
            taskCard.dataset.assuntoId = rev.assunto_id;
            taskCard.dataset.titulo = rev.titulo;
            
            const revDate = new Date(rev.data_revisao + 'T00:00:00'); 
            const todayLocal = new Date(todayServerFormatted + 'T00:00:00');
            const dataFormatada = revDate.toLocaleDateString('pt-BR', {day: '2-digit', month: 'short'});
            const corBorda = rev.tag_nome ? (coresTagsMap[rev.tag_nome] || 'var(--text-dark)') : 'var(--text-dark)';
            
            if (revDate < todayLocal) {
                taskCard.classList.add('overdue');
            }

            // CORREÇÃO: Restaurando o conteúdo completo do ícone futuro e do menu '...'
            const checkboxHtml = (rev.data_revisao <= todayServerFormatted) 
                ? `<div class="checkbox" style="border-color: ${corBorda}"></div>`
                : `<svg class="future-task-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;

            taskCard.innerHTML = `
                ${checkboxHtml}
                <span class="task-title">${rev.titulo}</span>
                <span class="task-date">${dataFormatada}</span>
                <div class="task-menu-container">
                    <button class="task-menu-btn">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="pointer-events: none;"><path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
                    </button>
                    <div class="task-menu-dropdown">
                        <a href="#" data-action="edit">Editar Assunto</a>
                        <a href="#" data-action="delete" class="delete">Apagar Assunto</a>
                    </div>
                </div>`;
            
            if (rev.feita == 1) {
                taskCard.classList.add('completed');
                completedTasksContainer.appendChild(taskCard);
            } else {
                if(isVisible) {
                    hasPending = true;
                    pendingTasksContainer.appendChild(taskCard);
                }
            }
        });

        if (!hasPending && pendingTasksContainer.innerHTML === '') {
            pendingTasksContainer.innerHTML = '<p style="color: var(--text-dark);">Nenhuma revisão pendente para este período.</p>';
        }
    }
    
    // O restante do JS é idêntico ao código anterior e funcional
    function handleTimeFilterClick(e) { /* ... */ }
    function activateFilterButtons() { /* ... */ }
    function activateTagCrudButtons() { /* ... */ }
    function handleTagFilterClick(e) { /* ... */ }
    mainView.addEventListener('click', function(e) { /* ... */ });
    tagSelect.addEventListener('change', function() { /* ... */ });
    cancelNewTagBtn.addEventListener('click', function() { /* ... */ });
    addForm.addEventListener('submit', function(e) { /* ... */ });
    function handleEditTagClick(e) { /* ... */ }
    saveEditedTagBtn.addEventListener('click', function() { /* ... */ });
    cancelEditTagBtn.addEventListener('click', () => editTagModal.style.display = 'none');
    function handleDeleteTagClick(e) { /* ... */ }
    function displayMessage(container, message, isSuccess) { /* ... */ }
    async function fetchDashboardDataAndRender() { /* ... */ }

    // Re-colando o código completo das funções que foram abreviadas para garantir que tudo funcione
    updateCoresTagsMap = function() {
        coresTagsMap = {};
        tagsList.forEach((tag, i) => {
            coresTagsMap[tag.nome] = coresDisponiveis[i % coresDisponiveis.length];
        });
    }

    renderTagsList = function() {
        tagsListContainer.innerHTML = '';
        tagSelect.innerHTML = '<option value="">Nenhuma</option>';
        const allTagsLink = document.createElement('a');
        allTagsLink.className = 'tag-filter-btn';
        if (currentTag === 'all') allTagsLink.classList.add('active');
        allTagsLink.dataset.filter = 'all';
        allTagsLink.textContent = 'Todas as Tags';
        tagsListContainer.appendChild(allTagsLink);
        tagsList.forEach(tag => {
            const tagColor = coresTagsMap[tag.nome] || 'var(--text-dark)';
            const tagLink = document.createElement('a');
            tagLink.className = 'tag-filter-btn';
            if (currentTag === tag.nome) tagLink.classList.add('active');
            tagLink.dataset.filter = tag.nome;
            tagLink.dataset.tagId = tag.id;
            tagLink.innerHTML = `
                <div class="tag-name-wrapper">
                    <span class="color-dot" style="background-color: ${tagColor};"></span>
                    <span>${tag.nome}</span>
                </div>
                <div class="tag-actions">
                    <button class="tag-action-btn edit-tag-btn" data-tag-id="${tag.id}" data-tag-name="${tag.nome}" title="Editar Tag"><i class="fas fa-edit"></i></button>
                    <button class="tag-action-btn delete-tag-btn" data-tag-id="${tag.id}" data-tag-name="${tag.nome}" title="Apagar Tag"><i class="fas fa-trash-alt"></i></button>
                </div>`;
            tagsListContainer.appendChild(tagLink);
            const option = document.createElement('option');
            option.value = tag.nome;
            option.textContent = tag.nome;
            tagSelect.appendChild(option);
        });
        const newTagOption = document.createElement('option');
        newTagOption.value = '--new--';
        newTagOption.textContent = '(Adicionar Nova Tag)';
        newTagOption.style.color = 'var(--accent-yellow)';
        newTagOption.style.fontWeight = 'bold';
        tagSelect.appendChild(newTagOption);
        activateFilterButtons();
        activateTagCrudButtons();
    }

    handleTimeFilterClick = function(e) {
        e.preventDefault();
        document.querySelectorAll('.time-filter-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        currentPeriod = this.dataset.period;
        mainViewTitle.textContent = this.textContent;
        renderTasks();
    }

    activateFilterButtons = function() {
        document.querySelectorAll('.time-filter-btn').forEach(button => {
            button.removeEventListener('click', handleTimeFilterClick);
            button.addEventListener('click', handleTimeFilterClick);
        });
        document.querySelectorAll('.tags-list > .tag-filter-btn').forEach(button => {
            button.removeEventListener('click', handleTagFilterClick);
            button.addEventListener('click', handleTagFilterClick);
        });
    }

    activateTagCrudButtons = function() {
        document.querySelectorAll('.edit-tag-btn').forEach(button => {
            button.removeEventListener('click', handleEditTagClick);
            button.addEventListener('click', handleEditTagClick);
        });
        document.querySelectorAll('.delete-tag-btn').forEach(button => {
            button.removeEventListener('click', handleDeleteTagClick);
            button.addEventListener('click', handleDeleteTagClick);
        });
    }

    handleTagFilterClick = function(e) {
        e.preventDefault();
        if (e.target.closest('.tag-action-btn')) return;
        document.querySelectorAll('.tags-list > .tag-filter-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        currentTag = this.dataset.filter;
        renderTasks();
    }

    mainView.addEventListener('click', function(e) {
        const menuBtn = e.target.closest('.task-menu-btn');
        const checkbox = e.target.closest('.checkbox');
        
        document.querySelectorAll('.task-menu-dropdown').forEach(menu => {
            if (menuBtn && menu.previousElementSibling === menuBtn) {
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            } else {
                menu.style.display = 'none';
            }
        });

        if (e.target.closest('[data-action="delete"]')) {
            e.preventDefault();
            const taskCard = e.target.closest('.task-card');
            const assuntoId = taskCard.dataset.assuntoId;
            if (confirm('Tem certeza que deseja apagar este assunto e todas as suas revisões?')) {
                const formData = new FormData();
                formData.append('action', 'delete_assunto');
                formData.append('assunto_id', assuntoId);
                fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    if (data.success) fetchDashboardDataAndRender();
                    else alert('Erro: ' + data.message);
                });
            }
        } else if (e.target.closest('[data-action="edit"]')) {
            e.preventDefault();
            const taskCard = e.target.closest('.task-card');
            const assuntoId = taskCard.dataset.assuntoId;
            const tituloAtual = taskCard.dataset.titulo;
            const novoTitulo = prompt("Digite o novo nome para o assunto:", tituloAtual);
            if (novoTitulo && novoTitulo.trim() !== '' && novoTitulo !== tituloAtual) {
                const formData = new FormData();
                formData.append('action', 'edit_assunto');
                formData.append('assunto_id', assuntoId);
                formData.append('novo_titulo', novoTitulo.trim());
                fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    if (data.success) fetchDashboardDataAndRender();
                    else alert('Erro: ' + data.message);
                });
            }
        } else if (checkbox) {
            const taskCard = checkbox.closest('.task-card');
            const action = taskCard.classList.contains('completed') ? 'unmark_done' : 'mark_done';
            const revisaoId = taskCard.dataset.id;
            const formData = new FormData();
            formData.append('action', action);
            formData.append('revisao_id', revisaoId);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.success) fetchDashboardDataAndRender();
                else alert('Erro: ' + data.message);
            });
        }
    });

    tagSelect.addEventListener('change', function() {
        if (this.value === '--new--') {
            tagSelect.style.display = 'none';
            newTagGroup.style.display = 'flex';
            newTagInput.required = true; newTagInput.focus();
        }
    });

    cancelNewTagBtn.addEventListener('click', function() {
        newTagGroup.style.display = 'none';
        newTagInput.required = false; newTagInput.value = '';
        tagSelect.style.display = 'block'; tagSelect.value = "";
    });

    addForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(addForm);
        formData.append('action', 'add_task');
        fetch('dashboard.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
            displayMessage(formMessage, data.message, data.success);
            if (data.success) {
                addForm.reset();
                cancelNewTagBtn.click();
                fetchDashboardDataAndRender();
            }
        });
    });

    handleEditTagClick = function(e) {
        e.stopPropagation();
        editTagIdInput.value = this.dataset.tagId;
        editTagNameInput.value = this.dataset.tagName;
        editTagModal.style.display = 'flex';
        editTagMessage.style.display = 'none';
    }

    saveEditedTagBtn.addEventListener('click', function() {
        const tagId = editTagIdInput.value;
        const novoNomeTag = editTagNameInput.value.trim();
        if (!novoNomeTag) {
            displayMessage(editTagMessage, 'O nome da tag não pode ser vazio.', false); return;
        }
        const formData = new FormData();
        formData.append('action', 'edit_tag');
        formData.append('tag_id', tagId);
        formData.append('novo_nome_tag', novoNomeTag);
        fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            displayMessage(editTagMessage, data.message, data.success);
            if (data.success) {
                setTimeout(() => {
                    editTagModal.style.display = 'none';
                    if(currentTag === editTagNameInput.defaultValue) { currentTag = novoNomeTag; }
                    fetchDashboardDataAndRender();
                }, 1500);
            }
        });
    });
    
    handleDeleteTagClick = function(e) {
        e.stopPropagation();
        const tagId = this.dataset.tagId;
        const tagName = this.dataset.tagName;
        if (confirm(`Tem certeza que deseja apagar a tag "${tagName}"?`)) {
            const formData = new FormData();
            formData.append('action', 'delete_tag');
            formData.append('tag_id', tagId);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.success) {
                    if (currentTag === tagName) currentTag = 'all';
                    fetchDashboardDataAndRender();
                } else { alert("Erro ao apagar: " + data.message); }
            });
        }
    }
    
    displayMessage = function(container, message, isSuccess) {
        container.textContent = message;
        container.className = 'msg ' + (isSuccess ? 'success' : 'error');
        container.style.display = 'block';
        setTimeout(() => { container.style.display = 'none'; }, 3000);
    }

    fetchDashboardDataAndRender = async function() {
        try {
            const response = await fetch('dashboard.php?action=get_data');
            const data = await response.json();
            if (data.success) {
                todasRevisoes = data.todas_revisoes;
                tagsList = data.tags_list;
                updateCoresTagsMap();
                renderTagsList();
                renderTasks();
            } else {
                console.error('Falha ao buscar dados:', data.message);
                if (data.message.includes('autenticado')) window.location.href = 'login.php';
            }
        } catch (error) {
            console.error('Erro de rede ao buscar dados:', error);
        }
    }

    updateCoresTagsMap();
    renderTagsList();
    renderTasks();
});
</script>
</body>
</html>