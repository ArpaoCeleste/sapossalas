<?php
session_start();
require_once 'backend/config.php';
require_once 'backend/verificar_conclusao.php';
$rootPath = './';
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $t = [];
}

if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$messages = [];
$defaultAvatar = 'imagens/default.png';

$sql_user = "SELECT id, nome, email, datanascimento, imagem_perfil, role, telefone, genero, two_fa_enabled FROM utilizadores WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user_data) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$user_email_db = $user_data['email'];
$user_nome = $user_data['nome'] ?? '';
$user_data_nasc = $user_data['datanascimento'] ?? '';
$user_imagem = !empty($user_data['imagem_perfil']) ? $user_data['imagem_perfil'] : $defaultAvatar;
$display_data_nasc = $user_data_nasc;

$is_admin = ($user_data['role'] === 'admin');


$reservas = [];
$total_pages = 0;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$reservas_per_page = 10;
$offset = ($current_page - 1) * $reservas_per_page;

if (!$is_admin) {

    $sql_count = "SELECT COUNT(*) as total FROM reservas WHERE user_id = ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $total_reservas = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

    $total_pages = ceil($total_reservas / $reservas_per_page);


    $sql_reservas = "SELECT r.id, r.room_id, r.data, r.hora_inicio, r.hora_fim, r.descricao, r.status_reserva, rooms.nome AS sala_nome
                  FROM reservas r 
                  JOIN rooms ON r.room_id = rooms.id 
                  WHERE r.user_id = ? 
                  ORDER BY r.data DESC, r.hora_inicio DESC
                  LIMIT ? OFFSET ?";
    $stmt_res = $conn->prepare($sql_reservas);
    $stmt_res->bind_param("iii", $user_id, $reservas_per_page, $offset);
    $stmt_res->execute();
    $reservas_result = $stmt_res->get_result();

    while ($r = $reservas_result->fetch_assoc()) {
        $reservas[] = $r;
    }
    $stmt_res->close();
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($t['dash_title']) ? $t['dash_title'] : 'Dashboard - Perfil'; ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <style>
        .section-hidden {
            display: none !important;
        }

        .aside-nav a {
            transition: all 0.25s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 font-['Poppins'] transition-colors duration-300">

    <?php include 'includes/navbar.php'; ?>

    <div class="flex min-h-screen pt-5 relative w-full">

        <div id="mobile-overlay" onclick="toggleSidebar()"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden transition-opacity duration-300"></div>

        <aside id="sidebar"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-[#151414] h-screen overflow-y-auto transition-transform duration-300 transform -translate-x-full lg:translate-x-0 lg:static lg:block shadow-2xl lg:shadow-none lg:min-h-screen">

            <div class="p-6 border-b border-gray-800 flex justify-between items-center">
                <h2 class="text-xl text-[#fff]">
                    <?php
                    if ($is_admin) {
                        echo isset($t['admin_sidebar_title_personal']) ? $t['admin_sidebar_title_personal'] : 'Painel do Administrador';
                    } else {
                        echo isset($t['dash_sidebar_title']) ? $t['dash_sidebar_title'] : 'Painel do Utilizador';
                    }
                    ?>
                </h2>
                <button class="lg:hidden text-white focus:outline-none" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="p-6 space-y-4 flex flex-col aside-nav">
                <?php if (!$is_admin): ?>
                    <a href="#reservas" onclick="toggleSidebar()"
                        class="flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 transition duration-1000">
                        <i
                            class="fas fa-calendar-check mr-3"></i><?php echo isset($t['dash_tab_reservations']) ? $t['dash_tab_reservations'] : 'As Minhas Reservas'; ?>
                    </a>
                <?php endif; ?>

                <a href="#definicoes" onclick="toggleSidebar()"
                    class="flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 transition duration-1000">
                    <i class="fas fa-cog mr-3"></i>
                    <?php echo isset($t['dash_tab_settings']) ? $t['dash_tab_settings'] : 'Definições do Perfil'; ?>
                </a>
                <a onclick="confirmLogout()"
                    class="flex items-center px-4 py-2 rounded text-red-400 font-medium hover:bg-red-900/50 hover:scale-110 transition duration-1000 cursor-pointer">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <?php echo isset($t['dash_tab_logout']) ? $t['dash_tab_logout'] : 'Terminar Sessão'; ?>
                </a>
            </nav>
        </aside>

        <main
            class="flex-1 flex flex-col lg:ml-0 transition-all duration-300 w-full min-h-screen bg-gray-100 dark:bg-gray-900">

            <div class="flex-1 p-4 sm:p-8">

                <button onclick="toggleSidebar()"
                    class="lg:hidden mb-6 px-4 py-2 bg-[#151414] text-white rounded-lg shadow-md hover:bg-[#5d3e8f] transition focus:outline-none flex items-center">
                    <i class="fas fa-bars mr-2"></i>
                    <?php echo isset($t['dash_btn_menu']) ? $t['dash_btn_menu'] : 'Menu'; ?>
                </button>

                <?php foreach ($messages as $m): ?>
                    <div
                        class="p-4 mb-4 rounded-lg <?php echo $m['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                        <?php echo htmlspecialchars($m['text']); ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$is_admin): ?>
                    <section id="reservas"
                        class="bg-white dark:bg-gray-800 p-6 rounded-xl  mb-8 section-visible max-w-6xl mx-auto transition-colors duration-300">
                        <h2 class="text-2xl font-bold mb-6 text-[#5d3e8f] dark:text-[#d8b4fe]">
                            <?php echo isset($t['res_title']) ? $t['res_title'] : 'Histórico e Próximas Reservas'; ?>
                            (<?php echo count($reservas); ?>)
                        </h2>

                        <?php if (count($reservas) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['res_th_room']) ? $t['res_th_room'] : 'Sala'; ?></th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['res_th_date']) ? $t['res_th_date'] : 'Data'; ?></th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['res_th_time']) ? $t['res_th_time'] : 'Horário'; ?></th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['res_th_desc']) ? $t['res_th_desc'] : 'Descrição'; ?></th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['res_th_status']) ? $t['res_th_status'] : 'Status'; ?></th>
                                            <th
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <?php echo isset($t['th_details']) ? $t['th_details'] : 'Detalhes'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($reservas as $r):

                                            $timestamp_fim = strtotime($r['data'] . ' ' . $r['hora_fim']);
                                            $is_past = $timestamp_fim < time();
                                            $status_db = $r['status_reserva'] ?? 'ativa';

                                            $status_class = '';
                                            $status_text = '';

                                            if ($status_db === 'cancelada') {
                                                $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                $status_text = $t['res_status_cancelled'] ?? 'Cancelada';
                                            } elseif ($status_db === 'concluida' || $is_past) {

                                                $status_class = 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
                                                $status_text = $t['res_status_completed'] ?? 'Concluída';
                                            } else {

                                                $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                $status_text = $t['res_status_active'] ?? 'Ativa';
                                            }


                                            $reserva_js = [
                                                'id' => $r['id'] ?? 0,
                                                'sala' => $r['sala_nome'],
                                                'salaId' => $r['room_id'],
                                                'data_raw' => $r['data'],
                                                'inicio_raw' => $r['hora_inicio'],
                                                'fim_raw' => $r['hora_fim'],
                                                'data' => date('d/m/Y', strtotime($r['data'])),
                                                'horario' => substr($r['hora_inicio'], 0, 5) . ' - ' . substr($r['hora_fim'], 0, 5),
                                                'descricao' => $r['descricao'] ?? ''
                                            ];
                                            $json_data = htmlspecialchars(json_encode($reserva_js), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    <?php echo htmlspecialchars($r['sala_nome']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('d/m/Y', strtotime($r['data'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo substr($r['hora_inicio'], 0, 5) . ' - ' . substr($r['hora_fim'], 0, 5); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate"
                                                    title="<?php echo htmlspecialchars($r['descricao'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($r['descricao'] ?? '—'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                    <button onclick='showUserReservationDetails(<?php echo $json_data; ?>)'
                                                        class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-indigo-900 dark:hover:text-white mr-3"
                                                        title="Ver Detalhes">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>

                                                    <?php if (!$is_past && $status_db === 'ativa'): ?>
                                                        <button onclick='editReservation(<?php echo $json_data; ?>)'
                                                            class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-indigo-900 dark:hover:text-white mr-3"
                                                            title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        <button onclick='cancelReservation(<?php echo $r['id']; ?>)'
                                                            class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-indigo-900 dark:hover:text-white mr-3"
                                                            title="Cancelar">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div class="flex justify-center items-center space-x-2 mt-6">
                                    <?php if ($current_page > 1): ?>
                                        <a href="userdashboard.php?page=<?php echo $current_page - 1; ?>&goto=reservas"
                                            class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                            <i class="fas fa-chevron-left text-xs"></i> <?php echo $t['btn_previous'] ?? 'Anterior'; ?>
                                        </a>
                                    <?php endif; ?>

                                    <span
                                        class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] rounded-lg font-semibold text-sm">
                                        <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                                    </span>

                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="userdashboard.php?page=<?php echo $current_page + 1; ?>&goto=reservas"
                                            class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                            <?php echo $t['btn_next'] ?? 'Próxima'; ?> <i class="fas fa-chevron-right text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-10">
                                <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <p class="text-gray-600 dark:text-gray-400 font-medium">
                                    <?php echo isset($t['res_empty_title']) ? $t['res_empty_title'] : 'Ainda não tens nenhuma reserva registada.'; ?>
                                </p>
                                <a href="reservar.php"
                                    class="mt-4 inline-block text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">
                                    <?php echo isset($t['res_empty_link']) ? $t['res_empty_link'] : 'Fazer uma nova reserva'; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section id="definicoes"
                    class="<?php echo $is_admin ? 'section-visible' : 'section-hidden'; ?> flex flex-col items-center justify-start space-y-8 w-full max-w-6xl mx-auto">
                    <div id="definicoesView"
                        class="w-full max-w-5xl bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mx-auto transition-colors duration-300">
                        <div class="grid grid-cols-1 md:grid-cols-3">
                            <div class="bg-[#151414] text-[#fbeaff] p-6 flex flex-col items-center relative">
                                <img src="<?php echo htmlspecialchars($user_imagem); ?>" alt="Foto de perfil"
                                    class="w-32 h-32 rounded-full object-cover border-4 border-[#5d3e8f] dark:border-[#d8b4fe] shadow-md mx-auto p-1 mb-3"
                                    onerror="this.onerror=null; this.src='<?php echo $defaultAvatar; ?>';">

                                <h2 class="text-xl font-bold text-center mb-1">
                                    <?php echo htmlspecialchars($user_nome); ?>
                                </h2>
                                <p class="text-sm text-gray-400">
                                    <?php
                                    $roleKey = 'role_' . $user_data['role'];
                                    echo isset($t[$roleKey]) ? $t[$roleKey] : ($user_data['role'] == 'admin' ? 'Administrador' : 'Professor');
                                    ?>
                                </p>

                                <a href="#" id="btnEditarInterno"
                                    class="mt-4 px-4 py-1 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-[#fbeaff] rounded-full text-sm transition">
                                    <i class="fas fa-edit mr-2"></i>
                                    <?php echo isset($t['prof_btn_edit']) ? $t['prof_btn_edit'] : 'Editar Perfil'; ?>
                                </a>
                            </div>

                            <div class="md:col-span-2 p-6 space-y-4 text-sm text-gray-800 dark:text-gray-200">
                                <h3
                                    class="text-lg font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-2 border-b dark:border-gray-600 pb-2">
                                    <?php echo isset($t['prof_info_title']) ? $t['prof_info_title'] : 'Informações Pessoais'; ?>
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div><span
                                            class="block font-semibold text-gray-700 dark:text-gray-400"><?php echo isset($t['label_name']) ? $t['label_name'] : 'Nome'; ?></span><span><?php echo htmlspecialchars($user_nome); ?></span>
                                    </div>
                                    <div><span
                                            class="block font-semibold text-gray-700 dark:text-gray-400"><?php echo isset($t['label_email']) ? $t['label_email'] : 'Email'; ?></span><span><?php echo htmlspecialchars($user_email_db); ?></span>
                                    </div>
                                    <div><span
                                            class="block font-semibold text-gray-700 dark:text-gray-400"><?php echo isset($t['label_dob']) ? $t['label_dob'] : 'Data de Nascimento'; ?></span><span><?php echo !empty($user_data_nasc) ? date('d/m/Y', strtotime($user_data_nasc)) : '—'; ?></span>
                                    </div>
                                    <div>
                                        <span
                                            class="block font-semibold text-gray-700 dark:text-gray-400"><?php echo isset($t['label_gender']) ? $t['label_gender'] : 'Género'; ?></span>
                                        <span>
                                            <?php
                                            $g = $user_data['genero'] ?? 'O';
                                            if ($g === 'M')
                                                echo isset($t['gender_male']) ? $t['gender_male'] : 'Homem';
                                            elseif ($g === 'F')
                                                echo isset($t['gender_female']) ? $t['gender_female'] : 'Mulher';
                                            else
                                                echo isset($t['gender_other']) ? $t['gender_other'] : 'Outro';
                                            ?>
                                        </span>
                                    </div>
                                    <div><span
                                            class="block font-semibold text-gray-700 dark:text-gray-400"><?php echo isset($t['label_phone']) ? $t['label_phone'] : 'Telefone'; ?></span><span><?php echo htmlspecialchars($user_data['telefone'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="definicoesEdit" class="section-hidden w-full max-w-5xl mx-auto">
                        <form id="updateForm" method="POST" action="backend/update_user.php"
                            enctype="multipart/form-data"
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden w-full transition-colors duration-300">

                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="grid grid-cols-1 md:grid-cols-3">
                                <div class="bg-[#151414] text-[#fbeaff] p-6 flex flex-col items-center">

                                    <div class="relative group cursor-pointer">
                                        <img src="<?php echo htmlspecialchars($user_imagem); ?>" id="imgPreviewMain"
                                            alt="Foto de perfil"
                                            class="w-32 h-32 rounded-full object-cover border-4 border-[#5d3e8f] dark:border-[#d8b4fe] shadow-md mx-auto p-1 mb-3 transition-transform hover:scale-110 transition duration-1000"
                                            onerror="this.onerror=null; this.src='<?php echo $defaultAvatar; ?>';">

                                        <div
                                            class="hidden group-hover:flex flex-col items-center justify-center absolute left-1/2 transform -translate-x-1/2 top-full z-50 p-2 rounded-xl w-48 mt-12 hover:scale-110 transition duration-1000">
                                            <img src="<?php echo htmlspecialchars($user_imagem); ?>"
                                                id="imgPreviewHover" alt="Preview"
                                                class="w-full h-auto rounded-lg object-cover"
                                                onerror="this.onerror=null; this.src='<?php echo $defaultAvatar; ?>';">
                                        </div>
                                    </div>

                                    <label for="imagem_perfil"
                                        class="inline-block bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-[#fbeaff] font-semibold px-4 py-1 rounded-full cursor-pointer hover:scale-110 transition duration-1000 mt-2">
                                        <i class="fas fa-camera mr-2"></i>
                                        <?php echo isset($t['edit_photo_btn']) ? $t['edit_photo_btn'] : 'Alterar Foto'; ?>
                                        <input type="file" id="imagem_perfil" name="imagem_perfil" accept="image/*"
                                            class="hidden">
                                    </label>
                                </div>

                                <div class="md:col-span-2 p-6 space-y-4">
                                    <h3
                                        class="text-lg font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4 border-b dark:border-gray-600 pb-2">
                                        <?php echo isset($t['edit_title']) ? $t['edit_title'] : 'Editar Dados Pessoais'; ?>
                                    </h3>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="nome"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_name']) ? $t['label_name'] : 'Nome'; ?></label>
                                            <input type="text" id="nome" name="nome"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]"
                                                value="<?php echo htmlspecialchars($user_nome); ?>">
                                        </div>
                                        <div>
                                            <label for="email_upd"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_email']) ? $t['label_email'] : 'Email'; ?></label>
                                            <input type="email" id="email_upd" name="email"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]"
                                                value="<?php echo htmlspecialchars($user_email_db); ?>">
                                        </div>
                                        <div>
                                            <label for="datanascimento"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_dob']) ? $t['label_dob'] : 'Data de Nascimento'; ?></label>
                                            <input type="date" id="datanascimento" name="datanascimento"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]"
                                                value="<?php echo htmlspecialchars($user_data_nasc); ?>">
                                        </div>
                                        <div>
                                            <label for="telefone"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_phone']) ? $t['label_phone'] : 'Telefone'; ?></label>
                                            <input type="text" id="telefone" name="telefone"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]"
                                                value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>"
                                                placeholder="Ex: 912345678">
                                        </div>
                                        <div>
                                            <label for="genero"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_gender']) ? $t['label_gender'] : 'Género'; ?></label>
                                            <select id="genero" name="genero"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">
                                                <option value="O" <?php echo ($user_data['genero'] ?? 'O') === 'O' ? 'selected' : ''; ?>>
                                                    <?php echo isset($t['gender_other']) ? $t['gender_other'] : 'Outro'; ?>
                                                </option>
                                                <option value="M" <?php echo ($user_data['genero'] ?? '') === 'M' ? 'selected' : ''; ?>>
                                                    <?php echo isset($t['gender_male']) ? $t['gender_male'] : 'Homem'; ?>
                                                </option>
                                                <option value="F" <?php echo ($user_data['genero'] ?? '') === 'F' ? 'selected' : ''; ?>>
                                                    <?php echo isset($t['gender_female']) ? $t['gender_female'] : 'Mulher'; ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="pt-6 space-y-4 border-t border-[#fbeaff] dark:border-gray-600 mt-4">
                                        <h3 class="text-lg font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-2">
                                            <?php echo isset($t['sec_confirm_title']) ? $t['sec_confirm_title'] : 'Confirmação de Segurança'; ?>
                                        </h3>

                                        <div class="sm:col-span-2 relative">
                                            <label for="current_password"
                                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_current_password']) ? $t['label_current_password'] : 'Password Atual'; ?></label>
                                            <input type="password" id="current_password" name="current_password"
                                                required placeholder="..."
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">
                                            <i class="fas fa-eye absolute right-3 top-8 text-gray-500 dark:text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                                onclick="togglePassword('current_password', this)"></i>
                                        </div>
                                    </div>

                                    <div
                                        class="pt-4 text-center space-x-4 border-t border-gray-100 dark:border-gray-700">
                                        <button type="submit"
                                            class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                            <?php echo isset($t['btn_save_changes']) ? $t['btn_save_changes'] : 'Guardar Alterações'; ?>
                                        </button>
                                        <button type="button" id="btnVoltar"
                                            class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000">
                                            <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>


                    <div
                        class="w-full max-w-5xl bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 mx-auto transition-colors duration-300 mt-8">
                        <h3
                            class="text-lg font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4 border-b dark:border-gray-600 pb-2">
                            <?php echo isset($t['2fa_title']) ? $t['2fa_title'] : 'Autenticação de Dois Fatores (Google Authenticator)'; ?>
                        </h3>

                        <div id="2fa_status_area" class="text-center">
                            <?php if (!empty($user_data['two_fa_enabled']) && $user_data['two_fa_enabled'] == 1): ?>

                                <div id="2fa_active_display">
                                    <div class="mb-6 flex flex-col items-center">
                                        <div
                                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] shadow-sm animate-pulse">
                                            <i class="fas fa-lock text-3xl text-white dark:text-black"></i>
                                        </div>
                                        <h4 class="text-xl font-bold text-gray-800 dark:text-white">
                                            <?php echo isset($t['2fa_status_active']) ? $t['2fa_status_active'] : 'Proteção Ativa'; ?>
                                        </h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            <?php echo isset($t['2fa_msg_active']) ? $t['2fa_msg_active'] : 'A sua conta está protegida com verificação em dois passos.'; ?>
                                        </p>
                                    </div>

                                    <button onclick="startDisable2FA()"
                                        class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white rounded-lg transition shadow-md hover:scale-110 duration-1000">
                                        <i class="fas fa-user-shield mr-2"></i>
                                        <?php echo isset($t['2fa_btn_disable']) ? $t['2fa_btn_disable'] : 'Desativar 2FA'; ?>
                                    </button>
                                </div>

                                <div id="2fa_disable_panel"
                                    class="hidden text-center py-4 bg-gray-50 dark:bg-gray-700/50 p-6 rounded-xl border border-dashed border-gray-300 dark:border-gray-600">
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                                        <?php echo isset($t['2fa_disable_intro']) ? $t['2fa_disable_intro'] : 'Para confirmar a desativação da segurança de 2 passos, precisamos de verificar a sua identidade.'; ?>
                                    </p>

                                    <div id="2fa_disable_start">
                                        <button onclick="sendSecurityCode2FADisable()" id="btnSend2FADisableCode"
                                            class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white rounded-lg hover:scale-110 duration-100 transition shadow-md">
                                            <i class="fas fa-paper-plane mr-2"></i>
                                            <?php echo isset($t['btn_send_code']) ? $t['btn_send_code'] : 'Enviar Código de Segurança'; ?>
                                        </button>
                                        <button onclick="cancelDisable2FA()"
                                            class="ml-2 px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                            <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                        </button>
                                    </div>

                                    <div id="2fa_disable_verify" class="hidden mt-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                            <?php echo isset($t['pass_msg_sent']) ? $t['pass_msg_sent'] : 'Um código foi enviado para'; ?>
                                            <b><?php echo $_SESSION['user_email'] ?? 'email'; ?></b>
                                        </p>

                                        <div class="flex flex-col items-center gap-3">
                                            <input type="text" id="2fa_disable_code_input" placeholder="..."
                                                class="text-center w-64 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">

                                            <div class="flex justify-center gap-4 mt-2">
                                                <button onclick="verifyAndDisable2FA()"
                                                    class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white rounded-lg font-semibold  shadow-md hover:scale-110 transition duration-1000">
                                                    <?php echo isset($t['2fa_btn_confirm_disable']) ? $t['2fa_btn_confirm_disable'] : 'Confirmar e Desativar'; ?>
                                                </button>
                                                <button onclick="cancelDisable2FA()"
                                                    class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                                    <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                                </button>
                                            </div>
                                            <p id="2fa_disable_msg" class="text-sm font-bold mt-2"></p>
                                        </div>
                                    </div>
                                </div>

                            <?php else: ?>

                                <div id="2fa_start_panel">
                                    <div class="mb-4">
                                        <div
                                            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 mb-3 text-gray-400">
                                            <i class="fas fa-shield-alt text-2xl"></i>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">
                                            <?php echo isset($t['2fa_msg_inactive']) ? $t['2fa_msg_inactive'] : 'Adicione uma camada extra de segurança à sua conta exigindo um código do telemóvel ao iniciar sessão.'; ?>
                                        </p>
                                    </div>

                                    <button id="btnSend2FACode" onclick="sendSecurityCode2FA()"
                                        class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white rounded-lg hover:scale-110 duration-100 transition shadow-md">
                                        <i class="fas fa-plus-circle mr-2"></i>
                                        <?php echo isset($t['2fa_btn_config']) ? $t['2fa_btn_config'] : 'Configurar 2FA'; ?>
                                    </button>
                                </div>

                                <div id="2fa_security_verify_panel" class="hidden text-center py-6">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                        <?php echo isset($t['pass_msg_sent']) ? $t['pass_msg_sent'] : 'Um código foi enviado para'; ?>
                                        <b><?php echo $_SESSION['user_email'] ?? 'email'; ?></b></p>

                                    <div class="flex flex-col items-center gap-3">
                                        <input type="text" id="2fa_security_code_input" placeholder="..."
                                            class="text-center w-64 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">

                                        <div class="flex justify-center gap-4 mt-2">
                                            <button onclick="verifySecurityCode2FA()"
                                                class="px-6 py-2 bg-[#5d3e8f] hover:scale-110 transition duration-1000 text-white dark:bg-[#d8b4fe] dark:text-[#151414] font-semibold rounded-lg shadow-md">
                                                <?php echo isset($t['btn_unlock']) ? $t['btn_unlock'] : 'Validar'; ?>
                                            </button>
                                            <button onclick="cancelSecurityCode2FA()"
                                                class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                                <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                            </button>
                                        </div>
                                        <p id="2fa_security_msg" class="text-sm font-bold mt-2"></p>
                                    </div>
                                </div>

                            <?php endif; ?>
                        </div>

                        <div id="2fa_setup_area"
                            class="hidden mt-6 text-center bg-gray-50 dark:bg-gray-700/50 p-6 rounded-xl border border-dashed border-gray-300 dark:border-gray-600">
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                                <?php echo isset($t['2fa_step1']) ? $t['2fa_step1'] : '1. Abra a app <strong>Google Authenticator</strong> no seu telemóvel.'; ?><br>
                                <?php echo isset($t['2fa_step2']) ? $t['2fa_step2'] : '2. Leia o QR Code abaixo.'; ?>
                            </p>

                            <div class="flex justify-center mb-4">
                                <img id="qr_code_img" src="" alt="QR Code"
                                    class="border-4 border-white shadow-lg rounded-lg">
                            </div>

                            <p class="text-xs text-gray-500 mb-4">
                                <?php echo isset($t['2fa_manual']) ? $t['2fa_manual'] : 'Ou insira este código manualmente:'; ?>
                                <span id="secret_text" class="font-mono font-bold"></span>
                            </p>

                            <div class="flex flex-col items-center gap-3">
                                <input type="text" id="verify_code_input" placeholder="000 000" maxlength="6"
                                    class="text-center w-40 border border-gray-300 rounded-lg px-3 py-2 text-xl tracking-widest font-bold focus:ring-[#5d3e8f] outline-none">

                                <button id="btnVerify2FA" onclick="verifyAndEnable2FA()"
                                    class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] rounded-lg text-white dark:hover:bg-[#c084fc] transition shadow-md">
                                    <?php echo isset($t['2fa_btn_validate']) ? $t['2fa_btn_validate'] : 'Validar e Ativar'; ?>
                                </button>
                                <button onclick="cancel2FA()" class="text-sm text-gray-500 underline">
                                    <?php echo isset($t['2fa_btn_cancel']) ? $t['2fa_btn_cancel'] : 'Cancelar'; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="w-full max-w-5xl bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 mx-auto transition-colors duration-300">

                        <h3
                            class="text-lg font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4 border-b dark:border-gray-600 pb-2">
                            <?php echo isset($t['pass_title']) ? $t['pass_title'] : 'Alterar Password'; ?>
                        </h3>

                        <div id="security_start_panel" class="text-center py-8">
                            <i class="fas fa-lock text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                <?php echo isset($t['pass_msg_start']) ? $t['pass_msg_start'] : 'Por motivos de segurança, é necessário verificar a sua identidade antes de alterar a password.'; ?>
                            </p>
                            <button onclick="sendSecurityCodePassword()" id="btnSendCode"
                                class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                <i class="fas fa-paper-plane mr-2"></i>
                                <?php echo isset($t['btn_send_code']) ? $t['btn_send_code'] : 'Enviar Código de Segurança'; ?>
                            </button>
                        </div>

                        <div id="security_verify_panel" class="hidden text-center py-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                <?php echo isset($t['pass_msg_sent']) ? $t['pass_msg_sent'] : 'Um código foi enviado para'; ?>
                                <b><?php echo $_SESSION['user_email'] ?? 'email'; ?></b></p>

                            <div class="flex flex-col items-center gap-3">
                                <input type="text" id="security_code_input" placeholder="..."
                                    class="text-center w-64 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">

                                <div class="flex justify-center gap-4 mt-2">
                                    <button onclick="verifySecurityCodePassword()"
                                        class="px-6 py-2 bg-[#5d3e8f] hover:scale-110 transition duration-1000 text-white dark:bg-[#d8b4fe] dark:text-[#151414] font-semibold rounded-lg shadow-md">
                                        <?php echo isset($t['btn_unlock']) ? $t['btn_unlock'] : 'Desbloquear'; ?>
                                    </button>
                                    <button onclick="cancelSecurityCodePassword()"
                                        class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                        <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                    </button>
                                </div>
                                <p id="security_msg" class="text-sm font-bold mt-2"></p>
                            </div>
                        </div>

                        <form id="passwordForm" method="POST" action="backend/update_user.php" class="hidden mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="update_password">

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                                <div class="relative">
                                    <label for="edit_current_password"
                                        class="block font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_current_password']) ? $t['label_current_password'] : 'Password Atual'; ?></label>
                                    <div class="relative mt-1">
                                        <input type="password" id="edit_current_password" name="edit_current_password"
                                            placeholder="..."
                                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">
                                        <i class="fas fa-eye absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                            onclick="togglePassword('edit_current_password', this)"></i>
                                    </div>
                                </div>

                                <div class="relative">
                                    <label for="new_password"
                                        class="block font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_new_pass']) ? $t['label_new_pass'] : 'Nova Password'; ?></label>
                                    <div class="relative mt-1">
                                        <input type="password" id="new_password" name="new_password" placeholder="..."
                                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">
                                        <i class="fas fa-eye absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                            onclick="togglePassword('new_password', this)"></i>
                                    </div>
                                </div>

                                <div class="relative">
                                    <label for="confirm_password"
                                        class="block font-semibold text-gray-700 dark:text-gray-300"><?php echo isset($t['label_confirm_pass']) ? $t['label_confirm_pass'] : 'Confirmar Nova Password'; ?></label>
                                    <div class="relative mt-1">
                                        <input type="password" id="confirm_password" name="confirm_password"
                                            placeholder="..."
                                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe]">
                                        <i class="fas fa-eye absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                            onclick="togglePassword('confirm_password', this)"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                <?php echo isset($t['pass_requirements']) ? $t['pass_requirements'] : '* Mínimo 8 caracteres, maiúscula, minúscula, número e símbolo.'; ?>
                            </p>

                            <div class="pt-4 text-center">
                                <button type="submit"
                                    class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-semibold rounded-lg transition shadow-md hover:scale-110 transition duration-1000">
                                    <?php echo isset($t['btn_save_pass']) ? $t['btn_save_pass'] : 'Guardar Nova Password'; ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (!$is_admin): ?>
                        <div
                            class="w-full max-w-5xl bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 mx-auto transition-colors duration-300 mt-8">
                            <h3
                                class="text-lg font-semibold text-red-600 dark:text-red-400 mb-4 border-b dark:border-gray-600 pb-2">
                                <?php echo $t['delete_account_title'] ?? 'Eliminar Conta'; ?>
                            </h3>

                            <div id="delete_security_start_panel" class="text-center py-4">
                                <i class="fas fa-lock text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                                <p class="text-gray-600 dark:text-gray-300 mb-4">
                                    <?php echo $t['delete_msg_start'] ?? 'Para confirmar a sua identidade e a eliminação, envie um código para o seu email.'; ?>
                                </p>
                                <button onclick="sendSecurityCodeDelete()" id="btnSendDeleteCode"
                                    class="px-6 py-2 bg-[#5d3e8f] hover:[#5d3e8f] dark:bg-[#d8b4fe] dark:hover:bg-[#d8b4fe] dark:text-[#151414] text-white font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    <?php echo $t['btn_send_code'] ?? 'Enviar Código de Segurança'; ?>
                                </button>
                                <br><br>
                                <div class="text-gray-600 dark:text-gray-400 text-sm ">
                                    <?php echo $t['delete_account_warning'] ?? 'A eliminação da conta é uma ação permanente e não pode ser revertida. É necessário verificar a identidade por email para prosseguir.'; ?>
                                </div>
                            </div>

                            <div id="delete_security_verify_panel" class="hidden text-center py-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                    <?php echo $t['pass_msg_sent'] ?? 'Um código foi enviado para'; ?>
                                    <b><?php echo $_SESSION['user_email'] ?? 'email'; ?></b></p>

                                <form id="deleteForm" method="POST" action="backend/update_user.php" class="space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="execute_delete">

                                    <div class="flex flex-col items-center gap-3">

                                        <input type="text" id="delete_security_code_input" placeholder="..."
                                            class="text-center w-64 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-red-600 dark:focus:ring-red-400">

                                        <div class="flex justify-center gap-4 mt-2">
                                            <button type="submit"
                                                class="w-48 px-6 py-2 bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 text-white font-semibold rounded-lg shadow-md hover:scale-110 transition duration-1000">
                                                <i class="fas fa-trash-alt mr-2"></i>
                                                <?php echo $t['btn_delete_confirm'] ?? 'Confirmar'; ?>
                                            </button>
                                            <button type="button" onclick="cancelSecurityCodeDelete()"
                                                class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg hover:scale-110 transition duration-1000 shadow-md">
                                                <?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : 'Cancelar'; ?>
                                            </button>
                                        </div>
                                        <p id="delete_security_msg" class="text-sm font-bold mt-2"></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

            </div>



        </main>


    </div>
    <div class="mt-auto">
        <?php include_once 'includes/footer.php'; ?>
    </div>
    <?php include_once 'includes/chat_support.php'; ?>

    <script>

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (window.innerWidth < 1024) {
                if (sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                }
            }
        }

        function start2FASetup() {
            Swal.showLoading();

            const formData = new FormData();
            formData.append('action', 'generate_secret');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            fetch('backend/api_2fa.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        document.getElementById('2fa_status_area').classList.add('hidden');
                        document.getElementById('2fa_setup_area').classList.remove('hidden');
                        document.getElementById('qr_code_img').src = data.qr_code_url;
                        document.getElementById('secret_text').innerText = data.secret;
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Erro', 'Falha na ligação.', 'error');
                });
        }

        function verifyAndEnable2FA() {
            const code = document.getElementById('verify_code_input').value;
            const btn = document.getElementById('btnVerify2FA');

            const isDark = document.documentElement.classList.contains('dark');
            const swalBackground = isDark ? '#1f2937' : '#fff';
            const swalColor = isDark ? '#fff' : '#000';

            if (code.length < 6) {
                return Swal.fire({
                    title: '<?php echo isset($t['js_error']) ? $t['js_error'] : "Erro!"; ?>',
                    text: '<?php echo isset($t['js_code_invalid']) ? $t['js_code_invalid'] : "Código inválido."; ?>',
                    icon: 'warning',
                    iconColor: '#5d3e8f',
                    confirmButtonColor: '#5d3e8f',
                    background: swalBackground,
                    color: swalColor
                });
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_validating']) ? $t['js_validating'] : "A validar..."; ?>';

            const formData = new FormData();
            formData.append('action', 'verify_and_enable');
            formData.append('code', code);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            fetch('backend/api_2fa.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;

                    if (data.success) {
                        Swal.fire({
                            title: '<?php echo isset($t['js_success']) ? $t['js_success'] : "Sucesso!"; ?>',
                            text: '<?php echo isset($t['js_2fa_success']) ? $t['js_2fa_success'] : "2FA Ativado com sucesso."; ?>',
                            icon: 'success',
                            iconColor: '#5d3e8f',
                            confirmButtonColor: '#5d3e8f',
                            background: swalBackground,
                            color: swalColor
                        }).then(() => {
                            window.location.href = 'userdashboard.php?status=success&goto=definicoes';
                        });
                    } else {
                        Swal.fire({
                            title: '<?php echo isset($t['js_error']) ? $t['js_error'] : "Erro!"; ?>',
                            text: data.message,
                            icon: 'error',
                            iconColor: '#5d3e8f',
                            confirmButtonColor: '#5d3e8f',
                            background: swalBackground,
                            color: swalColor
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = originalText;

                    Swal.fire({
                        title: '<?php echo isset($t['js_error']) ? $t['js_error'] : "Erro!"; ?>',
                        text: '<?php echo isset($t['js_server_error']) ? $t['js_server_error'] : "Erro de ligação."; ?>',
                        icon: 'error',
                        iconColor: '#5d3e8f',
                        confirmButtonColor: '#5d3e8f',
                        background: swalBackground,
                        color: swalColor
                    });
                });
        }

        function startDisable2FA() {
            document.getElementById('2fa_active_display').classList.add('hidden');
            document.getElementById('2fa_disable_panel').classList.remove('hidden');
        }

        function cancelDisable2FA() {
            document.getElementById('2fa_disable_panel').classList.add('hidden');
            document.getElementById('2fa_active_display').classList.remove('hidden');
            document.getElementById('2fa_disable_start').classList.remove('hidden');
            document.getElementById('2fa_disable_verify').classList.add('hidden');
            document.getElementById('2fa_disable_code_input').value = '';
        }

        function sendSecurityCode2FADisable() {
            const btn = document.getElementById('btnSend2FADisableCode');
            if (!btn) return;

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('context', '2fa_disable');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) formData.append('csrf_token', csrfInput.value);

            fetch('backend/api_security.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('2fa_disable_start').classList.add('hidden');
                        document.getElementById('2fa_disable_verify').classList.remove('hidden');
                    } else {
                        Swal.fire('Erro', data.message || 'Falha ao enviar código', 'error');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Erro', 'Falha na ligação.', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function verifyAndDisable2FA() {
            const code = document.getElementById('2fa_disable_code_input').value;
            const msg = document.getElementById('2fa_disable_msg');

            if (code.length < 4) {
                msg.innerText = "Código inválido.";
                msg.style.color = "red";
                return;
            }


            const formDataVerify = new FormData();
            formDataVerify.append('action', 'verify_code');
            formDataVerify.append('code', code);
            formDataVerify.append('context', '2fa_disable');
            formDataVerify.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) formDataVerify.append('csrf_token', csrfInput.value);

            fetch('backend/api_security.php', { method: 'POST', body: formDataVerify })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {

                        const formDataDisable = new FormData();
                        formDataDisable.append('action', 'disable');
                        formDataDisable.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                        fetch('backend/api_2fa.php', { method: 'POST', body: formDataDisable })
                            .then(res => res.json())
                            .then(resp => {
                                if (resp.success) {
                                    Swal.fire({
                                        title: '<?php echo isset($t['js_success']) ? $t['js_success'] : "Sucesso!"; ?>',
                                        text: '<?php echo isset($t['js_2fa_disabled_success']) ? $t['js_2fa_disabled_success'] : "2FA desativado com sucesso."; ?>',
                                        icon: 'success',
                                        iconColor: '#5d3e8f',
                                        confirmButtonColor: '#5d3e8f'
                                         }).then(() => {
               
                        window.location.href = 'userdashboard.php?status=success&goto=definicoes';
                    });
                                } else {
                                    msg.innerText = resp.message;
                                    msg.style.color = "red";
                                }
                            });

                    } else {
                        msg.innerText = data.message;
                        msg.style.color = "red";
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.innerText = "Erro de ligação.";
                });
        }

        function disable2FA() {

            startDisable2FA();
        }

        function cancel2FA() {

            document.getElementById('2fa_setup_area').classList.add('hidden');


            document.getElementById('2fa_status_area').classList.remove('hidden');


            const startPanel = document.getElementById('2fa_start_panel');
            const verifyPanel = document.getElementById('2fa_security_verify_panel');


            if (startPanel) startPanel.classList.remove('hidden');


            if (verifyPanel) verifyPanel.classList.add('hidden');


            document.getElementById('verify_code_input').value = '';


            const emailCodeInput = document.getElementById('2fa_security_code_input');
            if (emailCodeInput) emailCodeInput.value = '';


            const btnSend = document.getElementById('btnSend2FACode');
            if (btnSend) {
                btnSend.disabled = false;
                btnSend.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> <?php echo isset($t['2fa_btn_config']) ? $t['2fa_btn_config'] : "Configurar 2FA"; ?>';
            }
        }

        function getErrorText(data, fallback = '<?php echo isset($t['js_error']) ? $t['js_error'] : "Ocorreu um erro."; ?>') {
            if (Array.isArray(data.errors)) {
                return '• ' + data.errors.join('\n• ');
            }
            if (data.error) return data.error;
            return fallback;
        }

        window.togglePassword = function (inputId, icon) {
            const input = document.getElementById(inputId);
            if (!input) return;

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        function confirmLogout() {
            Swal.fire({
                title: '<?php echo isset($t['js_logout_title']) ? $t['js_logout_title'] : "Terminar sessão?"; ?>',
                icon: 'warning',
                iconColor: '#5d3e8f',
                showCancelButton: true,
                confirmButtonColor: '#5d3e8f',
                cancelButtonColor: '#aaa',
                confirmButtonText: '<?php echo isset($t['js_btn_yes_logout']) ? $t['js_btn_yes_logout'] : "Sim, Sair"; ?>',
                cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "logout.php";
                }
            });
        };

        function sendSecurityCodePassword() {
            const btn = document.getElementById('btnSendCode');
            if (!btn) return;

            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_sending']) ? $t['js_sending'] : "A enviar..."; ?>';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('context', 'password');

            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                formData.append('csrf_token', csrfInput.value);
            }

            fetch('backend/api_security.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            document.getElementById('security_start_panel').classList.add('hidden');
                            document.getElementById('security_verify_panel').classList.remove('hidden');
                        } else {
                            alert('Erro: ' + data.message);
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    } catch (e) {
                        console.error("Erro parsing:", text);
                        alert("Erro de servidor. Tente novamente.");
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro de ligação.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function cancelSecurityCodePassword() {
            document.getElementById('security_verify_panel').classList.add('hidden');
            document.getElementById('security_start_panel').classList.remove('hidden');
            document.getElementById('security_code_input').value = '';
            document.getElementById('security_msg').innerText = '';

            const btn = document.getElementById('btnSendCode');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> <?php echo isset($t['btn_send_code']) ? $t['btn_send_code'] : 'Enviar Código de Segurança'; ?>';
            }
        }

        function sendSecurityCodeDelete() {
            const btn = document.getElementById('btnSendDeleteCode');
            if (!btn) return;

            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_sending']) ? $t['js_sending'] : "A enviar..."; ?>';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('context', 'delete');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                formData.append('csrf_token', csrfInput.value);
            }

            fetch('backend/api_security.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            document.getElementById('delete_security_start_panel').classList.add('hidden');
                            document.getElementById('delete_security_verify_panel').classList.remove('hidden');
                        } else {
                            alert('Erro: ' + data.message);
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    } catch (e) {
                        console.error("Erro parsing:", text);
                        alert("Erro de servidor. Tente novamente.");
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro de ligação.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function verifySecurityCodePassword() {
            const codeInput = document.getElementById('security_code_input');
            const msg = document.getElementById('security_msg');
            const code = codeInput.value;

            if (code.length < 4) {
                msg.innerText = "<?php echo isset($t['js_code_short']) ? $t['js_code_short'] : 'Código muito curto.'; ?>";
                msg.style.color = "red";
                return;
            }

            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('code', code);
            formData.append('context', 'password');

            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) formData.append('csrf_token', csrfInput.value);

            fetch('backend/api_security.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('security_verify_panel').classList.add('hidden');
                        document.getElementById('passwordForm').classList.remove('hidden');
                    } else {
                        msg.innerText = data.message;
                        msg.style.color = "#5d3e8f";
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.innerText = "Erro de conexão.";
                });
        }

        function sendSecurityCode2FA() {
            const btn = document.getElementById('btnSend2FACode');
            if (!btn) return;

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_sending']) ? $t['js_sending'] : "A enviar..."; ?>';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('context', '2fa');

            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) formData.append('csrf_token', csrfInput.value);

            fetch('backend/api_security.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('2fa_start_panel').classList.add('hidden');
                        document.getElementById('2fa_security_verify_panel').classList.remove('hidden');
                    } else {
                        Swal.fire('Erro', data.message || 'Falha ao enviar código', 'error');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Erro', 'Falha na ligação.', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function verifySecurityCode2FA() {
            const codeInput = document.getElementById('2fa_security_code_input');
            const msg = document.getElementById('2fa_security_msg');
            const code = codeInput.value;

            if (code.length < 4) {
                msg.innerText = "Código muito curto.";
                msg.style.color = "red";
                return;
            }

            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('code', code);
            formData.append('context', '2fa');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) formData.append('csrf_token', csrfInput.value);

            fetch('backend/api_security.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('2fa_security_verify_panel').classList.add('hidden');
                        start2FASetup();
                    } else {
                        msg.innerText = data.message;
                        msg.style.color = "red";
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.innerText = "Erro de conexão.";
                });
        }

        function cancelSecurityCode2FA() {
            document.getElementById('2fa_security_verify_panel').classList.add('hidden');
            document.getElementById('2fa_start_panel').classList.remove('hidden');
            document.getElementById('2fa_security_code_input').value = '';
            document.getElementById('2fa_security_msg').innerText = '';

            const btn = document.getElementById('btnSend2FACode');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> <?php echo isset($t['2fa_btn_config']) ? $t['2fa_btn_config'] : 'Configurar 2FA'; ?>';
            }
        }

        function submitUpdateForm(formData) {
            fetch("backend/update_user.php", { method: "POST", body: formData })
                .then(res => {
                    const contentType = res.headers.get("content-type");
                    if (contentType && contentType.includes("application/json")) {
                        return res.json();
                    }
                    throw new Error("Resposta do servidor não é JSON.");
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: '<?php echo isset($t['js_success']) ? $t['js_success'] : "Sucesso!"; ?>',
                            text: data.message || 'Dados atualizados com sucesso!',
                            icon: 'success',
                            iconColor: '#5d3e8f',
                            confirmButtonColor: '#5d3e8f'
                        }).then(() => {
                            if (data.logout) {
                                window.location.href = "logout.php";
                            } else {
                                window.location.href = window.location.pathname + '?status=success&goto=definicoes';
                            }
                        });
                    } else {
                        Swal.fire({
                            title: '<?php echo isset($t['js_error']) ? $t['js_error'] : "Erro!"; ?>',
                            text: getErrorText(data, 'Não foi possível atualizar os dados.'),
                            icon: 'error',
                            iconColor: '#5d3e8f',
                            confirmButtonColor: '#5d3e8f'
                        }).then(() => {
                            window.location.href = window.location.pathname + '?status=error&goto=definicoes';
                        });
                    }
                })
                .catch(err => {
                    console.error("Erro no fetch:", err);
                    Swal.fire({ title: 'Erro de Comunicação!', text: 'Não foi possível ligar ao servidor.', icon: 'error', confirmButtonColor: '#5d3e8f' });
                });
        }

        document.addEventListener("DOMContentLoaded", () => {
            const viewDiv = document.getElementById("definicoesView");
            const editDiv = document.getElementById("definicoesEdit");
            const btnEditar = document.getElementById("btnEditarInterno");
            const btnVoltar = document.getElementById("btnVoltar");
            const sections = document.querySelectorAll("main section");
            const navLinks = document.querySelectorAll("aside nav a");
            const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

            const fileInput = document.getElementById('imagem_perfil');
            const mainPreview = document.getElementById('imgPreviewMain');
            const hoverPreview = document.getElementById('imgPreviewHover');

            if (fileInput) {
                fileInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            if (mainPreview) mainPreview.src = e.target.result;
                            if (hoverPreview) hoverPreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            const initialSection = '<?php echo htmlspecialchars($_GET['goto'] ?? "reservas"); ?>';

            function mostrarSecao(id, clickedLink = null) {
                sections.forEach(sec => sec.classList.add("section-hidden"));
                navLinks.forEach(link => {
                    link.classList.remove("bg-[#5d3e8f]", "text-white");
                    link.classList.add("text-[#fff]");
                });

                const ativa = document.getElementById(id);
                if (ativa) {
                    ativa.classList.remove("section-hidden");

                    if (isAdmin && id === 'reservas') {
                        mostrarSecao('definicoes', document.querySelector('aside nav a[href="#definicoes"]'));
                        return;
                    }

                    if (clickedLink) {
                        clickedLink.classList.add("bg-[#5d3e8f]", "text-white");
                        clickedLink.classList.remove("text-[#fff]");
                    }

                    if (id === 'definicoes') {
                        const statusUrl = new URLSearchParams(window.location.search).get('status');
                        if (statusUrl === 'error' || statusUrl === 'success') {
                            viewDiv.classList.add("section-hidden");
                            editDiv.classList.remove("section-hidden");
                        } else {
                            viewDiv.classList.remove("section-hidden");
                            editDiv.classList.add("section-hidden");
                        }
                    }
                }
            }

            let defaultSection = isAdmin ? 'definicoes' : 'reservas';

            const initialLink = document.querySelector(`aside nav a[href="#${initialSection}"]`);
            if (initialLink) {
                mostrarSecao(initialSection, initialLink);
                const mainEl = document.querySelector('main');
                if (mainEl) mainEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                mostrarSecao(defaultSection, document.querySelector(`aside nav a[href="#${defaultSection}"]`));
            }

            navLinks.forEach(link => {
                link.addEventListener("click", function (e) {
                    const href = this.getAttribute("href");
                    if (href && href.startsWith('#')) {
                        e.preventDefault();
                        const destino = href.substring(1);
                        mostrarSecao(destino, this);
                    }
                });
            });

            if (btnEditar) {
                btnEditar.addEventListener("click", (e) => {
                    e.preventDefault();
                    viewDiv.classList.add("section-hidden");
                    editDiv.classList.remove("section-hidden");
                });
            }

            if (btnVoltar) {
                btnVoltar.addEventListener("click", (e) => {
                    e.preventDefault();
                    editDiv.classList.add("section-hidden");
                    viewDiv.classList.remove("section-hidden");
                });
            }

            const updateForm = document.getElementById("updateForm");
            if (updateForm) {
                updateForm.addEventListener("submit", function (e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                    const newPass = formData.get('password');

                    if (newPass && newPass.length > 0) {
                        Swal.fire({
                            title: '<?php echo isset($t['js_pass_change_title']) ? $t['js_pass_change_title'] : "Alterar Password?"; ?>',
                            text: "<?php echo isset($t['js_pass_change_text']) ? $t['js_pass_change_text'] : "Tem a certeza?"; ?>",
                            icon: 'warning',
                            iconColor: '#5d3e8f',
                            showCancelButton: true,
                            confirmButtonColor: '#5d3e8f',
                            cancelButtonColor: '#aaa',
                            confirmButtonText: '<?php echo isset($t['js_btn_yes_change']) ? $t['js_btn_yes_change'] : "Sim"; ?>',
                            cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                submitUpdateForm(formData);
                            }
                        });
                    } else {
                        submitUpdateForm(formData);
                    }
                });
            }

            const passForm = document.getElementById("passwordForm");
            if (passForm) {
                passForm.addEventListener("submit", function (e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                    Swal.fire({
                        title: '<?php echo isset($t['js_pass_change_title']) ? $t['js_pass_change_title'] : "Alterar Password?"; ?>',
                        text: "<?php echo isset($t['js_pass_relogin']) ? $t['js_pass_relogin'] : "Login necessário."; ?>",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#5d3e8f',
                        iconColor: '#5d3e8f',
                        cancelButtonColor: '#aaa',
                        confirmButtonText: '<?php echo isset($t['js_btn_yes_change']) ? $t['js_btn_yes_change'] : "Sim"; ?>',
                        cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitUpdateForm(formData);
                        }
                    });
                });
            }
        });


        const deleteForm = document.getElementById("deleteForm");

        if (deleteForm) {
            deleteForm.addEventListener("submit", function (e) {
                e.preventDefault();

                const codeInput = document.getElementById('delete_security_code_input');
                const msg = document.getElementById('delete_security_msg');
                const code = codeInput.value;

                if (!code) {
                    msg.innerText = "<?php echo $t['js_code_required'] ?? 'Insira o código.'; ?>";
                    msg.style.color = "red";
                    return;
                }

                const formDataVerify = new FormData();
                formDataVerify.append('action', 'verify_code');
                formDataVerify.append('code', code);
                formDataVerify.append('context', 'delete');

                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) formDataVerify.append('csrf_token', csrfInput.value);

                fetch('backend/api_security.php', { method: 'POST', body: formDataVerify })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {

                            Swal.fire({
                                title: '<?php echo isset($t['js_delete_confirm_title']) ? $t['js_delete_confirm_title'] : "Tem a certeza?"; ?>',
                                text: '<?php echo isset($t['js_delete_final_warning']) ? $t['js_delete_final_warning'] : "Esta ação é irreversível."; ?>',
                                icon: 'warning',
                                confirmButtonColor: '#5d3e8f',
                                iconColor: '#5d3e8f',
                                cancelButtonColor: '#aaa',
                                showCancelButton: true,
                                confirmButtonText: '<?php echo isset($t['js_btn_yes_delete']) ? $t['js_btn_yes_delete'] : "Sim, Eliminar"; ?>',
                                cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>'
                            }).then((result) => {
                                if (result.isConfirmed) {

                                    const formData = new FormData(deleteForm);
                                    submitUpdateForm(formData);
                                }
                            });
                        } else {
                            msg.innerText = data.message;
                            msg.style.color = "red";
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        msg.innerText = "Erro de conexão.";
                    });
            });
        }

        function cancelSecurityCodeDelete() {
            document.getElementById('delete_security_verify_panel').classList.add('hidden');
            document.getElementById('delete_security_start_panel').classList.remove('hidden');
            document.getElementById('delete_security_code_input').value = '';
            document.getElementById('delete_security_msg').innerText = '';

            const btn = document.getElementById('btnSendDeleteCode');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> <?php echo isset($t['btn_send_code']) ? $t['btn_send_code'] : 'Enviar Código de Segurança'; ?>';
            }
        }


        function showUserReservationDetails(reserva) {
            Swal.fire({
                title: '<?php echo $t['js_loading'] ?? 'A carregar detalhes...'; ?>',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading() },
                iconColor: '#5d3e8f',
                background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
            });

            fetch(`backend/admin_room_details.php?room_id=${reserva.salaId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        Swal.fire({
                            title: 'Erro!',
                            text: data.error,
                            icon: 'error',
                            confirmButtonColor: '#5d3e8f',
                            background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                            color: document.documentElement.classList.contains('dark') ? '#fff' : '#000'
                        });
                        return;
                    }
                    const galeriaHtml = data.galeria.length > 0
                        ? data.galeria.map(url =>
                            `<a href="${url}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-transform duration-200 hover:scale-105">
                <img src="${url}" class="w-20 h-20 sm:w-24 sm:h-24 object-cover" alt="Imagem da sala">
            </a>`
                        ).join('')
                        : `<div class="text-gray-500 dark:text-gray-400 text-sm italic p-2 bg-gray-50 dark:bg-gray-800 rounded border dark:border-gray-700">
                <?php echo $t['salas_gallery_empty'] ?? 'Nenhuma imagem disponível.'; ?>
        </div>`;

                    const detailContent = `
        <div class="text-left text-gray-700 dark:text-gray-300 font-sans">

            <div class="mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                <h4 class="text-2xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] leading-tight mb-1">
                    ${data.nome}
                </h4>
                <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 font-medium">
                    <i class="fas fa-map-marker-alt mr-2 opacity-75"></i>
                    ${data.local}
                </div>
            </div>

            <div class="bg-[#5d3e8f]/5 dark:bg-gray-700/30 rounded-xl p-4 sm:p-5 mb-6 border border-[#5d3e8f]/10 dark:border-gray-600">
                <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-4 flex items-center">
                    <i class="far fa-calendar-alt mr-2 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                    Dados da Reserva
                </h5>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-2 flex items-center">
                        <i class="fas fa-clock mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php echo $t['res_th_date'] ?? 'Data e Horário'; ?>
                        </h5>
                        <div class="font-medium text-base">
                            ${reserva.data} <span class="mx-1 text-gray-400">|</span> ${reserva.horario}
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                        <?php echo $t['res_th_desc'] ?? 'Motivo/Descrição'; ?>
                    </h5>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                        ${reserva.descricao ? reserva.descricao : '<span class="italic text-gray-400">Sem descrição.</span>'}
                    </div>
                </div>
            </div>

            <div class="space-y-6 px-1">
                <div class="bg-[#5d3e8f]/5 dark:bg-gray-700/30 rounded-xl p-4 sm:p-5 mb-6 border border-[#5d3e8f]/10 dark:border-gray-600">
                    <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-4 flex items-center">
                        <i class="fas fa-door-open mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                        Dados da Sala
                    </h5>

                    <div>
                        <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php echo $t['tab_description'] ?? 'Sobre a Sala'; ?>
                        </h5>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                            ${data.descricao || '<span class="italic text-gray-500"><?php echo $t['msg_no_desc'] ?? 'Sem descrição disponível.'; ?></span>'}
                        </div>
                    </div>

                    <div>
                        <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-2 flex items-center">
                            <i class="fas fa-tools mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php echo $t['tab_equipment'] ?? 'Equipamentos'; ?>
                        </h5>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                            ${data.equipamentos || '<span class="italic text-gray-500"><?php echo $t['no_equip'] ?? 'N/A'; ?></span>'}
                        </div>
                    </div>

                    <div>
                        <h5 class="font-bold text-base text-gray-800 dark:text-gray-100 mb-3 flex items-center">
                            <i class="fas fa-images mr-2 opacity-75 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php echo $t['salas_label_gallery'] ?? 'Galeria'; ?>
                        </h5>
                        <div class="flex flex-wrap gap-3 pl-6">
                            ${galeriaHtml}
                        </div>
                    </div>
                </div>
            </div>
    `;
                    Swal.fire({
                        title: `Detalhes da Reserva`,
                        html: detailContent,
                        width: '600px',
                        icon: 'info',
                        iconColor: '#5d3e8f',
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: '<?php echo $t['btn_close'] ?? 'Fechar'; ?>',
                        cancelButtonColor: '#5d3e8f',
                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                        color: document.documentElement.classList.contains('dark') ? '#fff' : '#000'
                    });

                })
                .catch(error => {
                    console.error('Erro de rede/parsing:', error);
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Não foi possível obter detalhes da sala.',
                        icon: 'error',
                        confirmButtonColor: '#5d3e8f',
                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                        color: document.documentElement.classList.contains('dark') ? '#fff' : '#000'
                    });
                });
        }

        function cancelReservation(id) {
            Swal.fire({
                title: '<?php echo $t['swal_cancel_title'] ?? "Cancelar Reserva?"; ?>',
                text: "<?php echo $t['swal_cancel_text'] ?? "Esta ação não pode ser desfeita."; ?>",
                icon: 'warning',
                showCancelButton: true,
                iconColor: '#5d3e8f',
                confirmButtonColor: '#5d3e8f',
                cancelButtonColor: '#555555',
                confirmButtonText: '<?php echo $t['swal_btn_confirm_cancel'] ?? "Sim, cancelar"; ?>',
                cancelButtonText: '<?php echo $t['swal_btn_back'] ?? "Voltar"; ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'cancel');
                    formData.append('id', id);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                    fetch('backend/manage_user_reservations.php', { method: 'POST', body: formData })

                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: '<?php echo $t['js_success'] ?? "Sucesso!"; ?>',
                                    text: data.message,
                                    icon: 'success',
                                    iconColor: '#5d3e8f',
                                    confirmButtonColor: '#5d3e8f',
                                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#1f2937'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: '<?php echo $t['js_error'] ?? "Erro!"; ?>',
                                    text: data.error,
                                    icon: 'error',
                                    iconColor: '#5d3e8f',
                                    confirmButtonColor: '#5d3e8f',
                                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#1f2937'
                                });
                            }
                        })
                        .catch(() => Swal.fire('<?php echo $t['js_error'] ?? "Erro!"; ?>', '<?php echo $t['swal_comm_error'] ?? "Falha na comunicação."; ?>', 'error'));
                }
            });
        }

        function editReservation(reserva) {
            Swal.fire({
                title: '<?php echo $t['swal_edit_title'] ?? "Editar Reserva"; ?>',
                html: `
            <div class="text-left">
                <label class="block text-sm font-bold mb-1"><?php echo $t['label_date'] ?? "Data:"; ?></label>
                <input type="date" id="edit_data" class="swal2-input w-full m-0 mb-3" value="${reserva.data_raw}">
                
                <div class="flex gap-2 mb-3">
                    <div class="w-1/2">
                        <label class="block text-sm font-bold mb-1"><?php echo $t['label_start'] ?? "Início:"; ?></label>
                        <input type="time" id="edit_inicio" class="swal2-input w-full m-0" value="${reserva.inicio_raw}">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-sm font-bold mb-1"><?php echo $t['label_end'] ?? "Fim:"; ?></label>
                        <input type="time" id="edit_fim" class="swal2-input w-full m-0" value="${reserva.fim_raw}">
                    </div>
                </div>

                <label class="block text-sm font-bold mb-1"><?php echo $t['label_desc'] ?? "Descrição:"; ?></label>
                <textarea id="edit_desc" class="swal2-textarea w-full m-0" rows="3">${reserva.descricao}</textarea>
            </div>
         
        `,
                showCancelButton: true,
                iconColor: '#5d3e8f',
                confirmButtonText: '<?php echo $t['btn_save'] ?? "Guardar"; ?>',
                cancelButtonText: '<?php echo $t['btn_cancel'] ?? "Cancelar"; ?>',
                confirmButtonColor: '#5d3e8f',
                focusConfirm: false,
                preConfirm: () => {
                    const data = document.getElementById('edit_data').value;
                    const inicio = document.getElementById('edit_inicio').value;
                    const fim = document.getElementById('edit_fim').value;
                    const desc = document.getElementById('edit_desc').value;

                    if (!data || !inicio || !fim) {
                        Swal.showValidationMessage('<?php echo $t['swal_val_fill'] ?? "Preencha a data e os horários"; ?>');
                        return false;
                    }
                    if (inicio >= fim) {
                        Swal.showValidationMessage('<?php echo $t['swal_val_time'] ?? "A hora de fim deve ser depois do início"; ?>');
                        return false;
                    }

                    return { data: data, inicio: inicio, fim: fim, desc: desc };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'edit');
                    formData.append('id', reserva.id);
                    formData.append('data', result.value.data);
                    formData.append('hora_inicio', result.value.inicio);
                    formData.append('hora_fim', result.value.fim);
                    formData.append('descricao', result.value.desc);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                    fetch('backend/manage_user_reservations.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: '<?php echo $t['js_success'] ?? "Sucesso!"; ?>',
                                    text: data.message,
                                    icon: 'success',
                                    iconColor: '#5d3e8f',
                                    confirmButtonColor: '#5d3e8f',
                                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#1f2937'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: '<?php echo $t['js_error'] ?? "Erro!"; ?>',
                                    text: data.error,
                                    icon: 'error',
                                    iconColor: '#5d3e8f',
                                    confirmButtonColor: '#5d3e8f',
                                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#1f2937'
                                });
                            }
                        })
                        .catch(() => Swal.fire('<?php echo $t['js_error'] ?? "Erro!"; ?>', '<?php echo $t['swal_comm_error'] ?? "Falha na comunicação."; ?>', 'error'));
                }
            });
        }


    </script>

</body>

</html>