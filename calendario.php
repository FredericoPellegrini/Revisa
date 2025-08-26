<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$primeiro_dia_timestamp = mktime(0, 0, 0, $mes, 1, $ano);
$dias_no_mes = date('t', $primeiro_dia_timestamp);
$dia_semana_comeca = date('w', $primeiro_dia_timestamp);
$mes_anterior = $mes - 1;
$ano_anterior = $ano;
if ($mes_anterior == 0) {
    $mes_anterior = 12;
    $ano_anterior = $ano - 1;
}
$mes_proximo = $mes + 1;
$ano_proximo = $ano;
if ($mes_proximo == 13) {
    $mes_proximo = 1;
    $ano_proximo = $ano + 1;
}
setlocale(LC_TIME, 'pt_BR.utf-8', 'pt_BR', 'portuguese');
$nome_mes = strftime('%B', $primeiro_dia_timestamp);
$hoje = date('Y-m-d');

$sql_revisoes = "SELECT r.data_revisao, r.feita, a.titulo, t.nome as tag_nome 
                 FROM revisoes r 
                 JOIN assuntos a ON r.assunto_id = a.id 
                 LEFT JOIN assunto_tag at ON a.id = at.assunto_id 
                 LEFT JOIN tags t ON at.tag_id = t.id 
                 WHERE a.user_id = ? AND MONTH(r.data_revisao) = ? AND YEAR(r.data_revisao) = ?";
$stmt = $pdo->prepare($sql_revisoes);
$stmt->execute([$usuario_id, $mes, $ano]);
$revisoes_do_mes = $stmt->fetchAll();
$eventos_por_dia = [];
foreach ($revisoes_do_mes as $revisao) {
    $dia = date('j', strtotime($revisao['data_revisao']));
    $eventos_por_dia[$dia][] = $revisao;
}

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
    <link rel="stylesheet" href="css/calendario.css">
    <title>Calendário - Revisa</title>
</head>
<body>
    <div class="main-grid">
        <aside class="nav-icons">
            <div class="nav-icons-top">
                <a href="dashboard.php" title="Dashboard">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </a>
                <a href="#" class="active" title="Calendário">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </a>
                 <a href="perfil.php" title="Perfil">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                </a>
                <a href="busca.php" title="Busca">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                </a>
            </div>
            <div class="nav-icons-bottom">
                <a href="logout.php" title="Sair">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l-3-3m0 0l-3-3m3 3H9" /></svg>
                </a>
            </div>
        </aside>

        <main class="calendar-container">
            <header class="calendar-header">
                <a class="nav-arrow" href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>">&lt;</a>
                <h1><?= htmlspecialchars(ucfirst($nome_mes)) . ' ' . $ano ?></h1>
                <a class="nav-arrow" href="?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?>">&gt;</a>
            </header>

            <div class="calendar-grid">
                <div class="weekday">D</div><div class="weekday">S</div><div class="weekday">T</div><div class="weekday">Q</div><div class="weekday">Q</div><div class="weekday">S</div><div class="weekday">S</div>

                <?php
                for ($i = 0; $i < $dia_semana_comeca; $i++) { echo '<div></div>'; }

                for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
                    // DESTAQUE: Lógica para adicionar a classe 'current-day'
                    $classes_celula = 'day-cell';
                    if ($dia == date('j') && $mes == date('n') && $ano == date('Y')) {
                        $classes_celula .= ' current-day';
                    }
                    
                    echo '<div class="' . $classes_celula . '">';
                    echo '<span class="day-number">' . $dia . '</span>';
                    
                    if (isset($eventos_por_dia[$dia])) {
                        foreach ($eventos_por_dia[$dia] as $evento) {
                            $cor_fundo = '#555';
                            $cor_texto = 'var(--bg-darkest)';
                            $estilo_extra = ''; 

                            if ($evento['feita'] == 1 || ($evento['data_revisao'] < $hoje && $evento['feita'] == 0)) {
                                $cor_fundo = '#000000';
                                $cor_texto = '#FFFFFF';
                                $estilo_extra = 'border: 1px solid #FFFFFF; text-decoration: line-through;';
                            } else {
                                $cor_fundo = isset($evento['tag_nome']) ? ($cores_materias[$evento['tag_nome']] ?? '#555') : '#555';
                            }
                            
                            echo '<div class="event-pill" style="background-color:' . $cor_fundo . '; color:' . $cor_texto . ';' . $estilo_extra . '" title="' . htmlspecialchars($evento['titulo']) . '">' 
                                 . htmlspecialchars($evento['titulo']) 
                                 . '</div>';
                        }
                    }
                    echo '</div>';
                }

                $total_celulas = $dia_semana_comeca + $dias_no_mes;
                $celulas_finais = (7 - ($total_celulas % 7)) % 7;
                for ($i = 0; $i < $celulas_finais; $i++) { echo '<div></div>'; }
                ?>
            </div>
        </main>
    </div>
</body>
</html>