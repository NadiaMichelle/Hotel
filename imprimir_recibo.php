<?php
session_start();
require 'config.php';

// Obtener el ID de la reserva de la URL
$recibos_id = $_GET['id'];
date_default_timezone_set('America/Mexico_City');

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

// Consultar todos los elementos de la reserva (habitaciones y servicios)
$sql_elementos = "SELECT e.*, dr.tipo AS tipo_elemento, dr.tarifa, dr.created_at
                  FROM detalles_reserva dr
                  JOIN elementos e ON dr.elemento_id = e.id
                  WHERE dr.recibo_id = :id";

$stmt_elementos = $pdo->prepare($sql_elementos);
$stmt_elementos->execute(['id' => $recibos_id]);
$elementos = $stmt_elementos->fetchAll(PDO::FETCH_ASSOC);

// Consultar los anticipos asociados a la reserva
$sql_anticipos = "SELECT * FROM anticipos WHERE recibo_id = :id";
$stmt_anticipos = $pdo->prepare($sql_anticipos);
$stmt_anticipos->execute(['id' => $recibos_id]);
$anticipos = $stmt_anticipos->fetchAll(PDO::FETCH_ASSOC);

// Consultar los detalles de internet
$sql_internet = "SELECT * FROM internet ORDER BY fecha_registro DESC LIMIT 1";
$stmt_internet = $pdo->prepare($sql_internet);
$stmt_internet->execute();
$internet = $stmt_internet->fetch(PDO::FETCH_ASSOC);

// Si no hay elementos, mostrar un mensaje
if (!$elementos) {
    echo "No se encontraron elementos asociados a la reserva con ID: " . htmlspecialchars($recibos_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Reserva</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pkCnWkM6Z1N2KcQ3W2a+3s6QeVOna1E4u0pF6n/m1l5Y6hX5CqK5p+5+6n8z6r+6s5qX3tX5k3n+8v9A9t+6vX9w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            .elementos {
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
            .internet {
                margin-top: 3mm;
                text-align: center;
                font-size: 9px;
            }
            .reglas {
                margin-top: 3mm;
                text-align: center;
                font-size: 9px;
            }
            .anticipos {
                margin-top: 3mm;
                font-size: 10px;
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
            <p>Ignacio Gutiérrez Anaya</p>
            <p>Folio No 17020</p>
            <p>Régimen de Personas Físicas con Actividad Empresarial y Profesional</p>
            <p>Gómez Farías No. 31</p>
            <p>Tels.: (315) 355 5797</p>
            <p>San Patricio, Mpio. de Cihuatlán, Jalisco, México. C.P. 48980</p>
        </div>

        <div class="separador"></div>

        <div class="detalle">
            <p><strong>Recibo #:</strong> <?= htmlspecialchars($recibo['id']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
            <p><strong>Huésped:</strong> <?= htmlspecialchars($recibo['nombre_huesped']) ?></p>
            <p><strong>Encargado:</strong> <?= htmlspecialchars($_SESSION['nombre_usuario']) ?></p>
            <p><strong>Check-in:</strong> <?= htmlspecialchars($recibo['check_in']) ?></p>
            <p><strong>Check-out:</strong> <?= htmlspecialchars($recibo['check_out']) ?></p>
            <p><strong>Elementos de la Reserva:</strong></p>
            <div class="elementos">
                <?php foreach ($elementos as $elemento): ?>
                    <p>- <?= htmlspecialchars($elemento['nombre']) ?> (<?= htmlspecialchars($elemento['descripcion']) ?>) - Tarifa: $<?= number_format($elemento['tarifa'], 2) ?></p>
                <?php endforeach; ?>
            </div>
            <p><strong>Método de Pago:</strong> <?= ucfirst(htmlspecialchars($recibo['metodo_pago_primer'])) ?></p>
            <p><strong>Total:</strong> $<?= number_format($recibo['total_pagar'], 2) ?></p>
        </div>

        <div class="separador"></div>

        <div class="anticipos">
            <p><strong>Anticipos:</strong></p>
            <?php if ($anticipos): ?>
                <?php foreach ($anticipos as $anticipo): ?>
                    <p>- $<?= number_format($anticipo['monto'], 2) ?> pagado el <?= htmlspecialchars($anticipo['fecha']) ?> mediante <?= htmlspecialchars($anticipo['metodo_pago']) ?></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No se han realizado anticipos para esta reserva.</p>
            <?php endif; ?>
        </div>

        <div class="internet">
            <p><strong>INFORMACIÓN DE INTERNET</strong></p>
            <?php if ($internet): ?>
                <table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size:10px;">
                    <tr>
                        <th>Nombre de la Red (SSID)</th>
                        <th>Contraseña</th>
                        <th>Fecha de Registro</th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($internet['Nombre_wifi']) ?></td>
                        <td><?= htmlspecialchars($internet['contrasena']) ?></td>
                        <td><?= htmlspecialchars($internet['fecha_registro']) ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <p>No se encontró información de internet disponible.</p>
            <?php endif; ?>
        </div>

        <div class="reglas">
            <p><strong>REGLAS DEL HOTEL</strong></p>
            <p>GRACIAS POR NO FUMAR | NO MASCOTAS | NO MÚSICA | NO MUSICIANS</p>
            <p><i class="fa-solid fa-smoking-ban"></i> <i class="fa-solid fa-dog"></i> <i class="fa-solid fa-music"></i> <i class="fa-solid fa-user-ninja"></i></p>
        </div>

        <div class="footer">
            <p>1. EL HOTEL NO SE HACE RESPONSABLE POR LOS VALORES Y/O PERTENENCIAS NO DEPOSITADOS PARA SU CUSTODIA EN RECEPCIÓN.</p>
            <p>2. SE PAGARÁ EL IMPORTE DE LAS HABITACIONES OPORTUNAMENTE Y CUANDO EN LA RECEPCIÓN SE LE REQUIERE.</p>
            <p>3. PROHIBIDO INTRODUCIR AL HOTEL MELAQUE PUESTA DEL SOL ANIMALES, MÚSICOS, VENDEDORES Y/O PERSONAS NO AUTORIZADAS O NO REGISTRADAS.</p>
            <p>4. EN CASO DE CANCELACIÓN SE COBRARÁ EL 30% DE LO CANCELADO.</p>
            <p>5. ACEPTO DEVOLVER EN BUEN ESTADO AL HOTEL PUESTA DEL SOL, LAS TOALLAS PRESTADAS; DE NO SER ASÍ, ESTOY DE ACUERDO EN PAGAR POR CADA TOALLA DE ALBERCA 290.00 PESOS Y 250.00 PESOS POR CADA TOALLA DE HABITACIÓN.</p>
            <p>6. EN CUMPLIMIENTO A LA LEY FEDERAL DE PROTECCIÓN DE DATOS PERSONALES EN POSESIÓN DE LOS PARTICULARES, ESTÁ A LA VISTA EN LA RECEPCIÓN EL AVISO DE PRIVACIDAD, ASÍ COMO NUESTRA POLÍTICA DE PRIVACIDAD.</p>
            <p>7. ESTÁ PROHIBIDO PONER MÚSICA GRABADA O MÚSICA EN VIVO.</p>
            <p>8. ESTÁ PROHIBIDO FUMAR DENTRO DE TODO EL HOTEL INCLUYENDO SUS HABITACIONES.</p>
            <p>9. EL HOTEL MELAQUE PUESTA DEL SOL, NO ESTÁ OBLIGADO A RECIBIR Y/O PRESTAR SERVICIO A PERSONAS NO REGISTRADAS EN ESTE FORMATO.</p>
        </div>
    </div>
</body>
</html>