<?php
session_start();
require_once 'config.php';
require_once 'GoogleAuthenticator.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$email_user = $_SESSION['user_email'];
$ga = new PHPGangsta_GoogleAuthenticator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}



try {
    if ($action === 'generate_secret') {
        $secret = $ga->createSecret();
        
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($email_user , $secret, 'SAPOSalas');
        
        $_SESSION['temp_2fa_secret'] = $secret;
        
        echo json_encode([
            'success' => true, 
            'qr_code_url' => $qrCodeUrl,
            'secret' => $secret
        ]);
    } 
    elseif ($action === 'verify_and_enable') {
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        
        if (empty($secret)) {
            throw new Exception('Segredo não encontrado. Gere o QR Code novamente.');
        }

        $checkResult = $ga->verifyCode($secret, $code, 2); 

        if ($checkResult) {
            $stmt = $conn->prepare("UPDATE utilizadores SET two_fa_secret = ?, two_fa_enabled = 1 WHERE id = ?");
            $stmt->bind_param("si", $secret, $user_id);
            
            if ($stmt->execute()) {
                unset($_SESSION['temp_2fa_secret']);
                echo json_encode(['success' => true, 'message' => '2FA ativado com sucesso!']);
            } else {
                throw new Exception('Erro ao guardar na base de dados.');
            }
            $stmt->close();
        } else {
            throw new Exception('Código incorreto. Tente novamente.');
        }
    } 
    elseif ($action === 'disable') {
        $stmt = $conn->prepare("UPDATE utilizadores SET two_fa_secret = NULL, two_fa_enabled = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '2FA desativado.']);
        } else {
            throw new Exception('Erro ao desativar.');
        }
    }
    else {
        throw new Exception('Ação inválida.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>