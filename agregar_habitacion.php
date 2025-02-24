<?php
session_start();
require 'config.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numero_habitacion = $_POST['numero_habitacion'];
    $precio = $_POST['precio'];
    $caracteristicas = $_POST['caracteristicas'];

    $stmt = $pdo->prepare('INSERT INTO habitaciones (numero_habitacion, precio, caracteristicas) VALUES (?, ?, ?)');
    
    if ($stmt->execute([$numero_habitacion, $precio, $caracteristicas])) {
        $response["success"] = true;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
