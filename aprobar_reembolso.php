<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reembolso'], $_POST['accion'])) {
    $id_reembolso = intval($_POST['id_reembolso']);
    $accion = $_POST['accion'];

    // Obtener el reembolso
    $stmt = $pdo->prepare("SELECT * FROM reembolsos WHERE id = :id");
    $stmt->execute(['id' => $id_reembolso]);
    $reembolso = $stmt->fetch();

    if (!$reembolso) {
        header("Location: cancelaciones.php?error=Reembolso no encontrado");
        exit;
    }

    if ($accion === 'aprobar') {
        // Cambiar el estado a aprobado y actualizar el recibo
        $pdo->beginTransaction();

        try {
            // 1. Actualizar estado del reembolso
            $stmt1 = $pdo->prepare("UPDATE reembolsos SET estado = 'aprobado', fecha = NOW() WHERE id = :id");
            $stmt1->execute(['id' => $id_reembolso]);

            // 2. Actualizar el estado del recibo
            $stmt2 = $pdo->prepare("UPDATE recibos 
                                    SET estado_pago = 'reembolsado', 
                                        saldo = saldo - :monto, 
                                        total_pagado = total_pagado - :monto 
                                    WHERE id = :id_recibo");
            $stmt2->execute([
                'monto' => $reembolso['monto'],
                'id_recibo' => $reembolso['id_recibo']
            ]);

            $pdo->commit();
            header("Location: cancelaciones.php?exito=Reembolso aprobado");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: cancelaciones.php?error=Error al aprobar: " . urlencode($e->getMessage()));
            exit;
        }

    } elseif ($accion === 'rechazar') {
        // Si se rechaza, simplemente actualiza el estado
        $stmt = $pdo->prepare("UPDATE reembolsos SET estado = 'rechazado', fecha = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id_reembolso]);
        header("Location: cancelaciones.php?rechazo=Reembolso rechazado");
        exit;
    }
}
?>
