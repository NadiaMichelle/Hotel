<?php
session_start();
require 'config.php';

// Obtener los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar los datos del formulario
    $recibo_id = intval($_POST['id']);
    $numero_noches = intval($_POST['numero_noches']);
    $tipo_pago = $_POST['tipo_pago'];
    $estado_pago = $_POST['estado_pago'];
    $nombre_cliente = $_POST['nombre_cliente'];
    $pagado = floatval($_POST['pagado']);
    $subtotal = floatval($_POST['subtotal']);
    $adevolver = $subtotal - $pagado;

    // Actualizar los datos del recibo en la base de datos
    $stmt = $pdo->prepare("UPDATE recibos SET numero_noches = ?, tipo_pago = ?, estado_pago = ?, nombre_cliente = ?, pagado = ?, adevolver = ? WHERE id = ?");
    if ($stmt->execute([$numero_noches, $tipo_pago, $estado_pago, $nombre_cliente, $pagado, $adevolver, $recibo_id])) {
        // Redirigir a recibos.php después de una actualización exitosa
        header("Location: recibos.php");
        exit();
    } else {
        // Manejo de errores: puedes redirigir a una página de error o mostrar un mensaje
        echo "Error al actualizar el recibo.";
    }
} else {
    echo "Método de solicitud no válido.";
}
?>
