<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'backend/config.php';

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function writeLog($email, $ip, $status) {
    date_default_timezone_set('Europe/Lisbon');
    $logDir = 'logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . 'login_history.log'; 
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] IP: $ip | Email: $email | Status: $status" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$email = $_SESSION['user_email'] ?? 'Desconhecido';
$ip = getUserIP();

if ($email !== 'Desconhecido') {
    writeLog($email, $ip, "LOGOUT - Sessão terminada");
}


$_SESSION = array();


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}


session_destroy();


if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 42000, '/');
    unset($_COOKIE['user_email']);
}

header('Location: index.php');
exit;
?>