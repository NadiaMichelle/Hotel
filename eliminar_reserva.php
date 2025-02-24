<?php
session_start();
require 'config.php';

// Obtener el ID de la reserva a eliminar
$id = $_GET['id'];

// Actualizar el estado de la reserva a "cancelada"
$sql = "UPDATE recibos SET estado = 'cancelada' WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);

// Eliminar las entradas relacionadas en la tabla detalles_reserva
$sql_delete = "DELETE FROM detalles_reserva WHERE reserva_id = :id";
$stmt_delete = $pdo->prepare($sql_delete);
$stmt_delete->execute([':id' => $id]);

// Redirigir de vuelta a la página de reservas
header('Location: recibos.php');
?>