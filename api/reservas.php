// api/reservas.php
<?php
session_start();
require 'config.php';

// Obtener parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$tipo_elemento = isset($_GET['tipo_elemento']) ? $_GET['tipo_elemento'] : 'todos';

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina_actual > 1) ? ($pagina_actual * $registros_por_pagina) - $registros_por_pagina : 0;

// Construir la consulta SQL con filtros
$sql = "
    SELECT 
        r.*, 
        h.nombre as nombre_huesped,
        GROUP_CONCAT(e.nombre SEPARATOR ', ') as elementos_nombres
    FROM 
        recibos r 
    JOIN 
        huespedes h ON r.id_huesped = h.id
    LEFT JOIN 
        detalles_reserva dr ON r.id = dr.reserva_id
    LEFT JOIN 
        elementos e ON dr.elemento_id = e.id
    WHERE 
        1=1
";

// Aplicar filtros
$parametros = [];
if (!empty($busqueda)) {
    $sql .= " AND (h.nombre LIKE ? OR r.id = ?)";
    $parametros[] = "%$busqueda%";
    $parametros[] = $busqueda;
}

if (!empty($fecha_inicio)) {
    $sql .= " AND r.check_in >= ?";
    $parametros[] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $sql .= " AND r.check_out <= ?";
    $parametros[] = $fecha_fin;
}

if ($tipo_elemento !== 'todos') {
    $sql .= " AND e.tipo = ?";
    $parametros[] = $tipo_elemento;
}

$sql .= " GROUP BY r.id LIMIT ?, ?";

$parametros[] = $inicio;
$parametros[] = $registros_por_pagina;

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$reservaciones = $stmt->fetchAll();

// Calcular el total de reservas con filtros aplicados
$sql_count = "
    SELECT COUNT(*) FROM recibos r 
    JOIN huespedes h ON r.id_huesped = h.id
    LEFT JOIN detalles_reserva dr ON r.id = dr.reserva_id
    LEFT JOIN elementos e ON dr.elemento_id = e.id
    WHERE 1=1
";
$parametros_count = [];
if (!empty($busqueda)) {
    $sql_count .= " AND (h.nombre LIKE ? OR r.id = ?)";
    $parametros_count[] = "%$busqueda%";
    $parametros_count[] = $busqueda;
}

if (!empty($fecha_inicio)) {
    $sql_count .= " AND r.check_in >= ?";
    $parametros_count[] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $sql_count .= " AND r.check_out <= ?";
    $parametros_count[] = $fecha_fin;
}

if ($tipo_elemento !== 'todos') {
    $sql_count .= " AND e.tipo = ?";
    $parametros_count[] = $tipo_elemento;
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($parametros_count);
$total_reservas = $stmt_count->fetchColumn();
$paginas = ceil($total_reservas / $registros_por_pagina);

// Devolver los datos en formato JSON
header('Content-Type: application/json');
echo json_encode([
    'reservaciones' => $reservaciones,
    'paginas' => $paginas,
    'pagina_actual' => $pagina_actual
]);
?>