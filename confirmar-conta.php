<?php

require_once 'backend/config.php';

if (isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$rootPath = './'; 
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $t = []; 
}


$current_lang_code = $_SESSION['lang'] ?? 'pt';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$path_to_phpmailer = 'PHPMailer/src/'; 
if (!file_exists($path_to_phpmailer . 'Exception.php')) {
    $path_to_phpmailer = '../PHPMailer/src/';
}
if (file_exists($path_to_phpmailer . 'Exception.php')) {
    require_once $path_to_phpmailer . 'Exception.php';
    require_once $path_to_phpmailer . 'PHPMailer.php';
    require_once $path_to_phpmailer . 'SMTP.php';
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function writeLog($email, $ip, $status) {
    date_default_timezone_set('Europe/Lisbon');
    $logDir = 'logs/';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $logFile = $logDir . 'register_history.log'; 
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Ação: Confirmar Email | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$ip_address = getUserIP();
$message = "";
$status = "error";

$t_msg_success = isset($t['msg_success_text']) ? $t['msg_success_text'] : "A sua conta foi confirmada com sucesso!";
$t_msg_error_activate = isset($t['msg_error_activate']) ? $t['msg_error_activate'] : "Ocorreu um erro ao ativar a conta. Tente novamente.";
$t_msg_invalid = isset($t['msg_invalid_token']) ? $t['msg_invalid_token'] : "Este link é inválido ou a conta já foi ativada.";
$t_msg_no_token = isset($t['msg_no_token']) ? $t['msg_no_token'] : "Token não fornecido.";


if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id, email FROM utilizadores WHERE activation_token = ? AND is_active = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $email_log = $user['email'];
        $stmt->close();

        $stmt_upd = $conn->prepare("UPDATE utilizadores SET is_active = 1, activation_token = NULL WHERE activation_token = ?");
        $stmt_upd->bind_param("s", $token);
        
        if ($stmt_upd->execute()) {
            $status = "success";
            $message = $t_msg_success;
            writeLog($email_log, $ip_address, "SUCESSO - Conta Confirmada");
        } else {
            $message = $t_msg_error_activate;
            writeLog($email_log, $ip_address, "ERRO - Falha SQL Update");
        }
        $stmt_upd->close();
    } else {
        $message = $t_msg_invalid;
        writeLog('Desconhecido', $ip_address, "FALHA - Token inválido ou expirado");
    }
} else {
    $message = $t_msg_no_token;
    writeLog('Desconhecido', $ip_address, "FALHA - Acesso sem token");
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang_code; ?>">
<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/png" href="imagens/pngsapo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t['confirm_title']) ? $t['confirm_title'] : 'Confirmação - SAPOSalas'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dark .bg-gray-50 { background-color: #1f2937; } 
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-['Poppins'] min-h-screen flex flex-col transition-colors duration-300">

    <?php include_once 'includes/navbar.php'; ?>

    <div class="flex-grow flex items-center justify-center p-6">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl max-w-md w-full text-center border border-gray-100 dark:border-gray-700 transition-colors duration-300">
            
            <?php if ($status === 'success'): ?>
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/50 mb-6 animate-bounce">
                    <i class="fa-solid fa-check text-4xl text-green-600 dark:text-green-300"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    <?php echo isset($t['confirm_h1_success']) ? $t['confirm_h1_success'] : 'Conta Confirmada!'; ?>
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-8"><?php echo $message; ?></p>
                
                <a href="login.php" class="inline-block w-full py-3 px-6 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-gray-900 dark:hover:bg-[#c084fc] text-white font-bold rounded-lg shadow-md transition-all transform hover:-translate-y-1">
                    <?php echo isset($t['btn_go_login']) ? $t['btn_go_login'] : 'Ir para Login'; ?>
                </a>

            <?php else: ?>
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900/50 mb-6">
                    <i class="fa-solid fa-xmark text-4xl text-red-600 dark:text-red-400"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    <?php echo isset($t['confirm_h1_error']) ? $t['confirm_h1_error'] : 'Link Inválido'; ?>
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-8"><?php echo $message; ?></p>
                
                <a href="criar-conta.php" class="inline-block py-2 px-4 text-[#5d3e8f] dark:text-[#d8b4fe] font-semibold hover:bg-purple-50 dark:hover:bg-gray-700 rounded transition">
                    <?php echo isset($t['btn_back_register']) ? $t['btn_back_register'] : 'Voltar ao Registo'; ?>
                </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="text-center pb-6">
        <?php include_once 'includes/footer.php'; ?>
    </div>

</body>
</html>