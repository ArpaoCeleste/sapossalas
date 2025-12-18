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
    <meta name="description" content="Política de Privacidade - SAPOSalas">
    <title><?php echo $t['privacy_page_title'] ?? 'Política de Privacidade - SAPOSalas'; ?></title>

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
                <?php echo $t['privacy_h1'] ?? 'Política de Privacidade'; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
                <?php echo $t['privacy_last_update'] ?? 'Atualizado em: 08 de dezembro de 2025'; ?>
            </p>

            <div class="space-y-8 text-gray-600 dark:text-gray-300 leading-relaxed">

                <p>
                    <?php echo $t['privacy_intro'] ?? 'A visita a este site não implica que o utilizador esteja obrigado a fornecer dados pessoais. Caso o utilizador forneça dados pessoais, estes serão tratados de forma leal e lícita, em conformidade com o RGPD e demais legislação nacional aplicável.'; ?>
                </p>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['privacy_responsible_title'] ?? 'Responsável pelo Tratamento'; ?>
                    </h2>
                    <p><strong>SAPOSalas</strong></p>
                    <div class="mt-2 pl-4 border-l-4 border-[#5d3e8f] dark:border-[#d8b4fe]">
                        <p><strong>Local:</strong> Portugal</p>
                        <p><strong>Email:</strong> <a href="mailto:admin@saposalas.pt"
                                class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">admin@saposalas.pt</a></p>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
                        <?php echo $t['privacy_purposes_title'] ?? 'Finalidades do Tratamento'; ?>
                    </h2>

                    <div class="space-y-4">
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                                <?php echo $t['privacy_purpose_1_title'] ?? 'Gestão de Utilizadores'; ?></h3>
                            <p><?php echo $t['privacy_purpose_1_text'] ?? 'Tratamos os seus dados para gerir o seu registo na plataforma, permitir o acesso às áreas reservadas e identificar o utilizador nas reservas.'; ?>
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                                <?php echo $t['privacy_purpose_2_title'] ?? 'Gestão de Reservas'; ?></h3>
                            <p><?php echo $t['privacy_purpose_2_text'] ?? 'Os dados são tratados para processar as suas reservas de salas, enviar confirmações, gerir cancelamentos e garantir a disponibilidade dos espaços.'; ?>
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                                <?php echo $t['privacy_purpose_3_title'] ?? 'Comunicações e Suporte'; ?></h3>
                            <p><?php echo $t['privacy_purpose_3_text'] ?? 'Utilizamos os seus dados para responder a pedidos de suporte, dúvidas sobre salas ou questões técnicas através dos nossos canais de contacto.'; ?>
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                                <?php echo $t['privacy_purpose_4_title'] ?? 'Segurança e Prevenção de Fraude'; ?></h3>
                            <p><?php echo $t['privacy_purpose_4_text'] ?? 'Recolhemos dados técnicos (IP, logs) para proteger a plataforma contra ataques, acessos não autorizados e uso indevido.'; ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['privacy_recipients_title'] ?? 'Destinatários dos Dados'; ?>
                    </h2>
                    <p class="mb-2">
                        <?php echo $t['privacy_recipients_text'] ?? 'Os seus dados não são vendidos a terceiros. Podem ser comunicados apenas a:'; ?>
                    </p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><?php echo $t['privacy_recipient_1'] ?? 'Prestadores de serviços de TI (alojamento, manutenção) que atuam sob nossas instruções.'; ?>
                        </li>
                        <li><?php echo $t['privacy_recipient_2'] ?? 'Autoridades legais ou judiciais, quando obrigatório por lei.'; ?>
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['privacy_rights_title'] ?? 'Os seus Direitos'; ?>
                    </h2>
                    <p class="mb-2">
                        <?php echo $t['privacy_rights_intro'] ?? 'Como titular dos dados, tem o direito de:'; ?></p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><?php echo $t['privacy_right_access'] ?? 'Aceder aos seus dados;'; ?></li>
                        <li><?php echo $t['privacy_right_rectify'] ?? 'Solicitar a retificação de dados incorretos;'; ?>
                        </li>
                        <li><?php echo $t['privacy_right_delete'] ?? 'Solicitar o apagamento da sua conta e dados;'; ?>
                        </li>
                        <li><?php echo $t['privacy_right_oppose'] ?? 'Opor-se ao tratamento de dados;'; ?></li>
                        <li><?php echo $t['privacy_right_portability'] ?? 'Solicitar a portabilidade dos dados.'; ?>
                        </li>
                    </ul>
                    <p class="mt-4">
                        <?php echo $t['privacy_rights_contact'] ?? 'Para exercer estes direitos, contacte-nos através do email:'; ?>
                        <a href="mailto:admin@saposalas.pt"
                            class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">admin@saposalas.pt</a>.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        <?php echo $t['privacy_minors_title'] ?? 'Proteção de Menores'; ?>
                    </h2>
                    <p>
                        <?php echo $t['privacy_minors_text'] ?? 'Não recolhemos intencionalmente dados de menores. O uso da plataforma é reservado a maiores de idade ou utilizadores com autorização dos tutores legais.'; ?>
                    </p>
                </section>

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
        <?php include 'includes/footer.php'; ?>
    </div>


    <?php
    include_once 'includes/chat_support.php';
    ?>
</body>

</html>