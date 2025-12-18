<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
session_start();

require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function writeLog($email, $ip, $status) {
    date_default_timezone_set('Europe/Lisbon');
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $logFile = $logDir . 'login_history.log'; 
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$ip_address = getUserIP();

try {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Token de segurança inválido.");
    }

    $path_to_phpmailer = '../PHPMailer/src/'; 
    if (!file_exists($path_to_phpmailer . 'Exception.php')) {
        $path_to_phpmailer = '../../PHPMailer/src/';
        if (!file_exists($path_to_phpmailer . 'Exception.php')) {
            throw new Exception("Biblioteca PHPMailer não encontrada.");
        }
    }

    require_once $path_to_phpmailer . 'Exception.php';
    require_once $path_to_phpmailer . 'PHPMailer.php';
    require_once $path_to_phpmailer . 'SMTP.php';

    $action = $_POST['action'] ?? '';
    $context = $_POST['context'] ?? 'general'; 

    if ($action === 'send_code') {
        
        $email = $_SESSION['reset_email'] ?? $_SESSION['user_email'] ?? $_SESSION['email'] ?? $_SESSION['temp_login_data']['email'] ?? null;
        
        if (!$email) {
            throw new Exception("Sessão expirada. Faça login novamente.");
        }

        $token = strtoupper(bin2hex(random_bytes(8))); 
        
        $_SESSION['temp_security_token'] = $token;
        $_SESSION['security_verified'] = false;
        
        if ($context === '2fa' || $context === '2fa_change') {
            $_SESSION['security_verified_2fa'] = false;
        }
        if ($context === '2fa_disable') {
            $_SESSION['security_verified_2fa_disable'] = false;
        }
        
        $_SESSION['token_expiry'] = time() + ($context === 'delete' ? 86400 : 900); 

        $lang_email = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'pt';

       $security_texts = [
            'pt' => [
                'general' => [
                    'subject' => 'Código de Verificação - SAPOSalas',
                    'title'   => 'Código de Segurança',
                    'text'    => 'Utilize o seguinte código para validar a sua identidade na plataforma:',
                    'warning' => 'Este código expira em 15 minutos. Se não solicitou esta ação, ignore este email.'
                ],
                'delete' => [
                    'subject' => 'Confirmar Desativação de Conta - SAPOSalas',
                    'title'   => 'Confirmar Desativação',
                    'text'    => 'Recebemos um pedido para desativar a sua conta. Para confirmar esta ação, utilize o seguinte código:',
                    'warning' => 'Este código é válido por 24 horas. Esta ação é reversível se contactar o suporte.'
                ],
                '2fa_change' => [
                    'subject' => 'Configuração 2FA - SAPOSalas',
                    'title'   => 'Verificar Identidade',
                    'text'    => 'Para configurar a Autenticação de Dois Fatores (2FA), utilize o seguinte código:',
                    'warning' => 'Este código expira em 15 minutos.'
                ],
                '2fa_disable' => [
                    'subject' => 'Desativar 2FA - SAPOSalas',
                    'title'   => 'Desativar Autenticação',
                    'text'    => 'Recebemos um pedido para remover a proteção 2FA da sua conta. Utilize o código abaixo para confirmar:',
                    'warning' => 'Se não solicitou isto, a sua conta pode estar em risco. Mude a password imediatamente.'
                ],
                'footer' => 'Equipa de Segurança SAPOSalas'
            ],
            'en' => [
                'general' => [
                    'subject' => 'Verification Code - SAPOSalas',
                    'title'   => 'Security Code',
                    'text'    => 'Use the following code to verify your identity on the platform:',
                    'warning' => 'This code expires in 15 minutes. If you did not request this, please ignore this email.'
                ],
                'delete' => [
                    'subject' => 'Confirm Account Deactivation - SAPOSalas',
                    'title'   => 'Confirm Deactivation',
                    'text'    => 'We received a request to deactivate your account. To confirm this action, please use the following code:',
                    'warning' => 'This code is valid for 24 hours. This action can be reversed by contacting support.'
                ],
                '2fa_change' => [
                    'subject' => '2FA Security Change - SAPOSalas',
                    'title'   => 'Verify Identity',
                    'text'    => 'To set up your Two-Factor Authentication (2FA) settings, please use the following code:',
                    'warning' => 'This code expires in 15 minutes. If you did not request this change, secure your account.'
                ],
                '2fa_disable' => [
                    'subject' => 'Disable 2FA - SAPOSalas',
                    'title'   => 'Disable Authentication',
                    'text'    => 'We received a request to remove 2FA protection from your account. Use the code below to confirm:',
                    'warning' => 'If you did not request this, your account may be at risk. Change your password immediately.'
                ],
                'footer' => 'SAPOSalas Security Team'
            ],
             'es' => [
                'general' => [
                    'subject' => 'Código de Verificación - SAPOSalas',
                    'title'   => 'Código de Seguridad',
                    'text'    => 'Utilice el siguiente código para validar su identidad en la plataforma:',
                    'warning' => 'Este código expira en 15 minutos. Si no solicitó esta acción, ignore este correo.'
                ],
                'delete' => [
                    'subject' => 'Confirmación de Desactivación de Cuenta - SAPOSalas',
                    'title'   => 'Confirmar desactivación',
                    'text'    => 'Hemos recibido una solicitud para desactivar su cuenta. Para confirmar esta acción, utilice el siguiente código:',
                    'warning' => 'Este código es válido por 24 horas. Esta acción puede revertirse si contacta con el soporte.'
                ],
                '2fa_change' => [
                    'subject' => 'Cambio de Seguridad 2FA - SAPOSalas',
                    'title'   => 'Verificar Identidad',
                    'text'    => 'Para configurar la Autenticación de Dos Factores (2FA), utilice el siguiente código:',
                    'warning' => 'Este código expira en 15 minutos. Si no solicitó este cambio, proteja su cuenta.'
                ],
                '2fa_disable' => [
                    'subject' => 'Desactivar 2FA - SAPOSalas',
                    'title'   => 'Desactivar Autenticación',
                    'text'    => 'Hemos recibido una solicitud para eliminar la protección 2FA de su cuenta. Utilice el código a continuación para confirmar:',
                    'warning' => 'Si no solicitó esto, su cuenta puede estar en riesgo. Cambie su contraseña inmediatamente.'
                ],
                'footer' => 'Equipo de Seguridad SAPOSalas'
            ],
            'fr' => [
                'general' => [
                    'subject' => 'Code de Vérification - SAPOSalas',
                    'title'   => 'Code de Sécurité',
                    'text'    => 'Utilisez le code suivant pour valider votre identité sur la plateforme :',
                    'warning' => 'Ce code expire dans 15 minutes. Si vous n\'avez pas demandé cette action, ignorez cet e-mail.'
                ],
                'delete' => [
                    'subject' => 'Confirmation de Désactivation de Compte - SAPOSalas',
                    'title'   => 'Confirmer la désactivation',
                    'text'    => 'Nous avons reçu une demande de désactivation de votre compte. Pour confirmer cette action, utilisez le code suivant :',
                    'warning' => 'Ce code est valable pendant 24 heures. Cette action peut être annulée en contactant le support.'
                ],
                '2fa_change' => [
                    'subject' => 'Modification de Sécurité 2FA - SAPOSalas',
                    'title'   => 'Vérifier l\'Identité',
                    'text'    => 'Pour configurer l\'Authentification à Deux Facteurs (2FA), utilisez le code suivant :',
                    'warning' => 'Ce code expire dans 15 minutes. Si vous n\'avez pas demandé ce changement, sécurisez votre compte.'
                ],
                '2fa_disable' => [
                    'subject' => 'Désactiver 2FA - SAPOSalas',
                    'title'   => 'Désactiver l\'Authentification',
                    'text'    => 'Nous avons reçu une demande pour supprimer la protection 2FA de votre compte. Utilisez le code ci-dessous pour confirmer :',
                    'warning' => 'Si vous n\'avez pas demandé cela, votre compte peut être en danger. Changez votre mot de passe immédiatement.'
                ],
                'footer' => 'Équipe de Sécurité SAPOSalas'
            ],
            'de' => [
                'general' => [
                    'subject' => 'Bestätigungscode - SAPOSalas',
                    'title'   => 'Sicherheitscode',
                    'text'    => 'Verwenden Sie den folgenden Code, um Ihre Identität auf der Plattform zu bestätigen:',
                    'warning' => 'Dieser Code läuft in 15 Minuten ab. Wenn Sie dies nicht angefordert haben, ignorieren Sie diese E-Mail.'
                ],
                'delete' => [
                    'subject' => 'Bestätigung der Kontodeaktivierung - SAPOSalas',
                    'title'   => 'Deaktivierung bestätigen',
                    'text'    => 'Wir haben eine Anfrage erhalten, Ihr Konto zu deaktivieren. Um diese Aktion zu bestätigen, verwenden Sie bitte den folgenden Code:',
                    'warning' => 'Dieser Code ist 24 Stunden gültig. Diese Aktion kann rückgängig gemacht werden, wenn Sie den Support kontaktieren.'
                ],
                '2fa_change' => [
                    'subject' => '2FA Sicherheitsänderung - SAPOSalas',
                    'title'   => 'Identität Überprüfen',
                    'text'    => 'Um Ihre Einstellungen für die Zwei-Faktor-Authentifizierung (2FA) zu ändern, verwenden Sie bitte den folgenden Code:',
                    'warning' => 'Dieser Code läuft in 15 Minuten ab. Wenn Sie diese Änderung nicht angefordert haben, sichern Sie Ihr Konto.'
                ],
                 '2fa_disable' => [
                    'subject' => '2FA Deaktivieren - SAPOSalas',
                    'title'   => 'Authentifizierung Deaktivieren',
                    'text'    => 'Wir haben eine Anfrage erhalten, den 2FA-Schutz von Ihrem Konto zu entfernen. Verwenden Sie den folgenden Code zur Bestätigung:',
                    'warning' => 'Wenn Sie dies nicht angefordert haben, könnte Ihr Konto gefährdet sein. Ändern Sie sofort Ihr Passwort.'
                ],
                'footer' => 'SAPOSalas Sicherheitsteam'
            ],
            'ru' => [
                'general' => [
                    'subject' => 'Код подтверждения - SAPOSalas',
                    'title'   => 'Код безопасности',
                    'text'    => 'Используйте следующий код для подтверждения вашей личности на платформе:',
                    'warning' => 'Срок действия кода истекает через 15 минут. Если вы не запрашивали это действие, проигнорируйте это письмо.'
                ],
                'delete' => [
                    'subject' => 'Подтверждение деактивации аккаунта — SAPOSalas',
                    'title'   => 'Подтвердить деактивацию',
                    'text'    => 'Мы получили запрос на деактивацию вашего аккаунта. Чтобы подтвердить это действие, используйте следующий код:',
                    'warning' => 'Этот код действует 24 часа. Это действие можно отменить, связавшись со службой поддержки.'
                ],
                '2fa_change' => [
                    'subject' => 'Изменение настроек 2FA - SAPOSalas',
                    'title'   => 'Подтверждение личности',
                    'text'    => 'Для изменения настроек двухфакторной аутентификации (2FA) используйте следующий код:',
                    'warning' => 'Срок действия кода истекает через 15 минут. Если вы не запрашивали это изменение, обезопасьте свой аккаунт.'
                ],
                '2fa_disable' => [
                    'subject' => 'Отключить 2FA - SAPOSalas',
                    'title'   => 'Отключить аутентификацию',
                    'text'    => 'Мы получили запрос на удаление защиты 2FA из вашего аккаунта. Используйте код ниже для подтверждения:',
                    'warning' => 'Если вы этого не запрашивали, ваш аккаунт может быть под угрозой. Немедленно смените пароль.'
                ],
                'footer' => 'Служба безопасности SAPOSalas'
            ],
            'zh' => [
                'general' => [
                    'subject' => '验证码 - SAPOSalas',
                    'title'   => '安全码',
                    'text'    => '使用以下代码在平台上验证您的身份：',
                    'warning' => '此代码将在 15 分钟后过期。如果您未请求此操作，请忽略此电子邮件。'
                ],
                'delete' => [
                    'subject' => '账户停用确认 - SAPOSalas',
                    'title'   => '确认停用',
                    'text'    => '我们收到了停用您账户的请求。要确认此操作，请使用以下验证码：',
                    'warning' => '该验证码有效期为24小时。如需恢复操作，请联系技术支持。'
                ],
                '2fa_change' => [
                    'subject' => '2FA 安全设置更改 - SAPOSalas',
                    'title'   => '验证身份',
                    'text'    => '要更改双重认证 (2FA) 设置，请使用以下代码：',
                    'warning' => '此代码将在 15 分钟后过期。如果您未请求此更改，请保护您的帐户。'
                ],
                 '2fa_disable' => [
                    'subject' => '禁用 2FA - SAPOSalas',
                    'title'   => '禁用认证',
                    'text'    => '我们收到请求，要求从您的帐户中移除 2FA 保护。请使用以下代码确认：',
                    'warning' => '如果您未请求此操作，您的帐户可能面临风险。请立即更改密码。'
                ],
                'footer' => 'SAPOSalas 安全团队'
            ],
            'it' => [
                'general' => [
                    'subject' => 'Codice di Verifica - SAPOSalas',
                    'title'   => 'Codice di Sicurezza',
                    'text'    => 'Usa il seguente codice per convalidare la tua identità sulla piattaforma:',
                    'warning' => 'Questo codice scade tra 15 minuti. Se non hai richiesto questa azione, ignora questa email.'
                ],
                'delete' => [
                    'subject' => 'Conferma di Disattivazione dell’Account - SAPOSalas',
                    'title'   => 'Conferma disattivazione',
                    'text'    => 'Abbiamo ricevuto una richiesta per disattivare il tuo account. Per confermare questa azione, utilizza il seguente codice:',
                    'warning' => 'Questo codice è valido per 24 ore. Questa azione è reversibile contattando il supporto.'
                ],
                '2fa_change' => [
                    'subject' => 'Modifica Sicurezza 2FA - SAPOSalas',
                    'title'   => 'Verifica Identità',
                    'text'    => 'Per modificare le impostazioni dell\'Autenticazione a Due Fattori (2FA), usa il seguente codice:',
                    'warning' => 'Questo codice scade tra 15 minuti. Se non hai richiesto questa modifica, proteggi il tuo account.'
                ],
                '2fa_disable' => [
                    'subject' => 'Disattiva 2FA - SAPOSalas',
                    'title'   => 'Disattiva Autenticazione',
                    'text'    => 'Abbiamo ricevuto una richiesta per rimuovere la protezione 2FA dal tuo account. Usa il codice qui sotto per confermare:',
                    'warning' => 'Se non hai richiesto questo, il tuo account potrebbe essere a rischio. Cambia la password immediatamente.'
                ],
                'footer' => 'Team di Sicurezza SAPOSalas'
            ]
        ];


        if (!array_key_exists($lang_email, $security_texts)) {
            $lang_email = 'pt';
        }

        $type_key = 'general';
        if ($context === 'delete') $type_key = 'delete';
        elseif ($context === '2fa' || $context === '2fa_change') $type_key = '2fa_change';
        elseif ($context === '2fa_disable') $type_key = '2fa_disable';

        $et = $security_texts[$lang_email][$type_key];
        $footer_text = $security_texts[$lang_email]['footer'];

        $color_theme = ($context === 'delete' || $context === '2fa_disable') ? '#d9534f' : '#5d3e8f'; 
        $icon_url = ($context === 'delete' || $context === '2fa_disable') 
            ? 'https://img.icons8.com/ios-filled/100/d9534f/high-priority.png' 
            : 'https://img.icons8.com/ios-filled/100/5d3e8f/security-shield-green.png';

    
        if (!function_exists('get_credencial')) {
            function get_credencial($key, $default = null) {
                if (isset($_ENV[$key]) && !empty($_ENV[$key])) return $_ENV[$key];
                $val = getenv($key);
                if ($val !== false && !empty($val)) return $val;
                if (defined($key)) return constant($key);
                if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) return $_SERVER[$key];
                return $default;
            }
        }

        $mail = new PHPMailer(true); 
        $mail->isSMTP();
        
        $mail->Host       = get_credencial('SMTP_HOST', 'smtp.gmail.com'); 
        $mail->SMTPAuth   = true;
        
        $smtp_user = get_credencial('SMTP_USER');
        $smtp_pass = get_credencial('SMTP_PASS');

        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = get_credencial('SMTP_PORT', '587');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp_user, 'Segurança SAPOSalas');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = $et['subject'];
        
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .email-container { max-width: 500px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .header-strip { background-color: {$color_theme}; height: 6px; width: 100%; }
        .content { padding: 40px; text-align: center; }
        .icon-container { margin-bottom: 20px; }
        .title { color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 10px 0; }
        .text { color: #4b5563; font-size: 16px; line-height: 1.5; margin: 0 0 30px 0; }
        .code-box { background-color: #f8f7fa; border: 2px dashed {$color_theme}; border-radius: 12px; padding: 20px; margin-bottom: 30px; display: inline-block; width: auto; max-width: 90%; }
        .code { font-family: 'Courier New', monospace; font-size: 22px; font-weight: bold; color: {$color_theme}; letter-spacing: 2px; margin: 0; word-break: break-all; overflow-wrap: break-word; line-height: 1.4; }
        .footer { background-color: #f9fafb; padding: 15px; text-align: center; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header-strip"></div>
        <div class="content">
            <div class="icon-container">
                <img src="{$icon_url}" alt="Security" style="width: 60px; height: 60px;">
            </div>
            <h1 class="title">{$et['title']}</h1>
            <p class="text">{$et['text']}</p>
            <div class="code-box">
                <p class="code">{$token}</p>
            </div>
            <p style="color: #9ca3af; font-size: 13px; margin: 0;">
                {$et['warning']}
            </p>
        </div>
        <div class="footer">
            <p style="margin: 0;">{$footer_text}</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->send();
        writeLog($email, $ip_address, "2FA ($context) - Código Enviado");
        $response = ['success' => true, 'message' => 'Código enviado!'];

    } 
    elseif ($action === 'verify_code') {
        $inputCode = strtoupper(trim($_POST['code'] ?? ''));
        
        $sessionCode = $_SESSION['temp_security_token'] ?? null;
        
        $expiry = $_SESSION['token_expiry'] ?? 0;
        if (time() > $expiry) {
             throw new Exception("O código expirou. Por favor, peça um novo.");
        }

        if ($sessionCode && $inputCode === $sessionCode) {
            
            if (isset($_SESSION['temp_login_data'])) {
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

                writeLog($userData['email'], $ip_address, "SUCESSO TOTAL - Login Efetuado");

                unset($_SESSION['temp_login_data']);
                unset($_SESSION['temp_security_token']);
                unset($_SESSION['token_expiry']);

                $redirect = ($userData['role'] === 'admin') ? 'admin.php' : 'reservar.php';
                $response = ['success' => true, 'message' => 'Login efetuado!', 'redirect' => $redirect];
            
            } 
            else {
                $email_log = $_SESSION['reset_email'] ?? $_SESSION['user_email'] ?? 'Desconhecido';
                writeLog($email_log, $ip_address, "SUCESSO - Código Verificado ($context)");

                if ($context === '2fa' || $context === '2fa_change') {
                    $_SESSION['security_verified_2fa'] = true;
                } 
                elseif ($context === '2fa_disable') {
                    $_SESSION['security_verified_2fa_disable'] = true;
                }
                else {
                    $_SESSION['security_verified'] = true;
                }

                unset($_SESSION['temp_security_token']);
                unset($_SESSION['token_expiry']);
                
                $response = ['success' => true, 'message' => 'Código correto!'];
            }

        } else {
            $email_log = $_SESSION['reset_email'] ?? $_SESSION['temp_login_data']['email'] ?? $_SESSION['user_email'] ?? 'Desconhecido';
            writeLog($email_log, $ip_address, "FALHA - Código 2FA Incorreto");
            
            throw new Exception("Código incorreto.");
        }
    } else {
        throw new Exception("Ação inválida.");
    }

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

ob_end_clean();
echo json_encode($response);
exit;
?>