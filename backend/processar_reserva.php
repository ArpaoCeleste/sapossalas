<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../reservar.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Erro de segurança: Token inválido.");
}


$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$data = isset($_POST['data']) ? trim($_POST['data']) : '';
$hora_inicio_raw = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
$hora_fim_raw = isset($_POST['hora_fim']) ? trim($_POST['hora_fim']) : '';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';

$user_email = $_SESSION['user_email'];

if (empty($room_id) || $room_id <= 0) {
    header("Location: ../detalhes.php?id=$room_id&error=room_invalida");
    exit;
}

if (empty($data) || empty($hora_inicio_raw) || empty($hora_fim_raw)) {
    header("Location: ../detalhes.php?id=$room_id&error=campos_obrigatorios");
    exit;
}


if (strlen($descricao) > 500) {
    header("Location: ../detalhes.php?id=$room_id&error=descricao_muito_longa");
    exit;
}

date_default_timezone_set('Europe/Lisbon');


$data_obj = DateTime::createFromFormat('Y-m-d', $data);
$data_hoje = new DateTime('today');
$data_maxima = (clone $data_hoje)->modify('+1 year');

if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
    header("Location: ../detalhes.php?id=$room_id&error=data_invalida");
    exit;
}

if ($data_obj < $data_hoje) {
    header("Location: ../detalhes.php?id=$room_id&error=data_passada");
    exit;
}

if ($data_obj > $data_maxima) {
    header("Location: ../detalhes.php?id=$room_id&error=data_muito_futura");
    exit;
}


$hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio_raw);
$hora_fim_obj = DateTime::createFromFormat('H:i', $hora_fim_raw);

if (!$hora_inicio_obj || $hora_inicio_obj->format('H:i') !== $hora_inicio_raw) {
    header("Location: ../detalhes.php?id=$room_id&error=hora_inicio_invalida");
    exit;
}

if (!$hora_fim_obj || $hora_fim_obj->format('H:i') !== $hora_fim_raw) {
    header("Location: ../detalhes.php?id=$room_id&error=hora_fim_invalida");
    exit;
}


$hora_min = DateTime::createFromFormat('H:i', '08:00');
$hora_max = DateTime::createFromFormat('H:i', '20:00');

if ($hora_inicio_obj < $hora_min || $hora_inicio_obj >= $hora_max) {
    header("Location: ../detalhes.php?id=$room_id&error=hora_fora_intervalo");
    exit;
}

if ($hora_fim_obj <= $hora_min || $hora_fim_obj > $hora_max) {
    header("Location: ../detalhes.php?id=$room_id&error=hora_fim_fora_intervalo");
    exit;
}

$hora_inicio = $hora_inicio_obj->format('H:i:s');
$hora_fim = $hora_fim_obj->format('H:i:s');

if ($hora_fim <= $hora_inicio) {
    header("Location: ../detalhes.php?id=$room_id&error=hora_invalida");
    exit;
}


$diff = $hora_fim_obj->diff($hora_inicio_obj);
$duracao_minutos = ($diff->h * 60) + $diff->i;

if ($duracao_minutos < 30) {
    header("Location: ../detalhes.php?id=$room_id&error=duracao_minima");
    exit;
}

if ($duracao_minutos > 720) { 
    header("Location: ../detalhes.php?id=$room_id&error=duracao_maxima");
    exit;
}


$start_datetime_str = "{$data} {$hora_inicio}";
$required_time = new DateTime('+12 hours');
$reservation_start = DateTime::createFromFormat('Y-m-d H:i:s', $start_datetime_str);

if ($reservation_start === false) {
    header("Location: ../detalhes.php?id=$room_id&error=erro_bd");
    exit;
}


if ($reservation_start < $required_time) {
    header("Location: ../detalhes.php?id=$room_id&error=antecedencia_12h");
    exit;
}

$conn->query("LOCK TABLES reservas WRITE, rooms READ, utilizadores READ");

$sql_user = "SELECT id, nome FROM utilizadores WHERE email = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("s", $user_email);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows === 0) {
    $conn->query("UNLOCK TABLES");
    header("Location: ../detalhes.php?id=$room_id&error=erro_bd");
    exit;
}

$user = $result_user->fetch_assoc();
$user_id = $user['id'];
$user_nome_real = $user['nome'] ?? $user_email;
$stmt_user->close();

$sql_user_conflict = "SELECT id FROM reservas
                      WHERE user_id = ?
                      AND data = ?
                      AND status_reserva = 'ativa' 
                      AND (
                          (hora_inicio < ? AND hora_fim > ?)
                      )";

$stmt_user_conflict = $conn->prepare($sql_user_conflict);
$stmt_user_conflict->bind_param("isss", $user_id, $data, $hora_fim, $hora_inicio);
$stmt_user_conflict->execute();
$result_user_conflict = $stmt_user_conflict->get_result();

if ($result_user_conflict->num_rows > 0) {
    $conn->query("UNLOCK TABLES");
    header("Location: ../detalhes.php?id=$room_id&error=user_ocupado");
    exit;
}
$stmt_user_conflict->close();

$sql_room = "SELECT nome, local, imagem FROM rooms WHERE id = ?";
$stmt_room = $conn->prepare($sql_room);
$stmt_room->bind_param("i", $room_id);
$stmt_room->execute();
$result_room = $stmt_room->get_result();

if ($result_room->num_rows === 0) {
    $conn->query("UNLOCK TABLES");
    header("Location: ../reservar.php?error=sala_nao_existe");
    exit;
}

$room = $result_room->fetch_assoc();
$room_nome = $room['nome'];
$room_local = $room['local'];
$room_img = !empty($room['imagem']) ? $room['imagem'] : 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=1000';
$stmt_room->close();

$sql_room_conflict = "SELECT id FROM reservas
                      WHERE room_id = ?
                      AND data = ?
                      AND status_reserva = 'ativa' 
                      AND (
                          (hora_inicio < ? AND hora_fim > ?)
                      )";

$stmt_room_conflict = $conn->prepare($sql_room_conflict);
$stmt_room_conflict->bind_param("isss", $room_id, $data, $hora_fim, $hora_inicio);
$stmt_room_conflict->execute();
$result_room_conflict = $stmt_room_conflict->get_result();

if ($result_room_conflict->num_rows > 0) {
    
    $sql_all = "SELECT hora_inicio, hora_fim FROM reservas WHERE room_id = ? AND data = ? AND status_reserva = 'ativa' ORDER BY hora_inicio ASC";
    $stmt_all = $conn->prepare($sql_all);
    $stmt_all->bind_param("is", $room_id, $data);
    $stmt_all->execute();
    $result_all = $stmt_all->get_result();
    
    $bookings = [];
    while ($row = $result_all->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    $req_start = new DateTime($hora_inicio);
    $req_end = new DateTime($hora_fim);
    $diff = $req_end->diff($req_start);
    $duration_hours = $diff->h + ($diff->i / 60);

    $suggestions = [];

    for ($h = 8; $h <= (20 - $duration_hours); $h += 0.5) {
        $hours = floor($h);
        $minutes = ($h - $hours) * 60;
        
        $slot_start_ts = mktime($hours, $minutes, 0, date('m', strtotime($data)), date('d', strtotime($data)), date('Y', strtotime($data)));
        $slot_end_ts = $slot_start_ts + ($duration_hours * 3600);
        
        $slot_start = date("H:i:s", $slot_start_ts);
        $slot_end = date("H:i:s", $slot_end_ts);
        
        $is_free = true;
        foreach ($bookings as $b) {
            if ($b['hora_inicio'] < $slot_end && $b['hora_fim'] > $slot_start) {
                $is_free = false;
                break;
            }
        }
        
        if ($is_free) {
            $nice_start = date("H:i", $slot_start_ts);
            $nice_end = date("H:i", $slot_end_ts);
            $suggestions[] = "{$nice_start} às {$nice_end}";
            if (count($suggestions) >= 3) break;
        }
    }
    
    $sug_param = !empty($suggestions) ? implode(', ', $suggestions) : '';
    $conn->query("UNLOCK TABLES");
    header("Location: ../detalhes.php?id=$room_id&error=sala_indisponivel&suggestions=" . urlencode($sug_param));
    exit;
}
$stmt_room_conflict->close();

$status_reserva_valor = 'ativa'; 
$sql_insert = "INSERT INTO reservas (room_id, user_id, data, hora_inicio, hora_fim, descricao, email_responsavel, status_reserva)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    $conn->query("UNLOCK TABLES");
    header("Location: ../detalhes.php?id=$room_id&error=erro_bd");
    exit;
}

$stmt_insert->bind_param("iissssss", $room_id, $user_id, $data, $hora_inicio, $hora_fim, $descricao, $user_email, $status_reserva_valor);

if (!$stmt_insert->execute()) {
    $conn->query("UNLOCK TABLES");
    header("Location: ../detalhes.php?id=$room_id&error=erro_bd");
    exit;
}

$reservation_id = $stmt_insert->insert_id; 

$conn->query("UNLOCK TABLES");
$stmt_insert->close();

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/reservas_' . date('Y-m') . '.log';
$logEntry = sprintf(
    "[%s] [CRIAR] ID_Reserva: %d | User: %s (ID:%d) | Sala: %s (ID:%d) | Data: %s | Hora: %s-%s | Desc: %s\n",
    date('Y-m-d H:i:s'),
    $reservation_id,
    $user_email,
    $user_id,
    $room_nome,
    $room_id,
    $data,
    $hora_inicio,
    $hora_fim,
    $descricao
);
file_put_contents($logFile, $logEntry, FILE_APPEND);

$conn->close();

$lang_email = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'pt';

$email_texts = [
    'pt' => [
        'subject' => 'Reserva Confirmada',
        'title' => 'Reserva Confirmada!',
        'subtitle' => 'O espaço já está guardado para si.',
        'greeting' => 'Olá',
        'intro' => 'A sua reserva foi efetuada com sucesso. Abaixo encontra todos os detalhes do agendamento.',
        'label_room' => 'Sala',
        'label_location' => 'Local',
        'label_date' => 'Data',
        'label_time' => 'Horário',
        'label_reason' => 'Motivo',
        'btn_text' => 'Ver Minhas Reservas',
        'footer_rights' => 'Todos os direitos reservados.',
        'footer_auto' => 'Este é um email automático, por favor não responda.'
    ],
    'en' => [
        'subject' => 'Reservation Confirmed',
        'title' => 'Reservation Confirmed!',
        'subtitle' => 'The space has been saved for you.',
        'greeting' => 'Hello',
        'intro' => 'Your reservation was successful. Below are all the scheduling details.',
        'label_room' => 'Room',
        'label_location' => 'Location',
        'label_date' => 'Date',
        'label_time' => 'Time',
        'label_reason' => 'Reason',
        'btn_text' => 'View My Reservations',
        'footer_rights' => 'All rights reserved.',
        'footer_auto' => 'This is an automated email, please do not reply.'
    ],
    'es' => [
        'subject' => 'Reserva Confirmada',
        'title' => '¡Reserva Confirmada!',
        'subtitle' => 'El espacio ya está reservado para usted.',
        'greeting' => 'Hola',
        'intro' => 'Su reserva se ha realizado con éxito. A continuación encontrará todos los detalles.',
        'label_room' => 'Sala',
        'label_location' => 'Ubicación',
        'label_date' => 'Fecha',
        'label_time' => 'Hora',
        'label_reason' => 'Motivo',
        'btn_text' => 'Ver Mis Reservas',
        'footer_rights' => 'Todos los derechos reservados.',
        'footer_auto' => 'Este es un correo automático, por favor no responda.'
    ],
    'fr' => [
        'subject' => 'Réservation Confirmée',
        'title' => 'Réservation Confirmée !',
        'subtitle' => 'L\'espace est réservé pour vous.',
        'greeting' => 'Bonjour',
        'intro' => 'Votre réservation a été effectuée avec succès. Vous trouverez ci-dessous tous les détails.',
        'label_room' => 'Salle',
        'label_location' => 'Lieu',
        'label_date' => 'Date',
        'label_time' => 'Heure',
        'label_reason' => 'Motif',
        'btn_text' => 'Voir Mes Réservations',
        'footer_rights' => 'Tous droits réservés.',
        'footer_auto' => 'Ceci est un email automatique, merci de ne pas répondre.'
    ],
    'de' => [
        'subject' => 'Buchung Bestätigt',
        'title' => 'Buchung Bestätigt!',
        'subtitle' => 'Der Raum ist für Sie reserviert.',
        'greeting' => 'Hallo',
        'intro' => 'Ihre Reservierung war erfolgreich. Unten finden Sie alle Details.',
        'label_room' => 'Raum',
        'label_location' => 'Ort',
        'label_date' => 'Datum',
        'label_time' => 'Zeit',
        'label_reason' => 'Grund',
        'btn_text' => 'Meine Buchungen ansehen',
        'footer_rights' => 'Alle Rechte vorbehalten.',
        'footer_auto' => 'Dies ist eine automatische E-Mail, bitte nicht antworten.'
    ],
    'it' => [
        'subject' => 'Prenotazione Confermata',
        'title' => 'Prenotazione Confermata!',
        'subtitle' => 'Lo spazio è stato riservato per te.',
        'greeting' => 'Ciao',
        'intro' => 'La tua prenotazione è stata effettuata con successo. Di seguito trovi tutti i dettagli.',
        'label_room' => 'Sala',
        'label_location' => 'Luogo',
        'label_date' => 'Data',
        'label_time' => 'Orario',
        'label_reason' => 'Motivo',
        'btn_text' => 'Vedi Le Mie Prenotazioni',
        'footer_rights' => 'Tutti i diritti riservati.',
        'footer_auto' => 'Questa è un\'email automatica, per favore non rispondere.'
    ],
    'rus' => [
        'subject' => 'Бронирование подтверждено',
        'title' => 'Бронирование подтверждено!',
        'subtitle' => 'Место зарезервировано для вас.',
        'greeting' => 'Здравствуйте',
        'intro' => 'Ваше бронирование прошло успешно. Ниже приведены все детали.',
        'label_room' => 'Зал',
        'label_location' => 'Место',
        'label_date' => 'Дата',
        'label_time' => 'Время',
        'label_reason' => 'Причина',
        'btn_text' => 'Посмотреть мои бронирования',
        'footer_rights' => 'Все права защищены.',
        'footer_auto' => 'Это автоматическое письмо, пожалуйста, не отвечайте.'
    ],
    'zi' => [
        'subject' => '预订已确认',
        'title' => '预订已确认！',
        'subtitle' => '该空间已为您预留。',
        'greeting' => '您好',
        'intro' => '您的预订已成功。以下是详细信息。',
        'label_room' => '房间',
        'label_location' => '地点',
        'label_date' => '日期',
        'label_time' => '时间',
        'label_reason' => '原因',
        'btn_text' => '查看我的预订',
        'footer_rights' => '版权所有。',
        'footer_auto' => '这是一封自动邮件，请勿回复。'
    ]
];

if (!array_key_exists($lang_email, $email_texts)) {
    $lang_email = 'pt';
}

if (!isset($email_texts[$lang_email])) {
    $email_texts[$lang_email] = $email_texts['pt'];
}

$et = $email_texts[$lang_email];

$assunto = "{$et['subject']}: {$room_nome} ✅";
$data_formatada = date('d/m/Y', strtotime($data));
$ano_atual = date('Y');


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

$base_url = get_credencial('BASE_URL');
if (empty($base_url)) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = str_replace('/backend/processar_reserva.php', '', $_SERVER['REQUEST_URI']);
    $base_url = $protocol . "://" . $host . $path;
}
$link_reservas = rtrim($base_url, '/') . "/reservar.php";

$corpo = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$et['title']}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0; text-align: center;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                    
                    <div style="background-color: #5d3e8f; padding: 40px 20px; text-align: center;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;">{$et['title']}</h1>
                        <p style="color: #e0e7ff; margin-top: 10px; font-size: 16px;">{$et['subtitle']}</p>
                    </div>

                    <div style="padding: 40px 30px;">
                        <p style="color: #374151; font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
                            {$et['greeting']} <strong>{$user_nome_real}</strong>,<br>
                            {$et['intro']}
                        </p>

                        <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
                            <div style="padding: 0;">
                                <img src="{$room_img}" alt="Sala" style="width: 100%; height: 150px; object-fit: cover;">
                            </div>
                            <div style="padding: 20px;">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding-bottom: 12px; border-bottom: 1px solid #f3f4f6;">
                                            <span style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{$et['label_room']}</span><br>
                                            <span style="color: #111827; font-size: 16px; font-weight: 600;">{$room_nome}</span>
                                        </td>
                                        <td style="padding-bottom: 12px; border-bottom: 1px solid #f3f4f6; text-align: center;">
                                            <span style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{$et['label_location']}</span><br>
                                            <span style="color: #111827; font-size: 16px; font-weight: 600;">{$room_local}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                            <span style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{$et['label_date']}</span><br>
                                            <span style="color: #111827; font-size: 16px; font-weight: 600;">{$data_formatada}</span>
                                        </td>
                                        <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; text-align: center;">
                                            <span style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{$et['label_time']}</span><br>
                                            <span style="color: #5d3e8f; font-size: 16px; font-weight: 700;">{$hora_inicio} - {$hora_fim}</span>
                                        </td>
                                    </tr>
EOT;

if (!empty($descricao)) {
    $corpo .= <<<EOT
                                    <tr>
                                        <td colspan="2" style="padding-top: 12px;">
                                            <span style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{$et['label_reason']}</span><br>
                                            <span style="color: #374151; font-size: 15px;">{$descricao}</span>
                                        </td>
                                    </tr>
EOT;
}

$corpo .= <<<EOT
                                </table>
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <a href="{$link_reservas}" style="display: inline-block; background-color: #5d3e8f; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px -1px rgba(93, 62, 143, 0.3);">
                                {$et['btn_text']}
                            </a>
                        </div>
                    </div>

                    <div style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                        <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                            © {$ano_atual} SAPOSalas. {$et['footer_rights']}<br>
                            {$et['footer_auto']}
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
EOT;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {


    $mail->isSMTP();
    $mail->Host       = get_credencial('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = get_credencial('SMTP_USER');
    $mail->Password   = get_credencial('SMTP_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = get_credencial('SMTP_PORT', '587');
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(get_credencial('SMTP_USER'), 'Segurança SAPOSalas');
    $mail->addAddress($user_email);

    $mail->isHTML(true);
    $mail->Subject = $assunto;
    $mail->Body    = $corpo;
    $mail->AltBody = "{$et['subject']}: {$room_nome} | {$et['label_date']}: {$data_formatada} | {$et['label_time']}: {$hora_inicio} - {$hora_fim}";

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->send();

} catch (Exception $e) {
    error_log('Erro ao enviar email: ' . $mail->ErrorInfo);
}

header("Location: ../detalhes.php?id=$room_id&success=reserva_criada&date=$data&start=$hora_inicio&end=$hora_fim");
exit;
?>