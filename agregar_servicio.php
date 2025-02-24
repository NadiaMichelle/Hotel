<?php
// agregar_servicio.php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $huesped_id = $_POST['huesped_id'];
    $habitacion_id = $_POST['habitacion_id'];
    $numero_noches = $_POST['numero_noches'];

    // Calcular subtotal
    $stmt = $pdo->prepare('SELECT precio FROM habitaciones WHERE id = ?');
    $stmt->execute([$habitacion_id]);
    $habitacion = $stmt->fetch();
    $precio = $habitacion['precio'];
    $subtotal = $precio * $numero_noches;

    $stmt = $pdo->prepare('INSERT INTO servicios (huesped_id, habitacion_id, numero_noches, subtotal) VALUES (?, ?, ?, ?)');
    $stmt->execute([$huesped_id, $habitacion_id, $numero_noches, $subtotal]);
    header('Location: servicios.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agregar Servicio</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Agregar Servicio</h1>
    <form method="post">
        <label for="huesped_id">Nombre del Huesped:</label>
        <select id="huesped_id" name="huesped_id" required>
            <?php
            $stmt = $pdo->query('SELECT id, nombre FROM huespedes');
            while ($huesped = $stmt->fetch()) {
                echo "<option value='$huesped[id]'>$huesped[nombre]</option>";
            }
            ?>
        </select>
        <label for="habitacion_id">Numero de Habitacion:</label>
        <select id="habitacion_id" name="habitacion_id" required>
            <?php
            $stmt = $pdo->query('SELECT id, numero_habitacion FROM habitaciones');
            while ($habitacion = $stmt->fetch()) {
                echo "<option value='$habitacion[id]'>$habitacion[numero_habitacion]</option>";
            }
            ?>
        </select>
        <label for="numero_noches">N�mero de Noches:</label>
        <input type="number" id="numero_noches" name="numero_noches" required>
        <button type="submit">Agregar</button>
        <button type="button" onclick="window.location.href='servicios.php'">Cancelar</button>
    </form>
</body>
</html>
