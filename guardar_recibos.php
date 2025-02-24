<?php
session_start();
require 'config.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['habitacion_id'] as $key => $habitacion_id) {
        $descripcion_habitacion = $_POST['descripcion_habitacion'][$key];
        $precio_habitacion = floatval($_POST['precio_habitacion'][$key]);
        $numero_noches = intval($_POST['numero_noches'][$key]);
        $subtotal = $precio_habitacion * $numero_noches;
        $total = $subtotal;
        $pagado = isset($_POST['pagado']) ? floatval($_POST['pagado']) : 0.0;
        $adevolver = $pagado - $total;
        $tipo_pago = $_POST['tipo_pago'];
        $estado_pago = $_POST['estado_pago'];
        $fecha = date("Y-m-d");
        $nombre_cliente = $_POST['nombre_cliente']; 

        $stmt = $pdo->prepare("INSERT INTO `recibos` 
            (`habitacion_id`, `descripcion_habitacion`, `precio_habitacion`, `numero_noches`, `subtotal`, `total`, `fecha`, `nombre_cliente`, `pagado`, `adevolver`, `tipo_pago`, `estado_pago`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$habitacion_id, $descripcion_habitacion, $precio_habitacion, $numero_noches, $subtotal, $total, $fecha, $nombre_cliente, $pagado, $adevolver, $tipo_pago, $estado_pago]);
    }

    echo json_encode(['success' => true, 'message' => 'Recibo generado exitosamente.']);
}

    $total = $subtotal; // Assuming total is equal to subtotal

    // Execute the statement with the form data
    if ($stmt->execute([$habitacion_id, $descripcion_habitacion, $precio_habitacion, $numero_noches, $subtotal, $total, $fecha, $nombre_cliente, $pagado, $adevolver, $tipo_pago, $estado_pago])) {
        // Retrieve the last inserted ID
        $recibo_id = $pdo->lastInsertId();

        // Return success response with a message
        echo json_encode([
            'success' => true,
            'message' => 'Recibo generado exitosamente.',
            'recibo_id' => $recibo_id
        ]);
    } else {
        // Return error response if insertion fails
        echo json_encode([
            'success' => false,
            'message' => 'Error al generar el recibo.'
        ]);
    }
 else {
    // If the request method is not POST, return an error
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud no válido.'
    ]);
}
?>
