<?php
require 'config.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    // Buscar el usuario con el token
    $stmt = $pdo->prepare("SELECT id FROM huespedes WHERE verification_token = ? AND email_verified = 0");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // Actualizar el registro para marcar el correo como verificado
        $update = $pdo->prepare("UPDATE huespedes SET email_verified = 1, verification_token = NULL WHERE id = ?");
        $update->execute([$usuario['id']]);
        echo "Tu correo ha sido verificado correctamente.";
    } else {
        echo "El token es inválido o ya ha sido usado.";
    }
} else {
    echo "No se proporcionó ningún token.";
}
?>
