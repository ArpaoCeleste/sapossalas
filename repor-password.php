<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'backend/config.php';

$rootPath = './';
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
} else {
    $t = [];
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['security_verified']);
    unset($_SESSION['temp_security_token']);
}

$step = 1;
if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_user_id'])) {
    if (isset($_SESSION['security_verified']) && $_SESSION['security_verified'] === true) {
        $step = 3;
    } else {
        $step = 2;
    }
}

$lang_code = isset($_SESSION['lang']) ? $_SESSION['lang'] : (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'pt');

function get_credencial($key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    $val = getenv($key);
    if ($val !== false) return $val;
    if (defined($key)) return constant($key);
    if (function_exists('get_config')) {
        $val = get_config($key);
        if ($val) return $val;
    }
    return $default;
}

define('RECAPTCHA_SITE_KEY_LOCAL', get_credencial('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY_LOCAL', get_credencial('RECAPTCHA_SECRET_KEY'));

$error_message = "";
$success_message = "";
$showSuccess = false;

$max_attempts = 5;
$base_lockout_minutes = 20;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['reset_load_time'] = microtime(true);
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}
$ip_address = getUserIP();

function writeLog($email, $ip, $status) {
    date_default_timezone_set('Europe/Lisbon');
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    
    $logFile = $logDir . 'register_history.log'; 
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Ação: Recuperar Password | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$conn->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL 25 HOUR)");

$stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 24 HOUR)");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$stmt->bind_result($total_failures);
$stmt->fetch();
$stmt->close();

$multiplier = floor($total_failures / $max_attempts);
$lockout_time = $base_lockout_minutes * pow(2, max(0, $multiplier)); 

$stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
$stmt->bind_param("si", $ip_address, $lockout_time);
$stmt->execute();
$stmt->bind_result($failed_attempts);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        writeLog('Desconhecido', $ip_address, "CRÍTICO - Falha CSRF");
        die("Erro de segurança: Token inválido.");
    }

    if (!empty($_POST['website_check'])) die("Bot detetado.");

    if ($failed_attempts >= $max_attempts) {
        $error_message = (isset($t['err_too_many_attempts']) ? $t['err_too_many_attempts'] : "Muitas tentativas. Aguarde.") . " " . $lockout_time . " min.";
        writeLog('Bloqueado', $ip_address, "BLOQUEIO - Excesso de tentativas");
    } 
    else {
        $action_type = $_POST['action_type'] ?? '';

        if ($action_type === 'initiate_reset') {
            $email = trim($_POST['email'] ?? '');
            
            if (isset($_POST['g-recaptcha-response'])) {
                $recaptcha_response = $_POST['g-recaptcha-response'];
                $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET_KEY_LOCAL."&response=".$recaptcha_response);
                $json = json_decode($verify);
                
                if ($json->success) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $stmt = $conn->prepare("SELECT id, email, is_active FROM utilizadores WHERE email = ?");
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $user = $result->fetch_assoc();
                            
                            if ($user['is_active'] == 0) {
                                $error_message = isset($t['err_account_inactive']) ? $t['err_account_inactive'] : "A sua conta está inativa. Contacte o suporte.";
                                writeLog($email, $ip_address, "FALHA - Conta Inativa");
                                $conn->query("INSERT INTO login_attempts (ip_address, attempt_time) VALUES ('$ip_address', NOW())");
                            } else {
                                $_SESSION['reset_email'] = $user['email'];
                                $_SESSION['reset_user_id'] = $user['id']; 
                                
                                writeLog($email, $ip_address, "SUCESSO - Email encontrado, passo 2");
                                $step = 2; 
                            }
                        } else {
                            $error_message = isset($t['err_email_not_found']) ? $t['err_email_not_found'] : "Este email não se encontra registado.";
                            writeLog($email, $ip_address, "FALHA - Email não registado");
                            $conn->query("INSERT INTO login_attempts (ip_address, attempt_time) VALUES ('$ip_address', NOW())");
                        }
                        $stmt->close();
                    } else {
                        $error_message = isset($t['err_invalid_email']) ? $t['err_invalid_email'] : "Email inválido.";
                    }
                } else {
                    $error_message = isset($t['err_captcha']) ? $t['err_captcha'] : "Captcha inválido.";
                }
            } else {
                $error_message = isset($t['err_fill_captcha']) ? $t['err_fill_captcha'] : "Preencha o captcha.";
            }
        }
        
        elseif ($action_type === 'finish_reset') {
            
            if (isset($_SESSION['security_verified']) && $_SESSION['security_verified'] === true && isset($_SESSION['reset_user_id'])) {
                
                $newPwd = $_POST['pass'] ?? '';
                $confirmPwd = $_POST['pass-conf'] ?? '';
                $user_id = $_SESSION['reset_user_id'];
                $email_log = $_SESSION['reset_email'] ?? 'Desconhecido';

                if (empty($newPwd) || empty($confirmPwd)) {
                    $error_message = "Por favor, preencha ambos os campos.";
                    $step = 3;
                }
                elseif ($newPwd !== $confirmPwd) {
                    $error_message = isset($t['err_pass_mismatch']) ? $t['err_pass_mismatch'] : "As passwords não coincidem.";
                    writeLog($email_log, $ip_address, "FALHA - Passwords não coincidem");
                    $step = 3;
                }
                elseif (strlen($newPwd) < 8 || 
                        !preg_match('/[A-Z]/', $newPwd) || 
                        !preg_match('/[a-z]/', $newPwd) || 
                        !preg_match('/[0-9]/', $newPwd) || 
                        !preg_match('/[^a-zA-Z0-9]/', $newPwd)) {
                    $error_message = isset($t['pass_requirements']) ? $t['pass_requirements'] : "Password fraca: Min 8 chars, 1 Maiúscula, 1 Minúscula, 1 Número, 1 Símbolo.";
                    writeLog($email_log, $ip_address, "FALHA - Password fraca");
                    $step = 3;
                }
                else {
                    $stmt = $conn->prepare("SELECT pass FROM utilizadores WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($res && password_verify($newPwd, $res['pass'])) {
                        $error_message = isset($t['err_pass_same']) ? $t['err_pass_same'] : "A nova password não pode ser igual à atual.";
                        writeLog($email_log, $ip_address, "FALHA - Password igual à anterior");
                        $step = 3;
                    } 
                    else {
                        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE utilizadores SET pass = ? WHERE id = ?");
                        $stmt->bind_param("si", $hash, $user_id);
                        
                        if ($stmt->execute()) {
                            $showSuccess = true;
                            writeLog($email_log, $ip_address, "SUCESSO - Password alterada");
                            
                            unset($_SESSION['security_verified']);
                            unset($_SESSION['reset_user_id']);
                            unset($_SESSION['reset_email']);
                            unset($_SESSION['temp_security_token']);

                            if (isset($_COOKIE['user_email'])) {
                                setcookie('user_email', '', time() - 3600, '/');
                            }
                        } else {
                            $error_message = isset($t['err_db_error']) ? $t['err_db_error'] : "Erro ao atualizar a base de dados.";
                            writeLog($email_log, $ip_address, "ERRO - Base de dados");
                            $step = 3;
                        }
                        $stmt->close();
                    }
                }
            } else {
                $error_message = isset($t['err_session_expired']) ? $t['err_session_expired'] : "Sessão expirada. Comece de novo.";
                writeLog('Desconhecido', $ip_address, "FALHA - Sessão expirada no passo 3");
                $step = 1;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($t['forgot_title']) ? $t['forgot_title'] : 'Recuperar Acesso'; ?> - SAPOSalas</title>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://www.google.com/recaptcha/api.js?onload=renderRecaptcha&render=explicit&hl=<?php echo $lang_code; ?>" async defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-white dark:bg-gray-900 font-['Poppins'] h-screen text-gray-800 dark:text-gray-100 transition-colors duration-300 overflow-x-hidden">

    <?php 
    $rootPath = './';
    include_once 'includes/navbar.php'; 
    ?>

    <div class="w-full h-full flex pt-20">
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12 bg-white dark:bg-gray-900 overflow-y-auto transition-colors duration-300">
            <div class="w-full max-w-md space-y-6">
                
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[#fbeaff] dark:bg-[#2d1b4e] mb-4 shadow-sm">
                        <i class="fa-solid fa-key text-2xl text-[#5d3e8f] dark:text-[#d8b4fe]"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo isset($t['forgot_title']) ? $t['forgot_title'] : 'Recuperar Acesso'; ?></h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo isset($t['forgot_subtitle']) ? $t['forgot_subtitle'] : 'Siga os passos para redefinir a sua senha.'; ?></p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-3 rounded-r text-sm">
                        <p><i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>

                <div id="step1_panel" class="<?php echo $step === 1 ? '' : 'hidden'; ?>">
                    <form class="space-y-4" action="repor-password.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action_type" value="initiate_reset">
                        <div style="display:none;"><input type="text" name="website_check" value=""></div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($t['label_email']) ? $t['label_email'] : 'Email da Conta'; ?></label>
                            <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm mt-1" placeholder="email@gmail.com">
                        </div>

                        <div class="text-center mt-4">
                            <div id="recaptcha-placeholder" data-sitekey="<?php echo RECAPTCHA_SITE_KEY_LOCAL; ?>" style="display: inline-block;"></div>
                        </div>

                        <button type="submit" class="w-full py-3 px-4 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-bold rounded-lg shadow-md transition-all text-sm">
                            <?php echo isset($t['btn_send_code']) ? $t['btn_send_code'] : 'Enviar Código'; ?>
                        </button>
                    </form>
                </div>

                <div id="step2_panel" class="<?php echo $step === 2 ? '' : 'hidden'; ?>">
                    <div class="text-center mb-6">
                        <p class="text-gray-600 dark:text-gray-300 font-medium"><?php echo isset($t['forgot_email_sent']) ? $t['forgot_email_sent'] : 'Email Enviado!'; ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo isset($t['forgot_enter_code']) ? $t['forgot_enter_code'] : 'Insira o código que enviámos para o seu email.'; ?></p>
                    </div>
                    
                    <div class="space-y-4">
                        <input type="text" id="security_code_input"  maxlength="32" 
                               class="w-full text-center text-xl tracking-widest font-bold py-3 border-2 border-[#5d3e8f] dark:border-[#d8b4fe] rounded-lg uppercase focus:outline-none focus:ring-2 focus:ring-[#5d3e8f]/50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        
                        <p id="security_msg" class="text-center text-sm font-bold min-h-[20px] dark:text-red-400"></p>
                        
                        <button onclick="verifyResetCode()" id="btnVerify" class="w-full py-3 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-bold rounded-lg transition">
                            <?php echo isset($t['btn_verify_code']) ? $t['btn_verify_code'] : 'Validar Código'; ?>
                        </button>
                        <button onclick="triggerEmailSend()" id="btnResend" class="w-full py-2 text-[#5d3e8f] dark:text-[#d8b4fe] text-sm hover:underline"><?php echo isset($t['btn_resend']) ? $t['btn_resend'] : 'Reenviar Código'; ?></button>
                    </div>
                </div>

                <div id="step3_panel" class="<?php echo $step === 3 ? '' : 'hidden'; ?>">
                    <form class="space-y-4" action="repor-password.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action_type" value="finish_reset">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($t['label_new_pass']) ? $t['label_new_pass'] : 'Nova Password'; ?></label>
                            <div class="relative mt-1">
                                <input type="password" name="pass" id="pass" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="********">
                                <i class="fa-regular fa-eye absolute right-3 top-3.5 text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]" onclick="togglePass('pass')"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($t['label_confirm']) ? $t['label_confirm'] : 'Confirmar'; ?></label>
                            <div class="relative mt-1">
                                <input type="password" name="pass-conf" id="pass-conf" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="********">
                                <i class="fa-regular fa-eye absolute right-3 top-3.5 text-gray-400 cursor-pointer hover:text-[#5d3e8f] dark:hover:text-[#d8b4fe]" onclick="togglePass('pass-conf')"></i>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo isset($t['pass_requirements']) ? $t['pass_requirements'] : '* Min. 8 chars, Maiúscula, Minúscula, Nº e Símbolo.'; ?></p>

                        <button type="submit" class="w-full py-3 bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:text-[#151414] dark:hover:bg-[#c084fc] text-white font-bold rounded-lg shadow-md transition-all">
                            <?php echo isset($t['btn_reset_pass']) ? $t['btn_reset_pass'] : 'Alterar Password'; ?>
                        </button>
                    </form>
                </div>

                <div class="text-center mt-6">
                    <a href="login.php" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white transition-colors"><?php echo isset($t['link_back_login']) ? $t['link_back_login'] : 'Voltar ao Login'; ?></a>
                </div>

            </div>
        </div>
        
        <div class="hidden lg:flex lg:w-1/2 relative bg-[#5d3e8f] dark:bg-[#2d1b4e] items-center justify-center transition-colors duration-300">
            
            <img src="https://images.unsplash.com/photo-1497294815431-9365093b7331?q=80&w=1920&auto=format&fit=crop" 
                 class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-60 dark:opacity-60" 
                 alt="Background Sala de Aula">
            
            <div class="absolute inset-0 bg-gradient-to-br from-[#5d3e8f] to-black opacity-60 dark:from-[#2d1b4e] dark:to-black"></div>

            <div class="relative z-10 p-12 text-white text-center max-w-lg">
                <div class="mb-6">
                    <i class="fa-solid fa-calendar-days text-6xl opacity-80 animate-bounce"></i>
                </div>
                <h2 class="text-4xl font-extrabold mb-6 tracking-tight"><?php echo isset($t['forgot_side_title']) ? $t['forgot_side_title'] : 'Segurança em Primeiro Lugar'; ?></h2>
                <p class="text-lg text-purple-100 font-light leading-relaxed">
                    <?php echo isset($t['forgot_side_text']) ? $t['forgot_side_text'] : 'Recupere o acesso à sua conta de forma segura e rápida.'; ?>
                </p>
            </div>
        </div>
    </div>
 
    <div class="text-center bg-white dark:bg-gray-900 transition-colors duration-300"><?php include_once 'includes/footer.php'; ?></div>

    <script>
    function togglePass(id){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}

    function triggerEmailSend() {
        const btn = document.getElementById('btnResend');
        if(btn) { btn.innerText = "<?php echo isset($t['js_sending']) ? $t['js_sending'] : 'A Enviar...'; ?>"; btn.disabled = true; }
        
        const formData = new FormData();
        formData.append('action', 'send_code');
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('backend/api_security.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(btn) { 
                btn.innerText = data.success ? "<?php echo isset($t['js_code_sent']) ? $t['js_code_sent'] : 'Código Enviado!'; ?>" : "<?php echo isset($t['js_try_again']) ? $t['js_try_again'] : 'Tentar Novamente'; ?>"; 
                setTimeout(() => { btn.innerText = "<?php echo isset($t['btn_resend']) ? $t['btn_resend'] : 'Reenviar Código'; ?>"; btn.disabled = false; }, 5000); 
            }
        });
    }

    function verifyResetCode() {
        const code = document.getElementById('security_code_input').value;
        const msg = document.getElementById('security_msg');
        const btn = document.getElementById('btnVerify');

        if(code.length < 6) { msg.innerText = "<?php echo isset($t['js_code_incomplete']) ? $t['js_code_incomplete'] : 'Código incompleto.'; ?>"; msg.style.color="#ef4444"; return; }

        btn.disabled = true; btn.innerText = "<?php echo isset($t['js_verifying']) ? $t['js_verifying'] : 'A Verificar...'; ?>"; msg.innerText = "";

        const formData = new FormData();
        formData.append('action', 'verify_code');
        formData.append('code', code);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('backend/api_security.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('step2_panel').classList.add('hidden');
                document.getElementById('step3_panel').classList.remove('hidden');
            } else {
                msg.innerText = data.message || "<?php echo isset($t['js_code_incorrect']) ? $t['js_code_incorrect'] : 'Código incorreto.'; ?>";
                msg.style.color = "#ef4444";
                btn.disabled = false; btn.innerText = "<?php echo isset($t['btn_verify_code']) ? $t['btn_verify_code'] : 'Validar Código'; ?>";
            }
        })
        .catch(err => {
            msg.innerText = "<?php echo isset($t['js_error_connection']) ? $t['js_error_connection'] : 'Erro de ligação.'; ?>"; msg.style.color="#ef4444";
            btn.disabled = false; btn.innerText = "<?php echo isset($t['btn_verify_code']) ? $t['btn_verify_code'] : 'Validar Código'; ?>";
        });
    }

    <?php if ($step === 2): ?>
    document.addEventListener("DOMContentLoaded", () => { triggerEmailSend(); });
    <?php endif; ?>

    <?php if ($showSuccess): ?>
    document.addEventListener("DOMContentLoaded", () => {
        Swal.fire({
            title: '<?php echo isset($t['msg_pass_reset_success']) ? $t['msg_pass_reset_success'] : "Password Alterada!"; ?>',
            text: '<?php echo isset($t['msg_redirecting']) ? $t['msg_redirecting'] : "A redirecionar para o login..."; ?>',
            icon: 'success',
            iconColor: '#5d3e8f',
            confirmButtonColor: '#5d3e8f',
            timer: 2500,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'login.php';
        });
    });
    <?php endif; ?>
    
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