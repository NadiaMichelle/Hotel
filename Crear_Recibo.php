<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
// Cargar IVA e ISH desde la base de datos para mostrar en el formulario
try {
    $stmt = $pdo->query("SELECT iva, ish, wifi_nombre, wifi_contrasena FROM configuracion_general LIMIT 1");
    $config = $stmt->fetch();
    $iva_config = $config['iva'] ?? 16.00;
    $ish_config = $config['ish'] ?? 3.00;
    $wifi_nombre = $config['wifi_nombre'] ?? '';
$wifi_password = $config['wifi_password'] ?? '';
} catch (Exception $e) {
    $iva_config = 16.00;
    $ish_config = 3.00;
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

            // Insertar recibo
            $stmt = $pdo->prepare("INSERT INTO recibos (
                id_huesped, check_in, check_out, subtotal, descuento, 
                iva, ish, total_pagar, estado_pago, total_pagado, saldo,
                metodo_pago_primer, metodo_pago_restante, numero_inapan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
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
                $numero_inapam
            ]);
            $recibo_id = $pdo->lastInsertId();

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
                estado_pago = ?
                WHERE id = ?");
            $stmt->execute([$total_pagado, $saldo, $estado, $recibo_id]);

            // Insertar elementos reservados
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
                'message' => 'Reserva creada exitosamente',
                'recibo_id' => $recibo_id,
                'redirect' => 'Puede salir de esta pagina' // Agregar URL de redirección
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Caja</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
    --primary: #2c3e50;
    --secondary:rgb(31, 89, 128);
    --background: #f9fafb;
    --card-bg: #ffffff;
    --border: #e0e0e0;
    --text: #333333;
    --accent: #e74c3c;
    --shadow: rgba(0, 0, 0, 0.08);
    --white: #ffffff;
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: var(--background);
    color: var(--text);
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 2rem;
    padding-left: 270px;
}

h1 {
    text-align: center;
    color: var(--primary);
    margin-bottom: 2rem;
}

form {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 8px 24px var(--shadow);
    transition: all 0.3s ease;
}

.seccion {
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 1.5rem;
}

.seccion h2 {
    font-size: 1.25rem;
    color: var(--secondary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.seccion h2 i {
    margin-right: 0.5rem;
}

.section-icon {
    color: var(--secondary);
    font-size: 1.3rem;
    margin-bottom: 0.9rem;
    display: inline-block;
}

label,
select,
input[type="text"],
input[type="email"],
input[type="number"],
input[type="date"],
input[type="tel"] {
    display: block;
    width: 98%;
    margin-top: 0.25rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    font-size: 1rem;
    background-color: #fff;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

input:focus,
select:focus {
    border-color: var(--secondary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

input[readonly] {
    background-color: #f0f0f0;
}

#elementos-grid {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

/* Oculta scrollbar horizontal en caso de overflow */
#elementos-grid::-webkit-scrollbar {
    width: 8px;
}
#elementos-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
#elementos-grid::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
}

.habitacion-item {
    border: 2px solid #ddd;
    border-radius: 1rem;
    padding: 1rem;
    text-align: center;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.3s ease;
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Efecto visual cuando el checkbox está marcado */
.habitacion-item input[type="checkbox"]:checked + i,
.habitacion-item input[type="checkbox"]:checked ~ span,
.habitacion-item input[type="checkbox"]:checked ~ br,
.habitacion-item input[type="checkbox"]:checked ~ * {
    color: var(--secondary);
}

/* Ocultar el checkbox y usar solo el efecto visual */
.habitacion-item input[type="checkbox"] {
    display: none;
}

/* Mostrar ícono grande y centrado */
.habitacion-item i {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
    color: #888;
    transition: color 0.3s;
}

/* Nombre y descripción */
.habitacion-item span.descripcion {
    display: block;
    font-size: 0.9rem;
    color: #666;
}

/* Mostrar como cuadrícula */
.habitaciones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}


button[type="submit"],
button[type="button"] {
    background-color: var(--secondary);
    color: #fff;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    box-shadow: 0 4px 14px var(--shadow);
}

button:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background-color: var(--primary);
    color: var(--white);
    padding: 20px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: left 0.3s ease;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 30px;
}

.sidebar ul {
    list-style: none;
    padding: 0;
}

.sidebar ul li {
    margin: 20px 0;
}

.sidebar ul li a {
    color: var(--white);
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 1.1rem;
    padding: 10px;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.sidebar ul li a i {
    margin-right: 10px;
}

.sidebar ul li a:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.toggle-sidebar {
    display: none;
    position: fixed;
    top: 10px;
    left: 15px;
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 10px;
    border-radius: 4px;
    z-index: 1100;
}

.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

#seccion-totales div {
    font-size: 1.05rem;
    padding: 0.25rem 0;
}

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
    }

    .sidebar {
        left: -250px;
    }

    .sidebar.active {
        left: 0;
    }

    .toggle-sidebar {
        display: block;
    }

    .overlay.active {
        display: block;
    }
}
.busqueda-elementos input {
    width: 95%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.busqueda-elementos input:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}
h2 i {
    margin-right: 0.5rem;
    color: var(--secondary);
}


</style>
</head>
<body>
<button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
 <aside class="sidebar">
    <h2><i class="fas fa-columns"></i> Menú</h2>
    <ul>
    <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <?php endif; ?>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
        <li><a href="configuracion.php"><i class="fas fa-cogs"></i> Configuración</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>
<div class="overlay"></div>
<div class="container">
    <h1><i class="fas fa-archive"></i> Sistema de Registros</h1>
    <form id="reservaForm" method="post" novalidate>
        <!-- Sección Elementos -->
        <h2><i class="fas fa-bed"></i> Habitaciones</h2>
        <div class="seccion" id="seccion-elementos">
        <div class="busqueda-elementos" style="margin-bottom: 1rem;">
    <input type="text" id="buscadorElementos" placeholder="Buscar habitación o servicio..." />
</div>

            <div class="habitaciones-grid" id="elementos-grid">
                <?php 
                // Consulta SQL con filtro (por ejemplo, solo elementos disponibles)
                $sql = "SELECT * FROM elementos WHERE estado = 'disponible'"; // Cambia 'activo' según tus necesidades
                $stmt = $pdo->query($sql);
                while ($elemento = $stmt->fetch()):
                ?>
              <div class="habitacion-item">
                    <label>
                        <input type="checkbox" name="elementos[]" value="<?= htmlspecialchars($elemento['id']) ?>">
                        <?php
                        $tipo = htmlspecialchars($elemento['tipo']);
                        if ($tipo === 'habitacion') {
                            echo '<i class="fas fa-bed"></i>';
                        } elseif ($tipo === 'servicio') {
                            echo '<i class="fas fa-concierge-bell"></i>';
                        } else {
                            echo '<i class="fas fa-question-circle"></i>';
                        }
                        ?>
                        <span><?= htmlspecialchars($elemento['nombre']) ?></span><br>
                        <span class="descripcion"><?= htmlspecialchars($elemento['descripcion']) ?></span>
                        <span><?= htmlspecialchars($elemento['precio']) ?></span><br>

                    </label>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Sección Huésped -->
        <div class="seccion" id="seccion-huesped">
            <i class="fas fa-user section-icon"> Huesped </i>
            <select id="huesped_id" name="huesped_id">
                <option value="">Nuevo huésped</option>
                <?php 
                $stmt = $pdo->query("SELECT * FROM huespedes");
                while ($h = $stmt->fetch()): ?>
                <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <div id="nuevo-huesped" style="display:none;">
                <input type="text" name="nuevo_huesped_nombre" id="nuevo_huesped_nombre" placeholder="Nombre*">
                <input type="text" name="nuevo_huesped_rfc" placeholder="RFC">
                <input type="tel" name="nuevo_huesped_telefono" placeholder="Teléfono">
                <input type="email" name="nuevo_huesped_correo" placeholder="Correo">
            </div>
        </div>

        <!-- Sección Fechas -->
        <div class="seccion" id="seccion-fechas">
            <i class="fas fa-calendar-alt section-icon"> Fechas </i>
            <div class="campo-formulario">
                <label>Check-in:</label>
                <input type="date" name="check_in" required>
            </div>
            <div class="campo-formulario">
                <label>Check-out:</label>
                <input type="date" name="check_out" required>
            </div>
        </div>
        <i class="fas fa-couch section-icon"> TARIFA </i>
            <div class="campo-formulario">
                <label>Tarifa por noche:</label>
                <input type="number" id="tarifa_por_noche" name="tarifa_por_noche" 
                    step="0.01" min="0" required placeholder="Ingrese tarifa">
            </div>


        <?php if ($rol === 'admin'): ?>
<div class="seccion" id="seccion-impuestos">
    <i class="fas fa-percent section-icon">  Impuestos </i>
    <div class="campo-formulario">
        <label>IVA (%):</label>
        <input type="number" id="iva" name="iva" step="0.01" min="0" max="100" value="<?= $iva_config ?>" required>
    </div>
    <div class="campo-formulario">
        <label>ISH (%):</label>
        <input type="number" id="ish" name="ish" step="0.01" min="0" max="100" value="<?= $ish_config ?>" required>
    </div>
</div>
<?php else: ?>
<!-- Usuario no admin: usa campos ocultos con valores por defecto -->
<input type="hidden" id="iva" name="iva" value="<?= $iva_config ?>">
<input type="hidden" id="ish" name="ish" value="<?= $ish_config ?>">

<!-- Mostrar visualmente pero sin permitir editar -->
<div class="seccion" id="seccion-impuestos">
    <i class="fas fa-percent section-icon"></i>
    <div class="campo-formulario">
        <label>IVA (%):</label>
        <input type="text" value="<?= $iva_config ?>" readonly>
    </div>
    <div class="campo-formulario">
        <label>ISH (%):</label>
        <input type="text" value="<?= $ish_config ?>" readonly>
    </div>
</div>
<?php endif; ?>


        <!-- Sección Descuentos -->
        <div class="seccion" id="seccion-descuentos">
            <i class="fas fa-tags section-icon">  Descuentos </i>
            <label>
                <input type="checkbox" id="aplicar_descuento_inapam" name="aplicar_descuento_inapam"> DESCUENTO INAPAN
            </label>
            <div id="campos-descuento" style="display: none;">
                <input type="text" id="Num_inapam" name="numero_inapan" required>Ingrese Credencial
                <select id="tipo_descuento_inapam" name="tipo_descuento_inapam">
                    <option value="porcentaje">Porcentaje</option>
                    <option value="monto">Monto fijo</option>
                </select>
                <input type="number" id="valor_descuento_inapam" name="valor_descuento_inapam" 
                    step="0.01" min="0" placeholder="Valor del descuento">
            </div>
        </div>

        <!-- Sección Pagos -->
        <div class="seccion" id="seccion-pagos">
            <i class="fas fa-money-check section-icon">  Pagos</i>
            <select id="tipo_pago" name="tipo_pago">
                <option value="completo">Pago Completo</option>
                <option value="parcial">Pago Parcial</option>
            </select>

            <div id="pago-completo">
                <select name="metodo_pago_completo" class="metodo-pago">
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta_debito">Tarjeta Débito</option>
                    <option value="tarjeta_credito">Tarjeta Crédito</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="otro">Otro</option>
                </select>
                <div class="otro-metodo-container" style="display: none;">
                    <input type="text" name="detalle_metodo_completo" placeholder="Especificar método">
                </div>
                <input type="number" name="monto_pago_completo" step="0.01" placeholder="Monto">
            </div>

            <div id="pago-parcial" style="display:none;">
                <button type="button" id="agregar-pago">+ Añadir Pago</button>
                <div class="pago-item">
                    <select name="metodo_pago_parcial[]" class="metodo-pago">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta_debito">Tarjeta Débito</option>
                        <option value="tarjeta_credito">Tarjeta Crédito</option>
                        <option value="transferencia">Transferencia</a>
                        <option value="otro">Otro</option>
                    </select>
                    <div class="otro-metodo-container" style="display: none;">
                        <input type="text" name="detalle_metodo_parcial[]" placeholder="Especificar método">
                    </div>
                    <input type="number" name="primer_pago[]" step="0.01" placeholder="Monto">
                </div>
            </div>
        </div>

        <!-- Sección Wifi -->
<div class="seccion" id="seccion-wifi">
    <i class="fas fa-wifi section-icon"> WIFI </i>
    <label>Wifi:</label>
    <input type="text" name="Nombre_wifi" placeholder="Nombre WIFI" value="<?= htmlspecialchars($wifi_nombre) ?>" <?= $rol !== 'admin' ? 'readonly' : '' ?>>
</div>

        <!-- Totales -->
        <div class="seccion" id="seccion-totales">
            <i class="fas fa-calculator section-icon"> TOTALES</i>
            <div>Subtotal: $<span id="subtotal">0.00</span></div>
            <div>Descuento: $<span id="descuento">0.00</span></div>
            <div>Impuestos: $<span id="impuestos">0.00</span></div>
            <div>Total: $<span id="total">0.00</span></div>
            <div>Pagado: $<span id="pagado">0.00</span></div>
            <div>Saldo: $<span id="saldo">0.00</span></div>
            <div id="cambio" style="display:none;">
                Cambio: $<span id="cambio-monto">0.00</span>
            </div>
        </div>

        <button type="submit">Confirmar Reserva</button>
    </form>
</div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('reservaForm');
        const tipoPago = document.getElementById('tipo_pago');
        const huespedSelect = document.getElementById('huesped_id');
        const nuevoHuespedDiv = document.getElementById('nuevo-huesped');
        const nombreInput = document.getElementById('nuevo_huesped_nombre');
        const numeroinapanInput = document.getElementById('numero_inapan');
        const  wifiInput = document.getElementById('Nombre_wifi');
        const contrasenaInput = document.getElementById('contrasena');

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
document.getElementById('buscadorElementos').addEventListener('input', function () {
        const filtro = this.value.toLowerCase();
        const items = document.querySelectorAll('#elementos-grid .habitacion-item');

        items.forEach(item => {
            const texto = item.textContent.toLowerCase();
            item.style.display = texto.includes(filtro) ? 'flex' : 'none';
        });
    });
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

        // Manejar descuento
        document.getElementById('aplicar_descuento_inapam').addEventListener('change', function() {
            document.getElementById('campos-descuento').style.display = this.checked ? 'block' : 'none';
            calcularTotales();
        });

        // Función de cálculo
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
            if (document.getElementById('aplicar_descuento_inapam').checked) {
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
    });
  
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
    
    </script>
</body>
</html>