<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$path = isset($rootPath) ? $rootPath : './';


if (file_exists($path . 'includes/lang.php')) {
    require_once $path . 'includes/lang.php';
} else {
    $lang_code = 'pt';
}


if (!isset($conn) || (is_object($conn) && !$conn->ping())) {
    require_once $path . 'backend/config.php';
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_email']); 
$isAdmin = false;
$username = '';
$userPhoto = $path . 'imagens/default.png';

$current_lang = isset($lang_code) ? $lang_code : 'pt';

if ($isLoggedIn && isset($conn) && $conn->ping()) {
    $email = $_SESSION['user_email'];
    
    $stmt = $conn->prepare("SELECT id, nome, role, imagem_perfil FROM utilizadores WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $username = $row['nome'];
        $isAdmin = ($row['role'] === 'admin');
        
        if (!empty($row['imagem_perfil'])) {
            $raw_photo_path = $row['imagem_perfil'];
            
            if (strpos($raw_photo_path, 'http') === 0) {
                $userPhoto = $raw_photo_path; 
            } else {
                $userPhoto = $path . $raw_photo_path; 
            }
        }
      
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['role'];
    }
    $stmt->close();
}

function getLinkStyle($isActive) {
    $base = "font-['Poppins'] font-bold py-1 px-2 border-b-2 transition-all duration-1000 ease-in-out transform origin-center no-underline whitespace-nowrap dark:text-gray-200";

    if ($isActive) {
        return "$base border-[#5d3e8f] text-[#5d3e8f] font-bold scale-110 dark:text-[#d8b4fe] dark:border-[#d8b4fe]";
    } else {
        return "$base border-transparent font-bold hover:text-black hover:border-[#5d3e8f] hover:scale-110 dark:hover:text-white dark:hover:border-[#d8b4fe]";
    }
}
?>

<header class="w-full bg-white dark:bg-gray-900 transition-colors duration-300 z-[100] font-['Poppins'] shadow-sm">
    <div class="max-w-full px-4 sm:px-8 h-20 flex items-center justify-between relative">
        
        <div class="flex-shrink-0 z-20 py-2">
            <a href="<?php echo $path; ?>index.php" class="flex items-center gap-3 no-underline group">
                <img src="<?php echo $path; ?>imagens/pngsapo.png" 
                     alt="Logo SAPO" 
                     class="h-16 w-auto object-contain transition-transform duration-1000 group-hover:scale-105">
            </a>
        </div>

        <nav class="hidden md:flex gap-8 absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2">
            
            <a class="<?php echo getLinkStyle($currentPage == 'index.php'); ?>" href="<?php echo $path; ?>index.php">
                <?php echo isset($t['menu_home']) ? $t['menu_home'] : 'Home'; ?>
            </a>
            <a class="<?php echo getLinkStyle($currentPage == 'pagina_sobrenos.php'); ?>" href="<?php echo $path; ?>pagina_sobrenos.php">
                <?php echo isset($t['menu_about']) ? $t['menu_about'] : 'Sobre Nós'; ?>
            </a>
            
            <?php if ($isLoggedIn && !$isAdmin): ?>
                <span class="text-gray-300 self-center">|</span>
                <a class="<?php echo getLinkStyle($currentPage == 'reservar.php'); ?>" href="<?php echo $path; ?>reservar.php">
                    <?php echo isset($t['menu_book']) ? $t['menu_book'] : 'Reservar'; ?>
                </a>
               
            <?php endif; ?>

            <?php if ($isLoggedIn && $isAdmin): ?>
                <span class="text-gray-300 self-center">|</span>
                <a class="<?php echo getLinkStyle(in_array($currentPage, ['admin.php', 'admin_salas.php', 'admin_utilizadores.php'])); ?> text-[#5d3e8f] hover:text-[#5d3e8f] dark:text-[#5d3e8f] dark:hover:text-[#5d3e8f] font-bold" href="<?php echo $path; ?>admin.php">
                    <i class="fa-solid fa-lock mr-1 text-xs"></i>
                    <?php echo isset($t['menu_admin']) ? $t['menu_admin'] : 'Admin'; ?>
                </a>
            <?php endif; ?>

        </nav>
        <div class="flex items-center gap-4 z-20">

            <select onchange="const p = new URLSearchParams(window.location.search); p.set('lang', this.value); window.location.search = p.toString();" 
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#5d3e8f] focus:border-[#5d3e8f] block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#d8b4fe] dark:focus:border-[#d8b4fe] cursor-pointer outline-none">
                <option value="pt" <?php echo ($current_lang == 'pt') ? 'selected' : ''; ?>>🇵🇹 PT</option>
                <option value="en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>🇬🇧 EN</option>
                <option value="es" <?php echo ($current_lang == 'es') ? 'selected' : ''; ?>>🇪🇸 ES</option>
                <option value="fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>🇫🇷 FR</option>
                <option value="de" <?php echo ($current_lang == 'de') ? 'selected' : ''; ?>>🇩🇪 DE</option>
                <option value="ru" <?php echo ($current_lang == 'ru') ? 'selected' : ''; ?>>🇷🇺 RU</option>
                <option value="zh" <?php echo ($current_lang == 'zh') ? 'selected' : ''; ?>>🇨🇳 CN</option>
                <option value="it" <?php echo ($current_lang == 'it') ? 'selected' : ''; ?>>🇮🇹 IT</option>
            </select>

            <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5 transition-all">
                <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon"></i>
                <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-yellow-400"></i>
            </button>
            
            <?php if ($isLoggedIn): ?>
                <div class="flex flex-col justify-center items-center text-center">
                    <span class="font-bold text-gray-800 dark:text-gray-100 leading-tight"><?php echo htmlspecialchars($username); ?></span>
                    <span class="text-[10px] text-center text-gray-500 dark:text-gray-400 font-semibold tracking-wider uppercase">
                        <?php echo $isAdmin ? (isset($t['role_admin']) ? $t['role_admin'] : 'Administrador') : (isset($t['role_user']) ? $t['role_user'] : 'User'); ?>
                    </span>
                </div>
            <?php endif; ?>

            <a href="<?php echo $isLoggedIn ? $path . 'userdashboard.php' : $path . 'login.php'; ?>" 
               class="relative group hidden md:block" 
               title="<?php echo $isLoggedIn ? (isset($t['tooltip_profile']) ? $t['tooltip_profile'] : 'O meu perfil') : (isset($t['tooltip_login']) ? $t['tooltip_login'] : 'Entrar'); ?>">
                <img src="<?php echo htmlspecialchars($userPhoto); ?>" 
                     alt="Perfil"
                     class="h-11 w-11 rounded-full object-cover border-2 border-[#5d3e8f] shadow-sm transition-all duration-300 group-hover:scale-110 group-hover:shadow-md group-hover:border-[#7c3aed]"
                     onerror="this.src='<?php echo $path; ?>imagens/default.png';">
                <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-800 <?php echo $isLoggedIn ? 'bg-green-500' : 'bg-gray-300'; ?>"></span>
            </a>
            
            <button id="menu-button" class="md:hidden text-gray-600 dark:text-gray-200 hover:text-black dark:hover:text-white transition-colors" onclick="toggleMobileMenu()">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
        </div>
    
    <div id="mobile-menu" 
         class="fixed top-0 left-0 w-full h-full bg-white dark:bg-gray-900 transition-transform duration-300 transform translate-x-full z-[99] md:hidden overflow-y-auto">
        
        <div class="flex flex-col p-8 pt-6 space-y-6">
            
            <button class="absolute top-6 right-6 text-gray-600 dark:text-gray-300 hover:text-red-500 transition-colors" onclick="toggleMobileMenu()">
                <i class="fa-solid fa-xmark text-3xl"></i>
            </button>

            <div class="flex items-center gap-4 border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                <a href="<?php echo $isLoggedIn ? $path . 'userdashboard.php' : $path . 'login.php'; ?>" 
                   class="relative group block"
                   title="<?php echo $isLoggedIn ? (isset($t['tooltip_profile']) ? $t['tooltip_profile'] : 'O meu perfil') : (isset($t['tooltip_login']) ? $t['tooltip_login'] : 'Entrar'); ?>">
                    <img src="<?php echo htmlspecialchars($userPhoto); ?>" 
                         alt="Perfil"
                         class="h-14 w-14 rounded-full object-cover border-2 border-[#5d3e8f] shadow-md">
                    <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-800 <?php echo $isLoggedIn ? 'bg-green-500' : 'bg-gray-300'; ?>"></span>
                </a>
                
                <div class="flex flex-col">
                    <span class="font-bold text-gray-800 dark:text-white leading-tight"><?php echo htmlspecialchars($username) ?: 'Anonimo'; ?></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ">
                        <?php echo $isLoggedIn ? ($isAdmin ? (isset($t['role_admin']) ? $t['role_admin'] : 'Administrador') : (isset($t['role_user']) ? $t['role_user'] : 'User')) : (isset($t['role_not_auth']) ? $t['role_not_auth'] : 'Não Autenticado'); ?>
                    </span>
                </div>
            </div>

            <a class="<?php echo getLinkStyle($currentPage == 'index.php'); ?> text-xl " href="<?php echo $path; ?>index.php">
                <?php echo isset($t['menu_home']) ? $t['menu_home'] : 'Home'; ?>
            </a>
            <a class="<?php echo getLinkStyle($currentPage == 'pagina_sobrenos.php'); ?> text-xl pt-4" href="<?php echo $path; ?>pagina_sobrenos.php">
                <?php echo isset($t['menu_about']) ? $t['menu_about'] : 'Sobre Nós'; ?>
            </a>

            <?php if ($isLoggedIn && !$isAdmin): ?>
                <hr class="border-gray-200 dark:border-gray-700 mt-4">
                <a class="<?php echo getLinkStyle($currentPage == 'reservar.php'); ?> text-xl" href="<?php echo $path; ?>reservar.php">
                    <?php echo isset($t['menu_book_room']) ? $t['menu_book_room'] : 'Reservar Sala'; ?>
                </a>
             
            <?php endif; ?>

            <?php if ($isLoggedIn && $isAdmin): ?>
                <hr class="border-gray-200 dark:border-gray-700 mt-4">
                <a class="<?php echo getLinkStyle(in_array($currentPage, ['admin.php'])); ?> text-xl text[#5d3e8f] dark:text[#5d3e8f] " href="<?php echo $path; ?>admin.php">
                    <i class="fa-solid fa-lock mr-2"></i>
                    <?php echo isset($t['menu_admin_dashboard']) ? $t['menu_admin_dashboard'] : 'Dashboard Admin'; ?>
                </a>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <hr class="border-gray-200 dark:border-gray-700 mt-4">
                <a href="<?php echo $path; ?>logout.php" class="text-xl text-red-500 hover:underline">
                    <i class="fa-solid fa-sign-out-alt mr-2"></i>
                    <?php echo isset($t['menu_logout']) ? $t['menu_logout'] : 'Sair'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const body = document.body;

        if (menu.classList.contains('translate-x-full')) {
            menu.classList.remove('translate-x-full');
            body.classList.add('overflow-hidden'); 
        } else {
            menu.classList.add('translate-x-full');
            body.classList.remove('overflow-hidden');
        }
    }

    var themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    var themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    var themeToggleBtn = document.getElementById('theme-toggle');

    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
        themeToggleLightIcon.classList.remove('hidden');
    } else {
        document.documentElement.classList.remove('dark');
        themeToggleDarkIcon.classList.remove('hidden');
    }

    themeToggleBtn.addEventListener('click', function() {
        themeToggleDarkIcon.classList.toggle('hidden');
        themeToggleLightIcon.classList.toggle('hidden');

        if (localStorage.getItem('color-theme')) {
            if (localStorage.getItem('color-theme') === 'light') {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            }
        } else {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }

        if (typeof window.reloadRecaptchaTheme === 'function') {
            window.reloadRecaptchaTheme();
        }
   
    });
</script>