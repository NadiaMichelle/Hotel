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
        // Validar campos requeridos
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

        // Validar porcentajes
        $iva = (float)$_POST['iva'];
        $ish = (float)$_POST['ish'];
        if ($iva < 0 || $iva > 100 || $ish < 0 || $ish > 100) {
            throw new Exception("Los porcentajes deben estar entre 0 y 100");
        }

        // Validar tarifa
        $tarifa_por_noche = (float)$_POST['tarifa_por_noche'];
        if ($tarifa_por_noche <= 0) {
            throw new Exception("La tarifa por noche debe ser un valor positivo");
        }

        // Validar fechas
        $check_in = $_POST['check_in'];
        $check_out = $_POST['check_out'];
        $hoy = new DateTime('today');
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        
        if ($check_in_date < $hoy) {
            throw new Exception("No se pueden reservar fechas pasadas");
        }
        
        if ($check_out_date <= $check_in_date) {
            throw new Exception("Check-out debe ser posterior a check-in");
        }

        // Calcular días de estadía
        $dias = $check_out_date->diff($check_in_date)->days;
        $subtotal = $tarifa_por_noche * $dias;

        // Iniciar transacción
        $pdo->beginTransaction();

        try {
            // Manejar huésped
            $huesped_id = $_POST['huesped_id'] ?? null;
            if (empty($huesped_id)) {
                if (empty($_POST['nuevo_huesped_nombre'])) {
                    throw new Exception("El nombre del huésped es obligatorio");
                }
                
                $stmt = $pdo->prepare("INSERT INTO huespedes 
                    (nombre, rfc, telefono, correo) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nuevo_huesped_nombre'],
                    $_POST['nuevo_huesped_rfc'] ?? null,
                    $_POST['nuevo_huesped_telefono'] ?? null,
                    $_POST['nuevo_huesped_correo'] ?? null
                ]);
                $huesped_id = $pdo->lastInsertId();
            }

            // Insertar datos de WiFi
            $stmt = $pdo->prepare("INSERT INTO internet 
                (Nombre_wifi, contrasena) 
                VALUES (?, ?)");
            $stmt->execute([
                $_POST['Nombre_wifi'],
                $_POST['contrasena']
            ]);

            // Calcular descuento INAPAM
            $descuento = 0;
            $numero_inapam = null;
            if (isset($_POST['aplicar_descuento_inapam'])) {
                $valor = (float)$_POST['valor_descuento_inapam'];
                $descuento = ($_POST['tipo_descuento_inapam'] === 'porcentaje') 
                    ? $subtotal * ($valor / 100)
                    : $valor;
                $numero_inapam = $_POST['numero_inapan'] ?? null;
            }

            // Calcular total
            $total_pagar = ($subtotal - $descuento) * (1 + ($iva + $ish) / 100);

            // Actualizar recibo
            $recibo_id = $_POST['recibo_id'];
            $stmt = $pdo->prepare("UPDATE recibos SET 
                id_huesped = ?,
                check_in = ?,
                check_out = ?,
                subtotal = ?,
                descuento = ?,
                iva = ?,
                ish = ?,
                total_pagar = ?,
                estado_pago = ?,
                total_pagado = ?,
                saldo = ?,
                metodo_pago_primer = ?,
                metodo_pago_restante = ?,
                numero_inapan = ?
                WHERE id = ?");
            
            $stmt->execute([
                $huesped_id,
                $check_in,
                $check_out,
                $subtotal,
                $descuento,
                $iva,
                $ish,
                $total_pagar,
                'pendiente',
                0,
                $total_pagar,
                'Pendiente',
                'Pendiente',
                $numero_inapam,
                $recibo_id
            ]);

            // Manejar pagos
            $total_pagado = 0;
            if ($_POST['tipo_pago'] === 'completo') {
                $metodo = $_POST['metodo_pago_completo'];
                $detalle_metodo = $metodo;
                
                if ($metodo === 'otro') {
                    $detalle_metodo = $_POST['detalle_metodo_completo'] ?? 'Otro método';
                }
                
                $monto = (float)$_POST['monto_pago_completo'];
                $total_pagado = $monto;
                
                // Insertar anticipo
                $stmt = $pdo->prepare("INSERT INTO anticipos 
                    (recibo_id, monto, metodo_pago, fecha) 
                    VALUES (?, ?, ?, NOW())");
                $stmt->execute([$recibo_id, $monto, $detalle_metodo]);
                
            } else {
                foreach ($_POST['primer_pago'] as $key => $monto) {
                    $metodo = $_POST['metodo_pago_parcial'][$key];
                    $detalle_metodo = $metodo;
                    
                    if ($metodo === 'otro') {
                        $detalle_metodo = $_POST['detalle_metodo_parcial'][$key] ?? 'Otro método';
                    }
                    
                    $monto = (float)$monto;
                    $total_pagado += $monto;
                    
                    $stmt = $pdo->prepare("INSERT INTO anticipos 
                        (recibo_id, monto, metodo_pago, fecha) 
                        VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$recibo_id, $monto, $detalle_metodo]);
                }
            }

            // Actualizar estado de pago
            $saldo = $total_pagar - $total_pagado;
            $estado = $saldo <= 0 ? 'pagado' : 'pendiente';
            
            $stmt = $pdo->prepare("UPDATE recibos SET 
                total_pagado = ?,
                saldo = ?,
                estado_pago = ?,
                metodo_pago_restante = ?
                WHERE id = ?");
            $stmt->execute([$total_pagado, $saldo, $estado, $_POST['metodo_pago_parcial'][0], $recibo_id]);

            // Insertar elementos reservados
            $stmt = $pdo->prepare("DELETE FROM detalles_reserva WHERE recibo_id = ?");
            $stmt->execute([$recibo_id]);

            $stmt = $pdo->prepare("INSERT INTO detalles_reserva 
                (recibo_id, elemento_id, tarifa) 
                VALUES (?, ?, ?)");
                
            foreach ($_POST['elementos'] as $elemento_id) {
                if (!is_numeric($elemento_id)) {
                    throw new Exception("ID de elemento inválido");
                }
                $stmt->execute([$recibo_id, $elemento_id, $tarifa_por_noche]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Reserva actualizada exitosamente',
                'recibo_id' => $recibo_id,
                'redirect' => 'recibos.php' // URL de redirección
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        manejarError($e->getMessage());
    }
    exit;
}

// Obtener datos del recibo
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM recibos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $recibo = $stmt->fetch();

    // Determinar tipo de pago dinámicamente
    $recibo['tipo_pago'] = ($recibo['estado_pago'] === 'pagado' || $recibo['total_pagado'] >= $recibo['total_pagar'])
                          ? 'completo' 
                          : 'parcial';

    // Obtener elementos reservados
    $stmt = $pdo->prepare("SELECT elemento_id FROM detalles_reserva WHERE recibo_id = ?");
    $stmt->execute([$_GET['id']]);
    $elementos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // En el bloque GET, al recuperar la tarifa:
    $stmt = $pdo->prepare("SELECT tarifa FROM detalles_reserva WHERE recibo_id = ? LIMIT 1");
    $stmt->execute([$_GET['id']]);
    $tarifa = $stmt->fetchColumn();

    // Obtener anticipos
    $stmt = $pdo->prepare("SELECT * FROM anticipos WHERE recibo_id = ?");
    $stmt->execute([$_GET['id']]);
    $anticipos = $stmt->fetchAll();
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

    <aside class="sidebar">
        <h2><i class="fas fa-columns"></i> Menú</h2>
        <ul>
            <?php if ($rol === 'admin'): ?>
                <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
                <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <?php endif; ?>
            <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
            <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
            <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
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
                    <option value="completo" <?= $recibo['tipo_pago'] === 'completo' ? 'selected' : '' ?>>Completo</option>
                    <option value="parcial" <?= $recibo['tipo_pago'] === 'parcial' ? 'selected' : '' ?>>Parcial</option>
                </select>

                <!-- Pago Completo -->
                <div id="pago-completo" style="display: <?= $recibo['tipo_pago'] === 'completo' ? 'block' : 'none' ?>;">
                    <div class="campo-formulario">
                        <label>Método de Pago:</label>
                        <select name="metodo_pago_completo">
                            <?php $metodoActual = $recibo['metodo_pago_primer']; ?>
                            <option value="efectivo" <?= $metodoActual === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                            <option value="tarjeta_debito" <?= $metodoActual === 'tarjeta_debito' ? 'selected' : '' ?>>Tarjeta Débito</option>
                            <option value="tarjeta_credito" <?= $metodoActual === 'tarjeta_credito' ? 'selected' : '' ?>>Tarjeta Crédito</option>
                            <option value="transferencia" <?= $metodoActual === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                            <option value="otro" <?= !in_array($metodoActual, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? 'selected' : '' ?>>Otro</option>
                        </select>
                        <div class="otro-metodo-container" style="display: <?= !in_array($metodoActual, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? 'block' : 'none' ?>;">
                            <input type="text" name="detalle_metodo_completo" 
                                value="<?= htmlspecialchars(!in_array($metodoActual, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? $metodoActual : '') ?>">
                        </div>
                    </div>
                    <div class="campo-formulario">
                        <label>Monto Total:</label>
                        <input type="number" name="monto_pago_completo" step="0.01"
                            value="<?= htmlspecialchars($recibo['total_pagado']) ?>" required>
                    </div>
                </div>

                <!-- Pagos Parciales -->
                <div id="pago-parcial" style="display: <?= $recibo['tipo_pago'] === 'parcial' ? 'block' : 'none' ?>;">
                    <button type="button" id="agregar-pago">+ Añadir Pago</button>
                    <?php foreach ($anticipos as $index => $pago): ?>
                    <div class="pago-item">
                        <select name="metodo_pago_parcial[]" class="metodo-pago">
                            <?php $metodoPago = $pago['metodo_pago']; ?>
                            <option value="efectivo" <?= $metodoPago === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                            <option value="tarjeta_debito" <?= $metodoPago === 'tarjeta_debito' ? 'selected' : '' ?>>Tarjeta Débito</option>
                            <option value="tarjeta_credito" <?= $metodoPago === 'tarjeta_credito' ? 'selected' : '' ?>>Tarjeta Crédito</option>
                            <option value="transferencia" <?= $metodoPago === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                            <option value="otro" <?= !in_array($metodoPago, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? 'selected' : '' ?>>Otro</option>
                        </select>
                        <div class="otro-metodo-container" style="display: <?= !in_array($metodoPago, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? 'block' : 'none' ?>;">
                            <input type="text" name="detalle_metodo_parcial[]" 
                                value="<?= htmlspecialchars(!in_array($metodoPago, ['efectivo','tarjeta_debito','tarjeta_credito','transferencia']) ? $metodoPago : '') ?>">
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

        // Manejar nuevo huésped
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

        // Manejar descuento INAPAM
        const aplicarDescuentoInapam = document.getElementById('aplicar_descuento_inapam');
        if (aplicarDescuentoInapam) {
            aplicarDescuentoInapam.addEventListener('change', function() {
                const camposDescuento = document.getElementById('campos-descuento');
                if (this.checked) {
                    camposDescuento.style.display = 'block';
                } else {
                    camposDescuento.style.display = 'none';
                }
            });
        }

        // Manejar tipo de pago
        function actualizarSeccionPagos() {
            const pagoCompleto = document.getElementById('pago-completo');
            const pagoParcial = document.getElementById('pago-parcial');
            
            if (tipoPago.value === 'completo') {
                pagoCompleto.style.display = 'block';
                pagoParcial.style.display = 'none';
            } else {
                pagoCompleto.style.display = 'none';
                pagoParcial.style.display = 'block';
            }
        }
        tipoPago.addEventListener('change', actualizarSeccionPagos);
        actualizarSeccionPagos();

        // Agregar pagos parciales
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
            document.getElementById('pago-parcial').appendChild(div);

            // Evento para método "Otro"
            div.querySelector('.metodo-pago').addEventListener('change', function() {
                const container = this.parentElement.querySelector('.otro-metodo-container');
                container.style.display = this.value === 'otro' ? 'block' : 'none';
            });
        });

        // Calcular totales
        function calcularTotales() {
            // Obtener valores principales
            const tarifa = parseFloat(document.getElementById('tarifa_por_noche').value) || 0;
            const checkIn = new Date(form.check_in.value);
            const checkOut = new Date(form.check_out.value);
            
            // Calcular días
            const diferenciaTiempo = checkOut.getTime() - checkIn.getTime();
            const dias = Math.ceil(diferenciaTiempo / (1000 * 3600 * 24)) || 0;
            
            // Calcular subtotal
            const subtotal = tarifa * dias;

            // Calcular descuento
            let descuento = 0;
            if (aplicarDescuentoInapam.checked) {
                const tipoDescuento = document.getElementById('tipo_descuento_inapam').value;
                const valorDescuento = parseFloat(document.getElementById('valor_descuento_inapam').value) || 0;
                
                descuento = tipoDescuento === 'porcentaje' 
                    ? subtotal * (valorDescuento / 100)
                    : valorDescuento;
            }

            // Calcular impuestos
            const iva = parseFloat(document.getElementById('iva').value) || 0;
            const ish = parseFloat(document.getElementById('ish').value) || 0;
            const baseImponible = subtotal - descuento;
            const impuestos = baseImponible * (iva + ish) / 100;
            
            // Calcular total
            const total = baseImponible + impuestos;

            // Calcular pagos
            let pagos = 0;
            if (tipoPago.value === 'completo') {
                pagos = parseFloat(document.querySelector('#pago-completo input[type="number"]').value) || 0;
            } else {
                pagos = Array.from(document.querySelectorAll('#pago-parcial input[type="number"]'))
                    .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
            }

            // Actualizar UI
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('descuento').textContent = descuento.toFixed(2);
            document.getElementById('impuestos').textContent = impuestos.toFixed(2);
            document.getElementById('total').textContent = total.toFixed(2);
            document.getElementById('pagado').textContent = pagos.toFixed(2);
            
            const saldo = total - pagos;
            document.getElementById('saldo').textContent = Math.max(saldo, 0).toFixed(2);

            // Calcular cambio
            const usandoEfectivo = tipoPago.value === 'completo' 
                ? document.querySelector('#pago-completo select').value === 'efectivo'
                : Array.from(document.querySelectorAll('#pago-parcial select'))
                    .some(select => select.value === 'efectivo');

            if (usandoEfectivo && pagos > total) {
                document.getElementById('cambio').style.display = 'block';
                document.getElementById('cambio-monto').textContent = (pagos - total).toFixed(2);
            } else {
                document.getElementById('cambio').style.display = 'none';
            }
        }

        // Eventos de actualización
        const elementosCalculo = [
            'check_in', 'check_out', 'iva', 'ish', 'tarifa_por_noche',
            'tipo_descuento_inapam', 'valor_descuento_inapam'
        ];

        elementosCalculo.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                elemento.addEventListener('input', calcularTotales);
                elemento.addEventListener('change', calcularTotales);
            }
        });

        document.getElementById('aplicar_descuento_inapam').addEventListener('change', calcularTotales);
        document.addEventListener('input', function(e) {
            if (e.target.matches('#pago-completo input, #pago-parcial input')) {
                calcularTotales();
            }
        });

        // Inicializar cálculos
        calcularTotales();

        // Toggle del sidebar en móvil
        const toggleButton = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');

        toggleButton.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    });
</script>
</body>
</html>