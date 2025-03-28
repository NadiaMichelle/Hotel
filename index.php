<?php
// Index.php Admin
session_start();
require 'config.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener el rol y el nombre del usuario desde la sesión
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario']; // Asegúrate de que esta variable esté establecida en el inicio de sesión
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Hotel Puesta del Sol</title>
    <link rel="icon" type="image/png" sizes="32x32" href="logo_hotel.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
  :root {
    --color-primary: #2c3e50;
    --color-secondary: #2ecc71;
    --color-accent: #e74c3c;
    --color-background: #f5f6fa;
    --color-text: #2c3e50;
    --color-border: #bdc3c7;
    --color-shadow: rgba(0, 0, 0, 0.1);
    --sidebar-width: 250px;
    --transition-speed: 0.3s;
}



/* Estilos generales */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Animación del degradado */
@keyframes gradient-animation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

 /*Aplicación de la animación al fondo */
.content, .welcome-box {
    background: linear-gradient(-45deg, #ff9a9e, #fad0c4,rgb(195, 119, 98),rgb(62, 103, 115));
    background-size: 400% 400%;
    animation: gradient-animation 15s ease infinite;
}

/* Contenido principal */
.content {
    flex: 1;
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Estilos para la caja de bienvenida */
.welcome-box {
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 6px var(--color-shadow);
    display: flex;
    align-items: center;
    max-width: 900px;
    width: 100%;
    background: rgba(255, 255, 255, 0.8);
    text-align: center;
    animation: fadeIn 1s ease-in-out;
}

.welcome-box .text {
    flex: 1;
    padding-right: 20px;
}

.welcome-box .text h1 {
    margin-top: 0;
    color: var(--color-primary);
    font-size: 2em;
}

.welcome-box .text p {
    color: var(--color-text);
    font-size: 1.2em;
}

.welcome-box .highlight {
    color: var(--color-accent);
    font-weight: bold;
}

/* Animación de la imagen */
.welcome-img {
    width: 300px;
    border-radius: 8px;
    box-shadow: 0 2px 4px var(--color-shadow);
    animation: zoomIn 2s ease-in-out;
}

/* Animación de desvanecimiento */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Animación de zoom */
@keyframes zoomIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

/* Estilos para el menú inferior */
.bottom-menu {
    background-color: var(--color-primary);
    padding: 10px 0;
    position: fixed;
    width: 100%;
    bottom: 0;
    box-shadow: 0 -2px 5px var(--color-shadow);
}

.bottom-menu ul {
    list-style: none;
    display: flex;
    justify-content: space-around;
    margin: 0;
    padding: 0;
    flex-wrap: wrap;
}

.bottom-menu ul li {
    flex: 1;
    text-align: center;
    min-width: 80px;
}

.bottom-menu ul li a {
    color: white;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 14px;
    padding: 10px 0;
}

.bottom-menu ul li a i {
    font-size: 20px;
    margin-bottom: 5px;
}

.bottom-menu .logout-btn {
    color: var(--color-accent);
}

/* Estilos responsivos */
@media (max-width: 768px) {
    .welcome-box {
        flex-direction: column;
        text-align: center;
    }

    .welcome-box .text {
        padding-right: 0;
        margin-bottom: 20px;
    }

    .welcome-box .welcome-img {
        width: 80%;
        max-width: 250px;
    }

    .bottom-menu ul {
        flex-direction: row;
    }

    .bottom-menu ul li a {
        font-size: 12px;
        padding: 8px 0;
    }

    .bottom-menu ul li a i {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .welcome-box .text h1 {
        font-size: 1.5em;
    }

    .welcome-box .text p {
        font-size: 1em;
    }

    .welcome-img {
        width: 60%;
    }

    .bottom-menu ul li a {
        font-size: 10px;
        padding: 6px 0;
    }

    .bottom-menu ul li a i {
        font-size: 16px;
    }
}
.user-info {
    display: flex;
    justify-content: center;
    align-items: center;
    background: rgba(44, 62, 80, 0.9); /* Fondo semitransparente para combinar con la paleta */
    color: white;
    padding: 12px 25px;
    border-radius: 12px;
    font-size: 1.3rem;
    font-weight: bold;
    box-shadow: 0 4px 10px var(--color-shadow);
    max-width: 80%;
    margin: 15px auto;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Animación sutil al pasar el mouse */
.user-info:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px var(--color-shadow);
}

/* Ajustar el texto */
.user-info span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Responsivo */
@media (max-width: 768px) {
    .user-info {
        font-size: 1.1rem;
        padding: 10px 20px;
        max-width: 90%;
    }
}

@media (max-width: 480px) {
    .user-info {
        font-size: 1rem;
        padding: 8px 15px;
        max-width: 95%;
    }
}



</style>
</head>
<body background="playa fondo.gif" >
<main class="content">
    <!-- Contenido principal (Bienvenida) -->
    <div class="welcome-box">
            <div class="text">
                <h1>Bienvenido  <?php echo htmlspecialchars($nombre_usuario); ?> al <span class="highlight">Sistema Hotel Puesta del Sol</span></h1>
                <p>Gestión de habitaciones, huéspedes, servicios y reservaciones</p>
            </div>
            <img src="background.jpg" alt="Hotel en la playa" class="welcome-img">
        </div>
    </main>
    <!-- Menú abajo (fijo en la parte inferior) -->
    <nav class="bottom-menu">
    <ul>
        <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
        <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
        <li><a href="configuracion.php"><i class="fas fa-cogs"></i> Configuración</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</nav>




</body>
</html