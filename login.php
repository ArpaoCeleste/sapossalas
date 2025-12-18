<?php
require_once 'backend/config.php';
require_once 'backend/GoogleAuthenticator.php'; // Incluir a biblioteca

if (isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$rootPath = './';
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $current_lang_code = $_SESSION['lang'] ?? 'pt';
    $t = [];
}

$current_lang_code = $_SESSION['lang'] ?? 'pt';


function get_credencial_login($key)
{
    if (isset($_ENV[$key]))
        return $_ENV[$key];
    $val = getenv($key);
    if ($val !== false)
        return $val;
    if (defined($key))
        return constant($key);
    if (function_exists('get_config')) {
        $val = get_config($key);
        if ($val)
            return $val;
    }
    return null;
}

define('RECAPTCHA_SITE_KEY_LOGIN', get_credencial_login('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY_LOGIN', get_credencial_login('RECAPTCHA_SECRET_KEY'));

$error_message = "";
$show_2fa_form = false;
$two_fa_method = 'email';

$base_max_attempts = 3;
$base_lockout_minutes = 20;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['login_load_time'] = microtime(true);
}

function writeLog($email, $ip, $status)
{
    date_default_timezone_set('Europe/Lisbon');
    $logDir = 'logs/';
    if (!is_dir($logDir))
        mkdir($logDir, 0755, true);

    $logFile = $logDir . 'login_history.log';
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$conn->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL 25 HOUR)");

function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

$ip_address = getUserIP();


$stmt_escalation = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 24 HOUR)");
$stmt_escalation->bind_param("s", $ip_address);
$stmt_escalation->execute();
$stmt_escalation->bind_result($total_failures_24h);
$stmt_escalation->fetch();
$stmt_escalation->close();

$multiplier = floor(($total_failures_24h - 1) / $base_max_attempts);
$current_lockout_time = $base_lockout_minutes * pow(2, max(0, $multiplier));
$lockout_time = $current_lockout_time;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'verify_google_2fa') {
    header('Content-Type: application/json');
    ob_clean();

    if (!isset($_SESSION['temp_login_data'])) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
        exit;
    }

    $code = $_POST['code'] ?? '';
    $secret = $_SESSION['temp_login_data']['2fa_secret'] ?? '';

    if (empty($code) || empty($secret)) {
        echo json_encode(['success' => false, 'message' => 'Código ou segredo em falta.']);
        exit;
    }

    $ga = new PHPGangsta_GoogleAuthenticator();
    $checkResult = $ga->verifyCode($secret, $code, 2);

    if ($checkResult) {

        $userData = $_SESSION['temp_login_data'];

        session_regenerate_id(true);
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if (!empty($userData['remember'])) {
            setcookie('user_email', $userData['email'], [
                'expires' => time() + 1296000,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        writeLog($userData['email'], $ip_address, "SUCESSO TOTAL - Google 2FA");

        unset($_SESSION['temp_login_data']);

        $redirect = ($userData['role'] === 'admin') ? 'admin.php' : 'reservar.php';
        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        writeLog($_SESSION['temp_login_data']['email'], $ip_address, "FALHA - Google 2FA Incorreto");
        echo json_encode(['success' => false, 'message' => 'Código incorreto.']);
    }
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['action'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erro de segurança: Token inválido. Atualize a página.");
    }

    $email_attempt = trim($_POST["email"] ?? 'Desconhecido');

    if (!empty($_POST['website_check'])) {
        die("Acesso negado.");
    }

    $load_time = $_SESSION['login_load_time'] ?? 0;
    $submit_time = microtime(true);

    if (($submit_time - $load_time) < 0.5) {
        $error_message = isset($t['msg_too_fast']) ? $t['msg_too_fast'] : "Ação demasiado rápida. Por favor tente novamente.";
    } elseif (isset($_POST['g-recaptcha-response'])) {

        $recaptcha_response = $_POST['g-recaptcha-response'];
        $captcha_success = verifyRecaptcha(RECAPTCHA_SECRET_KEY_LOGIN, $recaptcha_response);
        if ($captcha_success->success == false) {
            $error_message = isset($t['msg_robot_check']) ? $t['msg_robot_check'] : "Por favor confirme que não é um robô.";
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
            $stmt->bind_param("si", $ip_address, $lockout_time);
            $stmt->execute();
            $stmt->bind_result($failed_attempts);
            $stmt->fetch();
            $stmt->close();

            if ($failed_attempts >= $base_max_attempts) {
                $msg_base = isset($t['msg_too_many_attempts']) ? $t['msg_too_many_attempts'] : "Muitas tentativas. Tente novamente daqui a %s minutos.";
                $error_message = sprintf($msg_base, $lockout_time);
                writeLog($email_attempt, $ip_address, "BLOQUEADO");
            } else {
                if (!empty($_POST["email"]) && !empty($_POST["pass"])) {
                    $email = trim($_POST["email"]);
                    $password = $_POST["pass"];

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error_message = isset($t['msg_invalid_email']) ? $t['msg_invalid_email'] : "Email inválido";
                    } else {

                        $stmt = $conn->prepare("SELECT id, email, pass, role, is_active, two_fa_enabled, two_fa_secret FROM utilizadores WHERE email = ?");
                        if ($stmt) {
                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($row = $result->fetch_assoc()) {
                                if (password_verify($password, $row["pass"])) {

                                    if ($row['is_active'] == 0) {
                                        $error_message = isset($t['msg_account_inactive']) ? $t['msg_account_inactive'] : "A sua conta ainda não foi ativada. Verifique o seu email.";

                                        $stmt_log = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                                        $stmt_log->bind_param("s", $ip_address);
                                        $stmt_log->execute();
                                        $stmt_log->close();
                                        writeLog($email, $ip_address, "FALHA - Conta Inativa");
                                    } else {

                                        $stmt_reset = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                                        $stmt_reset->bind_param("s", $ip_address);
                                        $stmt_reset->execute();
                                        $stmt_reset->close();

                                        writeLog($email, $ip_address, "SUCESSO PARCIAL (Password OK)");
                                        session_regenerate_id(true);


                                        $_SESSION['temp_login_data'] = [
                                            'id' => $row['id'],
                                            'email' => $row['email'],
                                            'role' => $row['role'] ?? 'user',
                                            'remember' => (!empty($_POST['remember']) && $_POST['remember'] === 'on'),
                                            '2fa_secret' => $row['two_fa_secret']
                                        ];

                                        $show_2fa_form = true;


                                        if (!empty($row['two_fa_enabled']) && $row['two_fa_enabled'] == 1 && !empty($row['two_fa_secret'])) {
                                            $two_fa_method = 'google';
                                        } else {
                                            $two_fa_method = 'email';
                                        }
                                    }

                                } else {
                                    $error_message = isset($t['msg_wrong_password']) ? $t['msg_wrong_password'] : "Dados inválidos.";
                                    $stmt_log = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                                    $stmt_log->bind_param("s", $ip_address);
                                    $stmt_log->execute();
                                    $stmt_log->close();
                                    writeLog($email, $ip_address, "FALHA - Senha Incorreta");
                                }
                            } else {
                                $error_message = isset($t['msg_email_not_found']) ? $t['msg_email_not_found'] : "Dados inválidos.";
                                $stmt_log = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                                $stmt_log->bind_param("s", $ip_address);
                                $stmt_log->execute();
                                $stmt_log->close();
                                writeLog($email, $ip_address, "FALHA - Email nao existe");
                            }
                            $stmt->close();
                        } else {
                            $error_message = isset($t['msg_internal_error']) ? $t['msg_internal_error'] : "Erro interno.";
                        }
                    }
                } else {
                    $error_message = isset($t['msg_fill_all']) ? $t['msg_fill_all'] : "Por favor preencha todos os campos.";
                }
            }
        }
    } else {
        $error_message = isset($t['msg_captcha_error']) ? $t['msg_captcha_error'] : "Por favor complete o captcha.";
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t['login_title']) ? $t['login_title'] : 'Login - SAPOSalas'; ?></title>
    <link rel="icon" type="image/png" href="imagens/pngsapo.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script
        src="https://www.google.com/recaptcha/api.js?onload=renderRecaptcha&render=explicit&hl=<?php echo $current_lang_code; ?>"
        async defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css" />
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
            class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white dark:bg-gray-900 overflow-y-auto transition-colors duration-300">
            <div class="w-full max-w-md space-y-8">

                <div class="text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#fbeaff] dark:bg-gray-800 mb-6 transition-colors">
                        <i class="fa-solid fa-right-to-bracket text-2xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight transition-colors">
                        <?php echo isset($t['login_welcome']) ? $t['login_welcome'] : 'Bem-vindo de volta!'; ?>
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 transition-colors">
                        <?php echo isset($t['login_subtitle']) ? $t['login_subtitle'] : 'Insira os seus dados para aceder à plataforma SAPOSalas.'; ?>
                    </p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div
                        class="bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-r shadow-sm flex items-center transition-colors">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i>
                        <span class="text-sm font-medium"><?php echo $error_message; ?></span>
                    </div>
                <?php endif; ?>

                <div id="login_panel" class="<?php echo $show_2fa_form ? 'hidden' : ''; ?>">
                    <form class="mt-8 space-y-6" action="login.php" method="POST">
                        <input type="hidden" id="csrf_token" name="csrf_token"
                            value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div style="display:none; opacity:0; visibility:hidden;"><input type="text" name="website_check"
                                id="website_check" value=""></div>

                        <div class="space-y-5">
                            <div>
                                <label for="email"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors"><?php echo isset($t['label_email']) ? $t['label_email'] : 'Email'; ?></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i
                                            class="fa-regular fa-envelope text-gray-400 dark:text-gray-500"></i></div>
                                    <input id="email" name="email" type="email" autocomplete="email" required
                                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent transition-all sm:text-sm"
                                        placeholder="<?php echo isset($t['placeholder_email']) ? $t['placeholder_email'] : 'seu.nome@escola.pt'; ?>"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <div>
                                <label for="password"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors"><?php echo isset($t['label_password']) ? $t['label_password'] : 'Password'; ?></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i
                                            class="fa-solid fa-lock text-gray-400 dark:text-gray-500"></i></div>
                                    <input id="password" name="pass" type="password" autocomplete="current-password"
                                        required
                                        class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent transition-all sm:text-sm"
                                        placeholder="<?php echo isset($t['placeholder_password']) ? $t['placeholder_password'] : '••••••••'; ?>">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 dark:text-gray-500 hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]"
                                        onclick="togglePasswordVisibility()">
                                        <i class="fa-regular fa-eye" id="eye-icon"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <div id="recaptcha-placeholder" data-sitekey="<?php echo RECAPTCHA_SITE_KEY_LOGIN; ?>"
                                    style="display: inline-block;"></div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input id="remember-me" name="remember" type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-[#5d3e8f] focus:ring-[#5d3e8f] accent-[#5d3e8f] cursor-pointer bg-white dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-[#d8b4fe] dark:ring-offset-gray-800 transition duration-200">
                                    <label for="remember-me"
                                        class="ml-2 block text-sm text-gray-900 dark:text-gray-300 cursor-pointer select-none"><?php echo isset($t['label_remember']) ? $t['label_remember'] : 'Lembrar-me'; ?></label>
                                </div>
                                <div class="text-sm">
                                    <a href="repor-password.php"
                                        class="font-medium text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-[#4a327a] dark:hover:text-[#c084fc] transition-colors"><?php echo isset($t['link_forgot_pass']) ? $t['link_forgot_pass'] : 'Esqueceu a senha?'; ?></a>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-gray-900 dark:hover:bg-[#c084fc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 hover:scale-105 duration-300">
                                <?php echo isset($t['btn_login']) ? $t['btn_login'] : 'Entrar'; ?>
                            </button>
                        </div>

                        <div class="text-center mt-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 transition-colors">
                                <?php echo isset($t['text_no_account']) ? $t['text_no_account'] : 'Ainda não tem conta?'; ?>
                                <a href="criar-conta.php"
                                    class="font-bold text-[#5d3e8f] dark:text-[#d8b4fe] hover:text-[#4a327a] dark:hover:text-[#c084fc] transition-colors"><?php echo isset($t['link_register']) ? $t['link_register'] : 'Registe-se aqui'; ?></a>
                            </p>
                        </div>
                    </form>
                </div>

                <div id="verify_panel" class="<?php echo $show_2fa_form ? '' : 'hidden'; ?> mt-8 space-y-6">

                    <div class="text-center mb-6">
                        <div
                            class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[#fbeaff] dark:bg-gray-800 mb-4 transition-colors">
                            <?php if ($two_fa_method === 'google'): ?>
                                <i class="fas fa-mobile-screen-button text-2xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-envelope text-2xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                            <?php endif; ?>
                        </div>

                        <p class="text-gray-900 dark:text-white font-bold text-lg transition-colors">
                            <?php echo $t['2fa_login_title'] ?? 'Verificação de Segurança'; ?>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 transition-colors">
                            <?php
                            if ($two_fa_method === 'google') {
                                echo $t['2fa_login_msg_app'] ?? 'Introduza o código da sua aplicação autenticadora.';
                            } else {
                                echo $t['verify_email_subtitle'] ?? 'Enviamos um código de segurança para o seu email.';
                            }
                            ?>
                        </p>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-key text-gray-400 dark:text-gray-500"></i>
                                </div>
                                <input type="text" id="security_code_input" maxlength="16"
                                    class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] focus:border-transparent transition-all sm:text-sm text-center uppercase tracking-widest font-bold text-xl"
                                    placeholder="">
                            </div>
                            <p id="security_msg" class="text-center text-sm font-bold mt-2 min-h-[20px]"></p>
                        </div>

                        <div>
                            <button
                                onclick="<?php echo ($two_fa_method === 'google') ? 'verifyGoogleCode()' : 'verifyEmailCode()'; ?>"
                                id="btnVerify"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-gray-900 dark:hover:bg-[#c084fc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 hover:scale-105 duration-300">
                                <?php echo isset($t['btn_validate_code']) ? $t['btn_validate_code'] : 'Validar Código'; ?>
                            </button>
                        </div>

                        <div class="flex flex-col gap-3">
                            <?php if ($two_fa_method === 'email'): ?>
                                <button onclick="triggerEmailSend()" id="btnResend"
                                    class="w-full py-2 text-[#5d3e8f] dark:text-[#d8b4fe] text-sm font-medium hover:underline transition-colors">
                                    <?php echo isset($t['btn_resend_code']) ? $t['btn_resend_code'] : 'Reenviar Código'; ?>
                                </button>
                            <?php endif; ?>

                            <button onclick="location.href='login.php'"
                                class="w-full py-2 text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm transition-colors">
                                <?php echo isset($t['btn_back_cancel']) ? $t['btn_back_cancel'] : 'Voltar / Cancelar'; ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div
            class="hidden lg:flex lg:w-1/2 relative bg-[#5d3e8f] items-center justify-center transition-colors duration-300">
            <img src="https://images.unsplash.com/photo-1497294815431-9365093b7331?q=80&w=1920&auto=format&fit=crop"
                class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-60 dark:opacity-60"
                alt="Background Sala de Aula">
            <div class="absolute inset-0 bg-gradient-to-br from-[#5d3e8f] to-black opacity-60 "></div>
            <div class="relative z-10 p-12 text-white text-center max-w-lg">
                <div class="mb-6"><i class="fa-solid fa-calendar-days text-6xl opacity-80 animate-bounce"></i></div>
                <h2 class="text-4xl font-extrabold mb-6 tracking-tight">
                    <?php echo isset($t['hero_login_title']) ? $t['hero_login_title'] : 'Gestão de Salas Simplificada'; ?>
                </h2>
                <p class="text-lg text-purple-100 font-light leading-relaxed">
                    <?php echo isset($t['hero_login_desc']) ? $t['hero_login_desc'] : 'Reserve salas de aula num piscar de olhos<br>O ambiente perfeito para o ensino começa aqui.'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="text-center"><?php include_once 'includes/footer.php'; ?></div>

    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') { input.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }


        function triggerEmailSend() {
            const btn = document.getElementById('btnResend');
            if (btn) { btn.innerText = "<?php echo isset($t['js_sending']) ? $t['js_sending'] : 'A enviar...'; ?>"; btn.disabled = true; }

            const formData = new FormData();
            formData.append('action', 'send_code');
            const csrf = document.getElementById('csrf_token').value;
            formData.append('csrf_token', csrf);

            fetch('backend/api_security.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (btn) {
                            btn.innerText = "<?php echo isset($t['js_code_sent']) ? $t['js_code_sent'] : 'Código Enviado!'; ?>";
                            setTimeout(() => { btn.innerText = "<?php echo isset($t['js_resend_code']) ? $t['js_resend_code'] : 'Reenviar Código'; ?>"; btn.disabled = false; }, 5000);
                        }
                    } else {
                        alert(data.message || "<?php echo isset($t['js_error_sending']) ? $t['js_error_sending'] : 'Erro ao enviar email.'; ?>");
                        if (btn) { btn.innerText = "<?php echo isset($t['js_resend_code']) ? $t['js_resend_code'] : 'Reenviar Código'; ?>"; btn.disabled = false; }
                    }
                })
                .catch(err => { console.error(err); if (btn) { btn.innerText = "<?php echo isset($t['js_resend_code']) ? $t['js_resend_code'] : 'Reenviar Código'; ?>"; btn.disabled = false; } });
        }

        function verifyEmailCode() {
            const code = document.getElementById('security_code_input').value;
            const msg = document.getElementById('security_msg');
            const btn = document.getElementById('btnVerify');

            if (code.length < 6) {
                msg.innerText = "<?php echo isset($t['js_enter_full_code']) ? $t['js_enter_full_code'] : 'Introduza o código completo.'; ?>";
                msg.style.color = "#ef4444";
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_validating']) ? $t['js_validating'] : "A validar..."; ?>';
            msg.innerText = "";

            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('code', code);
            const csrf = document.getElementById('csrf_token').value;
            formData.append('csrf_token', csrf);

            fetch('backend/api_security.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        msg.innerText = "<?php echo isset($t['js_success_redirect']) ? $t['js_success_redirect'] : 'Sucesso! A redirecionar...'; ?>";
                        const isDark = document.documentElement.classList.contains('dark');
                        msg.style.color = isDark ? "#d8b4fe" : "#5d3e8f";
                        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1000);
                    } else {
                        msg.innerText = data.message || "<?php echo isset($t['js_code_incorrect']) ? $t['js_code_incorrect'] : 'Código incorreto.'; ?>";
                        msg.style.color = "#ef4444";
                        btn.disabled = false;
                        btn.innerText = "<?php echo isset($t['btn_validate_code']) ? $t['btn_validate_code'] : 'Validar Código'; ?>";
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.innerText = "<?php echo isset($t['js_server_error']) ? $t['js_server_error'] : 'Erro de ligação ao servidor.'; ?>";
                    msg.style.color = "#ef4444";
                    btn.disabled = false;
                    btn.innerText = "<?php echo isset($t['btn_validate_code']) ? $t['btn_validate_code'] : 'Validar Código'; ?>";
                });
        }


        function verifyGoogleCode() {
            const code = document.getElementById('security_code_input').value;
            const msg = document.getElementById('security_msg');
            const btn = document.getElementById('btnVerify');

            if (code.length < 6) {
                msg.innerText = "<?php echo isset($t['js_enter_full_code']) ? $t['js_enter_full_code'] : 'Introduza o código completo.'; ?>";
                msg.style.color = "#ef4444";
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo isset($t['js_validating']) ? $t['js_validating'] : "A validar..."; ?>';
            msg.innerText = "";

            const formData = new FormData();
            formData.append('action', 'verify_google_2fa');
            formData.append('code', code);
            const csrf = document.getElementById('csrf_token').value;
            formData.append('csrf_token', csrf);

            fetch('login.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        msg.innerText = "<?php echo isset($t['js_success_redirect']) ? $t['js_success_redirect'] : 'Sucesso! A redirecionar...'; ?>";
                        const isDark = document.documentElement.classList.contains('dark');
                        msg.style.color = isDark ? "#d8b4fe" : "#5d3e8f";
                        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1000);
                    } else {
                        msg.innerText = data.message || "<?php echo isset($t['js_code_incorrect']) ? $t['js_code_incorrect'] : 'Código incorreto.'; ?>";
                        msg.style.color = "#ef4444";
                        btn.disabled = false;
                        btn.innerText = "<?php echo isset($t['btn_validate_code']) ? $t['btn_validate_code'] : 'Validar Código'; ?>";
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.innerText = "<?php echo isset($t['js_server_error']) ? $t['js_server_error'] : 'Erro de ligação ao servidor.'; ?>";
                    msg.style.color = "#ef4444";
                    btn.disabled = false;
                    btn.innerText = "<?php echo isset($t['btn_validate_code']) ? $t['btn_validate_code'] : 'Validar Código'; ?>";
                });
        }

        <?php if ($show_2fa_form && $two_fa_method === 'email'): ?>
            document.addEventListener("DOMContentLoaded", () => {
                triggerEmailSend();
            });
        <?php endif; ?>

    </script>

    <script>
        let recaptchaWidgetId;
        window.getCurrentTheme = function () { return document.documentElement.classList.contains('dark') ? 'dark' : 'light'; }
        window.renderRecaptcha = function () {
            const container = document.getElementById('recaptcha-placeholder');
            const sitekey = container.getAttribute('data-sitekey');
            const theme = getCurrentTheme();
            if (typeof grecaptcha !== 'undefined') {
                recaptchaWidgetId = grecaptcha.render(container, { 'sitekey': sitekey, 'theme': theme });
            }
        }
        window.reloadRecaptchaTheme = function () {
            const parentContainer = document.querySelector('.text-center.mt-4');
            const oldContainer = document.getElementById('recaptcha-placeholder');
            const sitekey = oldContainer.getAttribute('data-sitekey');
            if (typeof grecaptcha === 'undefined') return;
            if (oldContainer) { oldContainer.remove(); }
            const newContainer = document.createElement('div');
            newContainer.id = 'recaptcha-placeholder';
            newContainer.setAttribute('data-sitekey', sitekey);
            newContainer.style.display = 'inline-block';
            parentContainer.appendChild(newContainer);
            const newTheme = getCurrentTheme();
            recaptchaWidgetId = grecaptcha.render(newContainer, { 'sitekey': sitekey, 'theme': newTheme });
        }
    </script>
</body>

</html>