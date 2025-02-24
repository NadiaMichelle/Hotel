<?php
session_start();
require 'config.php';

// Obtener los parámetros de filtro
$cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$estado_pago = isset($_GET['estado_pago']) ? $_GET['estado_pago'] : '';

// Construir la consulta SQL con los filtros
$sql = "SELECT * FROM recibos WHERE 1=1";
$params = [];

if (!empty($cliente)) {
    $sql .= " AND nombre_cliente LIKE ?";
    $params[] = '%' . $cliente . '%';
}

if (!empty($estado_pago)) {
    $sql .= " AND estado_pago = ?";
    $params[] = $estado_pago;
}

$sql .= " ORDER BY fecha DESC";

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recibos = $stmt->fetchAll();

// Devolver los datos en formato JSON
echo json_encode([
    'success' => true,
    'recibos' => $recibos
]);
?>
