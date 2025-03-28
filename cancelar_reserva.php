<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_reserva'])) {
    $id_reserva = intval($_POST['id_reserva']);
    $motivo = $_POST['motivo'];

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT r.check_in, r.total_pagado, r.saldo, r.estado_pago, h.correo 
                               FROM recibos r 
                               INNER JOIN huespedes h ON r.id_huesped = h.id 
                               WHERE r.id = :id AND r.estado = 'pagado'");
        $stmt->execute(['id' => $id_reserva]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Reserva no encontrada o no válida.");
        }

        $check_in = strtotime($row['check_in']);
        $total_pagado = floatval($row['total_pagado']);
        $estado_pago = $row['estado_pago'];
        $hoy = strtotime(date("Y-m-d"));
        $dias_antes = ($check_in - $hoy) / 86400;
        $monto_reembolsar = 0;
        $monto_indemnizacion = 0;

        if (strtolower($estado_pago) === 'pagado') {
            // Calcular reembolso
            if ($dias_antes > 7) {
                $monto_reembolsar = $total_pagado;
            } elseif ($dias_antes >= 3) {
                $monto_reembolsar = $total_pagado * 0.5;
            }

            $monto_reembolsar = min($monto_reembolsar, $total_pagado);

            if ($monto_reembolsar > 0) {
                $stmt_reembolso = $pdo->prepare("INSERT INTO reembolsos (id_recibo, monto, metodo, estado, fecha) 
                                                 VALUES (:id_recibo, :monto, 'automático', 'pendiente', NOW())");
                $stmt_reembolso->execute([
                    'id_recibo' => $id_reserva,
                    'monto' => $monto_reembolsar
                ]);
            } else {
                // Si no aplica reembolso, aplicar indemnización
                $monto_indemnizacion = ($_SESSION['rol'] === 'admin' && isset($_POST['monto_indemnizacion']) && is_numeric($_POST['monto_indemnizacion']))
                    ? floatval($_POST['monto_indemnizacion'])
                    : 50.00;

                $stmt_indem = $pdo->prepare("INSERT INTO indemnizaciones (id_reserva, monto, motivo, fecha) 
                                             VALUES (:id, :monto, :motivo, NOW())");
                $stmt_indem->execute([
                    'id' => $id_reserva,
                    'monto' => $monto_indemnizacion,
                    'motivo' => 'Cancelación sin derecho a reembolso'
                ]);
            }
        } else {
            // Si no está pagado, aplicar indemnización
            $monto_indemnizacion = ($_SESSION['rol'] === 'admin' && isset($_POST['monto_indemnizacion']) && is_numeric($_POST['monto_indemnizacion']))
                ? floatval($_POST['monto_indemnizacion'])
                : 50.00;

            $stmt_indem = $pdo->prepare("INSERT INTO indemnizaciones (id_reserva, monto, motivo, fecha) 
                                         VALUES (:id, :monto, :motivo, NOW())");
            $stmt_indem->execute([
                'id' => $id_reserva,
                'monto' => $monto_indemnizacion,
                'motivo' => 'Cancelación sin pago o fuera de política de reembolso'
            ]);
        }

        // Actualizar estado de la reserva
        $stmt_update = $pdo->prepare("UPDATE recibos 
            SET estado = 'cancelada', 
                estado_pago = :nuevo_estado_pago, 
                saldo = saldo - :monto, 
                total_pagado = total_pagado - :monto2, 
                metodo_pago_restante = 'reembolso',
                updated_at = NOW()
            WHERE id = :id");
        $stmt_update->execute([
           'nuevo_estado_pago' => ($monto_reembolsar > 0) ? 'pendiente_reembolso' : 'cancelado',
            'monto' => $monto_reembolsar,
            'monto2' => $monto_reembolsar,
            'id' => $id_reserva
        ]);

        // Guardar motivo de cancelación
        $stmt_historial = $pdo->prepare("INSERT INTO cancelaciones (id_reserva, motivo, fecha) VALUES (:id_reserva, :motivo, NOW())");
        $stmt_historial->execute([
            'id_reserva' => $id_reserva,
            'motivo' => $motivo
        ]);

        $pdo->commit();

        if ($monto_reembolsar > 0) {
            $mensaje = "<div class='alert alert-success text-center'>✅ Reserva cancelada. Reembolso calculado: <strong>$$monto_reembolsar</strong></div>";
        } else {
            $mensaje = "<div class='alert alert-warning text-center'>⚠️ Reserva cancelada. Se aplicó indemnización por <strong>$$monto_indemnizacion</strong></div>";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert alert-danger text-center'>❌ Error: {$e->getMessage()}</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cancelar Reserva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a2d9d6d82e.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
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


</style>

</head>
<body>
<button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>


<aside class="sidebar">
    <h2><i class="fas fa-columns"></i> Menú</h2>
    <ul>
    <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <li><a href="reportes.php"><i class="fas fa-chart-line"></i> Reportes</a></li>
          
            <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
        <?php endif; ?>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>
<div class="overlay"></div>



<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🛑 Cancelar Reserva</h2>
        <a href="crear_reserva.php" class="btn btn-outline-primary">
            <i class="fas fa-plus"></i> Nueva Reservación
        </a>
    </div>

    <?php echo $mensaje; ?>

    <form method="POST" action="cancelar_reserva.php" class="border p-4 rounded bg-light shadow-sm">
        <div class="mb-3">
            <label for="id_reserva" class="form-label">Selecciona una reserva activa</label>
            <select class="form-select" name="id_reserva" id="id_reserva" required>
                <option value="">-- Seleccionar --</option>
                <?php
                $stmt_select = $pdo->query("SELECT r.id, r.check_in, r.check_out, r.total_pagado, h.nombre, r.estado_pago 
                                            FROM recibos r
                                            INNER JOIN huespedes h ON r.id_huesped = h.id
                                            WHERE TRIM(LOWER(r.estado)) = 'pagado'");
                while ($row = $stmt_select->fetch()) {
                    echo "<option value='{$row['id']}'>
                            #{$row['id']} - {$row['nombre']} ({$row['check_in']} a {$row['check_out']}) - {$row['estado_pago']} - \$ {$row['total_pagado']}
                          </option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo de Cancelación</label>
            <textarea class="form-control" name="motivo" id="motivo" rows="3" required></textarea>
        </div>

        <?php if ($_SESSION['rol'] === 'admin'): ?>
            <div class="mb-3">
                <label for="monto_indemnizacion" class="form-label">Monto de Indemnización (solo admin)</label>
                <input type="number" step="0.01" class="form-control" name="monto_indemnizacion" id="monto_indemnizacion" placeholder="Ej: 50.00">
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-danger w-100">
            <i class="fas fa-ban"></i> Cancelar Reserva
        </button>
    </form>
</div>
</body>
</html>

