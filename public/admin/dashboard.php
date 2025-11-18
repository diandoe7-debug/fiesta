<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Sistema de Votación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #007bff, #28a745);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .user-badge {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        
        .user-badge.role {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px 25px;
            border-radius: 15px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #28a745, #ffc107);
        }
        
        .menu-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .menu-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .menu-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .menu-card p {
            color: #7f8c8d;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(220,53,69,0.3);
        }
        
        .btn-logout:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220,53,69,0.4);
            background: linear-gradient(135deg, #c82333, #a71e2a);
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.9);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #007bff;
            display: block;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .menu-card {
            animation: fadeIn 0.6s ease-out;
        }
        
        .menu-card:nth-child(1) { animation-delay: 0.1s; }
        .menu-card:nth-child(2) { animation-delay: 0.2s; }
        .menu-card:nth-child(3) { animation-delay: 0.3s; }
        .menu-card:nth-child(4) { animation-delay: 0.4s; }
        .menu-card:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 Panel de Administración</h1>
            <p>Bienvenido al centro de control del sistema</p>
            
            <div class="user-info">
                <div class="user-badge">
                    👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <div class="user-badge role">
                    ⚡ <?php echo htmlspecialchars($_SESSION['user_role']); ?>
                </div>
            </div>
            
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-number">5</span>
                    <span class="stat-label">Módulos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Disponible</span>
                </div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="jurados.php" class="menu-card">
                <span class="menu-icon">🧑‍⚖️</span>
                <h3>Gestión de Jurados</h3>
                <p>Administrar usuarios jurados y sus permisos</p>
            </a>
            
            <a href="eventos.php" class="menu-card">
                <span class="menu-icon">🎉</span>
                <h3>Eventos</h3>
                <p>Crear y gestionar eventos de votación</p>
            </a>
            
            <a href="categorias.php" class="menu-card">
                <span class="menu-icon">🏷️</span>
                <h3>Categorías</h3>
                <p>Configurar criterios de evaluación</p>
            </a>
            
            <a href="participantes.php" class="menu-card">
                <span class="menu-icon">👩‍🎤</span>
                <h3>Participantes</h3>
                <p>Gestionar concursantes y representantes</p>
            </a>
            
            <a href="resultados.php" class="menu-card">
                <span class="menu-icon">📊</span>
                <h3>Resultados</h3>
                <p>Ver reportes y estadísticas detalladas</p>
            </a>
        </div>

        <div class="actions">
            <a href="../logout.php" class="btn-logout">
                <span>🚪</span> Cerrar Sesión
            </a>
        </div>
    </div>

    <script>
        // Efectos interactivos adicionales
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.menu-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.background = 'rgba(255, 255, 255, 1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.background = 'rgba(255, 255, 255, 0.95)';
                });
            });
        });
    </script>
</body>
</html>