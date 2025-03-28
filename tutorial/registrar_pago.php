<?php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_recibo = intval($_POST['id_recibo']);
    $monto = floatval($_POST['monto']);
    $metodo = $_POST['metodo'];

    // Obtener total pagado y total a pagar
    $stmt = $pdo->prepare("SELECT total_pagado, total_pagar FROM recibos WHERE id = ?");
    $stmt->execute([$id_recibo]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        echo "Reserva no encontrada.";
        exit;
    }

    $total_pagado_actual = floatval($reserva['total_pagado']);
    $total_pagar = floatval($reserva['total_pagar']);

    $nuevo_total_pagado = $total_pagado_actual + $monto;
    $nuevo_saldo = max($total_pagar - $nuevo_total_pagado, 0);
    $estado_pago = ($nuevo_total_pagado >= $total_pagar) ? 'pagado' : 'pendiente';

    // Actualizar recibo
    $update = $pdo->prepare("UPDATE recibos SET total_pagado = ?, saldo = ?, estado_pago = ? WHERE id = ?");
    $update->execute([$nuevo_total_pagado, $nuevo_saldo, $estado_pago, $id_recibo]);

    // (Opcional) Registrar pago en tabla de pagos
    $insert = $pdo->prepare("INSERT INTO pagos (id_recibo, monto, metodo, fecha) VALUES (?, ?, ?, NOW())");
    $insert->execute([$id_recibo, $monto, $metodo]);

    echo "✅ Pago registrado correctamente.";
}
?>
