<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
session_start();

require_once 'config.php';

$path_to_phpmailer = '../PHPMailer/src/';
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    if (file_exists($path_to_phpmailer . 'Exception.php')) {
        require_once $path_to_phpmailer . 'Exception.php';
        require_once $path_to_phpmailer . 'PHPMailer.php';
        require_once $path_to_phpmailer . 'SMTP.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


// Função helper para obter credenciais (compatível com .env e config.php direto)
if (!function_exists('get_credencial')) {
    function get_credencial($key, $default = null)
    {
        if (isset($_ENV[$key]) && !empty($_ENV[$key]))
            return $_ENV[$key];
        $val = getenv($key);
        if ($val !== false && !empty($val))
            return $val;
        if (defined($key))
            return constant($key);
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key]))
            return $_SERVER[$key];
        return $default;
    }
}

$cloud_name = get_credencial('CLOUDINARY_CLOUD_NAME', '');
$api_key = get_credencial('CLOUDINARY_API_KEY', '');
$api_secret = get_credencial('CLOUDINARY_API_SECRET', '');

header('Content-Type: application/json');

$response = ["success" => false, "error" => "Erro desconhecido."];
$user_id = $_SESSION['user_id'] ?? null;


$t = [];
if (file_exists('../includes/lang.php')) {
    require_once '../includes/lang.php';
}

if (!$user_id) {
    $response = ["success" => false, "error" => "Sessão expirada."];
    goto final_output;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $response = ["success" => false, "error" => "Token de segurança inválido."];
    goto final_output;
}

$action = $_POST['action'] ?? '';
$errors = [];

function verifyCurrentPassword($conn, $user_id, $pwdInput)
{
    $stmt = $conn->prepare("SELECT pass FROM utilizadores WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res && password_verify($pwdInput, $res['pass']);
}

if ($action === 'update_password') {
    if (!isset($_SESSION['security_verified']) || $_SESSION['security_verified'] !== true) {
        $errors[] = "Acesso negado. Valide o código de segurança.";
    } else {
        $currentPwd = $_POST['edit_current_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        if (empty($currentPwd) || empty($newPwd)) {
            $errors[] = "Preencha todos os campos.";
        } elseif ($newPwd !== $confirmPwd) {
            $errors[] = "As passwords não coincidem.";
        } elseif ($currentPwd === $newPwd) {
            $errors[] = "A nova password não pode ser igual à atual.";
        } elseif (strlen($newPwd) < 8 || !preg_match('/[A-Z]/', $newPwd) || !preg_match('/[a-z]/', $newPwd) || !preg_match('/[0-9]/', $newPwd) || !preg_match('/[^a-zA-Z0-9]/', $newPwd)) {
            $errors[] = "Password fraca. Requisitos: min 8 caracteres, maiúscula, minúscula, número e símbolo.";
        }

        if (empty($errors)) {
            if (!verifyCurrentPassword($conn, $user_id, $currentPwd)) {
                $errors[] = "Password atual incorreta.";
            } else {
                $hash = password_hash($newPwd, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE utilizadores SET pass=? WHERE id=?");
                $stmt->bind_param("si", $hash, $user_id);

                if ($stmt->execute()) {
                    unset($_SESSION['security_verified']);
                    unset($_SESSION['temp_security_token']);
                    $response = ["success" => true, "message" => isset($t['msg_pass_changed_success']) ? $t['msg_pass_changed_success'] : "Password alterada.", "logout" => true];
                } else {
                    $errors[] = "Erro na base de dados.";
                }
                $stmt->close();
            }
        }
    }
} elseif ($action === 'update_profile') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $data = $_POST['datanascimento'] ?? '';
    $pwd = $_POST['current_password'] ?? '';
    $tel = trim($_POST['telefone'] ?? '');
    $gen = $_POST['genero'] ?? 'O';


    if (empty($nome) || empty($email) || empty($data)) {
        $errors[] = "Preencha os campos obrigatórios.";
    }
    if (strlen($nome) < 2) {
        $errors[] = "O nome deve ter pelo menos 2 caracteres.";
    }
    if (strlen($nome) > 80) {
        $errors[] = "O nome não pode ter mais de 80 caracteres.";
    }
    if (!preg_match('/^[\p{L}\s\'-]+$/u', $nome)) {
        $errors[] = "O nome contém caracteres inválidos.";
    }
    if (strlen($email) > 255) {
        $errors[] = "O email não pode ter mais de 255 caracteres.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Formato de email inválido.";
    }
    if (!empty($tel)) {

        $tel_limpo = preg_replace('/[\s\-\(\)]/', '', $tel);
        if (strlen($tel_limpo) < 9 || strlen($tel_limpo) > 15) {
            $errors[] = "Número de telemóvel inválido (deve ter entre 9 e 15 dígitos).";
        } elseif (!preg_match('/^[+]?[0-9]{9,15}$/', $tel_limpo)) {
            $errors[] = "Número de telemóvel inválido (apenas números e + no início).";
        }

        if (strlen($tel) > 20) {
            $errors[] = "O número de telemóvel é muito longo.";
        }
    }

    if (!empty($data)) {
        $data_obj = DateTime::createFromFormat('Y-m-d', $data);
        $data_hoje = new DateTime('today');
        $data_minima = DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('-120 years')));
        $data_maxima = DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('-13 years')));

        if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
            $errors[] = "Formato de data inválido.";
        } elseif ($data_obj > $data_hoje) {
            $errors[] = "A data de nascimento não pode ser no futuro.";
        } elseif ($data_obj < $data_minima) {
            $errors[] = "Data de nascimento inválida (muito antiga).";
        } elseif ($data_obj > $data_maxima) {
            $errors[] = "Deve ter pelo menos 13 anos para usar esta plataforma.";
        }
    }

    if (!in_array($gen, ['M', 'F', 'O'])) {
        $errors[] = "Género inválido.";
    }
    if (empty($errors) && !verifyCurrentPassword($conn, $user_id, $pwd)) {
        $errors[] = "Password incorreta.";
    }
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM utilizadores WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0)
            $errors[] = "Email já em uso.";
        $stmt->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT nome, email, datanascimento, telefone, genero FROM utilizadores WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $currentUserData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hasChanges = (
            $nome !== $currentUserData['nome'] ||
            $email !== $currentUserData['email'] ||
            $data !== $currentUserData['datanascimento'] ||
            $tel !== $currentUserData['telefone'] ||
            $gen !== $currentUserData['genero'] ||
            !empty($_FILES['imagem_perfil']['tmp_name'])
        );

        if (!$hasChanges) {
            $errors[] = "Nenhuma alteração detetada.";
        }
    }

    $imgUrl = null;
    if (empty($errors) && !empty($_FILES['imagem_perfil']['tmp_name'])) {
        $file = $_FILES['imagem_perfil'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validação de tamanho máximo (5MB)
        $max_file_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_file_size) {
            $errors[] = "A imagem é muito grande (máximo 5MB).";
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($ext, $allowed) || strpos($mime, 'image/') !== 0) {
            $errors[] = "Ficheiro inválido. Apenas imagens reais são permitidas.";
        } else {
            if (empty($cloud_name) || empty($api_key) || empty($api_secret)) {
                $errors[] = "Erro de configuração do Cloudinary.";
                goto skip_upload;
            }
            $ts = time();
            $sig = sha1("timestamp=$ts$api_secret");
            $ch = curl_init("https://api.cloudinary.com/v1_1/$cloud_name/image/upload");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file' => new CURLFile($file['tmp_name']),
                'api_key' => $api_key,
                'timestamp' => $ts,
                'signature' => $sig
            ]);
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($res['secure_url'])) {
                $imgUrl = $res['secure_url'];
            } else {
                $errors[] = "Erro no upload para a cloud.";
            }
        }
    }

    skip_upload:

    if (empty($errors)) {
        $sql = "UPDATE utilizadores SET nome=?, email=?, datanascimento=?, telefone=?, genero=?";
        $params = [$nome, $email, $data, $tel, $gen];
        $types = "sssss";
        if ($imgUrl) {
            $sql .= ", imagem_perfil=?";
            $params[] = $imgUrl;
            $types .= "s";
        }
        $sql .= " WHERE id=?";
        $params[] = $user_id;
        $types .= "i";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $response = ["success" => true, "message" => "Perfil atualizado!"];
        } else {
            $errors[] = "Erro SQL.";
        }
        $stmt->close();
    }
} elseif ($action === 'execute_delete') {

    if (!isset($_SESSION['security_verified']) || $_SESSION['security_verified'] !== true) {
        $errors[] = "A validação de segurança expirou ou falhou. Por favor, peça um novo código.";
    } else {


        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM reservas 
                                      WHERE user_id = ? 
                                      AND status_reserva = 'ativa' 
                                      AND CONCAT(data, ' ', hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 12 HOUR)");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($res_check['total'] > 0) {
            $errors[] = "Não é possível desativar a conta pois tem reservas agendadas para as próximas 12 horas. Cancele-as primeiro ou aguarde.";
        } else {

            $stmt_get = $conn->prepare("SELECT email, nome FROM utilizadores WHERE id = ?");
            $stmt_get->bind_param("i", $user_id);
            $stmt_get->execute();
            $user_info = $stmt_get->get_result()->fetch_assoc();
            $stmt_get->close();

            if ($user_info) {
                $email_destino = $user_info['email'];
                $nome_destino = $user_info['nome'];


                $stmt = $conn->prepare("UPDATE utilizadores SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {


                    $stmt_cancel = $conn->prepare("UPDATE reservas SET status_reserva = 'cancelada' 
                                                   WHERE user_id = ? 
                                                   AND status_reserva = 'ativa' 
                                                   AND CONCAT(data, ' ', hora_inicio) > NOW()");
                    $stmt_cancel->bind_param("i", $user_id);
                    $stmt_cancel->execute();
                    $stmt_cancel->close();


                    try {
                        $lang_email = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'pt';


                        $goodbye_texts = [
                            'pt' => [
                                'subject' => 'Conta Desativada: ' . $nome_destino,
                                'title' => 'Conta Desativada com Sucesso',
                                'text' => 'A tua conta foi desativada e as reservas futuras foram canceladas.<br><br>Se quiseres reativar a tua conta, por favor contacta o nosso suporte.',
                                'btn' => 'Contactar Suporte',
                                'footer' => 'Com os melhores cumprimentos,<br>A Equipa SAPOSalas'
                            ],
                            'en' => [
                                'subject' => 'Account Deactivated: ' . $nome_destino,
                                'title' => 'Account Successfully Deactivated',
                                'text' => 'Your account has been deactivated and future bookings cancelled.<br><br>If you wish to reactivate your account, please contact our support.',
                                'btn' => 'Contact Support',
                                'footer' => 'Best regards,<br>The SAPOSalas Team'
                            ],
                            'es' => [
                                'subject' => 'Cuenta Desactivada: ' . $nome_destino,
                                'title' => 'Cuenta Desactivada con Éxito',
                                'text' => 'Su cuenta ha sido desactivada y las reservas futuras canceladas.<br><br>Si desea reactivar su cuenta, por favor contacte con nuestro soporte.',
                                'btn' => 'Contactar Soporte',
                                'footer' => 'Saludos cordiales,<br>El Equipo SAPOSalas'
                            ],
                            'fr' => [
                                'subject' => 'Compte Désactivé : ' . $nome_destino,
                                'title' => 'Compte Désactivé avec Succès',
                                'text' => 'Votre compte a été désactivé et les réservations futures annulées.<br><br>Si vous souhaitez réactiver votre compte, veuillez contacter notre support.',
                                'btn' => 'Contacter le Support',
                                'footer' => 'Cordialement,<br>L\'Équipe SAPOSalas'
                            ],
                            'de' => [
                                'subject' => 'Konto Deaktiviert: ' . $nome_destino,
                                'title' => 'Konto Erfolgreich Deaktiviert',
                                'text' => 'Ihr Konto wurde deaktiviert und zukünftige Buchungen storniert.<br><br>Wenn Sie Ihr Konto reaktivieren möchten, wenden Sie sich bitte an unseren Support.',
                                'btn' => 'Support Kontaktieren',
                                'footer' => 'Mit freundlichen Grüßen,<br>Das SAPOSalas Team'
                            ],
                            'it' => [
                                'subject' => 'Account Disattivato: ' . $nome_destino,
                                'title' => 'Account Disattivato con Successo',
                                'text' => 'Il tuo account è stato disattivato e le prenotazioni future cancellate.<br><br>Se desideri riattivare il tuo account, contatta il nostro supporto.',
                                'btn' => 'Contattare il Supporto',
                                'footer' => 'Cordiali saluti,<br>Il Team SAPOSalas'
                            ],
                            'zh' => [
                                'subject' => '账户已停用: ' . $nome_destino,
                                'title' => '账户已成功停用',
                                'text' => '您的账户已被停用，未来的预订已被取消。<br><br>如果您希望重新激活您的账户，请联系我们的支持团队。',
                                'btn' => '联系支持',
                                'footer' => '致以最诚挚的问候，<br>SAPOSalas 团队'
                            ],
                            'ru' => [
                                'subject' => 'Аккаунт деактивирован: ' . $nome_destino,
                                'title' => 'Аккаунт успешно деактивирован',
                                'text' => 'Ваш аккаунт был деактивирован, а будущие бронирования отменены.<br><br>Если вы хотите повторно активировать свой аккаунт, пожалуйста, свяжитесь с нашей службой поддержки.',
                                'btn' => 'Связаться с поддержкой',
                                'footer' => 'С наилучшими пожеланиями,<br>Команда SAPOSalas'
                            ]
                        ];

                        if (!array_key_exists($lang_email, $goodbye_texts))
                            $lang_email = 'pt';
                        $gt = $goodbye_texts[$lang_email];

                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $_ENV['SMTP_USER'];
                        $mail->Password = $_ENV['SMTP_PASS'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
                        $mail->CharSet = 'UTF-8';

                        $mail->setFrom($_ENV['SMTP_USER'], 'SAPOSalas');
                        $mail->addAddress($email_destino, $nome_destino);

                        $mail->isHTML(true);
                        $mail->Subject = $gt['subject'];

                        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f3f4f6; padding: 40px 0; }
        .container { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(93, 62, 143, 0.1); }
        .header { background-color: #5d3e8f; height: 100px; text-align: center; position: relative; }
        .logo { color: #ffffff; font-size: 24px; font-weight: 800; padding-top: 35px; letter-spacing: 1px; display: inline-block; }
        .icon-container { width: 80px; height: 80px; background-color: #ffffff; border-radius: 50%; margin: -40px auto 0; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); position: relative; z-index: 10; }
        .content { padding: 30px 40px 50px; text-align: center; }
        .title { color: #1f2937; font-size: 26px; font-weight: 700; margin-bottom: 16px; letter-spacing: -0.5px; }
        .text { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 32px; }
        .btn { display: inline-block; background-color: #5d3e8f; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 50px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 15px rgba(93, 62, 143, 0.3); transition: background-color 0.3s; }
        .footer { background-color: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer-text { color: #9ca3af; font-size: 12px; margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <span class="logo">SAPOSalas</span>
            </div>
            
      

            <div class="content">
                <h1 class="title">{$gt['title']}</h1>
                <p class="text">{$gt['text']}</p>
                
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center">
                            <a href="mailto:admin@saposalas.pt" class="btn">{$gt['btn']}</a>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <p class="footer-text">{$gt['footer']}</p>
                <p class="footer-text" style="margin-top: 10px;">© 2025 SAPOSalas. Todos os direitos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

                        $mail->send();
                    } catch (Exception $e) {

                    }

                    $_SESSION = [];
                    session_unset();
                    session_destroy();

                    $response = [
                        "success" => true,
                        "message" => "Conta desativada com sucesso.",
                        "logout" => true,
                        "redirect" => "../login.php"
                    ];
                } else {
                    $errors[] = "Erro de base de dados: Não foi possível desativar a conta.";
                }
                $stmt->close();
            } else {
                $errors[] = "Utilizador não encontrado.";
            }
        }
    }
} else {
    if (empty($action))
        $errors[] = "Ação inválida.";
}

if (!empty($errors)) {
    $response = ["success" => false, "errors" => $errors];
}

final_output:
ob_end_clean();
echo json_encode($response);
exit;
?>