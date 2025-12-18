<?php
session_start();
require_once 'backend/config.php';

$lang_code = isset($_SESSION['lang']) ? $_SESSION['lang'] : (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'pt');
$lang_file = "includes/lang_{$lang_code}.php";
if (file_exists($lang_file)) {
    require_once $lang_file;
} else {
    require_once 'includes/lang.php';
}

$rootPath = './';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Política de Cookies - SAPOSalas">
    <meta name="robots" content="index, follow">
    <title><?php echo $t['cookies_page_title'] ?? 'Política de Cookies - SAPOSalas'; ?></title>

    <link rel="icon" type="image/png" href="imagens/pngsapo.png">

    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body
    class="bg-gray-100 dark:bg-gray-900 font-['Poppins'] text-gray-800 dark:text-gray-200 transition-colors duration-300 flex flex-col min-h-screen">

    <?php include 'includes/navbar.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12 max-w-4xl">

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 border border-gray-200 dark:border-gray-700">

            <h1 class="text-3xl md:text-4xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4">
                <?php echo $t['cookies_h1'] ?? 'Política de Cookies'; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
                <?php echo $t['cookies_last_update'] ?? 'Última atualização: 08 de dezembro de 2025'; ?>
            </p>

            <div class="space-y-8">

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['cookies_what_is_title'] ?? 'O que é um cookie?'; ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                        <?php echo $t['cookies_what_is_text'] ?? 'Um cookie é um pequeno ficheiro de texto guardado no seu computador ou dispositivo móvel quando visita um site. Os cookies permitem que o site reconheça o seu navegador e memorize as suas preferências (como login, idioma, tamanho da fonte e outras preferências de visualização) durante um período de tempo.'; ?>
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['cookies_types_title'] ?? 'Tipos de cookies'; ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                        <?php echo $t['cookies_types_text'] ?? 'Utilizamos cookies técnicos (essenciais para o funcionamento do site), de funcionalidade (para guardar as suas preferências) e de segurança.'; ?>
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
                        <?php echo $t['cookies_used_title'] ?? 'Cookies utilizados no nosso site'; ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        <?php echo $t['cookies_used_text'] ?? 'Ao navegar no SAPOSalas, podem ser instalados os seguintes cookies:'; ?>
                    </p>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Cookie</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Finalidade</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Tipo</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Validade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[#5d3e8f] dark:text-[#d8b4fe]">
                                        PHPSESSID</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Identificador único
                                        de sessão PHP.</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Técnico</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Sessão</td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[#5d3e8f] dark:text-[#d8b4fe]">
                                        user_email</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Mantém o utilizador
                                        autenticado (se "Lembrar-me").</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Funcionalidade</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">24 horas</td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[#5d3e8f] dark:text-[#d8b4fe]">
                                        lang</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Guarda a preferência
                                        de idioma selecionada.</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Funcionalidade</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">1 ano</td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[#5d3e8f] dark:text-[#d8b4fe]">
                                        color-theme</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Guarda a preferência
                                        de Modo Escuro/Claro (LocalStorage).</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Funcionalidade</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">Persistente</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['cookies_manage_title'] ?? 'Configuração e Desativação'; ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                        <?php echo $t['cookies_manage_text'] ?? 'Pode controlar e/ou apagar os cookies conforme desejar. Consulte os guias oficiais do seu navegador para instruções detalhadas:'; ?>
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-gray-600 dark:text-gray-300">
                        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank"
                                class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">Google Chrome</a></li>
                        <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09"
                                target="_blank" class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">Microsoft
                                Edge</a></li>
                        <li><a href="https://support.mozilla.org/en-US/kb/clear-cookies-and-site-data-firefox"
                                target="_blank" class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">Mozilla
                                Firefox</a></li>
                    </ul>
                </section>

                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 rounded-r">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <strong><?php echo $t['alert_attention'] ?? 'Atenção'; ?>:</strong>
                        <?php echo $t['cookies_warning'] ?? 'A desativação de cookies técnicos pode impedir o funcionamento correto de algumas áreas do site, como o login e a gestão de reservas.'; ?>
                    </p>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-8 mt-8">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                        <?php echo $t['footer_contact'] ?? 'Contacte-nos'; ?>
                    </h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        <strong>Email:</strong> <a href="mailto:admin@saposalas.pt"
                            class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">admin@saposalas.pt</a>
                    </p>
                </div>

                <div class="text-center text-xs text-gray-400 dark:text-gray-500 mt-8">
                    <p>© <?php echo date('Y'); ?> SAPOSalas.
                        <?php echo $t['footer_rights'] ?? 'Todos os direitos reservados.'; ?></p>
                </div>

            </div>
        </div>
    </main>

    <div class="text-center bg-white dark:bg-gray-900 transition-colors duration-300">
        <?php include_once 'includes/footer.php'; ?>
    </div>

    <?php
    include_once 'includes/chat_support.php';
    ?>

</body>

</html>