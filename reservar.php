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

function getRoomImagePath($rawPath) {
    $DEFAULT_PLACEHOLDER_URL = 'https://via.placeholder.com/600x400?text=Sem+Imagem';
    if (!empty($rawPath) && filter_var($rawPath, FILTER_VALIDATE_URL)) {
        return $rawPath;
    }
    return $DEFAULT_PLACEHOLDER_URL;
}

$min_capacity = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 0;
$location = $_GET['location'] ?? '';


$sql = "SELECT id, nome, capacidade, local, imagem, equipamentos, status FROM rooms WHERE status != 'indisponivel'";
$params = [];
$types = '';

if (!empty($location)) {
    $sql .= " AND local = ?";
    $params[] = $location;
    $types .= 's';
}

if ($min_capacity > 0) {
    $sql .= " AND capacidade >= ?";
    $params[] = $min_capacity;
    $types .= 'i';
}

$sql .= " ORDER BY status DESC, nome";

$result_locations = $conn->query("SELECT DISTINCT local FROM rooms WHERE status = 'disponivel' ORDER BY local");


if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_all_filtered_rooms = $stmt->get_result();
        $stmt->close();
    } else {
        $result_all_filtered_rooms = null;
    }
} else {
    $result_all_filtered_rooms = $conn->query($sql);
}



$result_rooms = [];
$result_upcoming_rooms = [];

if ($result_all_filtered_rooms) {
    while ($row = $result_all_filtered_rooms->fetch_assoc()) {
        if ($row['status'] == 'brevemente') {
            $result_upcoming_rooms[] = $row;
        } else {
            $result_rooms[] = $row;
        }
    }
}

$count_available_rooms = count($result_rooms);
$count_upcoming_rooms = count($result_upcoming_rooms);

?>
<!DOCTYPE html>
<html lang="pt" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t['page_title_reserva']) ? $t['page_title_reserva'] : 'Salas Disponíveis'; ?> - SAPOSalas</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #5d3e8f; }
        
        .dark ::-webkit-scrollbar-track { background: #1f2937; }
        .dark ::-webkit-scrollbar-thumb { background: #4b5563; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-300">

    <?php include_once 'includes/navbar.php'; ?>

    <div class="pt-24 min-h-screen pb-12">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-4 transition-colors duration-300">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white transition-colors duration-300">
                    <?php echo isset($t['title_reserva']) ? $t['title_reserva'] : 'Reserva de Salas'; ?>
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1 transition-colors duration-300">
                    <?php echo isset($t['subtitle_reserva']) ? $t['subtitle_reserva'] : 'Encontre o espaço perfeito para a sua reunião ou estudo.'; ?>
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8 items-start">

                <aside class="w-full lg:w-1/4 lg:top-28 z-10">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 transition-all duration-300">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-bold text-[#5d3e8f] dark:text-[#d8b4fe] flex items-center gap-2 transition-colors duration-300">
                                <i class="fa-solid fa-sliders"></i>
                                <?php echo isset($t['filter_title']) ? $t['filter_title'] : 'Filtros'; ?>
                            </h2>
                            <?php if($min_capacity > 0 || !empty($location)): ?>
                                <a href="reservar.php" class="text-xs text-[#5d3e8f] hover:[#5d3e8f] dark:text-[#d8b4fe] dark:hover:[#d8b4fe] font-medium transition">
                                    <?php echo isset($t['btn_clear']) ? $t['btn_clear'] : 'Limpar'; ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="GET" action="reservar.php" class="space-y-6">
                            
                            <div>
                                <label for="capacity" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                    <?php echo isset($t['label_capacity']) ? $t['label_capacity'] : 'Capacidade Mínima'; ?>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-users text-gray-400 dark:text-gray-500 text-sm"></i>
                                    </div>
                                    <select name="capacity" id="capacity" onchange="this.form.submit()"
                                            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-lg focus:ring-[#5d3e8f] focus:border-[#5d3e8f] dark:focus:ring-[#d8b4fe] dark:focus:border-[#d8b4fe] block transition-colors cursor-pointer hover:bg-white dark:hover:bg-gray-600">
                                        <option value="0"><?php echo isset($t['opt_any_capacity']) ? $t['opt_any_capacity'] : 'Qualquer capacidade'; ?></option>
                                        <?php
                                        foreach ([10, 20, 30, 50, 80, 100] as $cap):
                                            $selected = ($min_capacity == $cap) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $cap; ?>" <?php echo $selected; ?>>
                                                <?php echo $cap; ?>+ <?php echo isset($t['text_seats']) ? $t['text_seats'] : 'lugares'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="location" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                    <?php echo isset($t['label_location']) ? $t['label_location'] : 'Localização'; ?>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-map-pin text-gray-400 dark:text-gray-500 text-sm"></i>
                                    </div>
                                    <select name="location" id="location" onchange="this.form.submit()"
                                            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-lg focus:ring-[#5d3e8f] focus:border-[#5d3e8f] dark:focus:ring-[#d8b4fe] dark:focus:border-[#d8b4fe] block transition-colors cursor-pointer hover:bg-white dark:hover:bg-gray-600">
                                        <option value=""><?php echo isset($t['opt_all_locations']) ? $t['opt_all_locations'] : 'Todas as localizações'; ?></option>
                                        <?php if ($result_locations && $result_locations->num_rows > 0): ?>
                                            <?php
                                            $result_locations->data_seek(0);
                                            while ($loc = $result_locations->fetch_assoc()):
                                                $selected = ($location == $loc['local']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($loc['local']); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($loc['local']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                        </form>
                    </div>

                </aside>

                <main class="w-full lg:w-3/4">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white transition-colors duration-300">
                            <?php echo isset($t['title_results']) ? $t['title_results'] : 'Salas para Reserva'; ?>
                        </h2>
                        <span class="bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 py-1 px-3 rounded-full text-xs font-bold transition-colors duration-300">
                            <?php echo $count_available_rooms; ?> <?php echo isset($t['count_rooms']) ? $t['count_rooms'] : 'salas'; ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        
                        <?php
                        if ($count_available_rooms > 0) {
                            foreach ($result_rooms as $row) {
                                $idSala = (int)$row['id'];
                                $img = getRoomImagePath($row["imagem"]);
                                $nome = htmlspecialchars($row["nome"]);
                                $cap = htmlspecialchars($row["capacidade"]);
                                $local = htmlspecialchars($row["local"]);
                                $equip_str = $row['equipamentos'] ?? '';
                                $equip_tags = !empty($equip_str) ? array_slice(array_map('trim', explode(',', $equip_str)), 0, 2) : [];

                            
                                $card_status_disponivel_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black border border-[#5d3e8f] dark:border-[#d8b4fe]';
                                $card_status_disponivel_text = isset($t['status_disponivel_card']) ? $t['status_disponivel_card'] : 'Disponível';
                                
                        
                                $card_status_class = $card_status_disponivel_class;
                                $card_status_text = $card_status_disponivel_text;
                        ?>

                        <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-2xl dark:shadow-gray-900 transition-all duration-300 hover:-translate-y-1 flex flex-col h-full">
                            
                            <div class="relative h-48 overflow-hidden">
                                <img src="<?php echo $img; ?>" alt="<?php echo $nome; ?>"
                                    class="w-full h-full object-cover transition-transform duration-300">
                                
                                <div class="absolute top-3 left-3 <?php echo $card_status_class; ?> px-3 py-1 rounded-full text-xs font-bold shadow-sm transition-colors">
                                    <?php echo $card_status_text; ?>
                                </div>

                                <div class="absolute top-3 right-3 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm text-[#5d3e8f] dark:text-[#d8b4fe] px-3 py-1 rounded-full text-xs font-bold shadow-sm transition-colors">
                                    <i class="fa-solid fa-users mr-1"></i> <?php echo $cap; ?>
                                </div>
                            </div>

                            <div class="p-5 flex flex-col flex-grow">
                                <div class="mb-auto">
                                    <p class="text-xs font-semibold text-[#5d3e8f] dark:text-[#d8b4fe] mb-1 uppercase tracking-wide transition-colors">
                                        <?php echo $local; ?>
                                    </p>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 line-clamp-1 group-hover:text-[#5d3e8f] dark:group-hover:text-[#d8b4fe] transition-colors">
                                        <?php echo $nome; ?>
                                    </h3>
                                    
                                    <?php
                                    $iconMap = [
                                        'projetor' => 'fa-video', 'wifi' => 'fa-wifi', 'internet' => 'fa-wifi',
                                        'ar condicionado' => 'fa-snowflake', 'climatização' => 'fa-snowflake',
                                        'quadro' => 'fa-chalkboard-user', 'lousa' => 'fa-chalkboard-user',
                                        'tv' => 'fa-tv', 'ecrã' => 'fa-tv', 'monitor' => 'fa-desktop',
                                        'pc' => 'fa-computer', 'computador' => 'fa-computer',
                                        'som' => 'fa-volume-high', 'colunas' => 'fa-volume-high',
                                        'microfone' => 'fa-microphone', 'câmara' => 'fa-camera',
                                        'webcam' => 'fa-camera', 'mesa' => 'fa-table', 'cadeira' => 'fa-chair',
                                        'wi-fi' => 'fa-wifi'
                                    ];
                                    if (!empty($equip_tags)):
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
                                                <span class="inline-flex items-center px-2.5 py-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 transition-colors hover:bg-gray-100 dark:hover:bg-gray-600 select-none">
                                                    <i class="fa-solid <?php echo $iconClass; ?> mr-2 text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                                                    <?php echo htmlspecialchars($tag); ?>
                                                </span>
                                            <?php endforeach; ?>
                                            
                                            <?php if(count(explode(',', $equip_str)) > 2): ?>
                                                <span class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-md">
                                                    +<?php echo count(explode(',', $equip_str)) - 2; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-4 h-8 flex items-center text-gray-400 dark:text-gray-500 text-sm italic">
                                            <?php echo isset($t['no_equip']) ? $t['no_equip'] : 'Sem equipamentos listados'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="detalhes.php?id=<?php echo $idSala; ?>"
                                    class="block w-full text-center bg-gray-50 dark:bg-gray-700 hover:bg-[#5d3e8f] dark:hover:bg-[#d8b4fe] text-gray-700 dark:text-gray-200 hover:text-white dark:hover:text-gray-900 font-semibold py-2.5 rounded-lg transition-all duration-300 border border-gray-200 dark:border-gray-600 hover:border-[#5d3e8f] dark:hover:border-[#d8b4fe]">
                                    <?php echo isset($t['btn_details']) ? $t['btn_details'] : 'Ver Detalhes'; ?>
                                </a>
                            </div>
                        </div>

                        <?php
                            }
                        } else {
                        ?>
                            <div class="col-span-full md:col-span-3 flex flex-col items-center justify-center py-16 bg-white dark:bg-gray-800 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 transition-colors duration-300">
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-full mb-4 transition-colors">
                                    <i class="fa-solid fa-magnifying-glass text-4xl text-gray-300 dark:text-gray-500"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2 transition-colors">
                                    <?php echo isset($t['msg_no_rooms']) ? $t['msg_no_rooms'] : 'Nenhuma sala encontrada'; ?>
                                </h3>
                                <p class="text-gray-500 dark:text-gray-400 text-sm mb-6 text-center max-w-xs transition-colors">
                                    <?php echo isset($t['msg_try_adjust']) ? $t['msg_try_adjust'] : 'Tente reduzir os filtros ou procurar por outra localização.'; ?>
                                </p>
                                <a href="reservar.php" class="text-[#5d3e8f] dark:text-[#d8b4fe] font-semibold hover:underline transition-colors">
                                    <?php echo isset($t['btn_clear_filters']) ? $t['btn_clear_filters'] : 'Limpar todos os filtros'; ?>
                                </a>
                            </div>
                        <?php
                        }
                        ?>

                    </div>
                    
                    
                    
                    
                    <?php if ($count_upcoming_rooms > 0): ?>
                    <div class="mt-16 pt-8 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2 transition-colors duration-300">
                                <i class="fa-solid fa-hourglass-half text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                                <?php echo isset($t['upcoming_section_title']) ? $t['upcoming_section_title'] : 'Novas Salas (Brevemente)'; ?>
                            </h2>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 mb-6 text-sm italic transition-colors duration-300">
                             <?php echo isset($t['upcoming_section_subtitle']) ? $t['upcoming_section_subtitle'] : 'Estas salas serão disponibilizadas para reserva em breve. Fique atento!'; ?>
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <?php foreach ($result_upcoming_rooms as $row): 
                                $idSala = (int)$row['id'];
                                $img = getRoomImagePath($row["imagem"]);
                                $nome = htmlspecialchars($row["nome"]);
                                $cap = htmlspecialchars($row["capacidade"]);
                                $local = htmlspecialchars($row["local"]);
                                $equip_str = $row['equipamentos'] ?? '';
                                $equip_tags = !empty($equip_str) ? array_slice(array_map('trim', explode(',', $equip_str)), 0, 2) : [];
                                
                                
                                $card_status_brevemente_class = 'bg-[#5d3e8f] text-white dark:bg-[#d8b4fe] dark:text-black border border-[#5d3e8f] dark:border-[#d8b4fe]';
                                $card_status_brevemente_text = isset($t['status_brevemente_card']) ? $t['status_brevemente_card'] : 'Brevemente';

                                $card_status_class = $card_status_brevemente_class;
                                $card_status_text = $card_status_brevemente_text;
                            ?>
                                 <div class="group relative bg-white dark:bg-gray-800 rounded-2xl  opacity-70 hover:opacity-100 overflow-hidden   transition-all duration-300 hover:-translate-y-1 flex flex-col h-full">
                                    <div class="relative h-48 overflow-hidden">
                                        <img src="<?php echo $img; ?>" alt="<?php echo $nome; ?>" class="w-full h-full object-cover transition-transform duration-300">
                                        
                                        <div class="absolute top-3 left-3 <?php echo $card_status_class; ?> px-3 py-1 rounded-full text-xs font-bold shadow-sm transition-colors">
                                            <?php echo $card_status_text; ?>
                                        </div>

                                        <div class="absolute top-3 right-3 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm text-gray-500 dark:text-gray-400 px-3 py-1 rounded-full text-xs font-bold shadow-sm transition-colors">
                                            <i class="fa-solid fa-users mr-1"></i> <?php echo $cap; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="p-5 flex flex-col flex-grow">
                                        <div class="mb-auto">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wide transition-colors">
                                                <?php echo $local; ?>
                                            </p>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 line-clamp-1 transition-colors">
                                                <?php echo $nome; ?>
                                            </h3>
                                            <?php if (!empty($equip_tags)): ?>
                                                <div class="flex flex-wrap gap-2 mb-4 text-xs text-gray-400 dark:text-gray-500 italic">
                                                    <i class="fa-solid fa-circle-info mr-1"></i> <?php echo count(explode(',', $equip_str)); ?> equipamentos
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-4 h-8 flex items-center text-gray-400 dark:text-gray-500 text-sm italic">
                                                    <?php echo isset($t['no_equip']) ? $t['no_equip'] : 'Sem equipamentos listados'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button disabled class="block w-full text-center bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-semibold py-2.5 rounded-lg transition-all duration-300 cursor-not-allowed">
                                            <?php echo isset($t['btn_details_unavailable']) ? $t['btn_details_unavailable'] : 'Detalhes Indisponíveis'; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    </main>
            </div>
        </div>
    </div>

    <div class="text-center">
        <?php
        include_once 'includes/footer.php';
        ?>
    </div>

    <?php
        include_once 'includes/chat_support.php';
        $conn->close();
    ?>

</body>
</html>