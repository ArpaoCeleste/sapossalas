<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

$t = [];
if (file_exists('../includes/lang.php')) {
    require_once '../includes/lang.php';
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $msg = ['type' => 'error', 'text' => 'Token de segurança inválido.'];
    $swal_msg = base64_encode(json_encode($msg));
    header("Location: ../admin.php?section=utilizadores&swal_msg={$swal_msg}");
    exit;
}

$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$section = $_POST['section'] ?? 'utilizadores';

try {
    if ($action === 'reativar' && $id > 0) {
        $stmt = $conn->prepare("UPDATE utilizadores SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $msg = [
                'type' => 'success',
                'text' => $t['users_msg_reactivate_success'] ?? 'Utilizador reativado com sucesso. O acesso ao sistema foi restaurado.'
            ];
        } else {
            throw new Exception($t['users_err_reactivate'] ?? 'Erro ao tentar reativar o utilizador.');
        }
        $stmt->close();
    } 
    else {
        throw new Exception($t['invalid_action'] ?? 'Ação inválida ou ID em falta.');
    }

} catch (Exception $e) {
    $msg = [
        'type' => 'error',
        'text' => $e->getMessage()
    ];
}

$swal_msg = base64_encode(json_encode($msg));

header("Location: ../admin.php?section={$section}&swal_msg={$swal_msg}");
exit;
?>