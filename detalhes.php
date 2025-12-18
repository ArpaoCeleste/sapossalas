<?php
session_start();
require_once 'backend/config.php';

$rootPath = './';
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $current_lang_code = $_SESSION['lang'] ?? 'pt';
    $t = [];
}

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo "<script>";
    echo "alert('Acesso negado: Por questões de permissões e separação de funções, o administrador não pode requisitar salas através desta interface.');";
    echo "window.location.href = 'admin.php';";
    echo "</script>";
    exit;
}

function getRoomImagePath($rawPath)
{
    $DEFAULT_PLACEHOLDER_URL = 'https://via.placeholder.com/600x400?text=Sem+Imagem';
    if (!empty($rawPath) && filter_var($rawPath, FILTER_VALIDATE_URL)) {
        return $rawPath;
    }
    return $DEFAULT_PLACEHOLDER_URL;
}

$room_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data_selecionada = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

if ($data_selecionada < date('Y-m-d')) {
    $data_selecionada = date('Y-m-d');
}

$sql_room = "SELECT *, status FROM rooms WHERE id = ?"; // Adicionar 'status' à seleção
$stmt = $conn->prepare($sql_room);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die(isset($t['msg_room_not_found']) ? $t['msg_room_not_found'] : "Sala não encontrada.");
}


if ($room['status'] !== 'disponivel') {

    header("Location: reservar.php?error=sala_indisponivel_acesso");
    exit;
}

$user_id_atual = $_SESSION['user_id'] ?? null;


$user_id_atual = $_SESSION['user_id'] ?? null;
if (!$user_id_atual) {
    $stmt_user_id = $conn->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt_user_id->bind_param("s", $_SESSION['user_email']);
    $stmt_user_id->execute();
    $user_id_atual = $stmt_user_id->get_result()->fetch_assoc()['id'] ?? null;
    $stmt_user_id->close();
    if ($user_id_atual) {
        $_SESSION['user_id'] = $user_id_atual;
    }
}


$reservas_sala = [];
$sql_res_sala = "SELECT hora_inicio, hora_fim, user_id FROM reservas WHERE room_id = ? AND data = ? AND status_reserva = 'ativa'";
$stmt_res_sala = $conn->prepare($sql_res_sala);
$stmt_res_sala->bind_param("is", $room_id, $data_selecionada);
$stmt_res_sala->execute();
$result_res_sala = $stmt_res_sala->get_result();

while ($row = $result_res_sala->fetch_assoc()) {
    $reservas_sala[] = $row;
}
$stmt_res_sala->close();



$reservas_pessoais = [];
if ($user_id_atual) {
    $sql_res_pessoal = "SELECT hora_inicio, hora_fim, room_id FROM reservas WHERE user_id = ? AND data = ? AND status_reserva = 'ativa'";
    $stmt_res_pessoal = $conn->prepare($sql_res_pessoal);
    $stmt_res_pessoal->bind_param("is", $user_id_atual, $data_selecionada);
    $stmt_res_pessoal->execute();
    $result_res_pessoal = $stmt_res_pessoal->get_result();

    while ($row = $result_res_pessoal->fetch_assoc()) {
        $reservas_pessoais[] = $row;
    }
    $stmt_res_pessoal->close();
}


$defaultImage = 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=1000';
$placeholderUrl = 'https://ralfvanveen.com/wp-content/uploads/2021/06/Placeholder-_-Glossary.svg?text=Foto+';

$imagem_sala = getRoomImagePath($room['imagem']);

$galeria = [];

if (!empty($room['fotos'])) {
    $db_photos = json_decode($room['fotos'], true);
    if (is_array($db_photos)) {
        foreach ($db_photos as $photo) {
            $galeria[] = getRoomImagePath($photo);
        }
    }
}

if (empty($galeria) || ($galeria[0] !== $imagem_sala && $imagem_sala !== $defaultImage)) {
    array_unshift($galeria, $imagem_sala);
    $galeria = array_unique($galeria);
}

while (count($galeria) < 4) {
    $index = count($galeria);
    $galeria[] = $placeholderUrl . ($index + 1);
}

$galeria = array_slice($galeria, 0, 4);

$hora_atual = (int) date('H');
$is_hoje = ($data_selecionada === date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">

    <title><?php echo isset($t['title_reserva']) ? $t['title_reserva'] : 'Reservar'; ?>
        <?php echo htmlspecialchars($room['nome']); ?> - SAPOSalas</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .tab-active {
            border-bottom: 2px solid #5d3e8f;
            color: #5d3e8f;
            font-weight: 600;
        }

        .tab-inactive {
            color: #6b7280;
        }


        .dark .tab-active {
            border-bottom: 2px solid #d8b4fe;
            color: #d8b4fe;
        }

        .dark .tab-inactive {
            color: #9ca3af;
        }

        .tab-content {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
            opacity: 1;
        }

        .time-slot-free:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 62, 143, 0.15);
        }

        .dark .time-slot-free:hover {
            box-shadow: 0 4px 12px rgba(216, 180, 254, 0.15);
        }

        .thumbnail.active {
            border-color: #5d3e8f;
            opacity: 1;
        }

        .dark .thumbnail.active {
            border-color: #d8b4fe;
        }
    </style>
</head>

<body
    class="bg-gray-50 text-gray-800 dark:bg-gray-900 font-['Poppins'] dark:text-gray-100 transition-colors duration-300">

    <?php include 'includes/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-12">

        <div class="mb-6 lg:mb-8">
            <a href="reservar.php"
                class="inline-flex items-center text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe] transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                <?php echo isset($t['link_back']) ? $t['link_back'] : 'Voltar à Lista'; ?>
            </a>
        </div>

        <div
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 sm:p-6 lg:p-10 transition-colors duration-300">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">

                <div class="space-y-4">
                    <div
                        class="aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-700 shadow-md relative group border border-gray-200 dark:border-gray-600">
                        <img id="mainImage" src="<?php echo htmlspecialchars($galeria[0]); ?>"
                            class="w-full h-full object-cover transition-transform duration-500">
                        <div
                            class="absolute top-4 right-4 bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-1 rounded-full text-xs font-bold text-[#5d3e8f] dark:text-[#d8b4fe] shadow-sm">
                            <i class="fas fa-users mr-1"></i> <?php echo htmlspecialchars($room['capacidade']); ?>
                            <?php echo isset($t['places']) ? $t['places'] : 'Lugares'; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 sm:gap-4">
                        <?php foreach ($galeria as $index => $imgUrl): ?>
                            <div onclick="updateImage('<?php echo htmlspecialchars($imgUrl); ?>', this)"
                                class="thumbnail aspect-square rounded-xl overflow-hidden cursor-pointer border-2 border-transparent hover:border-[#5d3e8f] dark:hover:border-[#d8b4fe] transition-all opacity-70 hover:opacity-100 <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-full h-full object-cover">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex flex-col">
                    <div class="mb-6">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                            <?php echo htmlspecialchars($room['nome']); ?></h1>
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-map-marker-alt text-[#5d3e8f] dark:text-[#d8b4fe] mr-2"></i>
                            <?php echo htmlspecialchars($room['local']); ?>
                        </div>
                    </div>

                    <div class="h-px bg-gray-200 dark:bg-gray-700 w-full mb-8"></div>

                    <form method="GET" action="detalhes.php" class="mb-6">
                        <input type="hidden" name="id" value="<?php echo $room_id; ?>">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo isset($t['label_select_date']) ? $t['label_select_date'] : 'Selecionar Data'; ?>
                        </label>
                        <div class="relative">
                            <input type="date" name="data" value="<?php echo $data_selecionada; ?>"
                                min="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()"
                                class="w-full pl-4 pr-10 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-200 font-medium focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent outline-none cursor-pointer transition-colors">
                            <div
                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">

                            </div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 italic">
                            *
                            <?php echo isset($t['alert_12h_notice_short']) ? $t['alert_12h_notice_short'] : 'Reserva requer 12h de antecedência.'; ?>
                        </p>
                    </form>

                    <form method="POST" action="backend/processar_reserva.php" class="space-y-6 flex-grow">
                        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="data" value="<?php echo $data_selecionada; ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <?php echo isset($t['label_start_time']) ? $t['label_start_time'] : 'Hora Início'; ?>
                                </label>
                                <div class="relative">
                                    <select id="hora_inicio" name="hora_inicio" required
                                        class="w-full pl-4 pr-10 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] outline-none appearance-none transition-colors">
                                        <option value="">--:--</option>
                                        <?php
                                        for ($h = 8; $h <= 19; $h++) {
                                            if ($is_hoje && $h <= $hora_atual)
                                                continue;
                                            $hora = sprintf("%02d:00", $h);
                                            echo "<option value='{$hora}'>{$hora}</option>";
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
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    <?php echo isset($t['label_end_time']) ? $t['label_end_time'] : 'Hora Fim'; ?>
                                </label>
                                <div class="relative">
                                    <select id="hora_fim" name="hora_fim" required
                                        class="w-full pl-4 pr-10 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] outline-none appearance-none transition-colors">
                                        <option value="">--:--</option>
                                        <?php
                                        for ($h = 9; $h <= 20; $h++) {
                                            if ($is_hoje && $h <= $hora_atual)
                                                continue;
                                            $hora = sprintf("%02d:00", $h);
                                            echo "<option value='{$hora}'>{$hora}</option>";
                                        }
                                        ?>
                                    </select>
                                    <div
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <?php echo isset($t['label_reason']) ? $t['label_reason'] : 'Motivo'; ?>
                                <span
                                    class="text-gray-400 font-normal text-xs"><?php echo isset($t['label_optional']) ? $t['label_optional'] : '(Opcional)'; ?></span>
                            </label>
                            <textarea name="descricao" rows="2"
                                class="w-full p-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] outline-none resize-none transition-colors"
                                placeholder="<?php echo isset($t['placeholder_reason']) ? $t['placeholder_reason'] : 'Ex: Reunião de projeto'; ?>"></textarea>
                        </div>

                        <button type="submit"
                            class="w-full py-3 sm:py-4 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:hover:bg-[#c084fc] text-white dark:text-gray-900 font-bold rounded-xl shadow-lg shadow-[#5d3e8f]/20 dark:shadow-[#d8b4fe]/20 transition-all transform hover:scale-110 duration-1000 flex items-center justify-center gap-2">
                            <span><?php echo isset($t['btn_confirm_booking']) ? $t['btn_confirm_booking'] : 'Confirmar Reserva'; ?></span>
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-12 lg:mt-16">
                <div
                    class="border-b border-gray-200 dark:border-gray-700 mb-6 lg:mb-8 overflow-x-auto transition-colors">
                    <div class="flex gap-4 sm:gap-8 min-w-max px-1">
                        <button onclick="switchTab('availability')" id="tab-availability"
                            class="pb-3 sm:pb-4 text-sm font-medium transition-colors tab-active">
                            <?php echo isset($t['tab_availability']) ? $t['tab_availability'] : 'Disponibilidade'; ?>
                        </button>
                        <button onclick="switchTab('description')" id="tab-description"
                            class="pb-3 sm:pb-4 text-sm font-medium transition-colors tab-inactive hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]">
                            <?php echo isset($t['tab_description']) ? $t['tab_description'] : 'Descrição'; ?>
                        </button>
                        <button onclick="switchTab('equipment')" id="tab-equipment"
                            class="pb-3 sm:pb-4 text-sm font-medium transition-colors tab-inactive hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]">
                            <?php echo isset($t['tab_equipment']) ? $t['tab_equipment'] : 'Equipamentos'; ?>
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-400 dark:text-gray-500 mb-6 italic" id="notice-availability">
                    *
                    <?php echo isset($t['alert_12h_notice_short']) ? $t['alert_12h_notice_short'] : 'Reserva requer 12h de antecedência.'; ?>
                </p>

                <div id="content-availability" class="tab-content active">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 transition-colors">
                        <?php echo isset($t['text_schedules_for']) ? $t['text_schedules_for'] : 'Horários para'; ?>
                        <?php echo date('d/m/Y', strtotime($data_selecionada)); ?>
                    </h3>
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 sm:gap-3">
                        <?php
                        for ($h = 8; $h <= 19; $h++) {
                            $hora_check = sprintf("%02d:00:00", $h);
                            $ocupado_sala = false;
                            $conflito_pessoal = false;


                            foreach ($reservas_sala as $reserva) {
                                if ($hora_check >= $reserva['hora_inicio'] && $hora_check < $reserva['hora_fim']) {
                                    $ocupado_sala = true;
                                    break;
                                }
                            }


                            if ($user_id_atual) {
                                foreach ($reservas_pessoais as $reserva) {

                                    if ((int) $reserva['room_id'] !== (int) $room_id) {
                                        if ($hora_check >= $reserva['hora_inicio'] && $hora_check < $reserva['hora_fim']) {
                                            $conflito_pessoal = true;
                                            break;
                                        }
                                    }
                                }
                            }

                            $passou = ($is_hoje && $h <= $hora_atual);

                            if ($passou) {
                                echo '
                <div class="flex flex-col items-center justify-center py-3 rounded-lg bg-gray-100 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-gray-300 dark:text-gray-500 cursor-not-allowed opacity-70 transition-colors">
                    <span class="font-bold text-sm">' . sprintf("%02d", $h) . '</span>
                    <span class="text-[9px] uppercase font-bold mt-1">' . (isset($t['status_passed']) ? $t['status_passed'] : 'Passou') . '</span>
                </div>';
                            } elseif ($conflito_pessoal) {

                                echo '
                <div class="flex flex-col items-center justify-center py-3 rounded-lg bg-red-50 dark:bg-red-900/40 border-2 border-red-500 dark:border-red-400 text-red-600 dark:text-red-400 cursor-not-allowed transition-colors relative group">
                    <span class="font-bold text-sm">' . sprintf("%02d", $h) . '</span>
                    <span class="text-[9px] uppercase font-bold mt-1">' . (isset($t['status_conflict_short']) ? $t['status_conflict_short'] : 'Conflito') . '</span>
                    <div class="absolute inset-0 flex items-center justify-center p-1 opacity-0 group-hover:opacity-100 bg-gray-800/90 text-white rounded-lg text-xs text-center leading-tight transition-opacity duration-300">
                         ' . (isset($t['status_conflict_full']) ? $t['status_conflict_full'] : 'Ocupado noutra sala.') . '
                    </div>
                </div>';
                            } elseif ($ocupado_sala) {

                                echo '
                <div class="flex flex-col items-center justify-center py-3 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-400 dark:text-gray-500 cursor-not-allowed transition-colors">
                    <span class="font-bold text-sm">' . sprintf("%02d", $h) . '</span>
                    <span class="text-[9px] uppercase font-bold mt-1">' . (isset($t['status_occupied']) ? $t['status_occupied'] : 'Ocupado') . '</span>
                </div>';
                            } else {

                                echo '
                <div class="time-slot-free flex flex-col items-center justify-center py-3 rounded-lg border border-[#5d3e8f] dark:border-[#d8b4fe] bg-white dark:bg-gray-800 text-[#5d3e8f] dark:text-[#d8b4fe] cursor-pointer transition-all" onclick="selectTime(' . $h . ')">
                    <span class="font-bold text-sm">' . sprintf("%02d", $h) . '</span>
                    <span class="text-[9px] uppercase font-bold mt-1">' . (isset($t['status_free']) ? $t['status_free'] : 'Livre') . '</span>
                </div>';
                            }
                        }
                        ?>
                    </div>
                </div>

                <div id="content-description" class="tab-content">
                    <div
                        class="prose max-w-none text-gray-600 dark:text-gray-300 text-sm sm:text-base transition-colors">
                        <?php echo !empty($room['descricao']) ? nl2br(htmlspecialchars($room['descricao'])) : '<p class="italic text-gray-400">' . (isset($t['msg_no_desc']) ? $t['msg_no_desc'] : 'Sem descrição disponível.') . '</p>'; ?>
                    </div>
                </div>

                <div id="content-equipment" class="tab-content">
                    <?php
                    $equip_str = $room['equipamentos'] ?? '';

                    $equip_tags = array_filter(array_map('trim', explode(',', $equip_str)));

                    if (!empty($equip_tags)):

                        $iconMap = [
                            'projetor' => 'fa-video',
                            'projector' => 'fa-video',
                            'wifi' => 'fa-wifi',
                            'wi-fi' => 'fa-wifi',
                            'internet' => 'fa-wifi',
                            'rede' => 'fa-network-wired',
                            'ethernet' => 'fa-network-wired',
                            'ac' => 'fa-snowflake',
                            'ar condicionado' => 'fa-snowflake',
                            'climatização' => 'fa-snowflake',
                            'aquecimento' => 'fa-temperature-high',
                            'quadro' => 'fa-chalkboard-user',
                            'lousa' => 'fa-chalkboard-user',
                            'tv' => 'fa-tv',
                            'televisão' => 'fa-tv',
                            'ecrã' => 'fa-tv',
                            'monitor' => 'fa-desktop',
                            'pc' => 'fa-desktop',
                            'computador' => 'fa-desktop',
                            'portátil' => 'fa-laptop',
                            'som' => 'fa-volume-high',
                            'colunas' => 'fa-volume-high',
                            'audio' => 'fa-volume-high',
                            'microfone' => 'fa-microphone',
                            'mic' => 'fa-microphone',
                            'câmara' => 'fa-camera',
                            'webcam' => 'fa-camera',
                            'mesa' => 'fa-table',
                            'secretária' => 'fa-table',
                            'cadeira' => 'fa-chair',
                            'tomada' => 'fa-plug',
                            'energia' => 'fa-bolt',
                            'impressora' => 'fa-print',
                            'acessibilidade' => 'fa-wheelchair',
                            'cadeira de rodas' => 'fa-wheelchair',
                            'janela' => 'fa-border-all',
                            'luz natural' => 'fa-sun'
                        ];
                        ?>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <?php foreach ($equip_tags as $tag):
                                $iconClass = 'fa-check';
                                $tagLower = mb_strtolower($tag, 'UTF-8');

                                foreach ($iconMap as $key => $icon) {
                                    if (strpos($tagLower, $key) !== false) {
                                        $iconClass = $icon;
                                        break;
                                    }
                                }
                                ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 transition-colors hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-gray-300 select-none">
                                    <i class="fa-solid <?php echo $iconClass; ?> mr-2 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                                    <?php echo htmlspecialchars($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-4 h-8 flex items-center text-gray-400 dark:text-gray-500 text-sm italic">
                            <?php echo isset($t['no_equip']) ? $t['no_equip'] : 'Sem equipamentos listados'; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="text-center">
        <?php include_once 'includes/footer.php'; ?>
    </div>

    <?php
    include_once 'includes/chat_support.php';
    ?>

    <script>
        function updateImage(src, element) {
            const mainImage = document.getElementById('mainImage');
            const isDark = document.documentElement.classList.contains('dark');
            const activeBorder = isDark ? 'border-[#d8b4fe]' : 'border-[#5d3e8f]';

            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 1);

            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active', 'border-[#5d3e8f]', 'border-[#d8b4fe]', 'opacity-100');
                thumb.classList.add('border-transparent', 'opacity-70');
            });

            element.classList.remove('border-transparent', 'opacity-70');
            element.classList.add('active', activeBorder, 'opacity-100');
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('button[id^="tab-"]').forEach(el => {
                el.classList.remove('tab-active');
                el.classList.add('tab-inactive');
            });

            document.getElementById('content-' + tabId).classList.add('active');
            const activeBtn = document.getElementById('tab-' + tabId);
            activeBtn.classList.remove('tab-inactive');
            activeBtn.classList.add('tab-active');
        }

        function selectTime(hour) {
            const startSelect = document.getElementById('hora_inicio');
            const endSelect = document.getElementById('hora_fim');
            const isDark = document.documentElement.classList.contains('dark');
            const ringColor = isDark ? 'ring-[#d8b4fe]' : 'ring-[#5d3e8f]';

            let startVal = (hour < 10 ? '0' : '') + hour + ':00';
            let startOptionExists = [...startSelect.options].some(o => o.value === startVal);

            if (startOptionExists) {
                startSelect.value = startVal;
                let endHour = hour + 1;
                let endVal = (endHour < 10 ? '0' : '') + endHour + ':00';
                let endOptionExists = [...endSelect.options].some(o => o.value === endVal);
                if (endOptionExists) {
                    endSelect.value = endVal;
                }
                startSelect.scrollIntoView({ behavior: "smooth", block: "center" });
                startSelect.classList.add('ring-2', ringColor);
                setTimeout(() => startSelect.classList.remove('ring-2', ringColor), 500);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: '<?php echo isset($t['alert_unavailable_title']) ? $t['alert_unavailable_title'] : "Horário Indisponível"; ?>',
                    text: '<?php echo isset($t['alert_unavailable_text']) ? $t['alert_unavailable_text'] : "Este horário já passou e não pode ser selecionado."; ?>',
                    confirmButtonColor: '#5d3e8f'
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            const suggestions = urlParams.get('suggestions');

            const errorMessages = {
                'campos_obrigatorios': 'Por favor, preencha todos os campos obrigatórios.',
                'data_invalida': 'A data selecionada é inválida.',
                'data_passada': 'Não é possível efetuar reservas no passado.',
                'hora_invalida': 'A hora de fim deve ser posterior à hora de início.',
                'sala_indisponivel': 'A sala já se encontra ocupada neste horário.',
                'user_ocupado': 'Já tem uma reserva que se sobrepõe a este horário. Não pode estar em duas salas ao mesmo tempo.', // Adicionado
                'erro_bd': 'Ocorreu um erro interno. Por favor, tente novamente.',
                'antecedencia_12h': 'A reserva deve ser feita com, pelo menos, 12 horas de antecedência.'
            };

            if (success === 'reserva_criada') {
                const date = urlParams.get('date');
                const start = urlParams.get('start');
                const end = urlParams.get('end');
                let msgText = '<?php echo isset($t['alert_confirmed_text']) ? $t['alert_confirmed_text'] : "A sua reserva foi registada com sucesso."; ?>';
                if (date && start && end) {
                    const [year, month, day] = date.split('-');
                    const formattedDate = `${day}/${month}/${year}`;
                    msgText = `${msgText}<br><b>${formattedDate}</b> (${start} - ${end})`;
                }
                Swal.fire({
                    title: '<?php echo isset($t['alert_confirmed_title']) ? $t['alert_confirmed_title'] : "Reserva Confirmada!"; ?>',
                    html: `<div class="text-gray-600 mb-4">${msgText}</div>`,
                    icon: 'success',
                    iconColor: '#5d3e8f',
                    confirmButtonColor: '#5d3e8f',
                    confirmButtonText: '<?php echo isset($t['alert_btn_great']) ? $t['alert_btn_great'] : "Excelente"; ?>'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname + '?id=' + urlParams.get('id'));
                });
            }

            if (error) {
                let message = errorMessages[error] || 'Ocorreu um erro desconhecido.';
                let htmlContent = `<div class="text-gray-600">${message}</div>`;
                let swalTitle = '<?php echo isset($t['alert_attention']) ? $t['alert_attention'] : "Atenção"; ?>';

                if (error === 'sala_indisponivel' && suggestions) {
                    const decodedSuggestions = decodeURIComponent(suggestions);
                    const suggestionsList = decodedSuggestions.split(', ').map(s =>
                        `<div class="bg-indigo-50 text-[#5d3e8f] px-3 py-2 rounded-lg font-semibold text-sm mb-2 border border-indigo-100 flex items-center justify-center gap-2"><i class="far fa-clock"></i> ${s}</div>`
                    ).join('');
                    htmlContent = `<div class="text-left"><p class="text-red-500 font-bold mb-3 text-center">${errorMessages['sala_indisponivel']}</p><p class="text-gray-500 text-sm mb-3 font-medium">Sugestões de horários livres:</p><div class="space-y-1">${suggestionsList}</div></div>`;
                    swalTitle = '<?php echo isset($t['alert_unavailable_title']) ? $t['alert_unavailable_title'] : "Horário Ocupado"; ?>';

                } else if (error === 'antecedencia_12h' || error === 'user_ocupado') { // Novo tratamento de título
                    swalTitle = '<?php echo isset($t['alert_unavailable_title']) ? $t['alert_unavailable_title'] : "Horário Ocupado"; ?>';
                }


                Swal.fire({
                    title: swalTitle,
                    html: htmlContent,
                    icon: 'error',
                    iconColor: '#5d3e8f',
                    textColor: '#5d3e8f',
                    confirmButtonColor: '#5d3e8f',
                    confirmButtonText: '<?php echo isset($t['alert_btn_understood']) ? $t['alert_btn_understood'] : "Entendido"; ?>'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname + '?id=' + urlParams.get('id'));
                });
            }
        });
    </script>
</body>

</html>