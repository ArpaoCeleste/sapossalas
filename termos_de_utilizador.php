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
    <meta name="description" content="Termos e Condições - SAPOSalas">
    <title><?php echo $t['terms_page_title'] ?? 'Termos e Condições - SAPOSalas'; ?></title>

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
                <?php echo $t['terms_h1'] ?? 'Termos e Condições de Uso'; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
                <?php echo $t['terms_last_update'] ?? 'Versão atualizada em: 08 de dezembro de 2025'; ?>
            </p>

            <div class="space-y-8 text-gray-600 dark:text-gray-300 leading-relaxed">

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        1. <?php echo $t['terms_intro_title'] ?? 'Dados Identificativos e Objeto'; ?>
                    </h2>
                    <p>
                        <?php echo $t['terms_intro_text'] ?? 'As presentes Condições Gerais regulam o uso do sistema de reservas de salas SAPOSalas. Ao registar-se e utilizar a plataforma, o utilizador aceita integralmente as regras aqui estabelecidas.'; ?>
                    </p>
                    <div
                        class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg border-l-4 border-[#5d3e8f] dark:border-[#d8b4fe]">
                        <p><strong>Entidade:</strong> SAPOSalas</p>
                        <p><strong>Email:</strong> <a href="mailto:admin@saposalas.pt"
                                class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline">admin@saposalas.pt</a></p>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        2. <?php echo $t['terms_booking_title'] ?? 'Processo de Reserva'; ?>
                    </h2>
                    <p class="mb-4">
                        <?php echo $t['terms_booking_text'] ?? 'A reserva de espaços é exclusiva a utilizadores registados e validados (Professores e Administradores).'; ?>
                    </p>
                    <ol class="list-decimal pl-5 space-y-2">
                        <li><?php echo $t['terms_step_1'] ?? 'O utilizador deve selecionar a sala, a data e o horário desejado.'; ?>
                        </li>
                        <li><?php echo $t['terms_step_2'] ?? 'O sistema verifica a disponibilidade em tempo real.'; ?>
                        </li>
                        <li><?php echo $t['terms_step_3'] ?? 'Após confirmação, a reserva fica registada no nome do utilizador.'; ?>
                        </li>
                        <li><?php echo $t['terms_step_4'] ?? 'Um email de confirmação é enviado com os detalhes do agendamento.'; ?>
                        </li>
                    </ol>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        3. <?php echo $t['terms_rules_title'] ?? 'Regras de Utilização dos Espaços'; ?>
                    </h2>
                    <div class="space-y-3">
                        <p><strong><?php echo $t['terms_rule_1_label'] ?? 'Pontualidade:'; ?></strong>
                            <?php echo $t['terms_rule_1_text'] ?? 'O utilizador deve ocupar e libertar a sala rigorosamente dentro do horário reservado.'; ?>
                        </p>
                        <p><strong><?php echo $t['terms_rule_2_label'] ?? 'Equipamentos:'; ?></strong>
                            <?php echo $t['terms_rule_2_text'] ?? 'O utilizador é responsável pelo bom uso dos equipamentos (projetores, computadores, AC) presentes na sala.'; ?>
                        </p>
                        <p><strong><?php echo $t['terms_rule_3_label'] ?? 'Limpeza:'; ?></strong>
                            <?php echo $t['terms_rule_3_text'] ?? 'A sala deve ser deixada nas mesmas condições de limpeza e arrumação em que foi encontrada.'; ?>
                        </p>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        4. <?php echo $t['terms_cancel_title'] ?? 'Cancelamento e Não Comparência'; ?>
                    </h2>
                    <p class="mb-2">
                        <?php echo $t['terms_cancel_text'] ?? 'Caso não necessite da sala, o utilizador deve proceder ao cancelamento da reserva com a maior antecedência possível através da sua Área Pessoal.'; ?>
                    </p>
                    <div
                        class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <strong><?php echo $t['alert_attention'] ?? 'Atenção'; ?>:</strong>
                            <?php echo $t['terms_warning_ban'] ?? 'O uso abusivo do sistema (reservar e não comparecer repetidamente) pode levar à suspensão temporária da conta.'; ?>
                        </p>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        5. <?php echo $t['terms_liability_title'] ?? 'Responsabilidade e Danos'; ?>
                    </h2>
                    <p>
                        <?php echo $t['terms_liability_text'] ?? 'O utilizador que efetua a reserva é o responsável direto por quaisquer danos causados ao espaço ou equipamentos durante o período da sua utilização, devendo reportar imediatamente qualquer anomalia detetada ao iniciar a ocupação.'; ?>
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        6. <?php echo $t['terms_privacy_title'] ?? 'Proteção de Dados'; ?>
                    </h2>
                    <p>
                        <?php echo $t['terms_privacy_text'] ?? 'Os dados pessoais recolhidos para efeitos de reserva são tratados em conformidade com o RGPD. Para mais detalhes, consulte a nossa'; ?>
                        <a href="politica_privacidade.php"
                            class="text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline font-semibold"><?php echo $t['privacy_h1'] ?? 'Política de Privacidade'; ?></a>.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                        7. <?php echo $t['terms_changes_title'] ?? 'Alterações aos Termos'; ?>
                    </h2>
                    <p>
                        <?php echo $t['terms_changes_text'] ?? 'O SAPOSalas reserva-se o direito de alterar os presentes termos a qualquer momento. Recomenda-se a sua consulta regular.'; ?>
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