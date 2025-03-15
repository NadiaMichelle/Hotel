<?php
// obtener_huespedes.php
// Iniciar la sesión
session_start();

// Incluir el archivo de configuración
require 'config.php';

// Obtener el término de búsqueda (si existe)
$busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';

// Preparar la consulta SQL con búsqueda (si hay un término de búsqueda)
$sql = "SELECT id, nombre, rfc, telefono, correo FROM huespedes";
$params = [];

if (!empty($busqueda)) {
    $sql .= " WHERE nombre LIKE :busqueda";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

// Preparar y ejecutar la consulta
try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    // Obtener los resultados
    $huespedes = $stmt->fetchAll();

    // Devolver los resultados como JSON
    header('Content-Type: application/json');
    echo json_encode($huespedes);
} catch (PDOException $e) {
    // Manejar errores de la base de datos
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los huéspedes']);
    // Puedes registrar el error en un archivo de registro si lo deseas
    // error_log($e->getMessage(), 3, 'error_log.txt');
    exit;
}
?>