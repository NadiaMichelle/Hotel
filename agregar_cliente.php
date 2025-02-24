<?php
session_start();
require 'config.php';

// Obtener los datos del cliente de la solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_cliente = $_POST['nombre_cliente'];
    $direccion_cliente = $_POST['direccion_cliente'];
    $telefono_cliente = $_POST['telefono_cliente'];

    // Insertar el nuevo cliente en la base de datos
    $stmt = $pdo->prepare("INSERT INTO huespedes (nombre_cliente, direccion, telefono) VALUES (?, ?, ?)");
    if ($stmt->execute([$nombre_cliente, $direccion_cliente, $telefono_cliente])) {
        $huesped_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'huesped_id' => $huesped_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al agregar el cliente.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud no válido.'
    ]);
}
?>
