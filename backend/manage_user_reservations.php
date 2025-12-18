<?php
session_start();
require_once 'config.php';


$lang_code = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'pt';
$lang_file = "../includes/lang_{$lang_code}.php";
if (file_exists($lang_file)) {
    require_once $lang_file;
} else {
    require_once '../includes/lang.php'; 
}

ob_clean(); 
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception($t['res_err_session'] ?? 'Sessão expirada.');
    }

   
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    throw new Exception('Token de segurança inválido.');
}

    $user_id = $_SESSION['user_id'];
    $user_email = $_SESSION['user_email'] ?? 'unknown@email.com'; 
    $action = $_POST['action'] ?? '';
    $reserva_id = (int)($_POST['id'] ?? 0);

    if ($reserva_id <= 0) {
        throw new Exception($t['res_err_id'] ?? 'ID inválido.');
    }


    $stmt = $conn->prepare("SELECT r.*, rm.nome as sala_nome FROM reservas r JOIN rooms rm ON r.room_id = rm.id WHERE r.id = ? AND r.user_id = ?");
    $stmt->bind_param("ii", $reserva_id, $user_id);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reserva) {
        throw new Exception($t['res_err_not_found'] ?? 'Reserva não encontrada ou sem permissão.');
    }

    date_default_timezone_set('Europe/Lisbon');
    $data_hora_reserva_inicio = strtotime($reserva['data'] . ' ' . $reserva['hora_inicio']);
    $antecedencia_minima = 43200; 

    
    if ($action === 'cancel') {
        
    
        if ($data_hora_reserva_inicio > time() && ($data_hora_reserva_inicio - time()) < $antecedencia_minima) {
             throw new Exception($t['res_err_12h_cancel'] ?? 'Só é permitido cancelar reservas com pelo menos 12 horas de antecedência.');
        }

      
        $stmt = $conn->prepare("UPDATE reservas SET status_reserva = 'cancelada' WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        
        if ($stmt->execute()) {
            
           
            log_activity(sprintf(
                "[CANCELAR] ID_Reserva: %d | User: %s (ID:%d) | Sala: %s | Data Original: %s %s",
                $reserva_id, 
                $user_email, 
                $user_id,
                $reserva['sala_nome'],
                $reserva['data'], 
                $reserva['hora_inicio']
            ));

            echo json_encode(['success' => true, 'message' => $t['res_success_cancel'] ?? 'Reserva cancelada com sucesso.']);
        } else {
            throw new Exception($t['res_err_cancel'] ?? 'Erro ao cancelar reserva.');
        }
        $stmt->close();
    } 

    elseif ($action === 'edit') {
        
        $tempo_atual = new DateTime('now');
        $reserva_inicio_obj = DateTime::createFromFormat('Y-m-d H:i:s', $reserva['data'] . ' ' . $reserva['hora_inicio']);
        
   
        if ($reserva_inicio_obj < $tempo_atual) {
             throw new Exception($t['res_err_past_edit'] ?? 'Não é possível editar reservas que já iniciaram ou passaram.');
        }
        
      
        $diff_segundos = $reserva_inicio_obj->getTimestamp() - $tempo_atual->getTimestamp();
        
        if ($diff_segundos < $antecedencia_minima) {
             throw new Exception($t['res_err_12h_edit'] ?? 'Só é permitido editar reservas com pelo menos 12 horas de antecedência.');
        }
        
        $nova_data = $_POST['data'] ?? '';
        $novo_inicio = $_POST['hora_inicio'] ?? '';
        $novo_fim = $_POST['hora_fim'] ?? '';
        $nova_desc = trim($_POST['descricao'] ?? '');

        if (empty($nova_data) || empty($novo_inicio) || empty($novo_fim)) {
            throw new Exception($t['res_err_fields'] ?? 'Preencha todos os campos obrigatórios.');
        }

        if (!strtotime($nova_data) || !strtotime($novo_inicio) || !strtotime($novo_fim)) {
            throw new Exception($t['res_err_format'] ?? 'Formato de data inválido.');
        }
    
        if (strtotime($novo_fim) <= strtotime($novo_inicio)) {
            throw new Exception($t['res_err_time_logic'] ?? 'A hora de fim deve ser superior à de início.');
        }

        $nova_data_hora = strtotime($nova_data . ' ' . $novo_inicio);
        if ($nova_data_hora < time()) {
            throw new Exception($t['res_err_past_date'] ?? 'Não pode mover a reserva para uma data/hora no passado.');
        }

 
        if (
            $nova_data === $reserva['data'] &&
            strtotime($novo_inicio) == strtotime($reserva['hora_inicio']) &&
            strtotime($novo_fim) == strtotime($reserva['hora_fim']) &&
            $nova_desc === $reserva['descricao']
        ) {
            throw new Exception($t['res_err_no_change'] ?? 'Nenhuma alteração detetada.');
        }
    
        $sala_id = $reserva['room_id'];
        
        
        $sql_check = "SELECT id FROM reservas 
                      WHERE room_id = ? 
                      AND data = ? 
                      AND id != ? 
                      AND status_reserva = 'ativa'
                      AND (
                          (hora_inicio < ? AND hora_fim > ?) 
                      )";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("isiss", $sala_id, $nova_data, $reserva_id, $novo_fim, $novo_inicio);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $stmt_check->close();
            throw new Exception($t['res_err_conflict'] ?? 'Este horário já está ocupado por outra reserva.');
        }
        $stmt_check->close();

        $stmt_update = $conn->prepare("UPDATE reservas SET data = ?, hora_inicio = ?, hora_fim = ?, descricao = ? WHERE id = ?");
        $stmt_update->bind_param("ssssi", $nova_data, $novo_inicio, $novo_fim, $nova_desc, $reserva_id);
        
        if ($stmt_update->execute()) {
            
          
            log_activity(sprintf(
                "[EDITAR] ID_Reserva: %d | User: %s (ID:%d) | Sala: %s | Mudou de [%s %s-%s] para [%s %s-%s]",
                $reserva_id, 
                $user_email, 
                $user_id,
                $reserva['sala_nome'],
                $reserva['data'], $reserva['hora_inicio'], $reserva['hora_fim'],
                $nova_data, $novo_inicio, $novo_fim
            ));

            echo json_encode(['success' => true, 'message' => $t['res_success_update'] ?? 'Reserva atualizada com sucesso.']);
        } else {
            throw new Exception($t['res_err_update'] ?? 'Erro ao atualizar reserva.');
        }
        $stmt_update->close();

    } else {
        throw new Exception($t['res_err_action'] ?? 'Ação inválida.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function log_activity($msg) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/reservas_' . date('Y-m') . '.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>