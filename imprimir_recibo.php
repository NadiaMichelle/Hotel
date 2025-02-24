<?php
session_start();
require 'config.php';

// Obtener el ID de la reserva de la URL
$recibos_id = $_GET['id'];

// Consultar la información del recibo y huésped
$sql_recibo = "SELECT r.*, h.nombre AS nombre_huesped, h.logo
               FROM recibos r
               JOIN huespedes h ON r.id_huesped = h.id
               WHERE r.id = :id";

$stmt_recibo = $pdo->prepare($sql_recibo);
$stmt_recibo->execute(['id' => $recibos_id]);
$recibo = $stmt_recibo->fetch(PDO::FETCH_ASSOC);

if (!$recibo) {
    echo "No se encontró la reserva con ID: " . htmlspecialchars($recibos_id);
    exit;
}

// Consultar los elementos de la reserva (habitaciones)
$sql_elementos = "SELECT e.*, dr.tipo AS tipo_elemento
                  FROM detalles_reserva dr
                  JOIN elementos e ON dr.elemento_id = e.id
                  WHERE dr.reserva_id = :id AND dr.tipo = 'habitacion'";

$stmt_elementos = $pdo->prepare($sql_elementos);
$stmt_elementos->execute(['id' => $recibos_id]);
$habitaciones = $stmt_elementos->fetchAll(PDO::FETCH_ASSOC);

// Si no hay habitaciones, mostrar un mensaje
if (!$habitaciones) {
    echo "No se encontraron habitaciones asociadas a la reserva con ID: " . htmlspecialchars($recibos_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Reserva</title>
    <style>
        @media print {
            body {
                font-family: 'Arial', sans-serif;
                font-size: 12px;
                width: 80mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                background-color: #f0f8ff;
                color: #333;
            }
            .recibo {
                width: 100%;
                padding: 5mm;
                box-sizing: border-box;
                background-color: #ffffff;
                border: 1px solid #add8e6;
                border-radius: 10px;
            }
            .header {
                text-align: center;
                padding-bottom: 3mm;
                border-bottom: 2px solid #4682b4;
            }
            .header img {
                max-width: 60px;
                margin-bottom: 3mm;
            }
            .header h3 {
                font-size: 16px;
                color: #4682b4;
                margin: 0;
            }
            .header p {
                font-size: 10px;
                margin: 1mm 0;
            }
            .separador {
                width: 100%;
                height: 1px;
                background-color: #4682b4;
                margin: 3mm 0;
            }
            .detalle {
                font-size: 10px;
            }
            .detalle p {
                margin: 1.5mm 0;
                font-weight: bold;
            }
            .habitaciones {
                margin-left: 3mm;
                font-size: 10px;
            }
            .total {
                text-align: right;
                font-weight: bold;
                font-size: 13px;
                margin-top: 5mm;
                border-top: 2px solid #4682b4;
                padding-top: 2mm;
            }
            .wifi {
                text-align: center;
                font-size: 9px;
                margin-top: 4mm;
                border-top: 1px dashed #4682b4;
                padding-top: 2mm;
            }
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            width: 80mm;
            height: 297mm;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background-color: #f0f8ff;
            color: #333;
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <div class="recibo">
        <div class="header">
            <h3>HOTEL MELAQUE PUESTA DEL SOL</h3>
            <p>Disfruta de unas vacaciones junto al mar</p>
            <p>Gómez Farias 31, Centro, 48980 San Patricio, Jal.</p>
            <p>Tel: 315 355 5797</p>
        </div>

        <div class="separador"></div>

        <div class="detalle">
            <p><strong>Recibo #:</strong> <?= htmlspecialchars($recibo['id']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
            <p><strong>Huésped:</strong> <?= htmlspecialchars($recibo['nombre_huesped']) ?></p>
            <p><strong>Check-in:</strong> <?= htmlspecialchars($recibo['check_in']) ?></p>
            <p><strong>Check-out:</strong> <?= htmlspecialchars($recibo['check_out']) ?></p>
            <p><strong>Habitaciones:</strong></p>
            <div class="habitaciones">
                <?php foreach ($habitaciones as $habitacion): ?>
                    <p>- <?= htmlspecialchars($habitacion['nombre']) ?> (<?= htmlspecialchars($habitacion['descripcion']) ?>)</p>
                <?php endforeach; ?>
            </div>
            <p><strong>Método de Pago:</strong> <?= ucfirst(htmlspecialchars($recibo['tipo_pago'])) ?></p>
        </div>

        <div class="separador"></div>

        <div class="total">
            <p>TOTAL: $<?= number_format($recibo['total_pagar'], 2) ?></p>
        </div>

        <div class="wifi">
            <p><strong>WIFI</strong></p>
            <p>RED: INFINITUM123566</p>
            <p>Contraseña: 1234puestasol</p>
            <p>¡Gracias por su preferencia!</p>
        </div>
    </div>
</body>
</html>