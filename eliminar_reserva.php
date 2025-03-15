<?php
session_start();
require 'config.php';

// Verificar si el ID de la reserva está presente en la URL
if (!isset($_GET['id'])) {
    echo "No se proporcionó un ID de reserva válido.";
    exit;
}

$reserva_id = $_GET['id'];

// Preparar la consulta para verificar si la reserva existe
$sql_verificar = "SELECT COUNT(*) FROM recibos WHERE id = :id";
$stmt_verificar = $pdo->prepare($sql_verificar);
$stmt_verificar->execute(['id' => $reserva_id]);
$count = $stmt_verificar->fetchColumn();

if ($count == 0) {
    echo "No se encontró la reserva con el ID proporcionado.";
    exit;
}

// Mostrar un formulario de confirmación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // El usuario ha confirmado la cancelación
    $sql_cancelar = "UPDATE recibos SET estado = 'cancelada' WHERE id = :id";
    $stmt_cancelar = $pdo->prepare($sql_cancelar);
    if ($stmt_cancelar->execute(['id' => $reserva_id])) {
        echo "La reserva ha sido cancelada exitosamente.";
        header("Location: recibos.php");
        exit;
    } else {
        echo "Hubo un error al cancelar la reserva.";
    }
} else {
    // Mostrar el formulario de confirmación
    echo "<form method='post'>";
    echo "<p>¿Está seguro de que desea cancelar esta reserva?</p>";
    echo "<button type='submit'>Sí, cancelar la reserva</button>";
    echo "</form>";
}
?>