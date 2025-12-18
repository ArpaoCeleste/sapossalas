<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$t = [];
if (file_exists('../includes/lang.php')) {
    require_once '../includes/lang.php';
}

$status_type = 'error';
$status_message = $t['js_error'] ?? 'Ocorreu um erro desconhecido.';
$messages = [];
$DEFAULT_IMAGE = 'https://ralfvanveen.com/wp-content/uploads/2021/06/Placeholder-_-Glossary.svg';


$CLOUD_NAME = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? '';
$API_KEY = $_ENV['CLOUDINARY_API_KEY'] ?? '';
$API_SECRET = $_ENV['CLOUDINARY_API_SECRET'] ?? '';

if (empty($CLOUD_NAME) || empty($API_KEY) || empty($API_SECRET)) {
    $messages[] = ['type' => 'error', 'text' => $t['cloudinary_error_config'] ?? "Erro: Credenciais do Cloudinary em falta."];
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    send_response('error', 'Token inválido.', '../admin.php?section=salas');
}


if ($conn->connect_error) {
    $status_message = $t['error_db_connection'] ?? 'Erro de ligação à base de dados.';
    send_response($status_type, $status_message, '../admin.php?section=salas');
}

if (!isset($_SESSION['user_email']) || $_SESSION['role'] !== 'admin') {
    send_response($status_type, $t['access_denied'] ?? 'Acesso negado. Apenas administradores.', '../index.php');
}

if (!isset($_POST['action'])) {
    send_response($status_type, $t['invalid_action'] ?? 'Ação inválida.', '../admin.php?section=salas');
}

function send_response($type, $message, $location)
{
    global $messages;
    $message_data = json_encode(['type' => $type, 'text' => $message, 'warnings' => $messages]);
    header("Location: " . $location . "&swal_msg=" . base64_encode($message_data));
    exit;
}

function handle_photo_upload($files, $t)
{
    global $messages, $CLOUD_NAME, $API_KEY, $API_SECRET;
    $final_urls = [];

    if (empty($CLOUD_NAME) || empty($API_KEY) || empty($API_SECRET)) {
        return [];
    }

    $upload_url = "https://api.cloudinary.com/v1_1/{$CLOUD_NAME}/image/upload";
    $uploaded_files = isset($files['galeria']) ? $files['galeria'] : [];

    if (isset($uploaded_files['name'])) {
        $file_count = count($uploaded_files['name']);

        for ($i = 0; $i < min($file_count, 4); $i++) {
            $file_tmp = $uploaded_files['tmp_name'][$i];
            $file_error = $uploaded_files['error'][$i];
            $file_type = $uploaded_files['type'][$i];

            if ($file_error === UPLOAD_ERR_OK && strpos($file_type, 'image/') === 0 && is_uploaded_file($file_tmp)) {

                try {
                    $unique_id = uniqid();
                    $file_name_with_path = 'sala/' . "{$unique_id}-sala-img{$i}";

                    $post_fields = [
                        'file' => new CURLFile($file_tmp, $file_type, $uploaded_files['name'][$i]),
                        'api_key' => $API_KEY,
                        'timestamp' => time(),
                        'public_id' => $file_name_with_path,
                        'folder' => 'salas',
                    ];

                    $sorted_params = $post_fields;
                    unset($sorted_params['file']);
                    unset($sorted_params['api_key']);
                    ksort($sorted_params);

                    $signature_string = '';
                    foreach ($sorted_params as $key => $value) {
                        $signature_string .= "{$key}={$value}&";
                    }
                    $signature_string = rtrim($signature_string, '&');
                    $post_fields['signature'] = sha1($signature_string . $API_SECRET);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $upload_url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $upload_result = json_decode($response, true);

                    if ($http_code === 200 && isset($upload_result['secure_url'])) {
                        $final_urls[] = $upload_result['secure_url'];
                    } else {
                        $error_details = isset($upload_result['error']['message']) ? $upload_result['error']['message'] : 'Erro desconhecido.';
                        $messages[] = ['type' => 'error', 'text' => $t['salas_msg_upload_error'] ?? "Erro no upload Cloudinary (Slot " . ($i + 1) . "): " . $error_details];
                    }

                } catch (Exception $e) {
                    $messages[] = ['type' => 'error', 'text' => $t['salas_msg_upload_error'] ?? "Erro interno no upload: " . $e->getMessage()];
                }
            } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                $messages[] = ['type' => 'error', 'text' => $t['salas_msg_upload_error'] ?? "Erro no upload de uma imagem (Código: " . $file_error . ")."];
            }
        }
    }

    return $final_urls;
}


if ($_POST['action'] === 'adicionar' || $_POST['action'] === 'editar') {

    $nome = trim($_POST['nome']);
    $capacidade = (int) $_POST['capacidade'];
    $local = trim($_POST['local']);
    $descricao = trim($_POST['descricao']);
    $equipamentos = trim($_POST['equipamentos']);
    $status = trim($_POST['status'] ?? 'disponivel');
    $id = (isset($_POST['id'])) ? (int) $_POST['id'] : 0;

    if (empty($nome) || $capacidade <= 0 || empty($local)) {
        send_response('error', $t['salas_msg_fill_fields'] ?? 'Preencha todos os campos obrigatórios.', '../admin.php?section=salas');
    }


    $sql_check_name = "SELECT id FROM rooms WHERE nome = ?";
    if ($_POST['action'] === 'editar') {
        $sql_check_name .= " AND id != ?";
    }

    $stmt_check = $conn->prepare($sql_check_name);
    if ($_POST['action'] === 'editar') {
        $stmt_check->bind_param("si", $nome, $id);
    } else {
        $stmt_check->bind_param("s", $nome);
    }

    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        send_response('error', $t['salas_msg_duplicate_name'] ?? 'Já existe uma sala com este nome.', '../admin.php?section=salas');
    }
    $stmt_check->close();



    if ($_POST['action'] === 'editar') {
   
        if ($status !== 'disponivel') {

          
            $sql_active_reservations = "SELECT COUNT(*) AS total FROM reservas 
                                        WHERE room_id = ? 
                                        AND data >= CURDATE() 
                                        AND status_reserva = 'ativa'";

            $stmt_res_check = $conn->prepare($sql_active_reservations);
            $stmt_res_check->bind_param("i", $id);
            $stmt_res_check->execute();
            $result_res_check = $stmt_res_check->get_result();
            $active_reservations_count = $result_res_check->fetch_assoc()['total'];
            $stmt_res_check->close();

            if ($active_reservations_count > 0) {
                $error_msg = ($t['salas_msg_status_blocked'] ?? 'Não é possível definir o estado para "%s" pois existem %d reservas ativas para esta sala.');
                $status_display = ($status === 'indisponivel' ? ($t['status_indisponivel'] ?? 'Indisponível') : ($t['status_brevemente'] ?? 'Brevemente'));
                $message = sprintf($error_msg, $status_display, $active_reservations_count);

                send_response('error', $message, '../admin.php?section=salas&editar=' . $id);
            }
        }

    }


    $newly_uploaded_urls = handle_photo_upload($_FILES, $t);
    $final_photos = [];

    if ($_POST['action'] === 'editar') {

        $existing_photos_from_post = isset($_POST['existing_photos']) ? array_filter($_POST['existing_photos'], function ($url) use ($DEFAULT_IMAGE) {
            return $url !== $DEFAULT_IMAGE;
        }) : [];

        $temp_photos = $existing_photos_from_post;

        foreach ($newly_uploaded_urls as $new_url) {
            $temp_photos[] = $new_url;
        }

        $final_photos = array_values(array_slice($temp_photos, 0, 4));

        if (count($temp_photos) > 4 && count($newly_uploaded_urls) > 0) {
            $messages[] = ['type' => 'warning', 'text' => $t['salas_msg_limit_exceeded'] ?? 'O limite de fotos (4) foi atingido. As mais antigas ou extras foram ignoradas.'];
        }

    } else {
        $final_photos = $newly_uploaded_urls;
    }

    $fotos_json = json_encode($final_photos);
    $imagem_capa = !empty($final_photos) ? $final_photos[0] : $DEFAULT_IMAGE;


    if ($_POST['action'] === 'adicionar') {
        $stmt = $conn->prepare("INSERT INTO rooms (nome, capacidade, local, descricao, equipamentos, imagem, fotos, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissssss", $nome, $capacidade, $local, $descricao, $equipamentos, $imagem_capa, $fotos_json, $status);
        $success_msg = $t['salas_msg_add_success'] ?? 'Sala adicionada com sucesso!';
        $error_msg = $t['salas_msg_add_error'] ?? 'Erro ao adicionar sala.';
    } else {
       
        $stmt = $conn->prepare("UPDATE rooms SET nome = ?, capacidade = ?, local = ?, descricao = ?, equipamentos = ?, imagem = ?, fotos = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sissssssi", $nome, $capacidade, $local, $descricao, $equipamentos, $imagem_capa, $fotos_json, $status, $id);
        $success_msg = $t['salas_msg_edit_success'] ?? 'Sala atualizada com sucesso!';
        $error_msg = $t['salas_msg_edit_error'] ?? 'Erro ao atualizar sala.';
    }

    if ($stmt->execute()) {
        send_response('success', $success_msg, '../admin.php?section=salas');
    } else {
        $messages[] = ['type' => 'error', 'text' => $conn->error];
        send_response('error', $error_msg, '../admin.php?section=salas');
    }
    $stmt->close();

} elseif ($_POST['action'] === 'eliminar') {
    $id = (int) $_POST['id'];


    $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM reservas WHERE room_id = ? AND status_reserva = 'ativa'");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();
    $stmt_check->close();

    if ($row['total'] > 0) {
        send_response('error', $t['salas_msg_delete_error'] ?? 'Não é possível eliminar esta sala pois tem reservas ativas.', '../admin.php?section=salas');
    } else {

        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            send_response('success', $t['salas_msg_delete_success'] ?? 'Sala eliminada com sucesso!', '../admin.php?section=salas');
        } else {
            send_response('error', $t['salas_msg_delete_error_db'] ?? 'Erro ao eliminar sala.', '../admin.php?section=salas');
        }
        $stmt->close();
    }
} else {
    send_response('error', $t['invalid_action'] ?? 'Ação desconhecida.', '../admin.php?section=salas');
}
?>