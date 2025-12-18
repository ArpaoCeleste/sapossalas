<?php
ob_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

try {

    $config_path = 'config.php';
    if (!file_exists($config_path)) {
        $config_path = '../backend/config.php';
    }

    if (!file_exists($config_path)) {
        throw new Exception("Ficheiro de configuração em falta. Verifique o caminho em api_occupancy.php.");
    }

    require_once $config_path;

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão à base de dados.");
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Não autenticado.");
    }

    $date = $_GET['data'] ?? date('Y-m-d');


    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        throw new Exception("Formato de data inválido.");
    }


    $max_date = (new DateTime('today'))->modify('+1 year');
    if ($date_obj > $max_date) {
        throw new Exception("Data muito no futuro (máximo 1 ano).");
    }


    $min_date = (new DateTime('today'))->modify('-1 year');
    if ($date_obj < $min_date) {
        throw new Exception("Data muito no passado (máximo 1 ano).");
    }

    $rooms = [];
    $conn->set_charset("utf8mb4");

    $sql_rooms = "SELECT id, nome FROM rooms WHERE status = 'disponivel' ORDER BY nome";
    $res_rooms = $conn->query($sql_rooms);

    if ($res_rooms) {
        while ($row = $res_rooms->fetch_assoc()) {
            $rooms[] = $row;
        }
    }

    $reservations = [];
    $sql_res = "SELECT r.room_id, r.hora_inicio, r.hora_fim, r.descricao, u.nome as professor_nome, u.email as professor_email FROM reservas r JOIN utilizadores u ON r.user_id = u.id WHERE r.data = ?  ";

    $stmt = $conn->prepare($sql_res);
    if ($stmt) {
        $stmt->bind_param("s", $date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
        }
        $stmt->close();
    }

    ob_clean();

    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'reservations' => $reservations,
        'updated_at' => date('H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>