<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$huesped = $_GET['huesped'] ?? '';
$habitacion = $_GET['habitacion'] ?? '';

$sql = "SELECT 
    r.id AS recibo_id,
    r.subtotal,
    r.descuento,
    r.total_pagar,
    r.estado,
    h.nombre AS huesped_nombre,
    GROUP_CONCAT(e.nombre SEPARATOR ', ') AS elementos,
    (SELECT COALESCE(SUM(a.monto), 0) FROM anticipos a WHERE a.recibo_id = r.id) AS anticipo_total,
    (r.total_pagar - (SELECT COALESCE(SUM(a.monto), 0) FROM anticipos a WHERE a.recibo_id = r.id)) AS saldo

        FROM recibos r
        JOIN huespedes h ON r.id_huesped = h.id
        JOIN detalles_reserva dr ON r.id = dr.recibo_id
        JOIN elementos e ON dr.elemento_id = e.id
        WHERE 1=1";

$params = [];
if (!empty($check_in)) {
    $sql .= " AND r.check_in >= :check_in";
    $params[':check_in'] = $check_in;
}
if (!empty($check_out)) {
    $sql .= " AND r.check_out <= :check_out";
    $params[':check_out'] = $check_out;
}
if (!empty($huesped)) {
    $sql .= " AND h.nombre LIKE :huesped";
    $params[':huesped'] = "%$huesped%";
}
if (!empty($habitacion)) {
    $sql .= " AND e.nombre LIKE :habitacion";
    $params[':habitacion'] = "%$habitacion%";
}
$sql .= " GROUP BY r.id ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
    --color-primary: #2c3e50;
    --color-secondary: #3498db;
    --color-success: #2ecc71;
    --color-accent: #e74c3c;
    --color-background: #f4f6f9;
    --color-white: #ffffff;
    --color-text: #2c3e50;
    --color-gray: #bdc3c7;
    --color-border: #dcdde1;
    --color-shadow: rgba(0, 0, 0, 0.05);
}

body {
    font-family: 'Segoe UI', sans-serif;
    background-color: var(--color-background);
    color: var(--color-text);
    margin: 0;
    padding: 0;
}

h1 {
    text-align: center;
    padding: 1rem;
    color: var(--color-primary);
}

.container {
    max-width: 1200px;
    margin: 2rem auto;
    background-color: var(--color-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px var(--color-shadow);
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
}

.table thead {
    background-color: var(--color-primary);
    color: var(--color-white);
}

.table th,
.table td {
    padding: 12px 16px;
    text-align: center;
    border-bottom: 1px solid var(--color-border);
}

.table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.table tbody tr:hover {
    background-color: #eef1f5;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    color: var(--color-white);
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
}

.btn-editar {
    background-color: var(--color-secondary);
}

.btn-imprimir {
    background-color: var(--color-success);
}

.btn:hover {
    opacity: 0.9;
}

.filtros {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.filtros label {
    font-weight: 600;
}

.filtros input {
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

.filtros button {
    background-color: var(--color-primary);
    color: var(--color-white);
    border: none;
    padding: 10px 18px;
    border-radius: 5px;
    cursor: pointer;
}

.filtros button:hover {
    background-color: #1f2f3f;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background-color: var(--color-primario);
    color: var(--color-letters);
    padding: 20px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1000;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 30px;
    font-size: 1.5em;
}

.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar ul li {
    margin: 20px 0;
}

.sidebar ul li a {
    color: var(--color-letters);
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 1.1em;
    padding: 10px;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.sidebar ul li a i {
    margin-right: 10px;
    font-size: 1.2em;
}

.sidebar ul li a:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.toggle-sidebar {
    display: none;
    position: fixed;
    top: 1px;
    left: 15px;
    background: var(--color-primario);
    color: var(--color-letters);
    border: none;
    padding: 10px;
    border-radius: 4px;
    z-index: 1000;
}

.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 998;
}

.contenido {
    margin-left: 250px;
    padding: 30px;
    transition: margin 0.3s;
}
.table-container {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid var(--color-border);
    border-radius: 8px;
}


@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: -250px;
        height: 100%;
        z-index: 999;
        transition: left 0.3s ease;
    }

    .sidebar.active {
        left: 0;
    }

    .toggle-sidebar {
        display: block;
    }

    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 998;
        transition: display 0.3s ease;
    }

    .overlay.active {
        display: block;
    }

    .contenido {
        margin-left: 0;
        padding: 20px;
        padding-top: 60px;
    }
}

@media (max-width: 480px) {
    .contenido {
        padding: 15px;
        padding-top: 60px;
    }

    h1 {
        font-size: 1.5rem;
        margin-bottom: 20px;
    }

    .sidebar ul li {
        padding: 12px 15px;
    }

    .sidebar h2 {
        font-size: 1.3rem;
    }
}
:root {
    --color-primario: #2c3e50; /* Azul oscuro */
    --color-secundario: #3498db; /* Azul claro */
    --color-fondo: #f5f6fa; /* Blanco grisáceo */
    --color-borde: #e0e0e0; /* Gris claro */
    --color-accent: #e74c3c; /* Rojo para resaltar */
    --color-background: #ffffff; /* Blanco puro */
    --color-text: #333333; /* Texto oscuro */
    --color-border: #bdc3c7; /* Gris para bordes */
    --color-shadow: rgba(0, 0, 0, 0.1); /* Sombra suave */
    --color-letters: #ffffff; /* Texto blanco para fondos oscuros */
}

</style>
</head>
<body>

  <!-- Botón para togglear el sidebar en móvil -->
  <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
  
  <!-- Sidebar -->
  <aside class="sidebar">
    <h2><i class="fas fa-columns"></i> Menú</h2>
    <ul>
    <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
            <li><a href="reportes.php"><i class="fas fa-chart-line"></i> Reportes</a></li>
        <?php endif; ?>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>

  <!-- Overlay opcional para móvil -->
  <div class="overlay"></div>

    <div class="container">
        <h1>Registros de Caja</h1>
        <div class="filtros">
            <form method="get">
                <label>Check-in:
                    <input type="date" name="check_in" value="<?= htmlspecialchars($check_in) ?>">
                </label>
                <label>Check-out:
                    <input type="date" name="check_out" value="<?= htmlspecialchars($check_out) ?>">
                </label>
                <label>Huésped:
                    <input type="text" name="huesped" value="<?= htmlspecialchars($huesped) ?>">
                </label>
                <label>Habitación:
                    <input type="text" name="habitacion" value="<?= htmlspecialchars($habitacion) ?>">
                </label>
                <button type="submit">Filtrar</button>
                <button type="button" onclick="window.location.href='recibos.php'">Limpiar</button>
            </form>
        </div>

<div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Huésped</th>
                    <th>Elementos</th>
                    <th>Descuento</th>
                    <th>Total</th>
                    <th>Abono</th>
                    <th>Saldo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($r['huesped_nombre']) ?></td>
                        <td><?= htmlspecialchars($r['elementos']) ?></td>
                        <td>$<?= number_format($r['descuento'], 2) ?></td>
                        <td>$<?= number_format($r['total_pagar'], 2) ?></td>
                        <td>$<?= number_format($r['anticipo_total'], 2) ?></td>
                        <td>$<?= number_format($r['saldo'], 2) ?></td>
                        <td>
                            <?php if ($r['estado'] !== 'cancelada'): ?>
                                <a class="btn btn-editar" href="editar_reserva.php?id=<?= $r['recibo_id'] ?>"><i class="fas fa-edit"></i></a>
                                <a class="btn btn-imprimir" href="javascript:window.open('imprimir_recibo.php?id=<?= $r['recibo_id'] ?>','_blank')"><i class="fas fa-print"></i></a>
                            <?php else: ?>
                                <span style="color: gray;">Cancelada</span>
                            <?php endif; ?>
                        </td>

                       
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>  
</body>
</html>
