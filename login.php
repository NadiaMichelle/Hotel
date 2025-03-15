<?php
// login.php
session_start();
require 'config.php';

$error = ''; // Para capturar errores

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener y limpiar los datos del formulario
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];

    // Validar que los campos no estén vacíos
    if (empty($nombre_usuario) || empty($contrasena)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        try {
            // Preparar la consulta para prevenir inyecciones SQL
            $stmt = $pdo->prepare('SELECT id, contrasena, rol, nombre_usuario FROM usuarios WHERE nombre_usuario = ?');
            $stmt->execute([$nombre_usuario]);
            $usuario = $stmt->fetch();

            // Verificar si el usuario existe y si la contraseña es correcta
            if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
                // Iniciar sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_usuario']; // Establecer nombre de usuario

                // Redirigir según el rol
                if ($usuario['rol'] === 'admin') {
                    header('Location: index.php');
                    exit;
                } elseif ($usuario['rol'] === 'usuario') {
                    header('Location: bottom_menu.php'); // Cambiar a index.php o la página principal del usuario
                    exit;
                } else {
                    $error = "Rol de usuario no reconocido.";
                }
            } else {
                $error = "Nombre de usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            // Manejo de errores de la base de datos
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
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
            height: 100vh;
        }

        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group i {
            position: absolute;
            top: 35px;
            left: 15px;
            color: var(--light-gray);
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--primary-color);
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        button[type="submit"]:hover {
            background-color: #1b2a3b;
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

<div class="login-container">
    <h1>Iniciar Sesión</h1>
    <form class="login-form" method="post">
        <div class="form-group">
            <label for="nombre_usuario"><i class="fas fa-user"></i></label>
            <input type="text" id="nombre_usuario" name="nombre_usuario" placeholder="Nombre de usuario" required>
        </div>
        <div class="form-group">
            <label for="contrasena"><i class="fas fa-lock"></i></label>
            <input type="password" id="contrasena" name="contrasena" placeholder="Contraseña" required>
        </div>
        <?php if (isset($error)) { echo "<p class='error-message'>$error</p>"; } ?>
        <button type="submit">Iniciar Sesión</button>
    </form>
    <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>.</p>
</div>

</body>
</html>