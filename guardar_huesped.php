<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $nombre = $_POST['nombre'];
    $rfc = $_POST['rfc'];
    $telefono = $_POST['telefono'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO huespedes (nombre, rfc, telefono) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $rfc, $telefono]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Huésped guardado correctamente.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>