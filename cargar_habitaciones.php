<?php
require 'config.php';

$stmt = $pdo->query('SELECT * FROM habitaciones');
$habitaciones = $stmt->fetchAll();

foreach ($habitaciones as $habitacion) {
    echo "<tr>";
    echo "<td>{$habitacion['id']}</td>";
    echo "<td>{$habitacion['numero_habitacion']}</td>";
    echo "<td>{$habitacion['precio']}</td>";
    echo "<td>{$habitacion['caracteristicas']}</td>";
    echo "<td>
            <form method='post' style='display:inline;'>
                <input type='hidden' name='id' value='{$habitacion['id']}'>
                <button type='submit' name='borrar'><i class='fas fa-trash'></i> Borrar</button>
            </form>
          </td>";
    echo "</tr>";
}
