<?php
session_start();
require 'config.php';

// Obtener el ID del recibo de la URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $recibo_id = intval($_GET['id']);

    // Obtener los datos del recibo de la base de datos
    $stmt = $pdo->prepare("SELECT * FROM recibos WHERE id = ?");
    $stmt->execute([$recibo_id]);
    $recibo = $stmt->fetch();

    if ($recibo) {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Editar Recibo</title>
            <link rel="stylesheet" href="styles.css">
        </head>
        <body>
            <h1>Editar Recibo</h1>
            <form method="post" action="actualizar_recibo.php">
                <input type="hidden" name="id" value="<?= htmlspecialchars($recibo['id']) ?>">

                <label for="habitacion_id">ID de la Habitación:</label>
                <select id="habitacion_id" name="habitacion_id" required>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM habitaciones");
                    while ($habitacion = $stmt->fetch()) {
                        $selected = ($habitacion['id'] == $recibo['habitacion_id']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($habitacion['id']) . "' $selected>" . htmlspecialchars($habitacion['numero_habitacion']) . " - " . htmlspecialchars($habitacion['caracteristicas']) . " ($" . htmlspecialchars($habitacion['precio']) . ")</option>";
                    }
                    ?>
                </select>

                <label for="descripcion_habitacion">Descripción de la Habitación:</label>
                <textarea id="descripcion_habitacion" name="descripcion_habitacion" readonly><?= htmlspecialchars($recibo['descripcion_habitacion']) ?></textarea>

                <label for="precio_habitacion">Precio de la Habitación:</label>
                <input type="number" id="precio_habitacion" name="precio_habitacion" value="<?= htmlspecialchars($recibo['precio_habitacion']) ?>" readonly>

                <label for="numero_noches">Número de Noches:</label>
                <input type="number" id="numero_noches" name="numero_noches" value="<?= htmlspecialchars($recibo['numero_noches']) ?>" required>

                <label for="subtotal">Subtotal:</label>
                <input type="number" id="subtotal" name="subtotal" value="<?= htmlspecialchars($recibo['subtotal']) ?>" readonly>

                <label for="tipo_pago">Tipo de Pago:</label>
                <select id="tipo_pago" name="tipo_pago">
                    <option value="en_efectivo" <?= ($recibo['tipo_pago'] == 'en_efectivo') ? 'selected' : '' ?>>Efectivo</option>
                    <option value="tarjeta" <?= ($recibo['tipo_pago'] == 'tarjeta') ? 'selected' : '' ?>>Tarjeta</option>
                    <option value="por_pagar" <?= ($recibo['tipo_pago'] == 'por_pagar') ? 'selected' : '' ?>>Por Pagar (al llegar al hotel)</option>
                </select>

                <label for="estado_pago">Estado de Pago:</label>
                <select id="estado_pago" name="estado_pago">
                    <option value="pendiente" <?= ($recibo['estado_pago'] == 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                    <option value="pagado" <?= ($recibo['estado_pago'] == 'pagado') ? 'selected' : '' ?>>Pagado</option>
                    <option value="pendiente_en_hotel" <?= ($recibo['estado_pago'] == 'pendiente_en_hotel') ? 'selected' : '' ?>>Pendiente (al llegar al hotel)</option>
                </select>

                <label for="nombre_cliente">Nombre del Cliente:</label>
                <input type="text" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars($recibo['nombre_cliente']) ?>" required>

                <label for="pagado">Monto Pagado:</label>
                <input type="number" id="pagado" name="pagado" value="<?= htmlspecialchars($recibo['pagado']) ?>" required>

                <label for="estado">Estado del Recibo:</label>
                <select id="estado" name="estado">
                    <option value="activo" <?= ($recibo['estado'] == 'activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= ($recibo['estado'] == 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>

                <button type="submit" id="btn-guardar">Guardar Cambios</button>
                <a href="imprimir_recibo.php?id=<?= $recibo['id'] ?>" id="btn-imprimir" class="btn-imprimir" target="_blank" <?= ($recibo['estado'] == 'activo') ? '' : 'disabled' ?>>
                    <i class="fas fa-print"></i> Imprimir
                </a>
            </form>

            <script>
                // Función para calcular el subtotal
                function calcularSubtotal() {
                    var precio = parseFloat(document.getElementById('precio_habitacion').value) || 0;
                    var noches = parseInt(document.getElementById('numero_noches').value) || 0;
                    var subtotal = precio * noches;
                    document.getElementById('subtotal').value = subtotal.toFixed(2);
                }

                // Función para actualizar el estado del botón de imprimir
                function actualizarEstadoImpresion() {
                    var estado = document.getElementById('estado').value;
                    var botonImprimir = document.getElementById('btn-imprimir');

                    if (estado === 'activo') {
                        botonImprimir.disabled = false;
                        botonImprimir.style.opacity = 1;
                    } else {
                        botonImprimir.disabled = true;
                        botonImprimir.style.opacity = 0.5;
                    }
                }

                // Evento para actualizar el subtotal cuando cambia el número de noches
                document.getElementById('numero_noches').addEventListener('input', calcularSubtotal);

                // Evento para actualizar el estado del botón de imprimir cuando cambia el estado del recibo
                document.getElementById('estado').addEventListener('change', actualizarEstadoImpresion);

                // Inicializar el estado del botón de imprimir al cargar la página
                window.onload = actualizarEstadoImpresion;
            </script>
        </body>
        </html>
        <?php
    } else {
        echo "Recibo no encontrado.";
    }
} else {
    echo "ID de recibo inválido.";
}
?>