<?php
require 'config.php';
require_once 'libs/dompdf/autoload.inc.php';
;
use Dompdf\Dompdf;
use Dompdf\Options;


// Configurar Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Obtener los datos
$reemb_aprob = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'aprobado'")->fetch();
$reemb_pend = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'pendiente'")->fetch();
$indem = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM indemnizaciones")->fetch();

$reembolsos = $pdo->query("SELECT * FROM reembolsos WHERE estado = 'pendiente'")->fetchAll();
$indemnizaciones = $pdo->query("SELECT * FROM indemnizaciones ORDER BY fecha DESC")->fetchAll();
$cancelaciones = $pdo->query("
    SELECT c.*, h.nombre 
    FROM cancelaciones c
    JOIN recibos r ON r.id = c.id_reserva
    JOIN huespedes h ON h.id = r.id_huesped
    ORDER BY c.fecha DESC
")->fetchAll();

// Construir contenido HTML
ob_start();
?>

<style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    h2, h3 { text-align: center; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #000; padding: 5px; text-align: center; }
    th { background-color: #f0f0f0; }
</style>

<h2>Panel de Cancelaciones y Reembolsos</h2>

<h3>Totales del Panel</h3>
<table>
    <tr><th>Tipo</th><th>Cantidad</th><th>Total ($)</th></tr>
    <tr><td>Reembolsos Aprobados</td><td><?= $reemb_aprob['cantidad'] ?></td><td><?= number_format($reemb_aprob['total'], 2) ?></td></tr>
    <tr><td>Reembolsos Pendientes</td><td><?= $reemb_pend['cantidad'] ?></td><td><?= number_format($reemb_pend['total'], 2) ?></td></tr>
    <tr><td>Indemnizaciones</td><td><?= $indem['cantidad'] ?></td><td><?= number_format($indem['total'], 2) ?></td></tr>
</table>

<h3>Reembolsos Pendientes</h3>
<table>
    <tr><th>ID</th><th>Recibo</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th></tr>
    <?php foreach ($reembolsos as $r): ?>
    <tr>
        <td><?= $r['id'] ?></td>
        <td>#<?= $r['id_recibo'] ?></td>
        <td><?= $r['monto'] ?></td>
        <td><?= $r['metodo'] ?></td>
        <td><?= $r['estado'] ?></td>
        <td><?= $r['fecha'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3>Indemnizaciones Registradas</h3>
<table>
    <tr><th>ID</th><th>Reserva</th><th>Monto</th><th>Motivo</th><th>Fecha</th></tr>
    <?php foreach ($indemnizaciones as $i): ?>
    <tr>
        <td><?= $i['id'] ?></td>
        <td>#<?= $i['id_reserva'] ?></td>
        <td><?= $i['monto'] ?></td>
        <td><?= $i['motivo'] ?></td>
        <td><?= $i['fecha'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3>Historial de Cancelaciones</h3>
<table>
    <tr><th>ID</th><th>ID Reserva</th><th>Nombre Huésped</th><th>Motivo</th><th>Fecha</th></tr>
    <?php foreach ($cancelaciones as $c): ?>
    <tr>
        <td><?= $c['id'] ?></td>
        <td>#<?= $c['id_reserva'] ?></td>
        <td><?= $c['nombre'] ?></td>
        <td><?= $c['motivo'] ?></td>
        <td><?= $c['fecha'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
$html = ob_get_clean();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("panel_cancelaciones.pdf", ["Attachment" => true]);
exit;
?>
