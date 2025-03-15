<?php
// actualizar_estado  .php
require 'config.php';

try {
    $hoy = date('Y-m-d');

    // Marcar habitaciones como ocupadas si la fecha de hoy es igual o mayor que la fecha de ocupación y menor que la fecha de liberación
    $stmt = $pdo->prepare("UPDATE elementos SET estado = 'ocupada' WHERE tipo = 'habitacion' AND fecha_ocupacion <= ? AND fecha_liberacion > ?");
    $stmt->execute([$hoy, $hoy]);

    // Marcar habitaciones como disponibles si la fecha de hoy es mayor o igual que la fecha de liberación
    $stmt = $pdo->prepare("UPDATE elementos SET estado = 'disponible' WHERE tipo = 'habitacion' AND fecha_liberacion <= ?");
    $stmt->execute([$hoy]);

    echo "Estado de las habitaciones actualizado correctamente.";
} catch (Exception $e) {
    echo "Error al actualizar el estado de las habitaciones: " . $e->getMessage();
}
?>