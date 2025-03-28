<?php
require 'config.php';

// Encabezados para forzar descarga como Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=panel_cancelaciones_reembolsos.xls");
header("Pragma: no-cache");
header("Expires: 0");

// OBTENER TOTALES DEL DASHBOARD
$reemb_aprob = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'aprobado'")->fetch();
$reemb_pend = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'pendiente'")->fetch();
$indem = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM indemnizaciones")->fetch();

// MOSTRAR RESUMEN
echo "<h3>TOTALES DEL PANEL</h3>";
echo "<table border='1'>";
echo "<tr><th>Tipo</th><th>Cantidad</th><th>Total ($)</th></tr>";
echo "<tr><td>Reembolsos Aprobados</td><td>{$reemb_aprob['cantidad']}</td><td>{$reemb_aprob['total']}</td></tr>";
echo "<tr><td>Reembolsos Pendientes</td><td>{$reemb_pend['cantidad']}</td><td>{$reemb_pend['total']}</td></tr>";
echo "<tr><td>Indemnizaciones</td><td>{$indem['cantidad']}</td><td>{$indem['total']}</td></tr>";
echo "</table><br><br>";

// REEMBOLSOS PENDIENTES
echo "<h3>REEMBOLSOS PENDIENTES</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Recibo</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th></tr>";

$stmt = $pdo->query("SELECT * FROM reembolsos WHERE estado = 'pendiente'");
while ($row = $stmt->fetch()) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>#{$row['id_recibo']}</td>
            <td>{$row['monto']}</td>
            <td>{$row['metodo']}</td>
            <td>{$row['estado']}</td>
            <td>{$row['fecha']}</td>
          </tr>";
}
echo "</table><br><br>";

// INDEMNIZACIONES
echo "<h3>INDEMNIZACIONES REGISTRADAS</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>ID Reserva</th><th>Monto</th><th>Motivo</th><th>Fecha</th></tr>";

$stmt = $pdo->query("SELECT * FROM indemnizaciones ORDER BY fecha DESC");
while ($row = $stmt->fetch()) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>#{$row['id_reserva']}</td>
            <td>{$row['monto']}</td>
            <td>{$row['motivo']}</td>
            <td>{$row['fecha']}</td>
          </tr>";
}
echo "</table><br><br>";

// CANCELACIONES
echo "<h3>HISTORIAL DE CANCELACIONES</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>ID Reserva</th><th>Nombre Huésped</th><th>Motivo</th><th>Fecha</th></tr>";

$stmt = $pdo->query("
    SELECT c.id, c.id_reserva, h.nombre, c.motivo, c.fecha 
    FROM cancelaciones c
    JOIN recibos r ON r.id = c.id_reserva
    JOIN huespedes h ON h.id = r.id_huesped
    ORDER BY c.fecha DESC
");

while ($row = $stmt->fetch()) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>#{$row['id_reserva']}</td>
            <td>{$row['nombre']}</td>
            <td>{$row['motivo']}</td>
            <td>{$row['fecha']}</td>
          </tr>";
}
echo "</table>";
?>
