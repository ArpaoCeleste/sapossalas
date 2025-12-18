<?php
require_once 'backend/config.php';


$rootPath = './';

if (isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $current_lang_code = $_SESSION['lang'] ?? 'pt';
    $t = [];
}

$current_lang_code = $lang_code;

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


function get_credencial($key, $default = null)
{

    if (isset($_ENV[$key]) && !empty($_ENV[$key])) {
        return $_ENV[$key];
    }

    $val = getenv($key);
    if ($val !== false && !empty($val)) {
        return $val;
    }

    if (defined($key)) {
        return constant($key);
    }

    if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
        return $_SERVER[$key];
    }

    return $default;
}

define('RECAPTCHA_SITE_KEY', get_credencial('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY', get_credencial('RECAPTCHA_SECRET_KEY'));

$error_message = "";
$success_message = "";

$max_registrations = 5;
$base_lockout_minutes = 20;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['register_load_time'] = microtime(true);
}

function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}
$ip_address = getUserIP();

function writeLog($email, $ip, $status)
{
    date_default_timezone_set('Europe/Lisbon');
    $logDir = 'logs/';
    if (!is_dir($logDir))
        mkdir($logDir, 0777, true);

    $logFile = $logDir . 'register_history.log';
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$conn->query("DELETE FROM register_attempts WHERE attempt_time < (NOW() - INTERVAL 25 HOUR)");
$stmt = $conn->prepare("SELECT COUNT(*) FROM register_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 24 HOUR)");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$stmt->bind_result($total_actions_24h);
$stmt->fetch();
$stmt->close();
$multiplier = max(0, floor(($total_actions_24h - 1) / $max_registrations));
$spam_window = $base_lockout_minutes * pow(2, $multiplier);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        writeLog('Desconhecido', $ip_address, "CRÍTICO - Falha CSRF");
        die("Erro de segurança: Token inválido. Atualize a página.");
    }

    $nome = trim($_POST["nome"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["pass"] ?? '';
    $password_conf = $_POST["pass-conf"] ?? '';
    $datanascimento = $_POST["datanascimento"] ?? '';

    if (!empty($_POST['website_check'])) {
        writeLog($email, $ip_address, "BOT - Honeypot detetado");
        die("Bot detetado.");
    }

    $load_time = $_SESSION['register_load_time'] ?? 0;
    if ((microtime(true) - $load_time) < 2.0) {
        $error_message = isset($t['msg_too_fast']) ? $t['msg_too_fast'] : "Ação impossivelmente rápida.";
        writeLog($email, $ip_address, "BOT - Registo demasiado rápido");
    } elseif (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        $error_message = isset($t['msg_captcha_missing']) ? $t['msg_captcha_missing'] : "Por favor complete o captcha.";
        writeLog($email, $ip_address, "FALHA - Captcha em falta");
    } else {

        $recaptcha_response = $_POST['g-recaptcha-response'];
        $captcha_success = verifyRecaptcha(RECAPTCHA_SECRET_KEY, $recaptcha_response);
        if ($captcha_success->success == false) {
            $error_message = isset($t['msg_captcha_fail']) ? $t['msg_captcha_fail'] : "Falha na verificação de robô.";
            $error_message .= " (Erro: " . implode(', ', $captcha_success->{'error-codes'} ?? []) . ")";
            writeLog($email, $ip_address, "FALHA - Captcha inválido");
        } else {

            $stmt = $conn->prepare("SELECT COUNT(*) FROM register_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
            $stmt->bind_param("si", $ip_address, $spam_window);
            $stmt->execute();
            $stmt->bind_result($recent_attempts);
            $stmt->fetch();
            $stmt->close();

            if ($recent_attempts >= $max_registrations) {
                $base_msg = isset($t['msg_too_many_regs']) ? $t['msg_too_many_regs'] : "Muitas tentativas. Aguarde";
                $error_message = rtrim($base_msg, '.') . " " . $spam_window . " min.";

                writeLog($email, $ip_address, "BLOQUEADO - Spam Escalonado");
                $stmt_log = $conn->prepare("INSERT INTO register_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                $stmt_log->bind_param("s", $ip_address);
                $stmt_log->execute();
                $stmt_log->close();
            } else {

                $stmt_log = $conn->prepare("INSERT INTO register_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                $stmt_log->bind_param("s", $ip_address);
                $stmt_log->execute();
                $stmt_log->close();

                if (empty($nome) || empty($email) || empty($password) || empty($datanascimento)) {
                    $error_message = isset($t['msg_fill_all']) ? $t['msg_fill_all'] : "Preencha todos os campos.";
                    writeLog($email, $ip_address, "FALHA - Campos vazios");
                } elseif (strlen($nome) > 80) {
                    $error_message = isset($t['msg_name_too_long']) ? $t['msg_name_too_long'] : "O nome não pode ter mais de 80 caracteres.";
                    writeLog($email, $ip_address, "FALHA - Nome muito longo");
                } elseif (strlen($nome) < 2) {
                    $error_message = isset($t['msg_name_too_short']) ? $t['msg_name_too_short'] : "O nome deve ter pelo menos 2 caracteres.";
                    writeLog($email, $ip_address, "FALHA - Nome muito curto");
                } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $nome)) {
                    $error_message = isset($t['msg_name_invalid']) ? $t['msg_name_invalid'] : "O nome contém caracteres inválidos.";
                    writeLog($email, $ip_address, "FALHA - Nome com caracteres inválidos");
                } elseif (strlen($email) > 255) {
                    $error_message = isset($t['msg_email_too_long']) ? $t['msg_email_too_long'] : "O email não pode ter mais de 255 caracteres.";
                    writeLog($email, $ip_address, "FALHA - Email muito longo");
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = isset($t['msg_invalid_email']) ? $t['msg_invalid_email'] : "Email inválido.";
                    writeLog($email, $ip_address, "FALHA - Formato email inválido");
                } elseif (strlen($password) > 128) {
                    $error_message = isset($t['msg_pass_too_long']) ? $t['msg_pass_too_long'] : "A password não pode ter mais de 128 caracteres.";
                    writeLog($email, $ip_address, "FALHA - Password muito longa");
                } elseif ($password !== $password_conf) {
                    $error_message = isset($t['msg_pass_mismatch']) ? $t['msg_pass_mismatch'] : "Senhas não coincidem.";
                    writeLog($email, $ip_address, "FALHA - Passwords não coincidem");
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                    $error_message = isset($t['msg_weak_pass']) ? $t['msg_weak_pass'] : "A senha é fraca (Min 8 chars, Maiúscula, Número, Símbolo).";
                    writeLog($email, $ip_address, "FALHA - Password fraca");
                } else {

                    $data_obj = DateTime::createFromFormat('Y-m-d', $datanascimento);
                    $data_hoje = new DateTime('today');
                    $data_minima = DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('-120 years')));
                    $data_maxima_16anos = DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('-16 years')));

                    if (!$data_obj) {
                        $error_message = isset($t['msg_date_invalid_format']) ? $t['msg_date_invalid_format'] : "Formato de data inválido.";
                        writeLog($email, $ip_address, "FALHA - Formato de data inválido");
                    } elseif ($data_obj > $data_hoje) {
                        $error_message = isset($t['msg_date_future']) ? $t['msg_date_future'] : "A data de nascimento não pode ser no futuro.";
                        writeLog($email, $ip_address, "FALHA - Data no futuro");
                    } elseif ($data_obj < $data_minima) {
                        $error_message = isset($t['msg_date_too_old']) ? $t['msg_date_too_old'] : "Data de nascimento inválida (muito antiga).";
                        writeLog($email, $ip_address, "FALHA - Data muito antiga");
                    } elseif ($data_obj > $data_maxima_16anos) {
                        $error_message = isset($t['msg_underage']) ? $t['msg_underage'] : "Tens de ter pelo menos 16 anos.";
                        writeLog($email, $ip_address, "FALHA - Menor de idade");
                    } else {
                        $stmt_check = $conn->prepare("SELECT email FROM utilizadores WHERE email = ?");
                        $stmt_check->bind_param("s", $email);
                        $stmt_check->execute();
                        $stmt_check->store_result();

                        if ($stmt_check->num_rows > 0) {
                            $error_message = isset($t['msg_email_exists']) ? $t['msg_email_exists'] : "Email já registado.";
                            writeLog($email, $ip_address, "FALHA - Email duplicado");
                        } else {
                            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                            $role = 'user';
                            $is_active = 0;
                            $activation_token = bin2hex(random_bytes(32));

                            $stmt = $conn->prepare("INSERT INTO utilizadores (nome, email, pass, role, datanascimento, is_active, activation_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssis", $nome, $email, $password_hashed, $role, $datanascimento, $is_active, $activation_token);

                            if ($stmt->execute()) {

                                try {
                                    $mail = new PHPMailer(true);

                                    $mail->isSMTP();
                                    $mail->Host = get_credencial('SMTP_HOST', 'smtp.gmail.com');
                                    $mail->SMTPAuth = true;
                                    $mail->Username = get_credencial('SMTP_USER');
                                    $mail->Password = get_credencial('SMTP_PASS');
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    $mail->Port = get_credencial('SMTP_PORT', '587');
                                    $mail->CharSet = 'UTF-8';

                                    $mail->setFrom(get_credencial('SMTP_USER'), 'Segurança SAPOSalas');
                                    $mail->addAddress($email);

                                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                    $link = "http://saw.pt/confirmar-conta.php?token=" . $activation_token;

                                    $ano_atual = date('Y');


                                    $welcome_subj = isset($t['email_welcome_subject']) ? $t['email_welcome_subject'] : 'Bem-vindo ao SAPOSalas - Confirme a sua conta';
                                    $welcome_title = sprintf(isset($t['email_welcome_title']) ? $t['email_welcome_title'] : 'Olá, %s 👋', $nome);
                                    $welcome_text = isset($t['email_welcome_text']) ? $t['email_welcome_text'] : 'Seja muito bem-vindo! Estamos entusiasmados por tê-lo connosco.<br><br>A sua conta foi criada com sucesso, mas antes de poder começar a reservar espaços, precisamos apenas que confirme o seu endereço de email.';
                                    $btn_confirm = isset($t['email_btn_confirm']) ? $t['email_btn_confirm'] : 'Confirmar Conta';
                                    $link_fallback = isset($t['email_link_fallback']) ? $t['email_link_fallback'] : 'Se o botão acima não funcionar, copie e cole o seguinte link no seu navegador:';
                                    $rights = isset($t['footer_rights']) ? $t['footer_rights'] : 'Todos os direitos reservados';
                                    $ignore_text = isset($t['email_ignore']) ? $t['email_ignore'] : 'Se não solicitou este registo, pode ignorar este email com segurança.';

                                    $mail->isHTML(true);
                                    $mail->Subject = $welcome_subj;
                                    $mail->Body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; background-color: #f3f4f6; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>
    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
        <tr>
            <td style='padding: 20px 0; text-align: center;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                    
                    <div style='background-color: #5d3e8f; padding: 30px 20px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 1px;'>SAPOSalas</h1>
                    </div>

                    <div style='padding: 40px 30px; text-align: left;'>
                        <h2 style='color: #111827; margin-top: 0; font-size: 20px; font-weight: 600;'>{$welcome_title}</h2>
                        
                        <p style='color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px;'>
                            {$welcome_text}
                        </p>

                        <div style='text-align: center; margin: 35px 0;'>
                            <a href='{$link}' style='background-color: #5d3e8f; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(93, 62, 143, 0.3);'>
                                {$btn_confirm}
                            </a>
                        </div>

                        <p style='color: #6b7280; font-size: 14px; line-height: 1.5; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;'>
                            {$link_fallback}<br>
                            <a href='{$link}' style='color: #5d3e8f; word-break: break-all;'>{$link}</a>
                        </p>
                    </div>

                    <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;'>
                        <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                            © {$ano_atual} SAPOSalas. {$rights}.<br>
                            {$ignore_text}
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
";

                                    $mail->send();
                                    $success_message = isset($t['msg_reg_success']) ? $t['msg_reg_success'] : "Conta criada! Enviamos um link de confirmação para o seu email.";
                                    writeLog($email, $ip_address, "SUCESSO - Conta criada e email enviado");

                                } catch (Exception $e) {
                                    $error_message = (isset($t['msg_reg_email_error']) ? $t['msg_reg_email_error'] : "Conta criada, mas erro ao enviar email: ") . $e->getMessage();
                                    writeLog($email, $ip_address, "ALERTA - Conta criada mas falha no email");
                                }

                            } else {
                                $error_message = isset($t['msg_db_error']) ? $t['msg_db_error'] : "Erro ao registar na base de dados.";
                                writeLog($email, $ip_address, "ERRO - Falha SQL Insert");
                            }
                            $stmt->close();
                        }
                        $stmt_check->close();
                    }
                }
            }
        }
    }
}

$max_date = date('Y-m-d', strtotime('-16 years'));
$min_date = date('Y-m-d', strtotime('-120 years'));
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t['register_title']) ? $t['register_title'] : 'Criar Conta - SAPOSalas'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css" />
    <script
        src="https://www.google.com/recaptcha/api.js?onload=renderRecaptcha&render=explicit&hl=<?php echo $current_lang_code; ?>"
        async defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #5d3e8f;
        }

        .dark ::-webkit-scrollbar-track {
            background: #1f2937;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #4b5563;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    </style>
</head>

<body class="bg-white dark:bg-gray-900 font-['Poppins'] h-screen transition-colors duration-300">

    <?php include_once 'includes/navbar.php'; ?>

    <div class="w-full h-full flex pt-20">
        <div
            class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12 bg-white dark:bg-gray-900 overflow-y-auto transition-colors duration-300">
            <div class="w-full max-w-md space-y-6">

                <div class="text-center">
                    <div
                        class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[#fbeaff] dark:bg-gray-800 mb-4 shadow-sm transition-colors">
                        <i class="fa-solid fa-user-plus text-2xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white transition-colors">
                        <?php echo isset($t['register_h1']) ? $t['register_h1'] : 'Criar Conta Nova'; ?>
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 transition-colors">
                        <?php echo isset($t['register_subtitle']) ? $t['register_subtitle'] : 'Junte-se à comunidade SAPOSalas.'; ?>
                    </p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div
                        class="bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-3 rounded-r text-sm transition-colors">
                        <p><i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div
                        class="bg-[#fbeaff] dark:bg-gray-800 border-l-4 border-[#5d3e8f] dark:border-[#d8b4fe] text-[#5d3e8f] dark:text-[#d8b4fe] p-6 rounded-r text-center shadow-sm transition-colors">

                        <div
                            class="inline-block p-3 rounded-full bg-white dark:bg-gray-700 mb-3 shadow-sm transition-colors">
                            <i class="fa-regular fa-paper-plane text-3xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                        </div>

                        <h3 class="font-bold text-lg mb-2">
                            <?php echo isset($t['email_verify_title']) ? $t['email_verify_title'] : 'Verifique o seu Email'; ?>
                        </h3>

                        <p class="text-sm text-gray-800 dark:text-gray-200 mb-4 font-medium transition-colors">
                            <?php echo $success_message; ?></p>

                        <p class="text-xs text-gray-500 dark:text-gray-400 transition-colors">
                            <?php echo isset($t['email_verify_text_1']) ? $t['email_verify_text_1'] : 'Não recebeu? Verifique a pasta de'; ?>
                            <b><?php echo isset($t['email_verify_text_2']) ? $t['email_verify_text_2'] : 'Spam'; ?></b>
                            <?php echo isset($t['email_verify_text_3']) ? $t['email_verify_text_3'] : 'ou tente novamente.'; ?>
                        </p>
                    </div>
                <?php else: ?>

                    <form class="mt-6 space-y-4" action="criar-conta.php" method="POST">

                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div style="display:none;"><input type="text" name="website_check" value=""></div>

                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 transition-colors">
                                <?php echo isset($t['label_fullname']) ? $t['label_fullname'] : 'Nome Completo'; ?>
                            </label>
                            <input type="text" name="nome" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] text-sm transition-all"
                                placeholder="<?php echo isset($t['placeholder_fullname']) ? $t['placeholder_fullname'] : 'João Silva'; ?>"
                                maxlength="80" value="<?php echo $_POST['nome'] ?? ''; ?>">
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 transition-colors">
                                <?php echo isset($t['label_email']) ? $t['label_email'] : 'Email'; ?>
                            </label>
                            <input type="email" name="email" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] text-sm transition-all"
                                placeholder="<?php echo isset($t['placeholder_email']) ? $t['placeholder_email'] : 'email@gmail.com'; ?>"
                                value="<?php echo $_POST['email'] ?? ''; ?>">
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 transition-colors">
                                <?php echo isset($t['label_dob_16']) ? $t['label_dob_16'] : 'Data de Nascimento (+16)'; ?>
                            </label>
                            <input type="date" name="datanascimento" required max="<?php echo $max_date; ?>"
                                min="<?php echo $min_date; ?>"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] text-sm transition-all"
                                value="<?php echo $_POST['datanascimento'] ?? ''; ?>">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 transition-colors">
                                    <?php echo isset($t['label_password']) ? $t['label_password'] : 'Password'; ?>
                                </label>
                                <div class="relative">
                                    <input type="password" name="pass" id="pass" required
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] text-sm transition-all"
                                        placeholder="<?php echo isset($t['placeholder_pass']) ? $t['placeholder_pass'] : '********'; ?>">
                                    <i class="fa-regular fa-eye absolute right-3 top-2.5 text-gray-400 dark:text-gray-500 cursor-pointer text-xs hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                        onclick="togglePass('pass')"></i>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 transition-colors">
                                    <?php echo isset($t['label_confirm']) ? $t['label_confirm'] : 'Confirmar'; ?>
                                </label>
                                <div class="relative">
                                    <input type="password" name="pass-conf" id="pass-conf" required
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] text-sm transition-all"
                                        placeholder="<?php echo isset($t['placeholder_pass']) ? $t['placeholder_pass'] : '********'; ?>">
                                    <i class="fa-regular fa-eye absolute right-3 top-2.5 text-gray-400 dark:text-gray-500 cursor-pointer text-xs hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                        onclick="togglePass('pass-conf')"></i>
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 transition-colors">
                            <?php echo isset($t['pass_requirements_short']) ? $t['pass_requirements_short'] : '* A senha deve ter min. 8 caracteres, maiúscula, minúscula, número e símbolo.'; ?>
                        </p>

                        <div class="text-center mt-4">
                            <div class="text-center mt-4">
                                <div id="recaptcha-placeholder" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"
                                    style="display: inline-block;">
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full py-3 px-4 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-gray-900 dark:hover:bg-[#c084fc] text-white font-bold rounded-lg shadow-md transition-all text-sm mt-4 transform hover:scale-105 duration-300">
                            <?php echo isset($t['btn_create_account']) ? $t['btn_create_account'] : 'Criar Conta'; ?>
                        </button>

                        <div class="text-center mt-4">
                            <p class="text-xs text-gray-600 dark:text-gray-400 transition-colors">
                                <?php echo isset($t['text_has_account']) ? $t['text_has_account'] : 'Já tem conta?'; ?>
                                <a href="login.php"
                                    class="font-bold text-[#5d3e8f] dark:text-[#d8b4fe] hover:underline transition-colors">
                                    <?php echo isset($t['link_login']) ? $t['link_login'] : 'Faça Login'; ?>
                                </a>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div
            class="hidden lg:flex lg:w-1/2 relative bg-[#5d3e8f]  items-center justify-center transition-colors duration-300">
            <img src="https://images.unsplash.com/photo-1497294815431-9365093b7331?q=80&w=1920&auto=format&fit=crop"
                class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-60 dark:opacity-40">
            <div class="absolute inset-0 bg-gradient-to-br from-[#5d3e8f] to-black opacity-60 "></div>
            <div class="relative z-10 p-12 text-white text-center max-w-lg">
                <div class="mb-6"><i class="fa-solid fa-calendar-days text-6xl opacity-80 animate-bounce"></i></div>
                <h2 class="text-4xl font-extrabold mb-6 transition-colors">
                    <?php echo isset($t['hero_login_title']) ? $t['hero_login_title'] : 'Gestão de Salas Simplificada'; ?>
                </h2>
                <p class="text-lg text-purple-100 font-light transition-colors">
                    <?php echo isset($t['hero_login_desc']) ? $t['hero_login_desc'] : 'Reserve salas de aula num piscar de olhos<br>O ambiente perfeito para o ensino começa aqui.'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="text-center"><?php include_once 'includes/footer.php'; ?></div>










    <script>function togglePass(id) { const i = document.getElementById(id); i.type = i.type === 'password' ? 'text' : 'password'; }</script>

    <script>

        let recaptchaWidgetId;

        window.getCurrentTheme = function () {

            return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        }


        window.renderRecaptcha = function () {
            const container = document.getElementById('recaptcha-placeholder');
            const sitekey = container.getAttribute('data-sitekey');
            const theme = getCurrentTheme();

            if (typeof grecaptcha !== 'undefined') {

                recaptchaWidgetId = grecaptcha.render(container, {
                    'sitekey': sitekey,
                    'theme': theme
                });
            }
        }


        window.reloadRecaptchaTheme = function () {

            const parentContainer = document.querySelector('.text-center.mt-4');
            const oldContainer = document.getElementById('recaptcha-placeholder');
            const sitekey = oldContainer.getAttribute('data-sitekey');

            if (typeof grecaptcha === 'undefined') return;


            if (oldContainer) {

                oldContainer.remove();
            }


            const newContainer = document.createElement('div');
            newContainer.id = 'recaptcha-placeholder';
            newContainer.setAttribute('data-sitekey', sitekey);
            newContainer.style.display = 'inline-block';


            parentContainer.appendChild(newContainer);


            const newTheme = getCurrentTheme();
            recaptchaWidgetId = grecaptcha.render(newContainer, {
                'sitekey': sitekey,
                'theme': newTheme
            });
        }
    </script>
</body>

</html>