<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cancelaciones y Reembolsos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://kit.fontawesome.com/a2d9d6d82e.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --color-primario: #2c3e50;
            --color-secundario: #3498db;
            --color-fondo: #f5f6fa;
            --color-borde: #e0e0e0;
            --color-accent: #e74c3c;
            --color-background: #ffffff;
            --color-text: #333333;
            --color-border: #bdc3c7;
            --color-shadow: rgba(0, 0, 0, 0.1);
            --color-letters: #ffffff;
        }

        body {
            background-color: var(--color-fondo);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .section-title {
            margin-top: 40px;
            margin-bottom: 20px;
            font-weight: bold;
            border-bottom: 2px solid #ccc;
            padding-bottom: 5px;
        }

        .table thead {
            background-color: #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f1f1f1;
        }

        .btn + .btn {
            margin-left: 5px;
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
            display: flex;
            flex-direction: column;
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
            top: 10px;
            left: 10px;
            background: var(--color-primario);
            color: var(--color-letters);
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
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }

        .overlay.active {
            display: block;
        }

        .contenido {
            margin-left: 250px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
      .sidebar {
        left: -250px;
      }

      .sidebar.active {
        left: 0;
      }

      .toggle-sidebar {
        display: block;
      }

      .contenido {
        margin-left: 0;
        padding-top: 60px;
        padding: 20px;
      }
    }
        @media (max-width: 480px) {
            .contenido {
                padding: 15px;
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
</head>
<body>
<button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
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
<div class="contenido">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Cancelaciones y Reembolsos</h2>
        <a href="cancelar_reserva.php" class="btn btn-outline-primary"><i class="fas fa-ban me-1"></i> Nueva Cancelación</a>
    </div>

    <!-- Filtro y acciones -->
    <form method="GET" class="row g-3 align-items-end mb-4">
        <div class="col-md-4">
            <label class="form-label">Buscar por ID o Nombre</label>
            <input type="text" class="form-control" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Ej. Juan o #12">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Buscar</button>
        </div>
        <div class="col-md-2">
            <a href="cancelaciones.php" class="btn btn-secondary w-100"><i class="fas fa-sync-alt"></i> Reset</a>
        </div>
        <div class="col-md-2">
            <a href="exportar_excel.php" class="btn btn-success w-100"><i class="fas fa-file-excel"></i> Excel</a>
        </div>
        <div class="col-md-2">
            <a href="exportar_pdf.php" class="btn btn-danger w-100"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </form>

    <!-- Dashboard -->
    <?php
    $reemb_aprob = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'aprobado'")->fetch();
    $reemb_pend = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM reembolsos WHERE estado = 'pendiente'")->fetch();
    $indem = $pdo->query("SELECT COUNT(*) as cantidad, SUM(monto) as total FROM indemnizaciones")->fetch();
    ?>
    <div class="row text-center mb-5">
        <div class="col-md-4 mb-3">
            <div class="card border-success shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-success">✅ Reembolsos Aprobados</h5>
                    <p class="card-text fs-5 fw-bold"><?php echo $reemb_aprob['cantidad'] ?? 0; ?> registros</p>
                    <p class="card-text">$<?php echo number_format($reemb_aprob['total'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-warning shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-warning">⏳ Reembolsos Pendientes</h5>
                    <p class="card-text fs-5 fw-bold"><?php echo $reemb_pend['cantidad'] ?? 0; ?> registros</p>
                    <p class="card-text">$<?php echo number_format($reemb_pend['total'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-danger shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-danger">⚠️ Indemnizaciones</h5>
                    <p class="card-text fs-5 fw-bold"><?php echo $indem['cantidad'] ?? 0; ?> registros</p>
                    <p class="card-text">$<?php echo number_format($indem['total'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reembolsos Pendientes -->
    <h4 class="section-title">🔁 Reembolsos Pendientes</h4>
    <table class="table table-hover table-bordered table-sm">
        <thead>
            <tr>
                <th>ID</th><th>Recibo</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("SELECT * FROM reembolsos WHERE estado = 'pendiente'");
        while ($r = $stmt->fetch()) {
            echo "<tr>
                <td>{$r['id']}</td>
                <td>#{$r['id_recibo']}</td>
                <td>\${$r['monto']}</td>
                <td>{$r['metodo']}</td>
                <td><span class='badge bg-warning text-dark'>{$r['estado']}</span></td>
                <td>{$r['fecha']}</td>
                <td>
                    <form method='post' action='aprobar_reembolso.php' class='d-inline'>
                        <input type='hidden' name='id_reembolso' value='{$r['id']}'>
                        <button name='accion' value='aprobar' class='btn btn-success btn-sm'>Aprobar</button>
                        <button name='accion' value='rechazar' class='btn btn-danger btn-sm'>Rechazar</button>
                    </form>
                </td>
            </tr>";
        }
        ?>
        </tbody>
    </table>

    <!-- Indemnizaciones -->
    <h4 class="section-title">💸 Indemnizaciones Registradas</h4>
    <table class="table table-hover table-bordered table-sm">
        <thead>
            <tr><th>ID</th><th>Reserva</th><th>Monto</th><th>Motivo</th><th>Fecha</th></tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("SELECT * FROM indemnizaciones ORDER BY fecha DESC");
        while ($i = $stmt->fetch()) {
            echo "<tr>
                <td>{$i['id']}</td>
                <td>#{$i['id_reserva']}</td>
                <td>\${$i['monto']}</td>
                <td>{$i['motivo']}</td>
                <td>{$i['fecha']}</td>
            </tr>";
        }
        ?>
        </tbody>
    </table>

    <!-- Cancelaciones -->
    <h4 class="section-title">📄 Historial de Cancelaciones</h4>
    <table class="table table-hover table-bordered table-sm">
        <thead><tr><th>ID</th><th>Reserva</th><th>Nombre</th><th>Motivo</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php
        if ($busqueda !== '') {
            $stmt = $pdo->prepare("
                SELECT c.*, h.nombre 
                FROM cancelaciones c
                JOIN recibos r ON r.id = c.id_reserva
                JOIN huespedes h ON h.id = r.id_huesped
                WHERE c.id_reserva LIKE :term OR h.nombre LIKE :term2
                ORDER BY c.fecha DESC
            ");
            $stmt->execute([
                'term' => "%$busqueda%",
                'term2' => "%$busqueda%"
            ]);
        } else {
            $stmt = $pdo->query("
                SELECT c.*, h.nombre 
                FROM cancelaciones c
                JOIN recibos r ON r.id = c.id_reserva
                JOIN huespedes h ON h.id = r.id_huesped
                ORDER BY c.fecha DESC
            ");
        }

        while ($c = $stmt->fetch()) {
            echo "<tr>
                <td>{$c['id']}</td>
                <td>#{$c['id_reserva']}</td>
                <td>{$c['nombre']}</td>
                <td>{$c['motivo']}</td>
                <td>{$c['fecha']}</td>
            </tr>";
        }
        ?>
        </tbody>
    </table>
    <!-- Reembolsos Rechazados -->
<h4 class="section-title text-danger">🚫 Reembolsos Rechazados</h4>
<table class="table table-hover table-bordered table-sm">
    <thead>
        <tr>
            <th>ID</th><th>Recibo</th><th>Monto</th><th>Método</th><th>Estado</th><th>Fecha</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $stmt = $pdo->query("SELECT * FROM reembolsos WHERE estado = 'rechazado'");
    while ($r = $stmt->fetch()) {
        echo "<tr>
            <td>{$r['id']}</td>
            <td>#{$r['id_recibo']}</td>
            <td>\${$r['monto']}</td>
            <td>{$r['metodo']}</td>
            <td><span class='badge bg-danger'>{$r['estado']}</span></td>
            <td>{$r['fecha']}</td>
        </tr>";
    }
    ?>
    </tbody>
</table>

</div>
     </div>
    </div>
    <script>
  document.addEventListener("DOMContentLoaded", function() {
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
