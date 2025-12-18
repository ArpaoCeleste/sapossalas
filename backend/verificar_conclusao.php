<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';


date_default_timezone_set('Europe/Lisbon');


$agora = date('Y-m-d H:i:s');


$sql_update = "UPDATE reservas 
               SET status_reserva = 'concluida' 
               WHERE status_reserva = 'ativa' 
               AND CONCAT(data, ' ', hora_fim) < ?";

$stmt = $conn->prepare($sql_update);
$stmt->bind_param("s", $agora);

if ($stmt->execute()) {

} else {
    error_log("Erro ao atualizar estados: " . $conn->error);
}

$stmt->close();
?>