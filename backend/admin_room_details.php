<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'error' => 'Erro interno.'];

$room_id = (int)($_GET['room_id'] ?? 0);

$t = [];
if (file_exists('../includes/lang.php')) {
    require_once '../includes/lang.php';
}

if (!isset($_SESSION['user_id'])) {
    $response['error'] = $t['access_denied'] ?? 'Acesso negado. Faça login.';
    echo json_encode($response);
    exit;
}

if ($room_id <= 0) {
    $response['error'] = $t['msg_invalid_id'] ?? 'ID de sala inválido.';
    echo json_encode($response);
    exit;
}

function getApiRoomImage($rawPath) {
    $DEFAULT_PLACEHOLDER = 'https://via.placeholder.com/600x400?text=Sem+Imagem';
    
    if (!empty($rawPath) && filter_var($rawPath, FILTER_VALIDATE_URL)) {
        return $rawPath;
    }
    return $DEFAULT_PLACEHOLDER;
}

try {
    $sql_room = "SELECT nome, local, descricao, equipamentos, fotos, imagem FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql_room);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($room_data) {
        $galeria = [];
        $galeria_db = json_decode($room_data['fotos'], true);
        
        if (is_array($galeria_db)) {
            foreach ($galeria_db as $foto) {
                $galeria[] = getApiRoomImage($foto);
            }
        }
        
        $imagem_capa = getApiRoomImage($room_data['imagem']);

        $response = [
            'success' => true,
            'nome' => htmlspecialchars($room_data['nome']),
            'local' => htmlspecialchars($room_data['local']),
            'descricao' => htmlspecialchars($room_data['descricao']),
            'equipamentos' => htmlspecialchars($room_data['equipamentos']),
            'galeria' => $galeria,
            'capa' => $imagem_capa
        ];
    } else {
        $response['error'] = $t['msg_room_not_found'] ?? 'Sala não encontrada.';
    }

} catch (Exception $e) {
    $response['error'] = $t['error_bd'] ?? 'Erro de base de dados.';
}

echo json_encode($response);
exit;
?>