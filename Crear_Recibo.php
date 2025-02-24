<?php
// Crear_Recibo.php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    try {
        $pdo->beginTransaction();

        // Validar datos básicos
        if (!isset($_POST['elementos']) || empty($_POST['elementos'])) {
            throw new Exception("Seleccione al menos una habitación o servicio.");
        }

        // Validar fechas
        if (!isset($_POST['check_in']) || !isset($_POST['check_out'])) {
        }

        $hoy = new DateTime('today');
        $check_in = new DateTime($_POST['check_in']);
        $check_out = new DateTime($_POST['check_out']);

        if ($check_in < $hoy) {
            throw new Exception("No se pueden hacer reservas en el pasado.");
        }

        $min_check_out = clone $check_in;
        $min_check_out->modify('+1 day');
        if ($check_out < $min_check_out) {
            throw new Exception("La fecha de salida debe ser al menos un día después de la de entrada.");
        }

        // Manejar huésped
        if (empty($_POST['huesped_id'])) {
            if (empty($_POST['nuevo_huesped_nombre'])) {
                throw new Exception("El nombre del huésped es obligatorio.");
            }

            // Insertar nuevo huésped
            $stmt = $pdo->prepare("INSERT INTO huespedes (nombre, rfc, telefono, correo) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['nuevo_huesped_nombre'],
                $_POST['nuevo_huesped_rfc'] ?? null,
                $_POST['nuevo_huesped_telefono'] ?? null,
                $_POST['nuevo_huesped_correo'] ?? null
            ]);
            $huesped_id = $pdo->lastInsertId();
        } else {
            $huesped_id = $_POST['huesped_id'];
        }

        // Crear la reserva
        $tipo_pago = $_POST['tipo_pago'];
        $total_pagar = 0; // Se calculará después
        $stmt = $pdo->prepare("INSERT INTO recibos (id_huesped, check_in, check_out, total_pagar, tipo_pago) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$huesped_id, $_POST['check_in'], $_POST['check_out'], $total_pagar, $tipo_pago]);
        $reserva_id = $pdo->lastInsertId();

        // Filtrar elementos seleccionados
        $stmt = $pdo->prepare("SELECT id, tipo, precio FROM elementos WHERE id IN (" . implode(',', array_fill(0, count($_POST['elementos']), '?')) . ")");
        $stmt->execute($_POST['elementos']);
        $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        foreach ($_POST['elementos'] as $elemento_id) {
            foreach ($elementos as $e) {
                if ($e['id'] == $elemento_id) {
                    if ($e['tipo'] == 'habitacion') {
                        // No hay validación de capacidad
                        $total += $e['precio'];
                    } elseif ($e['tipo'] == 'servicio') {
                        $total += $e['precio'];
                    }
                }
            }
        }

        // Aplicar IVA si está seleccionado
        if (isset($_POST['aplicar_iva']) && $_POST['aplicar_iva'] == '1') {
            $total *= 1.16;
        }

        // Aplicar descuento si está seleccionado
        if (isset($_POST['descuento']) && $_POST['descuento'] == '1') {
            $total *= 0.9;
        }

        // Actualizar el total en la reserva
        $stmt = $pdo->prepare("UPDATE recibos SET total_pagar = ? WHERE id = ?");
        $stmt->execute([$total, $reserva_id]);

    // Insertar en detalles_reserva
            $stmt_detalle = $pdo->prepare("INSERT INTO detalles_reserva (reserva_id, elemento_id, tipo) VALUES (?, ?, ?)");
            foreach ($_POST['elementos'] as $elemento_id) {
                $tipo = '';
                foreach ($elementos as $e) {
                    if ($e['id'] == $elemento_id) {
                        $tipo = $e['tipo'];
                        break;
                    }
                }
                $stmt_detalle->execute([$reserva_id, $elemento_id, $tipo]);

                // Actualizar la fecha de ocupación y liberación si es una habitación
                if ($tipo == 'habitacion') {
                    $fecha_ocupacion = $_POST['check_in'];
                    $fecha_liberacion = $_POST['check_out'];

                    $stmt_ocupacion = $pdo->prepare("UPDATE elementos SET fecha_ocupacion = ?, fecha_liberacion = ? WHERE id = ? AND tipo = 'habitacion'");
                    $stmt_ocupacion->execute([$fecha_ocupacion, $fecha_liberacion, $elemento_id]);
                }
            }
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "✅ Reserva realizada!"]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "❌ Error: " . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas Inteligente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="logo_hotel.jpg">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    :root {
    --color-primario: #2c3e50;
    --color-secundario: #3498db;
    --color-exito: #27ae60;
    --color-error: #e74c3c;
    --color-fondo: #f8f9fa;
    --color-borde: #e2e8f0;
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--color-fondo);
    padding: 20px;
    line-height: 1.6;
}

.contenedor {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
}

h1 {
    color: var(--color-primario);
    text-align: center;
    margin-bottom: 2rem;
    font-size: 2.5rem;
}

.seccion {
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    border-radius: 0.8rem;
    background: #f8fafc;
    border: 1px solid var(--color-borde);
}

.filtro {
    position: relative;
    margin-bottom: 1rem;
}

.filtro input {
    width: 100%;
    padding: 0.8rem 2.5rem 0.8rem 1rem;
    border: 2px solid var(--color-borde);
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.filtro input:focus {
    border-color: var(--color-secundario);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.habitaciones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
}

.habitacion-item {
    padding: 1rem;
    border: 2px solid var(--color-borde);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}

.habitacion-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    border-color: var(--color-secundario);
}

.habitacion-item label {
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
}

.nuevo-huesped {
    display: none;
    margin-top: 1rem;
    background: white;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--color-borde);
}

.campo-pago {
    background: white;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-top: 1rem;
}

#total-pagar {
    font-size: 1.8rem;
    color: var(--color-primario);
    font-weight: bold;
}

.rojo { color: var(--color-error); }
.verde { color: var(--color-exito); }

/* Estilos para el menú lateral */
.sidebar {
    position: fixed; /* Fija la barra lateral en una posición específica */
    top: 0;          /* Posición desde la parte superior */
    left: 0;         /* Posición desde la izquierda */
    width: 250px;
    height: 100vh;   /* Asegura que la barra lateral ocupe toda la altura de la ventana */
    background-color: var(--color-primario);
    color: white;
    padding: 20px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    /* Elimina la propiedad 'transition' si no necesitas que la barra lateral tenga una transición */
    /* transition: left 0.3s ease; */
    overflow: hidden; /* Asegura que no haya desplazamiento dentro de la barra lateral */
    z-index: 1000;   /* Asegura que la barra lateral esté por encima de otros elementos */
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
    color: white;
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
        top: 15px;
        left: 15px;
        background: var(--color-primario);
        color: white;
        border: none;
        padding: 10px;
        border-radius: 4px;
        z-index: 1000;
 }
        /* Overlay para el sidebar en móvil */
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
 /* Contenido principal */
.contenido {
    margin-left: 250px;
    padding: 30px;
    transition: margin 0.3s;
}

/* Estilos para dispositivos móviles */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: -250px; /* Oculto por defecto en móvil */
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


/* Estilos responsivos */
@media (max-width: 768px) {
    .contenedor {
        padding: 15px;
        margin-top: 60px; /* Espacio para la navbar móvil */
    }

    h1 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .seccion h2 {
        font-size: 1.2rem;
    }

    .habitaciones-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        max-height: 300px;
    }

    .habitacion-item {
        padding: 0.8rem;
    }

    .icono {
        font-size: 1.5rem;
    }

    .nombre {
        font-size: 1rem;
    }

    .precio {
        font-size: 0.9rem;
    }

    .descripcion {
        font-size: 0.8rem;
    }
}

/* Estilos para dispositivos móviles con ancho máximo de 480px */
@media (max-width: 480px) {
    .habitaciones-grid {
        grid-template-columns: 1fr;
        max-height: 250px;
    }

    .habitacion-item {
        flex-direction: row;
        align-items: center;
        gap: 1rem;
    }

    .icono {
        font-size: 1.2rem;
    }

    .detalles {
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
    }

    .nombre {
        font-size: 1rem;
    }

    .precio {
        display: none;
    }

    .descripcion {
        display: none;
    }

    .contenedor {
        padding: 15px;
        margin-top: 60px; /* Espacio para la navbar móvil */
    }

    h1 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .seccion h2 {
        font-size: 1.2rem;
    }
}

/* Estilos para dispositivos móviles con ancho máximo de 600px */
@media (max-width: 600px) {
    .seccion > div {
        flex-direction: column;
    }

    .campo-formulario {
        width: 100%;
        margin-bottom: 1rem;
    }
}

/* Ajustes específicos para formularios */
.campo-formulario input,
.campo-formulario select,
.boton-reservar {
    width: 100%;
    box-sizing: border-box;
}

/* Botón de reserva responsivo */
.boton-reservar {
    font-size: 1rem;
    padding: 15px;
    margin-top: 20px;
}

/* Flecha de regreso móvil */
#flecha-regreso {
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    font-size: 1.2rem;
}

/* Mejoras en inputs para móviles */
input[type="date"] {
    -webkit-appearance: none;
    min-height: 45px;
}

select {
    min-height: 45px;
    font-size: 1rem;
}

/* Ajustes para nueva sección huésped */
.nuevo-huesped .campo-formulario {
    width: 100%;
    margin-bottom: 1rem;
}
/* Estilos para el contenedor de filtros en dispositivos móviles */
@media (max-width: 768px) {
    .filtros form {
        flex-direction: column;
        align-items: stretch;
    }

    .filtros input[type="date"],
    .filtros input[type="text"] {
        width: 100%;
    }

    .filtros button {
        width: 100%;
    }
}

/* Menú lateral en dispositivos móviles */
@media (max-width: 480px) {
    .contenido {
        padding: 15px;
        padding-top: 60px;
    }

    h1 {
        font-size: 1.5rem;
        margin-bottom: 20px;
    }

}
@media (max-width: 768px) {
      .sidebar {
        position: fixed;
        top: 0;
        left: -250px; /* Oculto por defecto en móvil */
        height: 100%;
        z-index: 999;
      }
      .sidebar.active {
        left: 0;
      }
      .content {
        margin-left: 0;
      }
      .toggle-sidebar {
        display: block;
      }
      /* Overlay opcional para enfocar el sidebar */
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
      .overlay.active {
        display: block;
      }
    }

    @media (max-width: 480px) {
      .search-container {
        flex-direction: column;
        align-items: flex-start;
      }
    }
 
</style>
</head>
<button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
<!-- Menú Lateral -->
<aside class="sidebar">

        <h2>Menú</h2>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones y Servicios</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Crear Reservación</a></li>
            <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Reservas</a></li>
            <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </aside>

    <div class="overlay"></div>
<!-- Contenido Principal -->
    <div class="contenedor">
        <h1>🏨 Sistema de Reservas</h1>

        <div class="seccion">
    <div class="filtro">
        <label for="tipo-elemento">Seleccione el tipo de elemento:</label>
        <select id="tipo-elemento" name="tipo-elemento">
            <option value="todos">Todos</option>
            <option value="habitacion">Habitaciones</option>
            <option value="servicio">Servicios</option>
        </select>
    </div>
    <div class="habitaciones-grid" id="elementos-grid">
        <?php
        $sql = "
            SELECT * FROM elementos 
            WHERE (tipo = 'habitacion' AND estado = 'disponible') 
               OR (tipo = 'servicio' AND estado = 'activo')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        foreach ($elementos as $e): ?>
        <div class="habitacion-item" data-tipo="<?= htmlspecialchars($e['tipo']) ?>">
            <label>
                <input type="checkbox" name="elementos[]" value="<?= $e['id'] ?>" data-precio="<?= $e['precio'] ?>" data-tipo="<?= htmlspecialchars($e['tipo']) ?>">
                <div class="icono">
                    <?php
                    if ($e['tipo'] === 'habitacion') {
                        echo '<i class="fas fa-bed"></i>';
                    } elseif ($e['tipo'] === 'servicio') {
                        echo '<i class="fas fa-concierge-bell"></i>';
                    }
                    ?>
                </div>
                <div class="detalles">
                    <span class="nombre"><?= htmlspecialchars($e['nombre']) ?></span>
                    <span class="precio">$<?= number_format($e['precio'], 2) ?></span>
                    <p class="descripcion"><?= htmlspecialchars($e['descripcion']) ?></p>
                </div>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- Sección de Huésped -->
<div class="seccion">
    <div class="filtro">
        <input type="text" id="filtro-huesped" placeholder="🔍 Buscar huésped...">
    </div>
    <select id="huesped_id" name="huesped_id" class="full-width">
        <option value="">👤 Nuevo huésped</option>
        <?php
        $stmt = $pdo->prepare("SELECT * FROM huespedes");
        $stmt->execute();
        $huespedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($huespedes as $h): ?>
            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
    <div class="nuevo-huesped" id="nuevo-huesped">
        <div class="campo-formulario">
            <input type="text" id="nuevo_huesped_nombre" name="nuevo_huesped_nombre" placeholder="Nombre completo*" required>
        </div>
        <div class="campo-formulario">
            <input type="text" id="nuevo_huesped_rfc" name="nuevo_huesped_rfc" placeholder="RFC">
        </div>
        <div class="campo-formulario">
            <input type="tel" id="nuevo_huesped_telefono" name="nuevo_huesped_telefono" placeholder="Teléfono">
        </div>
        <button type="button" id="guardar-nuevo-huesped">Guardar Nuevo Huésped</button>
    </div>

        <!-- Sección de Fechas -->
        <div class="seccion">
            <div class="flex-fechas">
                <div class="campo-formulario">
                    <label>Check-in:</label>
                    <input type="date" id="check_in" name="check_in" required>
                </div>
                <div class="campo-formulario">
                    <label>Check-out:</label>
                    <input type="date" id="check_out" name="check_out" required>
                </div>
            </div>
        </div>

        <!-- Sección de IVA -->
        <div class="seccion">
            <div class="campo-formulario">
                <label>
                    <input type="checkbox" id="aplicar_iva" name="aplicar_iva"> 
                    Aplicar IVA (16%)
                </label>
            </div>
        </div>

        <!-- Sección de Pagos -->
        <div class="seccion">
            <div class="campo-pago">
                <label>Método de pago:</label>
                <select id="tipo_pago" name="tipo_pago" required>
                    <option value="efectivo">💵 Efectivo</option>
                    <option value="tarjeta">💳 Tarjeta</option>
                    <option value="transferencia">📤 Transferencia</option>
                </select>
            </div>

            <div class="campo-pago" id="seccion-efectivo">
                <input type="number" id="cantidad-recibida" placeholder="Monto recibido" step="0.01">
                <p>Cambio: <span id="cambio" class="verde">\\$0.00</span></p>
            </div>

            <div class="campo-pago">
                <label>
                    <input type="checkbox" id="descuento" name="descuento"> 
                    Aplicar 10% de descuento
                </label>
                <p>Total a pagar: <span id="total-pagar">\\$0.00</span></p>
            </div>
        </div>

        <button id="btn-reservar">📅 Confirmar Reserva</button>
    </div>

    <script>
$(document).ready(function() {
    // Filtros dinámicos
    function filtrarElementos() {
        const tipoSeleccionado = $('#tipo-elemento').val();
        $('.habitacion-item').each(function() {
            const tipoElemento = $(this).data('tipo');
            if (tipoSeleccionado === 'todos' || tipoElemento === tipoSeleccionado) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    $('#tipo-elemento').on('change', filtrarElementos);

    // Cálculos automáticos
    function calcularTotal() {
        let total = 0;
        const checkIn = $('#check_in').val();
        const checkOut = $('#check_out').val();

        if (checkIn && checkOut) {
            const diff = new Date(checkOut) - new Date(checkIn);
            const noches = Math.ceil(diff / (1000 * 3600 * 24)) || 0;

            if (noches <= 0) {
                alert('Error: La fecha de salida debe ser después de la fecha de entrada.');
                return;
            }

            $('input[name="elementos[]"]:checked').each(function() {
                const tipo = $(this).data('tipo');
                const precio = parseFloat($(this).data('precio'));
                if (isNaN(precio)) {
                    alert('Error: El precio de uno de los elementos seleccionados no es válido.');
                    return;
                }

                if (tipo === 'habitacion') {
                    total += precio * noches;
                } else if (tipo === 'servicio') {
                    total += precio;
                }
            });

            if ($('#aplicar_iva').prop('checked')) {
                total *= 1.16;
            }

            if ($('#descuento').prop('checked')) {
                total *= 0.9; // 10% de descuento
            }
        } else {
}

        $('#total-pagar').text('$' + total.toFixed(2));
        calcularCambio();
    }
    function actualizarInterfaz() {
    const tipoPago = $('#tipo_pago').val();
    if (tipoPago === 'efectivo') {
        $('#cantidad-recibida').prop('disabled', false);
    } else {
        $('#cantidad-recibida').prop('disabled', true);
        $('#cantidad-recibida').val('');
        $('#cambio').text('\$0.00').removeClass('rojo verde');
        $('#btn-reservar').prop('disabled', false);
    }
    calcularCambio();
}

// Llamar a la función al cambiar el tipo de pago
$('#tipo_pago').on('change', actualizarInterfaz);

// Llamar a la función al cargar la página
$(document).ready(function() {
    actualizarInterfaz();
});
function calcularCambio() {
    const tipoPago = $('#tipo_pago').val();
    const total = parseFloat($('#total-pagar').text().replace('$', '')) || 0;
    const recibido = parseFloat($('#cantidad-recibida').val()) || 0;
    let cambio = 0;

    if (tipoPago === 'efectivo') {
        cambio = recibido - total;
        $('#cambio')
            .text('$' + cambio.toFixed(2))
            .toggleClass('rojo', cambio < 0)
            .toggleClass('verde', cambio >= 0);
        $('#btn-reservar').prop('disabled', cambio < 0);
    } else {
        $('#cambio').text('\$0.00').removeClass('rojo verde');
        $('#btn-reservar').prop('disabled', false);
    }
}
$(document).ready(function() {
    // Función para mostrar/ocultar el formulario de nuevo huésped
    $('#huesped_id').on('change', function() {
        if ($(this).val() === '') {
            $('#nuevo-huesped').show();
        } else {
            $('#nuevo-huesped').hide();
        }
    });

    // Función para cargar los huéspedes en el select
    function cargarHuespedes(busqueda = '') {
        $.ajax({
            url: 'obtener_huespedes.php',
            method: 'POST',
            data: { busqueda: busqueda },
            dataType: 'json',
            success: function(data) {
                var select = $('#huesped_id');
                select.empty();
                select.append('<option value="">👤 Nuevo huésped</option>');
                $.each(data, function(index, huesped) {
                    select.append('<option value="' + huesped.id + '">' + huesped.nombre + '</option>');
                });
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar los huéspedes:', error);
            }
        });
    }

    // Cargar huéspedes iniciales
    cargarHuespedes();

    // Escuchar cambios en el campo de búsqueda
    $('#filtro-huesped').on('input', function() {
        var busqueda = $(this).val();
        cargarHuespedes(busqueda);
    });

    // Manejar el guardado de un nuevo huésped
    $('#guardar-nuevo-huesped').on('click', function() {
        var nombre = $('#nuevo_huesped_nombre').val();
        var rfc = $('#nuevo_huesped_rfc').val();
        var telefono = $('#nuevo_huesped_telefono').val();

        $.ajax({
            url: 'guardar_huesped.php',
            method: 'POST',
            data: { nombre: nombre, rfc: rfc, telefono: telefono },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Huésped guardado correctamente.');
                    cargarHuespedes();
                    $('#nuevo-huesped input').val('');
                } else {
                    alert('Error al guardar el huésped: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al guardar el huésped:', error);
            }
        });
    });
});

$(document).ready(function(){
    $('#filtro-huesped').on('input', function() {
        var filtro = $(this).val().toLowerCase();
        $('#huesped_id option').filter(function() {
            var texto = $(this).text().toLowerCase();
            $(this).toggle(texto.indexOf(filtro) > -1);
        });
    });
});

    // Eventos
    $('input, select').on('change keyup', calcularTotal);
    $('#cantidad-recibida').on('input', calcularCambio);

    // Validación de fechas
    $('#check_in, #check_out').on('change', function() {
        const checkIn = new Date($('#check_in').val());
        const checkOut = new Date($('#check_out').val());
        const hoy = new Date();

        // Normalizar hoy para que solo compare la fecha sin la hora
        hoy.setHours(0, 0, 0, 0);
        checkIn.setHours(0, 0, 0, 0);
        checkOut.setHours(0, 0, 0, 0);

        // Permitir reservar desde hoy en adelante
        if ($('#check_in').val() && checkIn < hoy) {
            alert('Error: No se puede reservar en el pasado');
            $('#check_in').val('');
            return;
        }

        // Check-out debe ser al menos el día siguiente al check-in
        const minCheckOut = new Date(checkIn);
        minCheckOut.setDate(minCheckOut.getDate() + 1); // Agregar 1 día

        if ($('#check_out').val() && checkOut < minCheckOut) {
            alert('Error: Fecha de salida inválida. Debe ser al menos un día después del check-in.');
            $('#check_out').val('');
            return;
        }
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

    // Enviar reserva
    $('#btn-reservar').click(function(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('tipo_pago', $('#tipo_pago').val());
        formData.append('descuento', $('#descuento').prop('checked') ? '1' : '0');
        formData.append('aplicar_iva', $('#aplicar_iva').prop('checked') ? '1' : '0');

        // Validación final
        if ($('#tipo_pago').val() === 'efectivo' && !$('#cantidad-recibida').val()) {
            alert('Ingrese el monto recibido para pago en efectivo');
            return;
        }

        $('input[name="elementos[]"]:checked, select, input[type="date"]').each(function() {
            formData.append($(this).attr('name'), $(this).val());
        });

        fetch('Crear_Recibo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Reserva realizada exitosamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Error en la conexión'));
    });
});
    </script>
</body>
</html>