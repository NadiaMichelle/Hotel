<?php
// buscar_recibos.php

// Obtener la consulta de búsqueda
$q = isset($_GET['q']) ? $_GET['q'] : '';

// Conectar a la base de datos (ajusta los parámetros según tu configuración)
$mysqli = new mysqli("localhost", "root", "", "hotel");

// Verificar la conexión
if ($mysqli->connect_errno) {
    echo "<tr><td colspan='8'>Error al conectar a la base de datos.</td></tr>";
    exit();
}

// Escapar la entrada del usuario para prevenir inyecciones SQL
$q = $mysqli->real_escape_string($q);

// Consulta SQL para buscar recibos por nombre de huésped
$sql = "SELECT r.*, h.nombre as nombre, GROUP_CONCAT(e.nombre SEPARATOR ', ') as elementos_nombres
        FROM recibos r 
        JOIN huespedes h ON r.id_huesped = h.id
        LEFT JOIN detalles_reserva dr ON r.id = dr.reserva_id
        LEFT JOIN elementos e ON dr.elemento_id = e.id
        WHERE h.nombre LIKE '%$q%'
        GROUP BY r.id
        LIMIT 100";

if ($result = $mysqli->query($sql)) {
    if ($result->num_rows > 0) {
        $contador = 1;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $contador . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre_huesped']) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['check_in'])) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['check_out'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['elementos_nombres']) . "</td>";
            echo "<td>$" . number_format($row['total_pagar'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['estado']) . "</td>";
            echo "<td class='acciones'>";
            echo "<a href='editar_reserva.php?id=" . $row['id'] . "' class='btn-editar'><i class='fas fa-edit'></i></a>";
            echo "<a href='eliminar_reserva.php?id=" . $row['id'] . "' class='btn-eliminar' onclick='return confirm(\"¿Seguro que deseas cancelar esta reservación?\")'><i class='fas fa-times'></i></a>";
            echo "<a href='javascript:void(0)' onclick='imprimirRecibo(" . $row['id'] . ")' class='btn-imprimir'><i class='fas fa-print'></i></a>";
            echo "</td>";
            echo "</tr>";
            $contador++;
        }
    } else {
        echo "<tr><td colspan='8'>No se encontraron resultados.</td></tr>";
    }
    $result->free();
} else {
    echo "<tr><td colspan='8'>Error al ejecutar la consulta.</td></tr>";
}

$mysqli->close();
?>