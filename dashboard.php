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

            // Lógica para determinar a tag final (nova ou existente)
            $tag_id = null;
            if (!empty($tag_nova)) {
                $tag_final = $tag_nova;
                // Procura por tag existente com o novo nome (case-insensitive)
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(nome) = LOWER(?)");
                $stmt->execute([$tag_final]);
                $existing_tag = $stmt->fetch();

                if ($existing_tag) {
                    $tag_id = $existing_tag['id'];
                } else {
                    // Insere nova tag
                    $stmt = $pdo->prepare("INSERT INTO tags (nome) VALUES (?)");
                    $stmt->execute([$tag_final]);
                    $tag_id = $pdo->lastInsertId();
                }
            } else if (!empty($tag_existente) && $tag_existente !== '--new--') {
                // Usa uma tag existente
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE nome = ?");
                $stmt->execute([$tag_existente]);
                $tag_id = $stmt->fetchColumn();
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO assuntos (user_id, titulo, criado_em) VALUES (?, ?, CURDATE())");
            $stmt->execute([$usuario_id, $titulo]);
            $assunto_id = $pdo->lastInsertId();

            if ($tag_id) { // Se uma tag foi selecionada ou criada
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
        // ############### INÍCIO DO CRUD DE TAGS ###############
        else if ($_POST['action'] === 'add_tag') {
            $nome_tag = trim($_POST['nome_tag'] ?? '');
            if (empty($nome_tag)) {
                throw new Exception("O nome da tag é obrigatório.");
            }
            // Verifica se a tag já existe (case-insensitive)
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(nome) = LOWER(?)");
            $stmt->execute([$nome_tag]);
            if ($stmt->fetch()) {
                throw new Exception("Esta tag já existe.");
            }
            // Insere a nova tag
            $stmt = $pdo->prepare("INSERT INTO tags (nome) VALUES (?)");
            $stmt->execute([$nome_tag]);
            $response = ['success' => true, 'message' => 'Tag adicionada com sucesso!', 'new_tag_id' => $pdo->lastInsertId(), 'new_tag_name' => $nome_tag];
        }
        else if ($_POST['action'] === 'edit_tag') {
            $tag_id = $_POST['tag_id'] ?? 0;
            $novo_nome_tag = trim($_POST['novo_nome_tag'] ?? '');
            if (empty($tag_id) || empty($novo_nome_tag)) {
                throw new Exception("ID da tag e novo nome são obrigatórios.");
            }
            // Verifica por nome duplicado (excluindo a tag que está sendo editada)
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(nome) = LOWER(?) AND id != ?");
            $stmt->execute([$novo_nome_tag, $tag_id]);
            if ($stmt->fetch()) {
                throw new Exception("Já existe outra tag com este nome.");
            }
            // Atualiza o nome da tag
            $stmt = $pdo->prepare("UPDATE tags SET nome = ? WHERE id = ?");
            $stmt->execute([$novo_nome_tag, $tag_id]);
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Tag editada com sucesso!'];
            } else {
                throw new Exception("Tag não encontrada ou nome não foi alterado.");
            }
        }
        else if ($_POST['action'] === 'delete_tag') {
            $tag_id = $_POST['tag_id'] ?? 0;
            if (empty($tag_id)) {
                throw new Exception("ID da tag é obrigatório.");
            }

            $pdo->beginTransaction();

            // 1. Desassociar todos os assuntos *deste usuário* desta tag
            $stmt = $pdo->prepare("SELECT at.assunto_id FROM assunto_tag at JOIN assuntos a ON at.assunto_id = a.id WHERE at.tag_id = ? AND a.user_id = ?");
            $stmt->execute([$tag_id, $usuario_id]);
            $user_subjects_with_tag = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($user_subjects_with_tag) > 0) {
                $placeholders = implode(',', array_fill(0, count($user_subjects_with_tag), '?'));
                $sql_disassociate = "DELETE FROM assunto_tag WHERE tag_id = ? AND assunto_id IN ($placeholders)";
                $stmt_disassociate = $pdo->prepare($sql_disassociate);
                $stmt_disassociate->execute(array_merge([$tag_id], $user_subjects_with_tag));
            }

            // 2. Verifica se a tag é usada por *qualquer* assunto (de qualquer usuário) globalmente
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assunto_tag WHERE tag_id = ?");
            $stmt->execute([$tag_id]);
            $global_associations = $stmt->fetchColumn();

            if ($global_associations == 0) {
                // Se a tag não tem mais associações globais, ela pode ser apagada da tabela de tags
                $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
                $stmt->execute([$tag_id]);
                $response = ['success' => true, 'message' => 'Tag e suas associações removidas com sucesso!'];
            } else {
                // Se a tag ainda é usada por outros assuntos (de outros usuários, ou outros assuntos deste usuário que não foram desassociados), apenas informa a desassociação.
                $response = ['success' => true, 'message' => 'Associações da tag para seus assuntos foram removidas. A tag ainda é usada por outros assuntos e não foi apagada globalmente.'];
            }
            $pdo->commit();
        }
        // ############### FIM DO CRUD DE TAGS ###############

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

// Refatorada a busca de matérias para incluir IDs para o JS
$sql_materias = "SELECT DISTINCT t.id, t.nome FROM tags t JOIN assunto_tag at ON t.id = at.tag_id JOIN assuntos a ON at.assunto_id = a.id WHERE a.user_id = ? ORDER BY t.nome ASC";
$stmt_materias = $pdo->prepare($sql_materias);
$stmt_materias->execute([$usuario_id]);
$materias_com_id = $stmt_materias->fetchAll(PDO::FETCH_ASSOC); // Busca com ID e Nome

// Prepara as cores e o array de nomes para o JavaScript
$materias = [];
foreach($materias_com_id as $m) {
    $materias[] = $m['nome']; // Mantém o array simples de nomes para a lista de opções
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Revisa</title>
    <!-- Font Awesome para ícones de edição e exclusão de tags -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .nav-icons .active svg { color: var(--accent-green); }
        .nav-icons svg { width: 28px; height: 28px; }

        .filters-sidebar { background-color: var(--bg-darker); padding: 24px; }
        .section-title { font-size: 0.75rem; font-weight: bold; color: var(--text-dark); text-transform: uppercase; margin-bottom: 16px; margin-top: 30px; }
        
        .time-filters a { display: block; padding: 10px; border-radius: 8px; font-weight: 500; cursor: pointer; margin-bottom: 4px;}
        .time-filters a:hover { background-color: var(--bg-dark); }
        .time-filters a.active { background-color: var(--bg-dark); color: white; }

        /* Estilos para a lista de matérias com botões de CRUD */
        .filters-sidebar .materias-list a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 10px; 
            border-radius: 8px; 
            font-weight: 500; 
            cursor: pointer; 
            justify-content: space-between; /* Espaça conteúdo e botões */
        }
        .filters-sidebar .materias-list a:hover, .filters-sidebar .materias-list a.active { background-color: var(--bg-dark); color: white; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .tag-name-wrapper { display: flex; align-items: center; gap: 12px; flex-grow: 1; } /* Para envolver o dot e o nome */
        .tag-actions { display: flex; gap: 5px; opacity: 0; transition: opacity 0.2s; }
        .filters-sidebar .materias-list a:hover .tag-actions { opacity: 1; } /* Mostra botões no hover */
        .tag-action-btn { background: none; border: none; color: var(--text-normal); cursor: pointer; padding: 4px; border-radius: 4px; line-height: 0; }
        .tag-action-btn:hover { background-color: #374151; color: white; }
        .tag-action-btn.delete-tag-btn:hover { color: var(--accent-red); }

        /* Estilo para o novo botão de adicionar matéria e o campo de input */
        .add-tag-button {
            background-color: var(--accent-blue); /* Mesma cor dos botões principais */
            color: #fff;
            border: none;
            padding: 10px; /* Um pouco menor para caber na barra lateral */
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%; /* Largura total da barra lateral */
            margin-top: 10px; /* Espaço do item anterior */
            margin-bottom: 10px; /* Espaço para a lista de matérias */
        }
        .add-tag-button:hover {
            background-color: #3165b6; /* Azul mais escuro no hover */
        }

        #new-tag-add-area {
            display: flex; /* Para organizar input e botões */
            flex-direction: column;
            gap: 10px; /* Espaço entre input e botões de ação */
            width: 100%;
            margin-bottom: 10px; /* Espaço antes da lista de matérias existentes */
        }
        #new-tag-add-area input {
            width: 100%; /* Input preenche a largura do seu contêiner */
            background-color: var(--bg-dark);
            border: 1px solid var(--bg-dark);
            color: var(--text-light);
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
        }
        .add-tag-action-buttons {
            display: flex;
            gap: 10px; /* Espaço entre Salvar e Cancelar */
        }
        .add-tag-action-buttons button {
            flex-grow: 1; /* Faz os botões preencherem o espaço disponível */
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
            color: #fff;
        }
        #add-tag-confirm-btn {
            background-color: var(--accent-green);
        }
        #add-tag-confirm-btn:hover {
            background-color: #16a34a;
        }
        #cancel-add-tag-btn {
            background-color: var(--text-dark);
        }
        #cancel-add-tag-btn:hover {
            background-color: #555;
        }


        .edit-tag-modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 100; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .edit-tag-modal-content {
            background-color: var(--bg-darker);
            margin: auto;
            padding: 20px;
            border: 1px solid var(--bg-dark);
            border-radius: 10px;
            width: 80%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .edit-tag-modal-content h3 { margin-bottom: 20px; color: var(--text-light); }
        .edit-tag-modal-content input { margin-bottom: 15px; }
        .edit-tag-modal-actions { display: flex; justify-content: space-around; gap: 10px; }
        .edit-tag-modal-actions button { flex-grow: 1; }
        .edit-tag-modal-actions .cancel-btn { background-color: var(--text-dark); }
        .edit-tag-modal-actions .cancel-btn:hover { background-color: #555; }


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
            <div class="materias-list" id="materias-list-container">
                <!-- As tags serão renderizadas aqui pelo JavaScript -->
            </div>

            <!-- Antiga Seção de Gerenciamento de Tags - será removida ou adaptada -->
            <!-- <div class="manage-tags-section">
                <div class="section-title">Gerenciar Matérias</div>
                <div id="add-new-tag-form">
                    <input type="text" id="new-tag-name-input" placeholder="Nome da Nova Matéria">
                    <button type="button" id="add-tag-btn">Adicionar Nova Matéria</button>
                </div>
            </div> -->
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
                            <!-- Opções de tags serão preenchidas via JS -->
                            <option value="--new--" style="color: #C04000; font-weight: bold;">(Adicionar Nova Tag)</option>
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

    <!-- Modal para Edição de Tag -->
    <div id="edit-tag-modal" class="edit-tag-modal">
        <div class="edit-tag-modal-content">
            <h3>Editar Matéria</h3>
            <input type="hidden" id="edit-tag-id">
            <input type="text" id="edit-tag-name-input" placeholder="Novo nome da matéria">
            <div class="edit-tag-modal-actions">
                <button id="save-edited-tag-btn">Salvar</button>
                <button id="cancel-edit-tag-btn" class="cancel-btn">Cancelar</button>
            </div>
            <div id="edit-tag-message" class="msg" style="display: none; margin-top: 10px;"></div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let todasRevisoes = <?= json_encode($todas_revisoes) ?>;
    let materiasList = <?= json_encode($materias_com_id) ?>; // Agora inclui IDs
    const coresDisponiveis = <?= json_encode($cores_disponiveis) ?>;
    
    // Mapeamento de nome da matéria para sua cor
    let coresMateriasMap = {};
    function updateCoresMateriasMap() {
        materiasList.forEach((materia, i) => {
            coresMateriasMap[materia.nome] = coresDisponiveis[i % coresDisponiveis.length];
        });
    }
    updateCoresMateriasMap();

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
    const materiasListContainer = document.getElementById('materias-list-container'); // Container para a lista de tags

    // Novos elementos para o botão e área de input de adicionar tag
    const addTagDisplayBtn = document.createElement('button');
    addTagDisplayBtn.type = 'button';
    addTagDisplayBtn.id = 'add-tag-display-btn';
    addTagDisplayBtn.className = 'add-tag-button';
    addTagDisplayBtn.textContent = 'Adicionar Nova Matéria';

    const newTagAddArea = document.createElement('div');
    newTagAddArea.id = 'new-tag-add-area';
    newTagAddArea.style.display = 'none'; // Escondido por padrão
    newTagAddArea.innerHTML = `
        <input type="text" id="new-tag-name-input" placeholder="Nome da Nova Matéria">
        <div class="add-tag-action-buttons">
            <button type="button" id="add-tag-confirm-btn">Salvar Matéria</button>
            <button type="button" id="cancel-add-tag-btn" class="cancel-btn">Cancelar</button>
        </div>
        <div id="add-tag-message" class="msg" style="display: none;"></div>
    `;

    // Acessando os novos botões e input dentro da área de adição
    const newTagNameInput = newTagAddArea.querySelector('#new-tag-name-input');
    const addTagConfirmBtn = newTagAddArea.querySelector('#add-tag-confirm-btn');
    const cancelAddTagBtn = newTagAddArea.querySelector('#cancel-add-tag-btn');
    const addTagMessage = newTagAddArea.querySelector('#add-tag-message');


    // Elementos do Modal de Edição
    const editTagModal = document.getElementById('edit-tag-modal');
    const editTagIdInput = document.getElementById('edit-tag-id');
    const editTagNameInput = document.getElementById('edit-tag-name-input');
    const saveEditedTagBtn = document.getElementById('save-edited-tag-btn');
    const cancelEditTagBtn = document.getElementById('cancel-edit-tag-btn');
    const editTagMessage = document.getElementById('edit-tag-message');


    let currentPeriod = 'today';
    let currentTag = 'all';

    // ################### FUNÇÕES DE RENDERIZAÇÃO ###################

    function renderMateriasList() {
        materiasListContainer.innerHTML = ''; // Limpa a lista existente
        tagSelect.innerHTML = '<option value="">Nenhuma</option>'; // Limpa e adiciona opção padrão no select de assunto
        
        // Adiciona a opção "Todas as Matérias" no topo da lista de filtros
        const allTagsLink = document.createElement('a');
        allTagsLink.className = 'tag-filter-btn';
        if (currentTag === 'all') allTagsLink.classList.add('active'); // Ativa se for o filtro atual
        allTagsLink.dataset.filter = 'all';
        allTagsLink.textContent = 'Todas as Matérias';
        materiasListContainer.appendChild(allTagsLink);

        // Adiciona o botão "Adicionar Nova Matéria"
        materiasListContainer.appendChild(addTagDisplayBtn);
        // Adiciona a área de input de nova matéria (escondida)
        materiasListContainer.appendChild(newTagAddArea);


        // Preenche a lista de filtros e o select de adicionar assunto
        materiasList.forEach(materia => {
            const tagColor = coresMateriasMap[materia.nome] || 'var(--text-dark)';

            // Adiciona à lista de filtros de matéria
            const tagLink = document.createElement('a');
            tagLink.className = 'tag-filter-btn';
            if (currentTag === materia.nome) tagLink.classList.add('active'); // Ativa se for o filtro atual
            tagLink.dataset.filter = materia.nome;
            tagLink.dataset.tagId = materia.id; // Adiciona o ID da tag

            tagLink.innerHTML = `
                <div class="tag-name-wrapper">
                    <span class="color-dot" style="background-color: ${tagColor};"></span>
                    <span>${materia.nome}</span>
                </div>
                <div class="tag-actions">
                    <button class="tag-action-btn edit-tag-btn" data-tag-id="${materia.id}" data-tag-name="${materia.nome}" title="Editar Matéria">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="tag-action-btn delete-tag-btn" data-tag-id="${materia.id}" data-tag-name="${materia.nome}" title="Apagar Matéria">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            materiasListContainer.appendChild(tagLink);

            // Adiciona ao select de adicionar assunto
            const option = document.createElement('option');
            option.value = materia.nome;
            option.textContent = materia.nome;
            tagSelect.appendChild(option);
        });

        // Adiciona a opção "--new--" no select de adicionar assunto
        const newTagOption = document.createElement('option');
        newTagOption.value = '--new--';
        newTagOption.textContent = '(Adicionar Nova Tag)';
        newTagOption.style.color = '#C04000';
        newTagOption.style.fontWeight = 'bold';
        tagSelect.appendChild(newTagOption);

        // Reativa os event listeners para os botões de filtro e CRUD
        activateFilterButtons();
        activateTagCrudButtons();
    }


    function renderTasks() {
        const todayLocal = new Date(); 
        todayLocal.setHours(0, 0, 0, 0); // Midnight in client's local timezone
        const todayLocalFormattedYYYYMMDD = 
            `${todayLocal.getFullYear()}-${(todayLocal.getMonth() + 1).toString().padStart(2, '0')}-${todayLocal.getDate().toString().padStart(2, '0')}`;

        pendingTasksContainer.innerHTML = ''; 
        completedTasksContainer.innerHTML = '';

        const filteredRevisoes = todasRevisoes.filter(rev => {
            let periodMatch = false;

            if (currentPeriod === 'today') {
                if (rev.data_revisao === todayLocalFormattedYYYYMMDD) { 
                    periodMatch = true; 
                }
            } 
            else if (currentPeriod === 'week') { 
                const revDateObjForComparison = new Date(rev.data_revisao + 'T00:00:00'); // Assumes local timezone for comparison
                const nextWeek = new Date(todayLocal); // Copy today's local midnight
                nextWeek.setDate(todayLocal.getDate() + 7); // Add 7 days

                if (revDateObjForComparison >= todayLocal && revDateObjForComparison <= nextWeek) { 
                    periodMatch = true; 
                }
            } 
            else if (currentPeriod === 'all') { 
                periodMatch = true; 
            }
            
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
            const revDate = new Date(rev.data_revisao + 'T00:00:00-03:00'); // Usado para formatação e comparação <= today para checkbox
            const dataFormatada = revDate.toLocaleDateString('pt-BR', {day: '2-digit', month: 'short'});
            const corBorda = rev.tag_nome ? (coresMateriasMap[rev.tag_nome] || 'var(--text-dark)') : 'var(--text-dark)';
            let checkboxHtml = '';
            if (revDate <= todayLocal) { // Comparação com todayLocal (meia-noite local)
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
                    <div class="task-menu-dropdown"><a href="#" data-action="edit">Editar Assunto</a><a href="#" data-action="delete" class="delete">Apagar Assunto</a></div>
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

    // ################### FUNÇÕES DE ATIVAÇÃO DE LISTENERS ###################

    function activateFilterButtons() {
        document.querySelectorAll('.time-filter-btn').forEach(button => {
            button.removeEventListener('click', handleTimeFilterClick); // Remove para evitar duplicidade
            button.addEventListener('click', handleTimeFilterClick);
        });
        // IMPORTANTE: Adiciona listeners apenas aos botões de filtro de tags, não aos botões de CRUD
        document.querySelectorAll('.materias-list > .tag-filter-btn').forEach(button => {
            button.removeEventListener('click', handleTagFilterClick); // Remove para evitar duplicidade
            button.addEventListener('click', handleTagFilterClick);
        });
    }

    function activateTagCrudButtons() {
        // Event listeners para botões de CRUD de tags
        document.querySelectorAll('.edit-tag-btn').forEach(button => {
            button.removeEventListener('click', handleEditTagClick);
            button.addEventListener('click', handleEditTagClick);
        });
        document.querySelectorAll('.delete-tag-btn').forEach(button => {
            button.removeEventListener('click', handleDeleteTagClick);
            button.addEventListener('click', handleDeleteTagClick);
        });
    }

    // ################### FUNÇÕES DE MANUSEIO DE EVENTOS ###################

    function handleTimeFilterClick(e) {
        e.preventDefault();
        document.querySelectorAll('.time-filter-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        currentPeriod = this.dataset.period;
        mainViewTitle.textContent = this.textContent;
        renderTasks();
    }

    function handleTagFilterClick(e) {
        e.preventDefault();
        // Não aplica 'active' se o clique for nos botões de CRUD
        if (e.target.closest('.tag-action-btn')) {
            return;
        }
        document.querySelectorAll('.materias-list > .tag-filter-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        currentTag = this.dataset.filter;
        renderTasks();
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
                newTagInput.value = ''; // Limpa o campo da nova tag
                tagSelect.style.display = 'block';
                tagSelect.value = ""; // Reseta o select
                // Recarrega os dados e re-renderiza tudo para incluir a nova tag
                fetchDashboardDataAndRender().then(() => {
                    // Após renderizar, verificar se o filtro "Hoje" está ativo
                    if (currentPeriod === 'today') {
                        // O filtro já deveria aplicar-se automaticamente
                        // Apenas para depuração visual, se necessário:
                        console.log("Novo assunto adicionado. Se o filtro for 'Hoje', ele deve aparecer.");
                    }
                }); 
                // Pequeno atraso para o usuário ler a mensagem de sucesso
                setTimeout(() => { formMessage.style.display = 'none'; }, 2000); 

            } else {
                 setTimeout(() => { formMessage.style.display = 'none'; }, 3000); // Esconde a mensagem de erro
            }
        });
    });

    // ################### LÓGICA DE CRUD DE TAGS (JS) ###################

    // Listener para o novo botão que exibe a área de adição
    addTagDisplayBtn.addEventListener('click', function() {
        addTagDisplayBtn.style.display = 'none'; // Esconde o botão "Adicionar Nova Matéria"
        newTagAddArea.style.display = 'flex'; // Mostra a área de input
        newTagNameInput.focus(); // Foca no campo de input
    });

    // Listener para o botão "Salvar Matéria" (confirmar adição)
    addTagConfirmBtn.addEventListener('click', function() {
        const nomeTag = newTagNameInput.value.trim();
        if (nomeTag) {
            const formData = new FormData();
            formData.append('action', 'add_tag');
            formData.append('nome_tag', nomeTag);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                displayMessage(addTagMessage, data.message, data.success);
                if (data.success) {
                    newTagNameInput.value = ''; // Limpa o input
                    // Esconde a área de input e mostra o botão novamente após sucesso
                    newTagAddArea.style.display = 'none';
                    addTagDisplayBtn.style.display = 'block';
                    fetchDashboardDataAndRender(); // Recarrega todas as tags e revisões
                }
            }).catch(error => {
                displayMessage(addTagMessage, 'Erro ao adicionar tag.', false);
                console.error('Erro:', error);
            });
        } else {
            displayMessage(addTagMessage, 'Por favor, digite o nome da matéria.', false);
        }
    });

    // Listener para o botão "Cancelar" (adição de nova matéria)
    cancelAddTagBtn.addEventListener('click', function() {
        newTagNameInput.value = ''; // Limpa o input
        newTagAddArea.style.display = 'none'; // Esconde a área de input
        addTagDisplayBtn.style.display = 'block'; // Mostra o botão "Adicionar Nova Matéria"
        addTagMessage.style.display = 'none'; // Esconde a mensagem
    });


    function handleEditTagClick(e) {
        e.stopPropagation(); // Evita que o clique se propague para o filtro de tag
        const tagId = this.dataset.tagId;
        const tagName = this.dataset.tagName;
        editTagIdInput.value = tagId;
        editTagNameInput.value = tagName;
        editTagModal.style.display = 'flex'; // Mostra o modal
        editTagMessage.style.display = 'none'; // Limpa mensagens antigas
    }

    saveEditedTagBtn.addEventListener('click', function() {
        const tagId = editTagIdInput.value;
        const novoNomeTag = editTagNameInput.value.trim();
        if (novoNomeTag) {
            const formData = new FormData();
            formData.append('action', 'edit_tag');
            formData.append('tag_id', tagId);
            formData.append('novo_nome_tag', novoNomeTag);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                displayMessage(editTagMessage, data.message, data.success);
                if (data.success) {
                    // Melhor recarregar tudo para garantir consistência após edição de nome de tag
                    setTimeout(() => { 
                        editTagModal.style.display = 'none';
                        fetchDashboardDataAndRender(); 
                    }, 1500);
                }
            }).catch(error => {
                displayMessage(editTagMessage, 'Erro ao salvar tag.', false);
                console.error('Erro:', error);
            });
        } else {
            displayMessage(editTagMessage, 'O nome da matéria não pode ser vazio.', false);
        }
    });

    cancelEditTagBtn.addEventListener('click', function() {
        editTagModal.style.display = 'none';
        editTagMessage.style.display = 'none'; // Esconde mensagem ao cancelar
    });

    function handleDeleteTagClick(e) {
        e.stopPropagation(); // Evita que o clique se propague para o filtro de tag
        const tagId = this.dataset.tagId;
        const tagName = this.dataset.tagName;
        // Substituído alert/confirm por uma mensagem modal futura ou um design de confirmação mais robusto,
        // mas por enquanto mantido o confirm() para funcionalidade rápida.
        if (confirm(`Tem certeza que deseja apagar a matéria "${tagName}"? Isso removerá a associação desta matéria de TODOS os seus assuntos e, se a matéria não for usada por mais ninguém, ela será excluída permanentemente.`)) {
            const formData = new FormData();
            formData.append('action', 'delete_tag');
            formData.append('tag_id', tagId);
            fetch('dashboard.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                displayMessage(document.getElementById('add-tag-message') || document.querySelector('.manage-tags-section'), data.message, data.success);
                if (data.success) {
                    fetchDashboardDataAndRender(); // Recarrega todas as tags e revisões
                }
            }).catch(error => {
                displayMessage(document.getElementById('add-tag-message') || document.querySelector('.manage-tags-section'), 'Erro ao apagar tag.', false);
                console.error('Erro:', error);
            });
        }
    }

    // Função para exibir mensagens na UI (usada para adicionar/editar/apagar tags)
    // Agora aceita um container específico para a mensagem.
    function displayMessage(container, message, isSuccess) {
        container.textContent = message;
        container.className = 'msg ' + (isSuccess ? 'success' : 'error');
        container.style.display = 'block';
        setTimeout(() => { container.style.display = 'none'; }, 3000);
    }

    // Função para buscar todos os dados atualizados e re-renderizar
    async function fetchDashboardDataAndRender() {
        const response = await fetch('dashboard.php'); // Busca a página novamente para obter os dados PHP atualizados
        const text = await response.text();
        // Parseia os dados JSON embutidos no HTML
        const newTodasRevisoesMatch = text.match(/let todasRevisoes = (\[.*?\]);/s);
        const newMateriasListMatch = text.match(/let materiasList = (\[.*?\]);/s);

        if (newTodasRevisoesMatch && newMateriasListMatch) {
            todasRevisoes = JSON.parse(newTodasRevisoesMatch[1]);
            materiasList = JSON.parse(newMateriasListMatch[1]);
            updateCoresMateriasMap(); // Atualiza o mapeamento de cores
            renderMateriasList(); // Re-renderiza a lista de tags
            renderTasks(); // Re-renderiza as tarefas
        } else {
            console.error('Falha ao parsear novos dados do dashboard.');
            // Se falhar, uma recarga completa da página pode ser necessária ou um tratamento de erro mais robusto
            // window.location.reload(); 
        }
    }


    // Inicialização
    renderMateriasList(); // Renderiza a lista de matérias na carga da página
    renderTasks(); // Renderiza as tarefas na carga da página
});
</script>
</body>
</html>