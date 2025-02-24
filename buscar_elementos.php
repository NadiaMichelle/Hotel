<?php
// buscar_elementos.php

// Obtener la consulta de búsqueda
$q = isset($_GET['q']) ? $_GET['q'] : '';

// Conectar a la base de datos (ajusta los parámetros según tu configuración)
$mysqli = new mysqli("localhost", "root", "", "hotel");

// Verificar la conexión
if ($mysqli->connect_errno) {
    echo "<tr><td colspan='9'>Error al conectar a la base de datos.</td></tr>";
    exit();
}

// Escapar la entrada del usuario para prevenir inyecciones SQL
$q = $mysqli->real_escape_string($q);

// Consulta SQL para buscar en la tabla "elementos" los campos relevantes
$sql = "SELECT * FROM elementos WHERE codigo LIKE '%$q%' OR nombre LIKE '%$q%' OR descripcion LIKE '%$q%' OR tipo LIKE '%$q%' OR estado LIKE '%$q%' LIMIT 100";

if ($result = $mysqli->query($sql)) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['codigo']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
            echo "<td>$" . number_format($row['precio'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['tipo']) . "</td>";
            echo "<td>" . htmlspecialchars($row['estado']) . "</td>";
            echo "<td>" . htmlspecialchars($row['creado_at']) . "</td>";
            echo "<td>" . htmlspecialchars($row['updated_at']) . "</td>";
            echo "<td>";
            echo "<form method='post' class='form-borrar'>";
            echo "<input type='hidden' name='id' value='" . $row['id'] . "'>";
            echo "<button type='submit' name='borrar_elemento' class='boton-borrar'><i class='fas fa-trash-alt'></i> Borrar</button>";
            echo "</form>";
            echo "<a href='editar_elemento.php?id=" . $row['id'] . "' class='boton-editar'><i class='fas fa-edit'></i> Editar</a>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='9'>No se encontraron resultados.</td></tr>";
    }
    $result->free();
} else {
    echo "<tr><td colspan='9'>Error al ejecutar la consulta.</td></tr>";
}

$mysqli->close();
?>