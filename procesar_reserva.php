<?php
header('Content-Type: application/json');

// Incluir el archivo de conexión a la base de datos
include 'config.php';
// Función para enviar respuestas JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Método no permitido.');
}

// Obtener los datos del formulario
$data = json_decode(file_get_contents('php://input'), true);

// Verificar que los datos necesarios estén presentes
$requiredFields = ['huesped_id', 'check_in', 'check_out', 'iva', 'ish', 'tarifa_por_noche', 'primer_pago', 'metodo_pago_primer', 'elementos'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        sendResponse(false, "Falta el campo requerido: $field.");
    }
}

// Realizar validaciones adicionales
$huespedId = intval($data['huesped_id']);
$checkIn = $data['check_in'];
$checkOut = $data['check_out'];
$iva = floatval($data['iva']);
$ish = floatval($data['ish']);
$tarifaPorNoche = floatval($data['tarifa_por_noche']);
$primerPago = floatval($data['primer_pago']);
$metodoPagoPrimer = $data['metodo_pago_primer'];
$elementos = $data['elementos'];

// Validar fechas
if (strtotime($checkOut) <= strtotime($checkIn)) {
    sendResponse(false, 'La fecha de check-out debe ser posterior a la fecha de check-in.');
}

// Calcular el subtotal, descuento, total a pagar y saldo
$subtotal = $tarifaPorNoche * ((strtotime($checkOut) - strtotime($checkIn)) / (60 * 60 * 24));
$descuento = 0;

// Aplicar descuento INAPAM si está seleccionado
if (isset($data['aplicar_descuento_inapam']) && $data['aplicar_descuento_inapam']) {
    if (!isset($data['tipo_descuento_inapam']) || !isset($data['valor_descuento_inapam'])) {
        sendResponse(false, 'Faltan datos para aplicar el descuento INAPAM.');
    }
    $tipoDescuentoInapam = $data['tipo_descuento_inapam'];
    $valorDescuentoInapam = floatval($data['valor_descuento_inapam']);

    if ($tipoDescuentoInapam === 'porcentaje') {
        $descuento = $subtotal * ($valorDescuentoInapam / 100);
    } elseif ($tipoDescuentoInapam === 'monto') {
        $descuento = $valorDescuentoInapam;
    } else {
        sendResponse(false, 'Tipo de descuento INAPAM no válido.');
    }
}

$totalPagar = $subtotal + ($subtotal * ($iva + $ish) / 100) - $descuento;
$saldo = $totalPagar - $primerPago;

// Insertar en la tabla recibos
$sqlRecibos = "INSERT INTO recibos (id_huesped, check_in, check_out, subtotal, descuento, iva, ish, total_pagar, estado_pago, total_pagado, saldo, metodo_pago_primer, metodo_pago_restante, numero_inapam, tipo_descuento_inapam, valor_descuento_inapam, created_at, updated_at) VALUES (:id_huesped, :check_in, :check_out, :subtotal, :descuento, :iva, :ish, :total_pagar, 'pendiente', :primer_pago, :saldo, :metodo_pago_primer, :metodo_pago_restante, :numero_inapam, :tipo_descuento_inapam, :valor_descuento_inapam, NOW(), NOW())";
$stmtRecibos = $pdo->prepare($sqlRecibos);
$stmtRecibos->execute([
    ':id_huesped' => $huespedId,
    ':check_in' => $checkIn,
    ':check_out' => $checkOut,
    ':subtotal' => $subtotal,
    ':descuento' => $descuento,
    ':iva' => $iva,
    ':ish' => $ish,
    ':total_pagar' => $totalPagar,
    ':primer_pago' => $primerPago,
    ':saldo' => $saldo,
    ':metodo_pago_primer' => $metodoPagoPrimer,
    ':metodo_pago_restante' => $data['metodo_pago_restante'] ?? null,
    ':numero_inapam' => $data['numero_inapam'] ?? null,
    ':tipo_descuento_inapam' => $data['tipo_descuento_inapam'] ?? null,
    ':valor_descuento_inapam' => $valorDescuentoInapam
]);

// Obtener el ID del recibo recién insertado
$reciboId = $pdo->lastInsertId();

// Insertar en la tabla detalles_reserva
foreach ($elementos as $elementoId) {
    $sqlDetalles = "INSERT INTO detalles_reserva (recibo_id, elemento_id, tipo, tarifa, created_at) VALUES (:recibo_id, :elemento_id, 'habitacion', :tarifa, NOW())";
    $stmtDetalles = $pdo->prepare($sqlDetalles);
    $stmtDetalles->execute([
        ':recibo_id' => $reciboId,
        ':elemento_id' => $elementoId,
        ':tarifa' => $tarifaPorNoche
    ]);
}

// Insertar en la tabla anticipos
$sqlAnticipos = "INSERT INTO anticipos (recibo_id, monto, fecha, metodo_pago) VALUES (:recibo_id, :monto, NOW(), :metodo_pago)";
$stmtAnticipos = $pdo->prepare($sqlAnticipos);
$stmtAnticipos->execute([
    ':recibo_id' => $reciboId,
    ':monto' => $primerPago,
    ':metodo_pago' => $metodoPagoPrimer
]);

// Devolver una respuesta al cliente
sendResponse(true, 'La reserva ha sido registrada exitosamente.')
?>