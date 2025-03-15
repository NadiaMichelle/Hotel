<?php
session_start();
require 'config.php';

// Manejar solicitud POST
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['recibo_id'])) {
    $recibo_id = $_POST['recibo_id'];
    $metodo = $_POST['metodo_pago'];
    $monto = $_POST['monto_pago'];
    $referencia = $_POST['referencia_pago'];

    try {
        $pdo->beginTransaction();

        // Insertar el pago en la base de datos
        $stmt_pago = $pdo->prepare("INSERT INTO pagos (recibo_id, monto, metodo, referencia) VALUES (?, ?, ?, ?)");
        $stmt_pago->execute([
            $recibo_id,
            floatval($monto),
            $metodo,
            $referencia
        ]);

        // Actualizar el total pagado y el estado del pago
        $stmt_recibo = $pdo->prepare("SELECT total_pagar, total_pagado FROM recibos WHERE id = ?");
        $stmt_recibo->execute([$recibo_id]);
        $recibo = $stmt_recibo->fetch(PDO::FETCH_ASSOC);

        $nuevo_total_pagado = $recibo['total_pagado'] + floatval($monto);
        $total_pagar = $recibo['total_pagar'];

        if ($nuevo_total_pagado >= $total_pagar) {
            $estado_pago = 'pagado';
        } else {
            $estado_pago = 'pendiente';
        }

        $stmt_actualizar = $pdo->prepare("UPDATE recibos SET total_pagado = ?, estado_pago = ? WHERE id = ?");
        $stmt_actualizar->execute([
            $nuevo_total_pagado,
            $estado_pago,
            $recibo_id
        ]);

        $pdo->commit();

        echo "<script>alert('Pago agregado correctamente.'); window.location.href='editar_reserva.php?id=" . $recibo_id . "';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>