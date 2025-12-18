<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once 'backend/config.php';

if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $t = [];
}


function getRoomImagePath($rawPath) {
    $DEFAULT_PLACEHOLDER_URL = 'https://via.placeholder.com/600x800?text=Sem+Imagem';
    
    if (!empty($rawPath) && filter_var($rawPath, FILTER_VALIDATE_URL)) {
        return $rawPath;
    }

    return $DEFAULT_PLACEHOLDER_URL;
}

?>
<!DOCTYPE html>
<html lang="pt" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPOSalas - Reservas de Salas de Aula</title>
        <link rel="icon" type="image/png" href="imagens/pngsapo.png">
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
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 1s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 font-['Poppins'] transition-colors duration-300">

    <?php include 'includes/navbar.php'; ?>

<div class="relative w-full h-[600px] flex items-center justify-center overflow-hidden bg-black">
        
        <img src="https://images.unsplash.com/photo-1580582932707-520aed937b7b?q=80&w=1920&auto=format&fit=crop" 
             class="absolute inset-0 w-full h-full object-cover blur-sm scale-105 opacity-80"
             alt="Sala de Aula Moderna">

        <div class="absolute inset-0 bg-gradient-to-r from-[#5d3e8f]/95 via-black/90 to-black/95 mix-blend-multiply"></div>

        <div class="relative z-10 text-center px-8 md:px-20 w-full max-w-4xl mx-auto animate-fade-in-up">
            
            <h1 class="text-4xl md:text-6xl font-extrabold text-white leading-tight mb-6 drop-shadow-xl">
                <?php echo isset($t['hero_title']) ? $t['hero_title'] : 'O Espaço Ideal para a'; ?><br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#fbeaff] to-[#d8b4fe]">
                    <?php echo isset($t['hero_subtitle']) ? $t['hero_subtitle'] : 'Sua Próxima Aula'; ?>
                </span>
            </h1>

            <p class="text-lg md:text-xl text-gray-300 mb-10 font-light max-w-2xl mx-auto">
                <?php echo isset($t['hero_desc']) ? $t['hero_desc'] : 'Gestão simplificada de salas de aula. Reserve o ambiente perfeito para o ensino.'; ?>
            </p>

            <div class="flex flex-col sm:flex-row gap-4 items-center justify-center">
                
                <a href="#salas-list" 
                   class="px-8 py-4 bg-[#5d3e8f] text-white font-bold rounded-full shadow-lg hover:bg-[#8c76a8] hover:scale-110 transition-all duration-1000 flex items-center gap-2">
                   <?php echo isset($t['btn_view_rooms']) ? $t['btn_view_rooms'] : 'Ver Salas'; ?>
                </a>

              
            </div>
        </div>
        
<div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none hidden lg:block">
    <svg class="relative block w-full h-[80px]" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="fill-gray-50 dark:fill-gray-900 transition-colors duration-300"></path>
    </svg>
</div>
    </div>
    <div id="salas-list" class="w-full px-8 py-16">

        <h2 class="text-3xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-2 border-l-8 border-[#5d3e8f] dark:border-[#d8b4fe] pl-4 transition-colors duration-300">
            <?php echo isset($t['avail_rooms_title']) ? $t['avail_rooms_title'] : 'Salas de Aula Disponíveis'; ?>
        </h2>
        <p class="text-gray-500 dark:text-gray-400 mb-10 pl-6 transition-colors duration-300">
            <?php echo isset($t['avail_rooms_desc']) ? $t['avail_rooms_desc'] : 'Encontre o espaço adequado para a sua turma ou evento.'; ?>
        </p>

        <?php
        
        $sql = "SELECT id, nome, capacidade, local, imagem FROM rooms WHERE status NOT IN ('indisponivel', 'brevemente') ORDER BY nome";
        $result = $conn->query($sql);
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-8">
            
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
             
                
                    $img = getRoomImagePath($row["imagem"]);
                    
                    $nome = htmlspecialchars($row["nome"]);
                    $cap = htmlspecialchars($row["capacidade"]);
                    $local = htmlspecialchars($row["local"]);
                    $idSala = $row['id'];
            ?>

            <div class="group relative h-96 w-full overflow-hidden rounded-2xl shadow-lg cursor-pointer transition-all duration-500 hover:shadow-2xl dark:shadow-gray-800">
          <img src="<?php echo $img; ?>" alt="<?php echo $nome; ?>" 
      class="h-full w-full object-cover transition-transform " 
      onerror="this.src='https://via.placeholder.com/600x800?text=Erro'">

<div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-black/30"></div>
                
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent opacity-90 transition-opacity duration-300"></div>
                
                <div class="absolute top-4 right-4 bg-[#fbeaff] dark:bg-gray-800 text-[#5d3e8f] dark:text-[#d8b4fe] px-4 py-1 rounded-full text-xs font-bold shadow-md border border-[#5d3e8f]/20 dark:border-[#d8b4fe]/20 transition-colors duration-300">
                    <i class="fa-solid fa-users mr-1"></i> <?php echo $cap; ?> <?php echo isset($t['places']) ? $t['places'] : 'Lugares'; ?>
                </div>

                <div class="absolute bottom-0 left-0 w-full p-6 translate-y-4 transition-transform duration-500 group-hover:translate-y-0">
                    <h2 class="text-2xl font-bold text-white mb-1 shadow-black drop-shadow-md"><?php echo $nome; ?></h2>
                    <p class="text-[#fbeaff] text-sm mb-4 flex items-center opacity-90">
                        <i class="fa-solid fa-location-dot mr-2 text-[#8c76a8]"></i> <?php echo $local; ?>
                    </p>
                    <div class="h-0 opacity-0 overflow-hidden transition-all duration-500 group-hover:h-auto group-hover:opacity-100 pb-2">
<p class="text-gray-300 text-xs mb-4 line-clamp-2">
        <?php if (isset($_SESSION['user_email'])): ?>
            <?php echo isset($t['click_to_view_login']) ? $t['click_to_view_login'] : 'Clique para ver o Horário e Reservar'; ?>
        <?php else: ?>
            <?php echo isset($t['click_to_view']) ? $t['click_to_view'] : 'Faça login para ver a disponibilidade'; ?>
        <?php endif; ?>
    </p>
    
    <a href="<?php echo isset($_SESSION['user_email']) ? 'detalhes.php?id=' . $idSala : 'login.php'; ?>" class="block w-[90%] item-align-center mx-auto text-center bg-[#5d3e8f] hover:scale-105 transition-all duration-1000 text-white font-bold py-3 rounded-xl ">
        <?php echo isset($t['btn_check_avail']) ? $t['btn_check_avail'] : 'Ver Disponibilidade'; ?>
    </a>
</div>
                </div>
            </div>

            <?php 
                }
            } else {
                echo "<div class='col-span-full text-center p-10 bg-[#fbeaff] dark:bg-gray-800 rounded-xl border-2 border-[#5d3e8f] dark:border-[#d8b4fe] border-dashed text-[#5d3e8f] dark:text-[#d8b4fe] font-bold transition-colors duration-300'>" . (isset($t['no_rooms']) ? $t['no_rooms'] : 'Nenhuma sala encontrada.') . "</div>";
            }

            ?>

        </div>
    </div>

    <br>
    <br>
    <div class="text-center">
        <?php
        include_once 'includes/footer.php';
        ?>
    </div>

      <?php
        include_once 'includes/chat_support.php';
        ?>
</body>
</html>