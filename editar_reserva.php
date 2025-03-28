<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];

function manejarError($mensaje) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $mensaje]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['recibo_id']) || !is_numeric($_POST['recibo_id'])) {
            manejarError("ID de recibo no válido.");
        }

        $recibo_id = $_POST['recibo_id'];

        $stmt = $pdo->prepare("SELECT * FROM recibos WHERE id = ?");
        $stmt->execute([$recibo_id]);
        $recibo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recibo) {
            throw new Exception("No se encontró la reserva.");
        }

        $stmt = $pdo->prepare("SELECT elemento_id FROM detalles_reserva WHERE recibo_id = ?");
        $stmt->execute([$recibo_id]);
        $elementos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $camposRequeridos = [
            'check_in' => 'Fecha de check-in es requerida',
            'check_out' => 'Fecha de check-out es requerida',
            'iva' => 'El IVA es requerido',
            'ish' => 'El ISH es requerido',
            'tarifa_por_noche' => 'La tarifa por noche es requerida',
            'elementos' => 'Debe seleccionar al menos un elemento',
            'Nombre_wifi' => 'El nombre del WiFi es requerido',
            'contrasena' => 'La contraseña del WiFi es requerida'
        ];

        foreach ($camposRequeridos as $campo => $mensaje) {
            if (empty($_POST[$campo])) {
                throw new Exception($mensaje);
            }
        }

        $iva = (float)$_POST['iva'];
        $ish = (float)$_POST['ish'];
        if ($iva < 0 || $iva > 100 || $ish < 0 || $ish > 100) {
            throw new Exception("Los porcentajes deben estar entre 0 y 100");
        }

        $tarifa_por_noche = (float)$_POST['tarifa_por_noche'];
        if ($tarifa_por_noche <= 0) {
            throw new Exception("La tarifa por noche debe ser un valor positivo");
        }

        $check_in = $_POST['check_in'];
        $check_out = $_POST['check_out'];
        $hoy = new DateTime('today');
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);

        $isEditando = isset($_POST['recibo_id']);
        $esPasada = $check_in_date < $hoy;

        if (!$isEditando && $esPasada) {
            throw new Exception("No se pueden reservar fechas pasadas");
        }

        if ($check_out_date <= $check_in_date) {
            throw new Exception("Check-out debe ser posterior a check-in");
        }

        $dias = $check_out_date->diff($check_in_date)->days;
        $subtotal = $tarifa_por_noche * $dias;

        $pdo->beginTransaction();

        $huesped_id = $_POST['huesped_id'] ?? null;
        if (empty($huesped_id)) {
            if (empty($_POST['nuevo_huesped_nombre'])) {
                throw new Exception("El nombre del huésped es obligatorio");
            }

            $stmt = $pdo->prepare("INSERT INTO huespedes (nombre, rfc, telefono, correo) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['nuevo_huesped_nombre'],
                $_POST['nuevo_huesped_rfc'] ?? null,
                $_POST['nuevo_huesped_telefono'] ?? null,
                $_POST['nuevo_huesped_correo'] ?? null
            ]);
            $huesped_id = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("INSERT INTO internet (Nombre_wifi, contrasena) VALUES (?, ?)");
        $stmt->execute([
            $_POST['Nombre_wifi'],
            $_POST['contrasena']
        ]);

        $descuento = 0;
        $numero_inapam = null;

        if (isset($_POST['aplicar_descuento_inapam'])) {
            $tipo_descuento = $_POST['tipo_descuento_inapam'] ?? 'porcentaje';
            $valor_descuento = (float)$_POST['valor_descuento_inapam'] ?? 0;
            $numero_inapam = $_POST['numero_inapan'] ?? null;

            $descuento = $tipo_descuento === 'porcentaje'
                ? $subtotal * ($valor_descuento / 100)
                : $valor_descuento;
        }

        $total_pagar = ($subtotal - $descuento) * (1 + ($iva + $ish) / 100);

        $stmt = $pdo->prepare("DELETE FROM anticipos WHERE recibo_id = ?");
        $stmt->execute([$recibo_id]);

        $total_pagado = 0;
        $metodo_pago_primer = 'Pendiente';
        $metodo_pago_restante = 'Pendiente';

        $tipo_pago = $_POST['tipo_pago'] ?? 'completo';

        if ($tipo_pago === 'completo') {
            $metodo = $_POST['metodo_pago_completo'];
            $detalle_metodo = $metodo === 'otro' ? ($_POST['detalle_metodo_completo'] ?? 'Otro método') : $metodo;
            $monto = (float)$_POST['monto_pago_completo'];
            $total_pagado = $monto;
            $metodo_pago_primer = $detalle_metodo;

            $stmt = $pdo->prepare("INSERT INTO anticipos (recibo_id, monto, metodo_pago, fecha) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$recibo_id, $monto, $detalle_metodo]);
        } elseif (!empty($_POST['primer_pago']) && is_array($_POST['primer_pago'])) {
            foreach ($_POST['primer_pago'] as $key => $monto) {
                $metodo = $_POST['metodo_pago_parcial'][$key] ?? 'otro';
                $detalle_metodo = $metodo === 'otro' ? ($_POST['detalle_metodo_parcial'][$key] ?? 'Otro método') : $metodo;
                $monto = (float)$monto;
                $total_pagado += $monto;

                if ($key == 0) {
                    $metodo_pago_primer = $detalle_metodo;
                } else {
                    $metodo_pago_restante = $detalle_metodo;
                }

                $stmt = $pdo->prepare("INSERT INTO anticipos (recibo_id, monto, metodo_pago, fecha) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$recibo_id, $monto, $detalle_metodo]);
            }
        }

        $saldo = max($total_pagar - $total_pagado, 0);
        $estado = $saldo <= 0 ? 'pagado' : 'pendiente';

        $stmt = $pdo->prepare("UPDATE recibos SET 
            id_huesped = ?, check_in = ?, check_out = ?, subtotal = ?, numero_inapan = ?, 
            descuento = ?, iva = ?, ish = ?, total_pagar = ?, estado_pago = ?, total_pagado = ?, 
            saldo = ?, metodo_pago_primer = ?, metodo_pago_restante = ?, estado = ?, 
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([
            $huesped_id, $check_in, $check_out, $subtotal, $numero_inapam,
            $descuento, $iva, $ish, $total_pagar, $estado, $total_pagado,
            $saldo, $metodo_pago_primer, $metodo_pago_restante, $estado,
            $recibo_id
        ]);

        $stmt = $pdo->prepare("DELETE FROM detalles_reserva WHERE recibo_id = ?");
        $stmt->execute([$recibo_id]);

        $stmt = $pdo->prepare("INSERT INTO detalles_reserva (recibo_id, elemento_id, tarifa) VALUES (?, ?, ?)");
        foreach ($_POST['elementos'] as $elemento_id) {
            if (!is_numeric($elemento_id)) {
                throw new Exception("ID de elemento inválido");
            }
            $stmt->execute([$recibo_id, $elemento_id, $tarifa_por_noche]);
        }

        $pdo->commit();

        header('Location: recibos.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        manejarError($e->getMessage());
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        die("ID de recibo no válido.");
    }

    $recibo_id = $_GET['id'];

    // Obtener los datos del recibo
    $stmt = $pdo->prepare("SELECT * FROM recibos WHERE id = ?");
    $stmt->execute([$recibo_id]);
    $recibo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recibo) {
        die("No se encontró la reserva.");
    }

    // Obtener elementos de la reserva
    $stmt = $pdo->prepare("SELECT elemento_id FROM detalles_reserva WHERE recibo_id = ?");
    $stmt->execute([$recibo_id]);
    $elementos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Obtener anticipos
    $stmt = $pdo->prepare("SELECT * FROM anticipos WHERE recibo_id = ?");
    $stmt->execute([$recibo_id]);
    $anticipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Variables auxiliares
    $tipo_pago = count($anticipos) > 1 ? 'parcial' : 'completo';
    $metodo_pago_primer = $recibo['metodo_pago_primer'] ?? '';
    $tarifa = $recibo['subtotal'] ?? 0;
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Editar Reserva</title>
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

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--color-fondo);
    color: var(--color-text);
    margin: 0;
    padding: 0;
    display: flex;
}

.contenedor {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto;
    background: var(--color-background);
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 0.5rem 1rem var(--color-shadow);
}

.seccion {
    margin-bottom: 2rem;
    padding: 1.5rem;
    border-radius: 0.8rem;
    background: #ffffff;
    border: 1px solid var(--color-borde);
    position: relative;
}

.seccion::before {
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    position: absolute;
    top: 1.5rem;
    left: 1.5rem;
    font-size: 1.2em;
    color: var(--color-secundario);
}

.habitaciones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem 0;
}

.habitacion-item {
    padding: 1rem;
    border: 2px solid var(--color-borde);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.habitacion-item:hover {
    border-color: var(--color-secundario);
}

.campo-formulario {
    margin-bottom: 1.5rem;
}

input, select {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid var(--color-borde);
    border-radius: 0.5rem;
    margin-top: 0.5rem;
    transition: border-color 0.3s;
}

input:focus, select:focus {
    border-color: var(--color-secundario);
    outline: none;
}

button {
    background: var(--color-secundario);
    color: var(--color-letters);
    padding: 1rem 2rem;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    margin-top: 1rem;
    transition: background 0.3s;
}

button:hover {
    background: #2980b9;
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
    </style>
</head>
<body>
    <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
  <!-- Sidebar -->
  <aside class="sidebar">
    <h2><i class="fas fa-columns"></i> Menú</h2>
    <ul>
    <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <?php endif; ?>
        <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>

    <div class="overlay"></div>
    <div class="contenedor">
        <h1>Editar Reserva #<?= $recibo['id'] ?></h1>
        <form method="post" id="formEditar">
            <input type="hidden" name="recibo_id" value="<?= $recibo['id'] ?>">

            <!-- Sección Elementos -->
            <div class="seccion" id="seccion-elementos">
                <div class="habitaciones-grid" id="elementos-grid">
                    <?php
                    // Consulta SQL con filtro (por ejemplo, solo elementos disponibles)
                    $sql = "SELECT * FROM elementos WHERE estado = 'disponible'"; // Cambia 'activo' según tus necesidades
                    $stmt = $pdo->query($sql);
                    while ($elemento = $stmt->fetch()):
                    ?>
                    <div class="habitacion-item">
                        <label>
                            <input type="checkbox" name="elementos[]" value="<?= htmlspecialchars($elemento['id']) ?>"
                                <?= in_array($elemento['id'], $elementos) ? 'checked' : '' ?>>
                            <?php
                            // Determinar el icono según el tipo de elemento
                            $tipo = htmlspecialchars($elemento['tipo']);
                            if ($tipo === 'habitacion') {
                                echo '<i class="fas fa-bed"></i> ';
                            } elseif ($tipo === 'servicio') {
                                echo '<i class="fas fa-concierge-bell"></i> ';
                            } else {
                                echo '<i class="fas fa-question-circle"></i> ';
                            }
                            ?>
                            <?= htmlspecialchars($elemento['nombre']) ?>
                            <br>
                            <span class="descripcion"><?= htmlspecialchars($elemento['descripcion']) ?></span>
                        </label>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Sección Huésped -->
            <div class="seccion" id="seccion-huesped">
                <i class="fas fa-user section-icon"></i>
                <select id="huesped_id" name="huesped_id">
                    <option value="">Nuevo huésped</option>
                    <?php 
                    $stmt = $pdo->query("SELECT * FROM huespedes");
                    while ($h = $stmt->fetch()): ?>
                    <option value="<?= $h['id'] ?>" <?= ($h['id'] == $recibo['id_huesped']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($h['nombre']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                
                <div id="nuevo-huesped" style="display: <?= empty($recibo['id_huesped']) ? 'block' : 'none' ?>;">
                    <input type="text" name="nuevo_huesped_nombre" id="nuevo_huesped_nombre" placeholder="Nombre*" 
                        value="<?= htmlspecialchars($recibo['nombre_huesped'] ?? '') ?>">
                    <input type="text" name="nuevo_huesped_rfc" placeholder="RFC" 
                        value="<?= htmlspecialchars($recibo['rfc_huesped'] ?? '') ?>">
                    <input type="tel" name="nuevo_huesped_telefono" placeholder="Teléfono" 
                        value="<?= htmlspecialchars($recibo['telefono_huesped'] ?? '') ?>">
                    <input type="email" name="nuevo_huesped_correo" placeholder="Correo" 
                        value="<?= htmlspecialchars($recibo['correo_huesped'] ?? '') ?>">
                </div>
            </div>

            <!-- Sección Fechas -->
            <div class="seccion" id="seccion-fechas">
                <i class="fas fa-calendar-alt section-icon"></i>
                <div class="campo-formulario">
                    <label>Check-in:</label>
                    <input type="date" name="check_in" required 
                        value="<?= htmlspecialchars($recibo['check_in'] ?? '') ?>">
                </div>
                <div class="campo-formulario">
                    <label>Check-out:</label>
                    <input type="date" name="check_out" required
                        value="<?= htmlspecialchars($recibo['check_out'] ?? '') ?>">
                </div>
            </div>
            <!-- Sección Tarifa por Noche -->
            <i class="fas fa-couch section-icon"></i>
            <div class="campo-formulario">
                <label>Tarifa por noche:</label>
                <input type="number" id="tarifa_por_noche" name="tarifa_por_noche" 
                    step="0.01" min="0" required placeholder="Ingrese tarifa"
                    value="<?= htmlspecialchars($tarifa ?? '') ?>">
            </div>

            <!-- Sección Impuestos -->
            <div class="seccion" id="seccion-impuestos">
                <i class="fas fa-percent section-icon"></i>
                <div class="campo-formulario">
                    <label>IVA (%):</label>
                    <input type="number" id="iva" name="iva" step="0.01" min="0" max="100" 
                        value="<?= htmlspecialchars($recibo['iva'] ?? 16) ?>" required>
                </div>
                <div class="campo-formulario">
                    <label>ISH (%):</label>
                    <input type="number" id="ish" name="ish" step="0.01" min="0" max="100"
                        value="<?= htmlspecialchars($recibo['ish'] ?? 3) ?>" required>
                </div>
            </div>

            <!-- Sección Descuentos -->
            <div class="seccion" id="seccion-descuentos">
                <i class="fas fa-tags section-icon"></i>
                <label>
                    <input type="checkbox" id="aplicar_descuento_inapam" name="aplicar_descuento_inapam"
                        <?= !empty($recibo['descuento']) ? 'checked' : '' ?>> Aplicar descuento INAPAM
                </label>
                <div id="campos-descuento" style="display: <?= !empty($recibo['descuento']) ? 'block' : 'none' ?>;">
                    <select id="tipo_descuento_inapam" name="tipo_descuento_inapam">
                        <option value="porcentaje" <?= ($recibo['tipo_descuento'] ?? 'porcentaje') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                        <option value="monto" <?= ($recibo['tipo_descuento'] ?? 'porcentaje') === 'monto' ? 'selected' : '' ?>>Monto fijo</option>
                    </select>
                    <input type="text" id="valor_descuento_inapam" name="valor_descuento_inapam" 
                        step="0.01" min="0" placeholder="Valor del descuento"
                        value="<?= htmlspecialchars($recibo['valor_descuento'] ?? '') ?>">
                </div>
            </div>

            <!-- Sección Pagos -->
            <div class="seccion" id="seccion-pagos">
    <i class="fas fa-money-check section-icon"></i>
    <select id="tipo_pago" name="tipo_pago">
        <option value="completo" <?= $tipo_pago === 'completo' ? 'selected' : '' ?>>Completo</option>
        <option value="parcial" <?= $tipo_pago === 'parcial' ? 'selected' : '' ?>>Parcial</option>
    </select>

    <!-- Pago Completo -->
    <div id="pago-completo" style="display: <?= $tipo_pago === 'completo' ? 'block' : 'none' ?>;">
        <div class="campo-formulario">
            <label>Método de Pago:</label>
            <select name="metodo_pago_completo" class="metodo-pago">
                <?php $metodoActual = $recibo['metodo_pago_primer'] ?? ''; ?>
                <option value="efectivo" <?= $metodoActual === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                <option value="tarjeta_debito" <?= $metodoActual === 'tarjeta_debito' ? 'selected' : '' ?>>Tarjeta Débito</option>
                <option value="tarjeta_credito" <?= $metodoActual === 'tarjeta_credito' ? 'selected' : '' ?>>Tarjeta Crédito</option>
                <option value="transferencia" <?= $metodoActual === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                <option value="otro" <?= !in_array($metodoActual, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? 'selected' : '' ?>>Otro</option>
            </select>
            <div class="otro-metodo-container" style="display: <?= !in_array($metodoActual, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? 'block' : 'none' ?>;">
                <input type="text" name="detalle_metodo_completo"
                    value="<?= htmlspecialchars(!in_array($metodoActual, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? $metodoActual : '') ?>">
            </div>
        </div>
        <div class="campo-formulario">
            <label>Monto Total:</label>
            <input type="number" name="monto_pago_completo" step="0.01" value="<?= htmlspecialchars($recibo['total_pagado'] ?? '0.00') ?>">

        </div>
    </div>

    <!-- Pagos Parciales -->
    <div id="pago-parcial" style="display: <?= $tipo_pago === 'parcial' ? 'block' : 'none' ?>;">
        <button type="button" id="agregar-pago">+ Añadir Pago</button>
        <?php foreach ($anticipos as $index => $pago): ?>
        <div class="pago-item">
            <?php $metodoPago = $pago['metodo_pago']; ?>
            <select name="metodo_pago_parcial[]" class="metodo-pago">
                <option value="efectivo" <?= $metodoPago === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                <option value="tarjeta_debito" <?= $metodoPago === 'tarjeta_debito' ? 'selected' : '' ?>>Tarjeta Débito</option>
                <option value="tarjeta_credito" <?= $metodoPago === 'tarjeta_credito' ? 'selected' : '' ?>>Tarjeta Crédito</option>
                <option value="transferencia" <?= $metodoPago === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                <option value="otro" <?= !in_array($metodoPago, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? 'selected' : '' ?>>Otro</option>
            </select>
            <div class="otro-metodo-container" style="display: <?= !in_array($metodoPago, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? 'block' : 'none' ?>;">
                <input type="text" name="detalle_metodo_parcial[]"
                    value="<?= htmlspecialchars(!in_array($metodoPago, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia']) ? $metodoPago : '') ?>">
            </div>
            <input type="number" name="primer_pago[]" step="0.01"
                value="<?= htmlspecialchars($pago['monto']) ?>" required>
        </div>
        <?php endforeach; ?>
    </div>
</div>


            <!-- Sección Wifi -->
            <div class="seccion" id="seccion-wifi">
                <i class="fas fa-wifi section-icon"></i>
                <label>Wifi:</label>
                <input type="text" name="Nombre_wifi" placeholder="Nombre WIFI" 
                    value="<?= htmlspecialchars($recibo['Nombre_wifi'] ?? '') ?>">
                <input type="text" name="contrasena" placeholder="Contraseña Wifi" 
                    value="<?= htmlspecialchars($recibo['contrasena'] ?? '') ?>">
            </div>

            <!-- Totales -->
            <div class="seccion" id="seccion-totales">
                <i class="fas fa-calculator section-icon"></i>
                <div>Subtotal: $<span id="subtotal">0.00</span></div>
                <div>Descuento: $<span id="descuento">0.00</span></div>
                <div>Impuestos: $<span id="impuestos">0.00</span></div>
                <div>Total: $<span id="total">0.00</span></div>
                <div>Pagado: $<span id="pagado">0.00</span></div>
                <div>Saldo: $<span id="saldo">0.00</span></div>
                <div id="cambio" style="display:none;">
                    Cambio: $<span id="cambio-monto" name= 'saldo'>0.00</span>
                </div>
            </div>

            <button type="submit">Guardar Cambios</button>
        </form>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formEditar');
    const tipoPago = document.getElementById('tipo_pago');
    const huespedSelect = document.getElementById('huesped_id');
    const nuevoHuespedDiv = document.getElementById('nuevo-huesped');
    const nombreInput = document.getElementById('nuevo_huesped_nombre');
    const montoCompletoInput = document.querySelector('[name="monto_pago_completo"]');
    const pagoCompleto = document.getElementById('pago-completo');
    const pagoParcial = document.getElementById('pago-parcial');

    // Manejo de nuevo huésped
    function actualizarCamposHuesped() {
        if (huespedSelect.value === '') {
            nuevoHuespedDiv.style.display = 'block';
            nombreInput.setAttribute('required', 'required');
        } else {
            nuevoHuespedDiv.style.display = 'none';
            nombreInput.removeAttribute('required');
        }
    }
    huespedSelect.addEventListener('change', actualizarCamposHuesped);
    actualizarCamposHuesped();

    // Mostrar campos descuento INAPAM
    const aplicarDescuento = document.getElementById('aplicar_descuento_inapam');
    if (aplicarDescuento) {
        aplicarDescuento.addEventListener('change', function () {
            document.getElementById('campos-descuento').style.display = this.checked ? 'block' : 'none';
        });
    }

    // Mostrar/ocultar secciones de pago
    function actualizarSeccionPagos() {
        if (tipoPago.value === 'completo') {
            pagoCompleto.style.display = 'block';
            pagoParcial.style.display = 'none';
            montoCompletoInput.setAttribute('required', 'required');
        } else {
            pagoCompleto.style.display = 'none';
            pagoParcial.style.display = 'block';
            montoCompletoInput.removeAttribute('required');
        }
    }
    tipoPago.addEventListener('change', actualizarSeccionPagos);
    actualizarSeccionPagos();

    // Añadir pagos parciales
    document.getElementById('agregar-pago').addEventListener('click', function() {
        const div = document.createElement('div');
        div.className = 'pago-item';
        div.innerHTML = `
            <select name="metodo_pago_parcial[]" class="metodo-pago">
                <option value="efectivo">Efectivo</option>
                <option value="tarjeta_debito">Tarjeta Débito</option>
                <option value="tarjeta_credito">Tarjeta Crédito</option>
                <option value="transferencia">Transferencia</option>
                <option value="otro">Otro</option>
            </select>
            <div class="otro-metodo-container" style="display: none;">
                <input type="text" name="detalle_metodo_parcial[]" placeholder="Especificar método">
            </div>
            <input type="number" name="primer_pago[]" step="0.01" placeholder="Monto">
        `;
        pagoParcial.appendChild(div);

        // Manejar método "otro"
        const select = div.querySelector('.metodo-pago');
        select.addEventListener('change', function () {
            const contenedor = div.querySelector('.otro-metodo-container');
            contenedor.style.display = this.value === 'otro' ? 'block' : 'none';
        });
    });

    // Método de pago "otro"
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('metodo-pago')) {
            const container = e.target.closest('.pago-item')?.querySelector('.otro-metodo-container');
            if (container) {
                container.style.display = e.target.value === 'otro' ? 'block' : 'none';
            }
        }
    });

    // Cálculo de totales
    function calcularTotales() {
        const tarifa = parseFloat(document.getElementById('tarifa_por_noche').value) || 0;
        const checkIn = new Date(form.check_in.value);
        const checkOut = new Date(form.check_out.value);
        const dias = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24)) || 0;
        const subtotal = tarifa * dias;

        let descuento = 0;
        if (aplicarDescuento.checked) {
            const tipo = document.getElementById('tipo_descuento_inapam').value;
            const valor = parseFloat(document.getElementById('valor_descuento_inapam').value) || 0;
            descuento = tipo === 'porcentaje' ? subtotal * valor / 100 : valor;
        }

        const iva = parseFloat(document.getElementById('iva').value) || 0;
        const ish = parseFloat(document.getElementById('ish').value) || 0;
        const impuestos = (subtotal - descuento) * (iva + ish) / 100;
        const total = subtotal - descuento + impuestos;

        let pagado = 0;
        if (tipoPago.value === 'completo') {
            pagado = parseFloat(montoCompletoInput.value) || 0;
        } else {
            document.querySelectorAll('#pago-parcial input[name="primer_pago[]"]').forEach(input => {
                pagado += parseFloat(input.value) || 0;
            });
        }

        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('descuento').textContent = descuento.toFixed(2);
        document.getElementById('impuestos').textContent = impuestos.toFixed(2);
        document.getElementById('total').textContent = total.toFixed(2);
        document.getElementById('pagado').textContent = pagado.toFixed(2);
        document.getElementById('saldo').textContent = Math.max(total - pagado, 0).toFixed(2);

        const cambio = pagado - total;
        const cambioEl = document.getElementById('cambio');
        document.getElementById('cambio-monto').textContent = cambio.toFixed(2);
        cambioEl.style.display = cambio > 0 ? 'block' : 'none';
    }

    // Eventos para actualizar totales
    const inputs = [
        'check_in', 'check_out', 'iva', 'ish', 'tarifa_por_noche',
        'tipo_descuento_inapam', 'valor_descuento_inapam'
    ];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calcularTotales);
            el.addEventListener('change', calcularTotales);
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.matches('[name="primer_pago[]"], [name="monto_pago_completo"]')) {
            calcularTotales();
        }
    });

    calcularTotales();

    // Sidebar para móviles
    const toggleButton = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    toggleButton.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
});
</script>

</body>
</html>