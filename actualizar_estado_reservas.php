<?php
// actualizar_estado_reservas.php
session_start();
require 'config.php';

$hoy = date('Y-m-d');

$sql = "UPDATE recibos SET estado = 'pasada' WHERE check_out < ? AND estado != 'cancelada'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$hoy]);

echo "Estado de las reservas actualizado correctamente.";
?>