<?php
session_start();
require 'config.php';

// Obtener detalles de la reserva
if ($_SERVER['REQUEST_METHOD'] == "GET" && isset($_GET['id'])) {
    $reserva_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM recibos WHERE id = ?");
    $stmt->execute([$reserva_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        echo "ID de reserva no válido.";
        exit;
    }

    // Obtener detalles de los elementos
    $stmt_detalles = $pdo->prepare("SELECT elemento_id FROM detalles_reserva WHERE reserva_id = ?");
    $stmt_detalles->execute([$reserva_id]);
    $detalles = $stmt_detalles->fetchAll(PDO::FETCH_COLUMN);

    // Calcular el total base
    $total_base = 0;
    if ($reserva['check_in'] && $reserva['check_out']) {
        $elementos = [];
        foreach ($detalles as $elemento_id) {
            $stmt_elemento = $pdo->prepare("SELECT precio, tipo FROM elementos WHERE id = ?");
            $stmt_elemento->execute([$elemento_id]);
            $elemento = $stmt_elemento->fetch(PDO::FETCH_ASSOC);
            $elementos[] = $elemento;
        }

        foreach ($elementos as $elemento) {
            if ($elemento['tipo'] === 'habitacion') {
                // Calcular noches
                $diff = strtotime($reserva['check_out']) - strtotime($reserva['check_in']);
                $noches = ceil($diff / (60 * 60 * 24));
                $total_base += $elemento['precio'] * $noches;
            } else {
                $total_base += $elemento['precio'];
            }
        }
    }
} else {
    // Manejo de la solicitud POST para creación de reserva
    $reserva = [
        'id_huesped' => '',
        'check_in' => '',
        'check_out' => '',
        'tipo_pago' => '',
        'total_pagar' => '',
    ];
    $detalles = [];
    $total_base = 0;
}

// Obtener lista de huéspedes
$stmt_huespedes = $pdo->query("SELECT * FROM huespedes");
$huespedes = $stmt_huespedes->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de elementos
$sql_elementos = "
    SELECT * FROM elementos 
    WHERE (tipo = 'habitacion' AND estado = 'disponible') 
       OR (tipo = 'servicio' AND estado = 'activo')
";
$stmt_elementos = $pdo->prepare($sql_elementos);
$stmt_elementos->execute();
$elementos = $stmt_elementos->fetchAll(PDO::FETCH_ASSOC);

// Manejo de la solicitud POST para creación/edición de reserva
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    try {
        $pdo->beginTransaction();

        $reserva_id = $_POST['reserva_id'];
        $huesped_id = $_POST['huesped_id'];
        $check_in = $_POST['check_in'];
        $check_out = $_POST['check_out'];
        $tipo_pago = $_POST['tipo_pago'];
        $total_pagar = $_POST['total_pagar'];
        $aplicar_iva = isset($_POST['aplicar_iva']) ? 1 : 0;

        // Calcular el total base
        $total_base = 0;
        $elementos = $_POST['elementos'];
        foreach ($elementos as $elemento_id) {
            // Obtener el precio del elemento
            $stmt_precio = $pdo->prepare("SELECT precio, tipo FROM elementos WHERE id = ?");
            $stmt_precio->execute([$elemento_id]);
            $elemento = $stmt_precio->fetch(PDO::FETCH_ASSOC);

            if ($elemento['tipo'] === 'habitacion') {
                // Calcular noches
                $diff = strtotime($check_out) - strtotime($check_in);
                $noches = ceil($diff / (60 * 60 * 24));
                $total_base += $elemento['precio'] * $noches;
            } else {
                $total_base += $elemento['precio'];
            }
        }

        if ($aplicar_iva) {
            $total_pagar = $total_base * 1.16;
        } else {
            $total_pagar = $total_base;
        }

        // Actualizar o insertar la reserva
        if ($reserva_id) {
            // Actualizar la reserva
            $stmt = $pdo->prepare("UPDATE recibos SET id_huesped = ?, check_in = ?, check_out = ?, tipo_pago = ?, total_pagar = ? WHERE id = ?");
            $stmt->execute([$huesped_id, $check_in, $check_out, $tipo_pago, $total_pagar, $reserva_id]);
        } else {
            // Crear una nueva reserva
            $stmt = $pdo->prepare("INSERT INTO recibos (id_huesped, check_in, check_out, tipo_pago, total_pagar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$huesped_id, $check_in, $check_out, $tipo_pago, $total_pagar]);
            $reserva_id = $pdo->lastInsertId();
        }

        // Limpiar detalles anteriores
        $stmt = $pdo->prepare("DELETE FROM detalles_reserva WHERE reserva_id = ?");
        $stmt->execute([$reserva_id]);

        // Insertar nuevos detalles
        $stmt_detalle = $pdo->prepare("INSERT INTO detalles_reserva (reserva_id, elemento_id, tipo) VALUES (?, ?, ?)");
        foreach ($elementos as $elemento_id) {
            // Obtener el tipo del elemento
            $stmt_tipo = $pdo->prepare("SELECT tipo FROM elementos WHERE id = ?");
            $stmt_tipo->execute([$elemento_id]);
            $tipo = $stmt_tipo->fetchColumn();

            $stmt_detalle->execute([$reserva_id, $elemento_id, $tipo]);
        }

        $pdo->commit();
        echo "<script>alert('Reserva guardada correctamente.'); window.location.href='editar_reserva.php?id=" . $reserva_id . "';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos generales */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        /* Estilos para la barra lateral */
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            box-sizing: border-box;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            transition: left 0.3s ease;
        }

        .sidebar.active {
            left: 0;
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

        /* Contenido principal */
        .content {
            margin-left: 250px;
            padding: 30px;
            flex-grow: 1;
            overflow-y: auto;
        }

        /* Estilos para el contenedor de la reserva */
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-top: 10px;
            margin-bottom: 5px;
            color: #555;
        }

        input, select, button {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
        }

        button {
            margin-top: 20px;
            background-color: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #2980b9;
        }

        /* Estilos para la sección de habitaciones y servicios */
        .seccion {
            margin-top: 20px;
        }

        .habitaciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .habitacion-item {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            transition: border-color 0.3s, transform 0.3s;
            background-color: #f9f9f9;
        }

        .habitacion-item:hover {
            border-color: #3498db;
            transform: translateY(-3px);
        }

        .habitacion-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .icono {
            font-size: 1.5em;
        }

        .detalles .nombre {
            font-weight: bold;
        }

        .detalles .precio {
            color: #e74c3c;
        }

        /* Botón de guardar cambios */
        .boton-guardar {
            background-color: #27ae60;
        }

        .boton-guardar:hover {
            background-color: #1e8449;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }

            .content {
                margin-left: 0;
            }

            .toggle-sidebar {
                display: block;
            }
        }

        /* Botón para togglear el sidebar en móvil */
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            z-index: 1000;
            cursor: pointer;
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

        .overlay.active {
            display: block;
        }

        /* Sección de IVA */
        .iva-seccion {
            margin-top: 20px;
        }

        .iva-seccion label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
        }

        .iva-seccion input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .iva-seccion p {
            margin: 0;
            font-size: 1em;
            color: #555;
        }
    </style>
</head>
<body>
    <!-- Barra lateral -->
    <nav class="sidebar">
        <h2>Menú</h2>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones y Servicios</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Crear Reservación</a></li>
            <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Reservas</a></li>
            <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </nav>

    <!-- Overlay para el sidebar en móvil -->
    <div class="overlay"></div>

    <!-- Contenido principal -->
    <div class="content">
        <div class="container">
            <h1>Editar Reserva</h1>
            <form method="POST" action="editar_reserva.php">
                <input type="hidden" name="reserva_id" value="<?= htmlspecialchars($reserva['id']) ?>">
                <label for="huesped_id">Huésped:</label>
                <select id="huesped_id" name="huesped_id">
                    <?php
                    foreach ($huespedes as $huesped) {
                        $selected = ($huesped['id'] == $reserva['id_huesped']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($huesped['id']) . "' " . $selected . ">" . htmlspecialchars($huesped['nombre']) . "</option>";
                    }
                    ?>
                </select>

                <label for="check_in">Check-in:</label>
                <input id="check_in" type="date" name="check_in" value="<?= htmlspecialchars(date('Y-m-d', strtotime($reserva['check_in']))) ?>">

                <label for="check_out">Check-out:</label>
                <input id="check_out" type="date" name="check_out" value="<?= htmlspecialchars(date('Y-m-d', strtotime($reserva['check_out']))) ?>">

                <label for="tipo_pago">Método de pago:</label>
                <select id="tipo_pago" name="tipo_pago">
                    <option value="efectivo" <?= ($reserva['tipo_pago'] == 'efectivo') ? 'selected' : '' ?>>Efectivo</option>
                    <option value="tarjeta" <?= ($reserva['tipo_pago'] == 'tarjeta') ? 'selected' : '' ?>>Tarjeta</option>
                    <option value="transferencia" <?= ($reserva['tipo_pago'] == 'transferencia') ? 'selected' : '' ?>>Transferencia</option>
                </select>

                <label for="total_pagar">Total a pagar:</label>
                <input id="total_pagar" type="number" step="0.01" name="total_pagar" value="<?= htmlspecialchars($reserva['total_pagar']) ?>" readonly>

                <!-- Sección de IVA -->
                <div class="seccion">
    <div class="campo-formulario">
        <label>
            <input type="checkbox" id="aplicar_iva" name="aplicar_iva" <?= ($reserva['total_pagar'] > $total_base) ? 'checked' : '' ?>> 
            Aplicar IVA (16%)
        </label>
    </div>
                </div>

                <!-- Sección de habitaciones y servicios -->
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
                        foreach ($elementos as $e) {
                            $checked = (in_array($e['id'], $detalles)) ? 'checked' : '';
                            echo '<div class="habitacion-item">';
                            echo '<label>';
                            echo '<input type="checkbox" name="elementos[]" value="' . htmlspecialchars($e['id']) . '" data-precio="' . $e['precio'] . '" data-tipo="' . htmlspecialchars($e['tipo']) . '" ' . $checked . '>';
                            echo '<div class="icono">';
                            if ($e['tipo'] === 'habitacion') {
                                echo '<i class="fas fa-bed"></i>';
                            } elseif ($e['tipo'] === 'servicio') {
                                echo '<i class="fas fa-concierge-bell"></i>';
                            }
                            echo '</div>';
                            echo '<div class="detalles">';
                            echo '<span class="nombre">' . htmlspecialchars($e['nombre']) . '</span>';
                            echo '<span class="precio">$' . number_format($e['precio'], 2) . '</span>';
                            echo '<p class="descripcion">' . htmlspecialchars($e['descripcion']) . '</p>';
                            echo '</div>';
                            echo '</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <button type="submit" class="boton-guardar">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- Script para calcular el total -->
    <script>
// Cálculos automáticos
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

        // Manejo del IVA
        if ($('#aplicar_iva').is(':checked')) {
            total *= 1.16;
        }

        // Manejo del descuento (si aplica)
        if ($('#descuento').is(':checked')) {
            total *= 0.9; // 10% de descuento
        }
    } else {
        alert('Por favor, seleccione las fechas de check-in y check-out.');
        return;
    }

    $('#total-pagar').text('$' + total.toFixed(2));
    calcularCambio();
}

// Función para calcular el cambio (si aplica)
function calcularCambio() {
    const total = parseFloat($('#total-pagar').text().replace('$', ''));
    const cantidadRecibida = parseFloat($('#cantidad-recibida').val());

    if (!isNaN(cantidadRecibida) && !isNaN(total)) {
        const cambio = cantidadRecibida - total;
        $('#cambio').text('$' + cambio.toFixed(2));
    } else {
        $('#cambio').text('\$0.00');
    }
}

// Evento para escuchar cambios en los checkboxes y inputs
$('#aplicar_iva, #descuento, input[name="elementos[]"]').on('change', calcularTotal);
$('#check_in, #check_out').on('change', calcularTotal);
$('#cantidad-recibida').on('input', calcularCambio);

// Inicializar el cálculo al cargar la página
$(document).ready(function() {
    calcularTotal();
});
   

        $(document).ready(function() {
            $('.toggle-sidebar').on('click', function() {
                $('.sidebar').toggleClass('active');
                $('.overlay').toggleClass('active');
            });

            $('.overlay').on('click', function() {
                $('.sidebar').removeClass('active');
                $('.overlay').removeClass('active');
            });
        });
</script>

</body>
</html>