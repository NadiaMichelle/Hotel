<?php
session_start();
require 'config.php';

// Obtener el nombre del cliente de la solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_cliente = $_POST['nombre_cliente'];

    // Buscar el cliente en la base de datos
    $stmt = $pdo->prepare("SELECT * FROM huespedes WHERE nombre_cliente LIKE ?");
    $stmt->execute(['%' . $nombre_cliente . '%']);
    $huesped = $stmt->fetch();

    if ($huesped) {
        echo json_encode([
            'success' => true,
            'datos' => [
                'id' => $huesped['id'],
                'nombre_cliente' => $huesped['nombre_cliente'],
                'direccion' => $huesped['direccion'],
                'telefono' => $huesped['telefono']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Cliente no encontrado.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud no válido.'
    ]);
}
?>
