<?php
// register.php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);

    // Verificar si el nombre de usuario ya existe
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE nombre_usuario = ?');
    $stmt->execute([$nombre_usuario]);
    if ($stmt->fetch()) {
        $error = "El nombre de usuario ya está en uso.";
    } else {
        // Encriptar contraseña
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // Insertar nuevo usuario
        $stmt = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena, rol, correo, telefono) VALUES (?, ?, ?, ?, ?)');
        if ($stmt->execute([$nombre_usuario, $contrasena_hash, $rol, $correo, $telefono])) {
            header('Location: login.php');
            exit;
        } else {
            $error = "Hubo un error al registrar el usuario.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
    <!-- Fuente de Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Paleta de colores */
        :root {
            --primary-color: #2c3e50; /* Azul oscuro */
            --secondary-color: #34495e; /* Azul medio */
            --background-color: #ecf0f1; /* Blanco */
            --text-color: #333333; /* Gris oscuro */
            --light-gray: #bdc3c7; /* Gris claro */
            --error-color: #e74c3c; /* Rojo */
            --success-color: #2ecc71; /* Verde */
        }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        select:focus {
            border-color: var(--primary-color);
        }

        button[type="submit"],
        button[type="button"] {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            margin-bottom: 15px;
        }

        button[type="submit"]:hover {
            background-color: #1b2a3b;
        }

        button[type="button"] {
            background-color: var(--light-gray);
            color: var(--text-color);
        }

        button[type="button"]:hover {
            background-color: #d0d0d0;
        }

        p {
            text-align: center;
            margin-top: 15px;
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: var(--error-color);
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Registro de Usuario</h1>
    <form class="register-form" method="post" action="register.php">
        <div class="form-group">
            <label for="nombre_usuario"><i class="fas fa-user"></i></label>
            <input type="text" id="nombre_usuario" name="nombre_usuario" placeholder="Nombre de usuario" value="<?= htmlspecialchars($nombre_usuario ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="contrasena"><i class="fas fa-lock"></i></label>
            <input type="password" id="contrasena" name="contrasena" placeholder="Contraseña" required>
        </div>
        <div class="form-group">
            <label for="rol">Rol:</label>
            <select id="rol" name="rol" required>
                <option value="admin" <?= isset($rol) && $rol == 'admin' ? 'selected' : '' ?>>Administrador</option>
                <option value="usuario" <?= isset($rol) && $rol == 'usuario' ? 'selected' : '' ?>>Usuario</option>
            </select>
        </div>
        <div class="form-group">
            <label for="correo"><i class="fas fa-envelope"></i></label>
            <input type="email" id="correo" name="correo" placeholder="Correo electrónico" value="<?= htmlspecialchars($correo ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="telefono"><i class="fas fa-phone"></i></label>
            <input type="text" id="telefono" name="telefono" placeholder="Teléfono" value="<?= htmlspecialchars($telefono ?? '') ?>">
        </div>
        <?php if (isset($error)) { echo "<p class='error-message'>$error</p>"; } ?>
        <button type="submit">Registrar</button>
        <button type="button" onclick="window.location.href='login.php'">Cancelar</button>
    </form>
</div>

</body>
</htmldw
