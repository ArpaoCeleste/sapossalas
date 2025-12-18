<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'backend/verificar_conclusao.php';
require_once 'backend/config.php';

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

if ($conn) {
    $sql_user = "SELECT id, nome, email, role FROM utilizadores WHERE id = ?";
    $stmt = $conn->prepare($sql_user);
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $user_data = null;
    }
} else {
    $user_data = null;
}

if (!$user_data) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$is_admin = ($user_data['role'] === 'admin');

if (!$is_admin) {
    header("Location: reservar.php");
    exit;
}

$edit_room = null;
$DEFAULT_IMAGE = 'imagens/default.png';


$filtro_professor = isset($_GET['professor']) ? (int) $_GET['professor'] : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$valid_statuses = ['ativa', 'cancelada', 'concluida'];
if (!in_array($filtro_status, $valid_statuses)) {
    $filtro_status = '';
}

$sql_users = "SELECT id, email, nome, role FROM utilizadores WHERE is_active = 1 ORDER BY nome, email";
$result_users = $conn->query($sql_users);
if (!$result_users) {
    $result_users = [];
}

$sql_users_inactive = "SELECT id, email, nome, role FROM utilizadores WHERE is_active = 0 ORDER BY nome, email";
$result_users_inactive = $conn->query($sql_users_inactive);
if (!$result_users_inactive) {
    $result_users_inactive = [];
}

$total_users_count = 0;
$active_users_count = 0;

$res_total = $conn->query("SELECT COUNT(*) as total FROM utilizadores WHERE role != 'admin' AND is_active = 1");
if ($res_total)
    $total_users_count = $res_total->fetch_assoc()['total'];

$res_active = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM reservas WHERE data >= CURDATE() AND status_reserva = 'ativa'");
if ($res_active)
    $active_users_count = $res_active->fetch_assoc()['total'];


$reservas_per_page = 5;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $reservas_per_page;

$sql_reservas_base = "FROM reservas r 
                      JOIN rooms ON r.room_id = rooms.id 
                      JOIN utilizadores u ON r.user_id = u.id";

$conditions = [];
$params = [];
$types = "";


if ($filtro_professor > 0) {
    $conditions[] = "r.user_id = ?";
    $params[] = $filtro_professor;
    $types .= "i";
}

if ($filtro_status !== '') {
    $conditions[] = "r.status_reserva = ?";
    $params[] = $filtro_status;
    $types .= "s";
}
$where_sql = "";
if (!empty($conditions)) {
    $where_sql = " WHERE " . implode(" AND ", $conditions);
}

$sql_count = "SELECT COUNT(*) as total " . $sql_reservas_base . $where_sql;
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();

if ($result_count) {
    $total_reservas = $result_count->fetch_assoc()['total'];
    $total_pages = ceil($total_reservas / $reservas_per_page);
    $stmt_count->close();
} else {
    $total_reservas = 0;
    $total_pages = 0;
}


$sql_reservas = "SELECT r.*, rooms.nome as sala_nome, u.nome as professor_nome, u.email as professor_email " . $sql_reservas_base . $where_sql;
$sql_reservas .= " ORDER BY r.data DESC, r.hora_inicio DESC LIMIT ? OFFSET ?";

$params[] = $reservas_per_page;
$params[] = $offset;
$types .= "ii";
$stmt_reservas = $conn->prepare($sql_reservas);
$stmt_reservas->bind_param($types, ...$params);
$stmt_reservas->execute();
$result_reservas = $stmt_reservas->get_result();

if (!$result_reservas) {
    error_log("Erro ao buscar reservas: " . $conn->error);
    $result_reservas = false;
}



if (isset($_GET['editar'])) {
    $edit_id = (int) $_GET['editar'];
    $stmt_edit = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($result_edit->num_rows > 0) {
        $edit_room = $result_edit->fetch_assoc();
    }
    $stmt_edit->close();
    $_GET['section'] = 'salas';
}



$rooms_per_page = 5;

$rooms_page = isset($_GET['rooms_page']) && is_numeric($_GET['rooms_page']) ? (int) $_GET['rooms_page'] : 1;
$rooms_offset = ($rooms_page - 1) * $rooms_per_page;



$sql_count_rooms = "SELECT COUNT(*) as total FROM rooms";
$res_count_rooms = $conn->query($sql_count_rooms);
$total_rooms = ($res_count_rooms) ? $res_count_rooms->fetch_assoc()['total'] : 0;
$total_rooms_pages = ceil($total_rooms / $rooms_per_page);



$sql_rooms_list = "SELECT * FROM rooms ORDER BY nome LIMIT {$rooms_per_page} OFFSET {$rooms_offset}";
$result_rooms_list = $conn->query($sql_rooms_list);


$section_rooms = isset($t['admin_sec_rooms']) ? $t['admin_sec_rooms'] : 'Gestão de Salas';
$section_users = isset($t['admin_sec_users']) ? $t['admin_sec_users'] : 'Utilizadores';
$section_occupancy = isset($t['admin_sec_occupancy']) ? $t['admin_sec_occupancy'] : 'Ocupação de Salas';
$section_dashboard = isset($t['admin_sec_dashboard']) ? $t['admin_sec_dashboard'] : 'Dashboard';
$welcome_title = isset($t['admin_welcome']) ? $t['admin_welcome'] : 'Bem-vindo, Administrador';
$admin_email = $_SESSION['user_email'];
$admin_role = $_SESSION['role'];
$desc_rooms = isset($t['admin_desc_rooms']) ? $t['admin_desc_rooms'] : 'Adicionar, editar e eliminar salas';
$btn_rooms = isset($t['admin_btn_rooms']) ? $t['admin_btn_rooms'] : 'Gerir Salas';
$desc_users = isset($t['admin_desc_users']) ? $t['admin_desc_users'] : 'Visualizar utilizadores e suas reservas';
$btn_users = isset($t['admin_btn_users']) ? $t['admin_btn_users'] : 'Ver Utilizadores';
$desc_occupancy = isset($t['admin_desc_occupancy']) ? $t['admin_desc_occupancy'] : 'Consultar ocupação por dia';
$btn_occupancy = isset($t['admin_btn_occupancy']) ? $t['admin_btn_occupancy'] : 'Ver Ocupação';
$sidebar_title = isset($t['admin_sidebar_title']) ? $t['admin_sidebar_title'] : 'Menu Admin';
$admin_dashboard_current_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($t['dash_title']) ? $t['dash_title'] : 'Dashboard - Admin'; ?></title>

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

        .ocupacao-grid {
            display: grid;
            grid-template-columns: 150px repeat(13, 1fr);
            gap: 1px;
            background: #d1d5db;
            border: 1px solid #d1d5db;
            overflow-x: auto;
        }

        .tooltip-custom {
            position: relative;
        }

        .tooltip-custom:hover::after {
            content: attr(data-info);
            position: absolute;
            background: #111827;
            color: #fff;
            padding: 8px;
            border-radius: 6px;
            white-space: pre-line;
            z-index: 1000;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            margin-bottom: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            text-align: left;
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
                    <?php echo isset($t['admin_sidebar_title_personal']) ? $t['admin_sidebar_title_personal'] : 'Painel do Administrador'; ?>
                </h2>
                <button class="lg:hidden text-white focus:outline-none" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="p-6 space-y-4 flex flex-col aside-nav">
                <a href="#dashboard"
                    class="nav-link flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 hover:text-white transition duration-1000"
                    data-section="dashboard">
                    <i class="fas fa-tachometer-alt mr-3"></i> <?php echo htmlspecialchars($section_dashboard); ?>
                </a>
                <a href="#salas"
                    class="nav-link flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 hover:text-white transition duration-1000"
                    data-section="salas">
                    <i class="fas fa-door-open mr-3"></i> <?php echo htmlspecialchars($section_rooms); ?>
                </a>
                <a href="#utilizadores"
                    class="nav-link flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 hover:text-white transition duration-1000"
                    data-section="utilizadores">
                    <i class="fas fa-users mr-3"></i> <?php echo htmlspecialchars($section_users); ?>
                </a>
                <a href="#ocupacao"
                    class="nav-link flex items-center px-4 py-2 rounded text-[#fff] font-medium hover:bg-[#5d3e8f] hover:scale-110 hover:text-white transition duration-1000"
                    data-section="ocupacao">
                    <i class="fas fa-calendar-days mr-3"></i> <?php echo htmlspecialchars($section_occupancy); ?>
                </a>

                <a onclick="confirmLogout()"
                    class="flex items-center px-4 py-2 rounded text-red-400 font-medium hover:bg-red-900/50 hover:scale-110 transition duration-1000 cursor-pointer mt-auto">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <?php echo isset($t['dash_tab_logout']) ? $t['dash_tab_logout'] : 'Terminar Sessão'; ?>
                </a>
            </nav>
        </aside>

        <main
            class="flex-1 flex flex-col lg:ml-0 transition-all duration-300 w-full max-w-full overflow-hidden min-h-screen bg-gray-100 dark:bg-gray-900">

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


                <section id="dashboard" class="admin-section">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl  border border-gray-100 dark:border-gray-700 p-8 mb-10 text-center transition-all duration-300 relative overflow-hidden group">
                        <div
                            class="absolute top-0 right-0 -mr-16 -mt-16 w-48 h-48 rounded-full bg-[#5d3e8f]/5 dark:bg-[#d8b4fe]/5 blur-3xl group-hover:bg-[#5d3e8f]/10 transition-colors duration-500">
                        </div>

                        <div class="relative z-10">
                            <h1
                                class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white mb-3 tracking-tight">
                                <?php echo htmlspecialchars($welcome_title); ?>
                            </h1>
                            <p class="text-gray-500 dark:text-gray-400 text-base max-w-2xl mx-auto mb-6">
                                <i class="fas fa-user-shield text-[#5d3e8f] dark:text-[#d8b4fe] mr-2"></i>
                                <?php echo isset($t['admin_role_text']) ? $t['admin_role_text'] : 'Use o menu lateral para gerir o sistema.'; ?>
                            </p>
                            <div
                                class="inline-flex items-center gap-3 px-4 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-full border border-gray-200 dark:border-gray-600">
                                <span class="text-sm text-gray-600 dark:text-gray-300">
                                    <i class="far fa-envelope mr-1.5 opacity-70"></i>
                                    <?php echo htmlspecialchars($admin_email); ?>
                                </span>
                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                <span
                                    class="text-sm font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] uppercase tracking-wider">
                                    <?php echo htmlspecialchars($admin_role); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                        <div onclick="navigateToSection('salas')"
                            class="group cursor-pointer bg-white dark:bg-gray-800 rounded-2xl  border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 w-full h-1 bg-[#5d3e8f] transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300">
                            </div>
                            <div
                                class="w-16 h-16 rounded-full bg-[#fbeaff] dark:bg-[#2d1b4e] flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fa-solid fa-door-open text-3xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            </div>
                            <h3
                                class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-[#5d3e8f] dark:group-hover:text-[#d8b4fe] transition-colors">
                                <?php echo htmlspecialchars($section_rooms); ?>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 line-clamp-2">
                                <?php echo htmlspecialchars($desc_rooms); ?>
                            </p>
                            <span
                                class="mt-auto inline-flex items-center justify-center px-6 py-2.5 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white text-sm font-semibold rounded-lg shadow-sm transition-all w-full sm:w-auto">
                                <?php echo htmlspecialchars($btn_rooms); ?>
                                <i class="fas fa-arrow-right ml-2 text-xs opacity-70"></i>
                            </span>
                        </div>

                        <div onclick="navigateToSection('utilizadores')"
                            class="group cursor-pointer bg-white dark:bg-gray-800 rounded-2xl  border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 w-full h-1 bg-[#5d3e8f] transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300">
                            </div>
                            <div
                                class="w-16 h-16 rounded-full bg-[#fbeaff] dark:bg-[#2d1b4e] flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fa-solid fa-users text-3xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            </div>
                            <h3
                                class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-[#5d3e8f] dark:group-hover:text-[#d8b4fe] transition-colors">
                                <?php echo htmlspecialchars($section_users); ?>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 line-clamp-2">
                                <?php echo htmlspecialchars($desc_users); ?>
                            </p>
                            <span
                                class="mt-auto inline-flex items-center justify-center px-6 py-2.5 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white text-sm font-semibold rounded-lg shadow-sm transition-all w-full sm:w-auto">
                                <?php echo htmlspecialchars($btn_users); ?>
                                <i class="fas fa-arrow-right ml-2 text-xs opacity-70"></i>
                            </span>
                        </div>

                        <div onclick="navigateToSection('ocupacao')"
                            class="group cursor-pointer bg-white dark:bg-gray-800 rounded-2xl  border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 w-full h-1 bg-[#5d3e8f] transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300">
                            </div>
                            <div
                                class="w-16 h-16 rounded-full bg-[#fbeaff] dark:bg-[#2d1b4e] flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fa-solid fa-calendar-days text-3xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            </div>
                            <h3
                                class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-[#5d3e8f] dark:group-hover:text-[#d8b4fe] transition-colors">
                                <?php echo htmlspecialchars($section_occupancy); ?>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 line-clamp-2">
                                <?php echo htmlspecialchars($desc_occupancy); ?>
                            </p>
                            <span
                                class="mt-auto inline-flex items-center justify-center px-6 py-2.5 bg-[#5d3e8f] hover:bg-[#4a2f70] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white text-sm font-semibold rounded-lg shadow-sm transition-all w-full sm:w-auto">
                                <?php echo htmlspecialchars($btn_occupancy); ?>
                                <i class="fas fa-arrow-right ml-2 text-xs opacity-70"></i>
                            </span>
                        </div>
                    </div>
                </section>

                <section id="salas" class="admin-section section-hidden">
                    <h2 class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe] mb-6  pb-2">
                        <?php echo htmlspecialchars($section_rooms); ?>
                    </h2>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl  mb-8 transition-colors duration-300">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?php echo $edit_room ? ($t['salas_edit_title'] ?? 'Editar Sala') : ($t['salas_add_title'] ?? 'Adicionar Nova Sala'); ?>
                        </h3>

                        <form method="POST" action="backend/manage_rooms.php" class="space-y-4"
                            enctype="multipart/form-data">

                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action"
                                value="<?php echo $edit_room ? 'editar' : 'adicionar'; ?>">
                            <input type="hidden" name="section" value="salas">
                            <?php if ($edit_room): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_room['id']; ?>">
                            <?php endif; ?>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="nome"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_name'] ?? 'Nome da Sala'; ?>
                                        *</label>
                                    <input type="text" id="nome" name="nome"
                                        value="<?php echo $edit_room ? htmlspecialchars($edit_room['nome']) : ''; ?>"
                                        required
                                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent">
                                </div>
                                <div>
                                    <label for="capacidade"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_capacity'] ?? 'Capacidade'; ?>
                                        *</label>
                                    <input type="number" id="capacidade" name="capacidade" min="1"
                                        value="<?php echo $edit_room ? $edit_room['capacidade'] : ''; ?>" required
                                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent">
                                </div>
                                <div>
                                    <label for="status"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_status'] ?? 'Estado da Sala'; ?>
                                        *</label>
                                    <div class="relative">
                                        <select id="status" name="status" required
                                            class="w-full dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent appearance-none">
                                            <?php
                                            $current_status = $edit_room['status'] ?? 'disponivel';
                                            $statuses = [
                                                'disponivel' => $t['status_disponivel'] ?? 'Disponível',
                                                'indisponivel' => $t['status_indisponivel'] ?? 'Indisponível',
                                                'brevemente' => $t['status_brevemente'] ?? 'Brevemente'
                                            ];
                                            foreach ($statuses as $value => $label):
                                                $selected = ($current_status === $value) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400 dark:text-gray-500"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="local"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_location'] ?? 'Localização'; ?>
                                        *</label>
                                    <input type="text" id="local" name="local"
                                        value="<?php echo $edit_room ? htmlspecialchars($edit_room['local']) : ''; ?>"
                                        required
                                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent">
                                </div>
                                <div>
                                    <label for="equipamentos"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_equipment'] ?? 'Equipamentos'; ?></label>
                                    <input type="text" id="equipamentos" name="equipamentos"
                                        value="<?php echo $edit_room ? htmlspecialchars($edit_room['equipamentos']) : ''; ?>"
                                        placeholder="<?php echo $t['salas_ph_equipment'] ?? 'Ex: Projetor, Wi-Fi, AC'; ?>"
                                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent">
                                </div>
                            </div>

                            <div>
                                <label for="descricao"
                                    class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['salas_label_desc'] ?? 'Descrição'; ?></label>
                                <textarea id="descricao" name="descricao" rows="3"
                                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent"><?php echo $edit_room ? htmlspecialchars($edit_room['descricao']) : ''; ?></textarea>
                            </div>

                            <div class="pt-6 border-t border-gray-200 dark:border-gray-700 mt-6 items-center ">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">
                                    <?php echo $t['salas_label_gallery'] ?? 'Galeria de Fotos (Máx. 4)'; ?>
                                </h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    <?php echo $t['salas_gallery_hint_select'] ?? 'Clique nos slots vazios ou existentes para carregar as fotos.'; ?>
                                </p>

                                <div id="previews-container" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <?php
                                    $current_photos_urls = [];
                                    if ($edit_room && !empty($edit_room['fotos'])) {
                                        $current_photos_urls = json_decode($edit_room['fotos'], true);
                                        if (!is_array($current_photos_urls)) {
                                            $current_photos_urls = [];
                                        }
                                    }
                                    $photo_slots = array_pad($current_photos_urls, 4, $DEFAULT_IMAGE);
                                    ?>
                                    <?php for ($i = 0; $i < 4; $i++):
                                        $is_cover = ($i === 0);
                                        $current_url = $photo_slots[$i];
                                        ?>
                                        <div class="flex flex-col items-center group relative preview-slot-<?php echo $i; ?> cursor-pointer"
                                            data-slot-index="<?php echo $i; ?>">
                                            <div class="aspect-square w-full rounded-lg overflow-hidden bg-gray-200 dark:bg-gray-700 border-2 <?php echo $is_cover ? 'border-[#5d3e8f]' : 'border-gray-300 dark:border-gray-600'; ?> hover:ring-2 hover:ring-[#5d3e8f] transition duration-300"
                                                onclick="document.getElementById('file_slot_<?php echo $i; ?>').click()">
                                                <img id="preview_<?php echo $i; ?>"
                                                    src="<?php echo htmlspecialchars($current_url); ?>"
                                                    alt="Foto <?php echo $i + 1; ?>" class="w-full h-full object-cover"
                                                    onerror="this.src='<?php echo $DEFAULT_IMAGE; ?>';">
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                <?php echo $t['salas_slot_label'] ?? 'Slot'; ?>     <?php echo $i + 1; ?>
                                                <?php if ($is_cover): ?>
                                                    <span
                                                        class="text-[8px] font-bold text-[#5d3e8f] dark:text-[#d8b4fe]">(<?php echo $t['salas_capa'] ?? 'CAPA'; ?>)</span>
                                                <?php endif; ?>
                                            </p>
                                            <?php $show_remove_btn = $current_url !== $DEFAULT_IMAGE; ?>
                                            <button type="button" id="remove_btn_<?php echo $i; ?>"
                                                onclick="event.stopPropagation(); removeSlotImage('preview_<?php echo $i; ?>', 'file_slot_<?php echo $i; ?>', this, <?php echo $i; ?>)"
                                                class="absolute top-1 right-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-[#4a2f70] dark:hover:bg-[#c084fc] transition-opacity duration-300 z-10 <?php echo $show_remove_btn ? 'opacity-100' : 'opacity-0 pointer-events-none'; ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <input type="file" id="file_slot_<?php echo $i; ?>" name="galeria[]"
                                                accept="image/*" class="hidden"
                                                onchange="previewSlotImage(event, 'preview_<?php echo $i; ?>', 'remove_btn_<?php echo $i; ?>')">
                                            <input type="hidden" name="existing_photos[<?php echo $i; ?>]"
                                                value="<?php echo htmlspecialchars($current_url); ?>">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="pt-4 space-x-4">
                                <button type="submit"
                                    class="px-6 py-2 bg-[#5d3e8f] hover:bg-[#4a2f70] text-white font-semibold rounded-lg shadow-md transition hover:scale-105">
                                    <i class="fas fa-save mr-2"></i>
                                    <?php echo $edit_room ? ($t['salas_btn_update'] ?? 'Atualizar Sala') : ($t['salas_btn_add'] ?? 'Adicionar Sala'); ?>
                                </button>
                                <?php if ($edit_room): ?>
                                    <a href="admin.php?section=salas"
                                        class="px-6 py-2 bg-gray-400 text-white dark:bg-gray-600 font-semibold rounded-lg transition hover:scale-105">
                                        <i class="fas fa-times mr-2"></i> <?php echo $t['btn_cancel'] ?? 'Cancelar'; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl  transition-colors duration-300">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?php echo $t['salas_list_title'] ?? 'Lista de Salas Ativas'; ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_name'] ?? 'Nome'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_capacity'] ?? 'Capacidade'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_location'] ?? 'Localização'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_status'] ?? 'Estado'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_actions'] ?? 'Ações'; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if ($result_rooms_list && $result_rooms_list->num_rows > 0): ?>
                                        <?php while ($room = $result_rooms_list->fetch_assoc()):
                                            $status_class = '';
                                            $status_text = $room['status'];

                                            switch ($room['status']) {
                                                case 'disponivel':
                                                    $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                    $status_text = $t['status_disponivel'] ?? 'Disponível';
                                                    break;
                                                case 'indisponivel':
                                                    $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                    $status_text = $t['status_indisponivel'] ?? 'Indisponível';
                                                    break;
                                                case 'brevemente':
                                                    $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                    $status_text = $t['status_brevemente'] ?? 'Brevemente';
                                                    break;
                                                default:
                                                    $status_class = 'bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-400';
                                                    $status_text = $t['status_unknown'] ?? ucfirst($room['status']);
                                            }
                                            ?>
                                            <tr>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?php echo htmlspecialchars($room['nome']); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $room['capacidade']; ?>
                                                    <?php echo $t['salas_capacity_unit'] ?? 'pessoas'; ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($room['local']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                        <?php echo htmlspecialchars($status_text); ?>
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2 text-center">
                                                    <a href="admin.php?section=salas&editar=<?php echo $room['id']; ?>&rooms_page=<?php echo $rooms_page; ?>"
                                                        class="text-[#5d3e8f] hover:text-[#4a2f70] dark:text-[#d8b4fe] dark:hover:text-[#c084fc] transition">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a onclick="confirmDelete(<?php echo $room['id']; ?>)"
                                                        class="text-[#5d3e8f] hover:text-[#4a2f70] dark:text-[#d8b4fe] dark:hover:text-[#c084fc] transition cursor-pointer ml-4">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5"
                                                class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $t['salas_msg_empty'] ?? 'Nenhuma sala encontrada.'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_rooms_pages > 1): ?>
                            <div class="flex justify-center items-center space-x-2 mt-6">
                                <?php if ($rooms_page > 1): ?>
                                    <a href="admin.php?section=salas&rooms_page=<?php echo $rooms_page - 1; ?>"
                                        class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                        <?php echo $t['btn_previous'] ?? 'Anterior'; ?>
                                    </a>
                                <?php endif; ?>

                                <span
                                    class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] rounded-lg font-semibold text-sm">
                                    <?php echo $rooms_page; ?> / <?php echo $total_rooms_pages; ?>
                                </span>

                                <?php if ($rooms_page < $total_rooms_pages): ?>
                                    <a href="admin.php?section=salas&rooms_page=<?php echo $rooms_page + 1; ?>"
                                        class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                        <?php echo $t['btn_next'] ?? 'Próxima'; ?> <i class="fas fa-chevron-right text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </section>

                <section id="utilizadores" class="admin-section section-hidden">
                    <h2 class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe] mb-6 border-b pb-2">
                        <?php echo htmlspecialchars($section_users); ?>
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div
                            class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo $t['users_stats_total'] ?? 'Total de Professores'; ?>
                            </h4>
                            <p class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe]">
                                <?php echo $total_users_count; ?>
                            </p>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 ">
                            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo $t['users_stats_active'] ?? 'Professores Ativos (Hoje)'; ?>
                            </h4>
                            <p class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe]">
                                <?php echo $active_users_count; ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl   mb-8 transition-colors duration-300">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?php echo $t['users_list_title'] ?? 'Lista de Utilizadores'; ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            ID</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['label_name'] ?? 'Nome'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['label_email'] ?? 'Email'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['users_th_role'] ?? 'Tipo'; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if ($result_users && $result_users->num_rows > 0): ?>
                                        <?php mysqli_data_seek($result_users, 0); ?>
                                        <?php while ($user = $result_users->fetch_assoc()): ?>
                                            <tr>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?php echo $user['id']; ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['nome'] ?? 'N/A'); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black' : 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black'; ?>">
                                                        <?php echo $user['role'] === 'admin' ? ($t['role_admin'] ?? 'Administrador') : ($t['role_user'] ?? 'Professor'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4"
                                                class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $t['users_msg_empty'] ?? 'Nenhum utilizador encontrado.'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl  mb-8 transition-colors duration-300">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?php echo $t['users_inactive_list_title'] ?? 'Utilizadores Desativados'; ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            ID</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['label_name'] ?? 'Nome'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['label_email'] ?? 'Email'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['users_th_role'] ?? 'Tipo'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['users_th_actions'] ?? 'Ações'; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if ($result_users_inactive && $result_users_inactive->num_rows > 0): ?>
                                        <?php mysqli_data_seek($result_users_inactive, 0); ?>
                                        <?php while ($user = $result_users_inactive->fetch_assoc()): ?>
                                            <tr>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?php echo $user['id']; ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['nome'] ?? 'N/A'); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black' : 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black'; ?>">
                                                        <?php echo $user['role'] === 'admin' ? ($t['role_admin'] ?? 'Administrador') : ($t['role_user'] ?? 'Professor'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                    <button onclick="confirmReactivate(<?php echo $user['id']; ?>)"
                                                        class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-[#5d3e8f]  dark:hover:text-[#d8b4fe] transition"
                                                        title="<?php echo $t['users_btn_reactivate'] ?? 'Reativar Utilizador'; ?>">
                                                        <i class="fas fa-check-circle text-lg"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5"
                                                class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $t['users_inactive_msg_empty'] ?? 'Nenhum utilizador desativado.'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl  transition-colors duration-300">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?php echo $t['users_reservations_title'] ?? 'Reservas por Professor'; ?>
                        </h3>

                        <div class="mb-4">
                            <form method="GET" action="admin.php"
                                class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                                <input type="hidden" name="section" value="utilizadores">
                                <div>
                                    <label for="professor"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['users_label_filter'] ?? 'Filtrar por professor:'; ?></label>
                                    <div class="relative">
                                        <select id="professor" name="professor" onchange="this.form.submit()"
                                            class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] outline-none appearance-none transition-colors">
                                            <option value="0"><?php echo $t['users_filter_all'] ?? 'Todos'; ?></option>
                                            <?php
                                            if ($result_users) {
                                                mysqli_data_seek($result_users, 0);
                                                while ($user = $result_users->fetch_assoc()) {
                                                    if ($user['role'] === 'user') {
                                                        $selected = ($filtro_professor == $user['id']) ? 'selected' : '';
                                                        echo "<option value=\"{$user['id']}\" {$selected}>" . htmlspecialchars($user['nome'] ?? $user['email']) . "</option>";
                                                    }
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="status"
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $t['res_th_status'] ?? 'Filtrar por estado:'; ?></label>
                                    <div class="relative">
                                        <select id="status" name="status" onchange="this.form.submit()"
                                            class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] outline-none appearance-none transition-colors">
                                            <option value=""><?php echo $t['users_filter_all'] ?? 'Todos'; ?></option>
                                            <option value="ativa" <?php echo ($filtro_status === 'ativa') ? 'selected' : ''; ?>><?php echo $t['res_status_active'] ?? 'Ativa'; ?></option>
                                            <option value="concluida" <?php echo ($filtro_status === 'concluida') ? 'selected' : ''; ?>>
                                                <?php echo $t['res_status_completed'] ?? 'Concluída'; ?>
                                            </option>
                                            <option value="cancelada" <?php echo ($filtro_status === 'cancelada') ? 'selected' : ''; ?>>
                                                <?php echo $t['res_status_cancelled'] ?? 'Cancelada'; ?>
                                            </option>
                                        </select>
                                        <div
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <a href="admin.php?section=utilizadores"
                                    class="px-4 py-2.5 bg-[#5d3e8f] text-white dark:text-black dark:bg-[#d8b4fe]  font-semibold rounded-lg shadow-md transition hover:scale-105">
                                    <i class="fas fa-undo mr-2"></i>
                                    <?php echo $t['users_btn_reset'] ?? 'Limpar Filtro'; ?>
                                </a>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['users_th_professor'] ?? 'Professor'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['salas_th_name'] ?? 'Sala'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['res_th_date'] ?? 'Data'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['res_th_time'] ?? 'Horário'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['res_th_desc'] ?? 'Descrição'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['res_th_status'] ?? 'Status'; ?>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <?php echo $t['th_details'] ?? 'Detalhes'; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (is_object($result_reservas) && $result_reservas->num_rows > 0): ?>
                                        <?php while ($reserva = $result_reservas->fetch_assoc()):
                                            $reserva_data = [
                                                'id' => $reserva['id'],
                                                'sala' => $reserva['sala_nome'],
                                                'salaId' => $reserva['room_id'],
                                                'professor' => $reserva['professor_nome'] ?? $reserva['professor_email'],
                                                'data' => date('d/m/Y', strtotime($reserva['data'])),
                                                'horario' => substr($reserva['hora_inicio'], 0, 5) . ' - ' . substr($reserva['hora_fim'], 0, 5),
                                                'descricao' => $reserva['descricao'] ?? 'N/A'
                                            ];
                                            $json_data = htmlspecialchars(json_encode($reserva_data), ENT_QUOTES, 'UTF-8');

                                            $status_db = $reserva['status_reserva'] ?? 'ativa';
                                            $timestamp_fim = strtotime($reserva['data'] . ' ' . $reserva['hora_fim']);
                                            $is_past = $timestamp_fim < time();

                                            $status_class = '';
                                            $status_text = '';

                                            if ($status_db === 'cancelada') {
                                                $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                $status_text = $t['res_status_cancelled'] ?? 'Cancelada';
                                            } elseif ($status_db === 'concluida' || $is_past) {
                                                $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                $status_text = $t['res_status_completed'] ?? 'Concluída';
                                            } else {
                                                $status_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black';
                                                $status_text = $t['res_status_active'] ?? 'Ativa';
                                            }
                                            ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?php echo htmlspecialchars($reserva_data['professor']); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($reserva_data['sala']); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $reserva_data['data']; ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $reserva_data['horario']; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate"
                                                    title="<?php echo htmlspecialchars($reserva_data['descricao']); ?>">
                                                    <?php echo htmlspecialchars($reserva_data['descricao']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <a href="#"
                                                        onclick="showReservationDetails(<?php echo $json_data; ?>); return false;"
                                                        class="cursor-pointer text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-indigo-700 dark:hover:text-indigo-300 transition"
                                                        title="<?php echo $t['th_details'] ?? 'Ver Detalhes'; ?>">
                                                        <i class="fas fa-info-circle"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7"
                                                class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $t['users_reservations_empty'] ?? 'Nenhuma reserva encontrada.'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1):

                            $current_section = $_GET['section'] ?? 'utilizadores';
                            $filtro_prof_param = $filtro_professor > 0 ? "&professor={$filtro_professor}" : "";
                            $filtro_status_param = $filtro_status !== '' ? "&status={$filtro_status}" : "";
                            $base_url = "admin.php?section={$current_section}{$filtro_prof_param}{$filtro_status_param}&page=";
                            ?>
                            <div class="flex justify-center items-center space-x-2 mt-6">
                                <?php if ($current_page > 1): ?>
                                    <a href="<?php echo $base_url . ($current_page - 1); ?>"
                                        class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                        <?php echo $t['btn_previous'] ?? 'Anterior'; ?>
                                    </a>
                                <?php endif; ?>

                                <span
                                    class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe]  rounded-lg font-semibold text-sm">
                                    <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                                </span>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?php echo $base_url . ($current_page + 1); ?>"
                                        class="px-3 py-1 bg-[#5d3e8f] dark:bg-[#d8b4fe] text-white dark:text-black rounded-lg hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] transition">
                                        <?php echo $t['btn_next'] ?? 'Próxima'; ?> <i class="fas fa-chevron-right text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section id="ocupacao" class="admin-section section-hidden">
                    <h2 class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe] mb-6 border-b pb-2">
                        <?php echo htmlspecialchars($section_occupancy); ?>
                    </h2>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl  mb-8 transition-colors duration-300">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                    <?php echo $t['occupancy_title'] ?? 'Ocupação de Salas'; ?>
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
                                    <span class="relative flex h-3 w-3">
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#4f46e5] opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-[#5d3e8f]"></span>
                                    </span>
                                    <?php echo $t['occupancy_realtime_update'] ?? 'Atualização em tempo real.'; ?> <span
                                        id="last-updated" class="font-mono font-bold">--:--:--</span>
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <label for="occupancy_date"
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    <?php echo $t['occupancy_label_date'] ?? 'Data:'; ?>
                                </label>
                                <input type="date" id="occupancy_date" value="<?php echo date('Y-m-d'); ?>"
                                    class=" dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 focus:ring-[#5d3e8f] focus:border-[#5d3e8f] shadow-sm">
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-lg  custom-scrollbar">
                            <div id="occupancy-grid" class="min-w-[1000px]">
                                <div class="flex items-center justify-center p-12">
                                    <i class="fas fa-spinner fa-spin text-4xl text-[#5d3e8f]"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-6 mt-6 text-sm dark:text-gray-300 justify-end  pt-4">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-4 h-4 rounded bg-[#5d3e8f] dark:bg-[#d8b4fe] border border-[#5d3e8f] dark:border-[#d8b4fe]">
                                </div>
                                <span><?php echo $t['occupancy_occupied'] ?? 'Ocupado'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div
                            class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo $t['occupancy_total_rooms'] ?? 'Total de Salas'; ?>
                            </h4>
                            <p id="total-rooms-count"
                                class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe]">0</p>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo $t['occupancy_total_reservations'] ?? 'Reservas no Dia '; ?> <span
                                    id="occupancy-display-date"><?php echo date('d/m/Y'); ?></span>
                            </h4>
                            <p id="total-reservations-count"
                                class="text-3xl font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe]">0</p>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="text-center">
        <?php
        include_once 'includes/footer.php';
        ?>
    </div>

    <?php
    include_once 'includes/chat_support.php';
    ?>

    <script>
        const allSections = document.querySelectorAll('.admin-section');
        const navLinks = document.querySelectorAll('.nav-link');
        const currentSection = '<?php echo $admin_dashboard_current_section; ?>';
        const DEFAULT_IMAGE = 'https://ralfvanveen.com/wp-content/uploads/2021/06/Placeholder-_-Glossary.svg';

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

        function setActiveLink(sectionId) {
            navLinks.forEach(link => {
                link.classList.remove("bg-[#5d3e8f]", "text-white");
                link.classList.add("text-[#fff]");
                if (link.getAttribute('data-section') === sectionId) {
                    link.classList.add("bg-[#5d3e8f]", "text-white");
                    link.classList.remove("text-[#fff]");
                }
            });
        }

        function showSection(sectionId) {
            allSections.forEach(sec => sec.classList.add("section-hidden"));
            const activeSection = document.getElementById(sectionId);
            if (activeSection) {
                activeSection.classList.remove("section-hidden");
                setActiveLink(sectionId);

                if (sectionId === 'ocupacao') {
                    initOccupancy();
                }
            }
            if (window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
                }
            }
        }

        function navigateToSection(sectionId) {
            const url = new URL(window.location);
            url.searchParams.set('section', sectionId);
            window.history.pushState({}, '', url);
            showSection(sectionId);
        }

        function previewSlotImage(event, previewId, removeBtnId) {
            const reader = new FileReader();
            const preview = document.getElementById(previewId);
            const removeBtn = document.getElementById(removeBtnId);
            const fileInput = event.target;

            reader.onload = function () {
                if (reader.readyState == 2) {
                    preview.src = reader.result;
                    removeBtn.classList.add('opacity-100', 'pointer-events-auto');
                    removeBtn.classList.remove('opacity-0', 'pointer-events-none');
                }
            }
            if (fileInput.files.length > 0) {
                reader.readAsDataURL(fileInput.files[0]);
            }
        }

        function removeSlotImage(previewId, fileInputId, buttonElement, index) {
            const preview = document.getElementById(previewId);
            const hiddenInput = document.getElementsByName(`existing_photos[${index}]`)[0];
            const fileInput = document.getElementById(fileInputId);

            preview.src = DEFAULT_IMAGE;
            fileInput.value = '';
            hiddenInput.value = DEFAULT_IMAGE;

            if (buttonElement) {
                buttonElement.classList.remove('opacity-100', 'pointer-events-auto');
                buttonElement.classList.add('opacity-0', 'pointer-events-none');
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

        function confirmDelete(id) {
            Swal.fire({
                title: '<?php echo isset($t['salas_js_delete_title']) ? $t['salas_js_delete_title'] : "Eliminar esta Sala?"; ?>',
                text: '<?php echo isset($t['salas_js_delete_text']) ? $t['salas_js_delete_text'] : "Tem a certeza que deseja eliminar esta sala? Esta ação não pode ser desfeita."; ?>',
                icon: 'warning',
                iconColor: '#5d3e8f',
                showCancelButton: true,
                confirmButtonColor: '#5d3e8f',
                cancelButtonColor: '#aaa',
                confirmButtonText: '<?php echo isset($t['salas_js_btn_delete']) ? $t['salas_js_btn_delete'] : "Sim, Eliminar"; ?>',
                cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>',
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'backend/manage_rooms.php';

                    const inputs = [
                        { name: 'action', value: 'eliminar' },
                        { name: 'id', value: id },
                        { name: 'section', value: 'salas' },
                        { name: 'csrf_token', value: '<?php echo $_SESSION['csrf_token']; ?>' }
                    ];

                    inputs.forEach(data => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function confirmReactivate(id) {
            Swal.fire({
                title: '<?php echo isset($t['users_js_reactivate_title']) ? $t['users_js_reactivate_title'] : "Reativar Utilizador?"; ?>',
                text: '<?php echo isset($t['users_js_reactivate_text']) ? $t['users_js_reactivate_text'] : "Deseja reativar este utilizador? Ele poderá aceder novamente ao sistema."; ?>',
                icon: 'question',
                iconColor: '#5d3e8f',
                showCancelButton: true,
                confirmButtonColor: '#5d3e8f',
                cancelButtonColor: '#aaa',
                confirmButtonText: '<?php echo isset($t['users_js_btn_reactivate']) ? $t['users_js_btn_reactivate'] : "Sim, Reativar"; ?>',
                cancelButtonText: '<?php echo isset($t['btn_cancel']) ? $t['btn_cancel'] : "Cancelar"; ?>',
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'backend/manage_users.php';

                    const inputs = [
                        { name: 'action', value: 'reativar' },
                        { name: 'id', value: id },
                        { name: 'section', value: 'utilizadores' },
                        { name: 'csrf_token', value: '<?php echo $_SESSION['csrf_token']; ?>' }
                    ];

                    inputs.forEach(data => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function showSwalMessage(messageData) {
            if (!messageData) return;
            try {
                const data = JSON.parse(atob(messageData));
                let icon = data.type === 'success' ? 'success' : (data.type === 'warning' ? 'warning' : 'error');
                let title = data.type === 'success' ? 'Sucesso!' : (icon === 'warning' ? 'Aviso!' : 'Erro!');
                let text = data.text;

                if (data.warnings && data.warnings.length > 0) {
                    text += "\n\n" + data.warnings.map(w => w.text).join("\n");
                    if (icon !== 'error' && icon !== 'success') icon = 'warning';
                }

                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    iconColor: '#5d3e8f',
                    confirmButtonColor: '#5d3e8f',
                });
                const url = new URL(window.location.href);
                url.searchParams.delete('swal_msg');
                history.replaceState(null, '', url.toString());
            } catch (e) {
                console.error("Erro ao descodificar Swal:", e);
            }
        }

        let occupancyInterval;
        const tooltipId = 'global-occupancy-tooltip';

        if (!document.getElementById(tooltipId)) {
            const tooltipDiv = document.createElement('div');
            tooltipDiv.id = tooltipId;
            tooltipDiv.className = 'fixed hidden z-[9999] w-56 p-3 bg-[#2d1b4e] dark:bg-[#1a102e] text-gray-100 text-xs rounded-lg shadow-xl border border-[#5d3e8f]/30 dark:border-[#d8b4fe]/30 pointer-events-none leading-relaxed whitespace-pre-line text-left transition-opacity duration-150 backdrop-blur-sm';
            document.body.appendChild(tooltipDiv);
        }

        function initOccupancy() {
            const dateInput = document.getElementById('occupancy_date');
            if (!dateInput) return;

            if (occupancyInterval) clearInterval(occupancyInterval);

            fetchOccupancyData(dateInput.value);

            dateInput.addEventListener('change', (e) => {
                const container = document.getElementById('occupancy-grid');
                if (container) container.innerHTML = '<div class="flex items-center justify-center p-12"><i class="fas fa-spinner fa-spin text-4xl text-[#5d3e8f]"></i></div>';
                fetchOccupancyData(e.target.value);
            });

            occupancyInterval = setInterval(() => {
                const currentSec = new URLSearchParams(window.location.search).get('section');
                const sectionEl = document.getElementById('ocupacao');
                if (currentSec === 'ocupacao' || (sectionEl && !sectionEl.classList.contains('section-hidden'))) {
                    fetchOccupancyData(dateInput.value);
                }
            }, 10000);
        }

        function fetchOccupancyData(date) {
            // Atualizar o texto da data selecionada
            const dateDisplayEl = document.getElementById('occupancy-display-date');
            if (dateDisplayEl && date) {
                const parts = date.split('-');
                if (parts.length === 3) {
                    dateDisplayEl.textContent = `${parts[2]}/${parts[1]}/${parts[0]}`;
                }
            }

            fetch(`backend/api_occupancy.php?data=${date}`)
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede ou servidor');
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        renderOccupancyGrid(data.rooms || [], data.reservations || []);

                        const lastUpdatedEl = document.getElementById('last-updated');
                        if (lastUpdatedEl) lastUpdatedEl.innerText = data.updated_at || '--:--:--';

                        const totalRoomsEl = document.getElementById('total-rooms-count');
                        if (totalRoomsEl) totalRoomsEl.innerText = (data.rooms || []).length;

                        const totalResEl = document.getElementById('total-reservations-count');
                        if (totalResEl) totalResEl.innerText = (data.reservations || []).length;
                    } else {
                        console.error("Erro na API:", data.error);
                        showErrorGrid("Não foi possível carregar os dados: " + data.error);
                    }
                })
                .catch(err => {
                    console.error("Erro Fetch:", err);
                    showErrorGrid("Erro de ligação ao servidor.");
                });
        }

        function showErrorGrid(msg) {
            const container = document.getElementById('occupancy-grid');
            if (container) {
                container.innerHTML = `<div class="p-8 text-center text-red-500 font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i> ${msg}</div>`;
            }
        }

        function renderOccupancyGrid(rooms, reservations) {
            const container = document.getElementById('occupancy-grid');
            if (!container) return;

            if (rooms.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-gray-500">Nenhuma sala registada no sistema.</div>';
                return;
            }

            let html = '';


            html += '<div class="grid grid-cols-[150px_repeat(13,_minmax(80px,_1fr))] border-b border-gray-300 dark:border-gray-500 sticky top-0 z-20 shadow-sm">';
            html += '<div class="p-3 text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 flex items-center justify-center sticky left-0 z-30 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.1)] border-r border-gray-300 dark:border-gray-500"><?php echo $t['salas_th_name'] ?? 'Salas'; ?></div>';

            for (let h = 8; h <= 20; h++) {
                const time = h.toString().padStart(2, '0') + ':00';
                html += `<div class="p-3 text-xs font-bold text-center text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 border-r border-gray-300 dark:border-gray-600 last:border-0">${time}</div>`;
            }
            html += '</div>';

            rooms.forEach(room => {
                html += '<div class="grid grid-cols-[150px_repeat(13,_minmax(80px,_1fr))] hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border-b border-gray-300 dark:border-gray-600">';

                html += `<div class="p-3 text-sm font-semibold text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 flex items-center border-r border-gray-300 dark:border-gray-500 sticky left-0 z-10 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.1)]">
                            ${room.nome}
                         </div>`;

                for (let h = 8; h <= 20; h++) {
                    const currentHourStart = h;

                    const reserva = reservations.find(r => {
                        const rStart = parseInt(r.hora_inicio.split(':')[0]);
                        const rEnd = parseInt(r.hora_fim.split(':')[0]);
                        const rEndReal = r.hora_fim.endsWith('00:00') ? rEnd : rEnd + 1;

                        return parseInt(r.room_id) === parseInt(room.id) &&
                            (currentHourStart >= rStart && currentHourStart < rEndReal);
                    });

                    if (reserva) {
                        const tooltipContent = `<span class="font-semibold text-[#d8b4fe]"><?php echo $t['users_th_professor'] ?? 'Professor'; ?>:</span> ${reserva.professor_nome}\n` +
                            `<span class="font-semibold text-[#d8b4fe]"><?php echo $t['res_th_time'] ?? 'Horário'; ?>:</span> ${reserva.hora_inicio.slice(0, 5)} - ${reserva.hora_fim.slice(0, 5)}\n` +
                            `<span class="font-semibold text-[#d8b4fe]"><?php echo $t['res_th_desc'] ?? 'Descrição'; ?>:</span> ${reserva.descricao}`;

                        html += `<div class="h-14 p-1 border-r border-gray-300 dark:border-gray-600 relative group-cell"
                                     onmousemove="showTooltip(event, this)"
                                     onmouseleave="hideTooltip()">
                                    <div class="w-full h-full bg-[#5d3e8f] dark:bg-[#d8b4fe] rounded shadow-sm cursor-help flex items-center justify-center transition-all hover:bg-[#4a327a] dark:hover:bg-[#c084fc]">
                                        <span class="text-[10px] dark:text-black text-white font-bold truncate px-1 w-full text-center hidden md:block">
                                            <?php echo $t['occupancy_occupied'] ?? 'Ocupado'; ?>
                                        </span>
                                    </div>
                                    <div class="hidden tooltip-data">${tooltipContent}</div>
                                 </div>`;
                    } else {
                        html += `<div class="h-14 p-1 border-r border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                <div class="w-full h-full rounded transition-colors hover:bg-[#5d3e8f]/10 dark:hover:bg-[#d8b4fe]/10"></div>
                                 </div>`;
                    }
                }
                html += '</div>';
            });

            container.innerHTML = html;
        }

        function showTooltip(e, el) {
            const tooltipEl = document.getElementById(tooltipId);
            const dataDiv = el.querySelector('.tooltip-data');
            if (!dataDiv) return;

            tooltipEl.innerHTML = dataDiv.innerHTML;
            tooltipEl.classList.remove('hidden');

            const rect = tooltipEl.getBoundingClientRect();
            const padding = 15;

            let top = e.clientY + padding;
            let left = e.clientX + padding;

            if (left + rect.width > window.innerWidth) left = e.clientX - rect.width - padding;
            if (top + rect.height > window.innerHeight) top = e.clientY - rect.height - padding;

            tooltipEl.style.top = `${top}px`;
            tooltipEl.style.left = `${left}px`;
        }

        function hideTooltip() {
            const tooltipEl = document.getElementById(tooltipId);
            if (tooltipEl) tooltipEl.classList.add('hidden');
        }


        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            const swalMsg = urlParams.get('swal_msg');
            if (swalMsg) showSwalMessage(swalMsg);

            showSection(currentSection);

            navLinks.forEach(link => {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    const sectionId = this.getAttribute('data-section');
                    navigateToSection(sectionId);
                });
            });

            window.addEventListener('popstate', () => {
                const params = new URLSearchParams(window.location.search);
                const section = params.get('section') || 'dashboard';
                showSection(section);
            });

            const salaForm = document.querySelector('#salas form');
            if (salaForm) {
                salaForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const action = this.querySelector('input[name="action"]').value;
                    const isEdit = action === 'editar';

                    Swal.fire({
                        title: isEdit ? 'Confirmar Edição?' : 'Confirmar Criação?',
                        text: isEdit ? 'Deseja guardar as alterações?' : 'Deseja adicionar esta nova sala?',
                        icon: 'question',
                        iconColor: '#5d3e8f',
                        showCancelButton: true,
                        confirmButtonColor: '#5d3e8f',
                        cancelButtonColor: '#aaa',
                        confirmButtonText: 'Sim',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) this.submit();
                    });
                });
            }
        });

        function showReservationDetails(reserva) {

            Swal.fire({
                title: '<?php echo $t['js_loading'] ?? 'A carregar detalhes...'; ?>',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading() },
                iconColor: '#5d3e8f',
            });


            fetch(`backend/admin_room_details.php?room_id=${reserva.salaId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        Swal.fire({ title: 'Erro!', text: data.error, icon: 'error', confirmButtonColor: '#5d3e8f' });
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
                        title: `Detalhes da Reserva #${reserva.id}`,
                        html: detailContent,
                        width: '600px',
                        icon: 'info',
                        iconColor: '#5d3e8f',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $t['salas_btn_update'] ?? 'Gerir Sala'; ?>',
                        cancelButtonText: '<?php echo $t['btn_cancel'] ?? 'Fechar'; ?>',
                        confirmButtonColor: '#5d3e8f',
                        cancelButtonColor: '#aaa',
                        reverseButtons: true,

                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                        color: document.documentElement.classList.contains('dark') ? '#fff' : '#000'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `admin.php?section=salas&editar=${reserva.salaId}`;
                        }
                    });

                })
                .catch(error => {
                    console.error('Erro de rede/parsing:', error);
                    Swal.fire({ title: 'Erro de Comunicação!', text: 'Não foi possível obter detalhes da sala.', icon: 'error', confirmButtonColor: '#5d3e8f' });
                });
        }
    </script>

</body>

</html>