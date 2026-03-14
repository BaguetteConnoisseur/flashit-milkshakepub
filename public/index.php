<?php
// index.php
require_once("/var/www/html/private/initialize.php");

// handle_login_post now returns a string error if login fails, or false if nothing happened
$errorMessage = handle_login_post();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashit Milkshake Pub - Dashboard</title>
    <link rel="icon" href="img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="img/logo/favicon.png" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            margin: 0 auto 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeInUp 0.6s ease;
        }
        
        .login-card h2 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9e9e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            float: right;
        }
        
        .btn-logout:hover {
            background: #c53030;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.4);
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border-left: 4px solid #e53e3e;
        }

        .public-screen-link {
            max-width: 400px;
            margin: -20px auto 40px;
            text-align: center;
        }

        .btn-open-bar {
            display: inline-block;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            box-sizing: border-box;
        }

        .btn-open-bar:hover {
            transform: translateY(-2px);
            border-color: #06b6d4;
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.25);
        }
        
        .views-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            animation: fadeInUp 0.6s ease 0.2s both;
        }
        
        .view-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .view-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .view-card:hover::before {
            transform: scaleX(1);
        }
        
        .view-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .view-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        .view-card h3 {
            font-size: 1.4rem;
            margin-bottom: 8px;
            color: #1a202c;
        }
        
        .view-card p {
            color: #718096;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .view-card.cashier { --accent: #f59e0b; }
        .view-card.delivery { --accent: #10b981; }
        .view-card.milkshake { --accent: #ec4899; }
        .view-card.toast { --accent: #f97316; }
        .view-card.inventory { --accent: #8b5cf6; }
        .view-card.bar { --accent: #06b6d4; }
        .view-card.stats { --accent: #0ea5e9; }
        .view-card.leaderboard { --accent: #22c55e; }
        .view-card.startup { --accent: #3b82f6; }
        
        .view-card.cashier .view-card-icon { background: rgba(245, 158, 11, 0.1); }
        .view-card.delivery .view-card-icon { background: rgba(16, 185, 129, 0.1); }
        .view-card.milkshake .view-card-icon { background: rgba(236, 72, 153, 0.1); }
        .view-card.toast .view-card-icon { background: rgba(249, 115, 22, 0.1); }
        .view-card.inventory .view-card-icon { background: rgba(139, 92, 246, 0.1); }
        .view-card.bar .view-card-icon { background: rgba(6, 182, 212, 0.1); }
        .view-card.stats .view-card-icon { background: rgba(14, 165, 233, 0.12); }
        .view-card.leaderboard .view-card-icon { background: rgba(34, 197, 94, 0.14); }
        .view-card.startup .view-card-icon { background: rgba(59, 130, 246, 0.14); }
        
        .logout-wrapper {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .views-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>🥤 Flashit Milkshake Pub</h1>
            <p>Beställningsark</p>
        </div>

        <?php if (!$loggedIn): ?>
            <div class="login-card">
                <h2>Välkommen tillbaka</h2>
                
                <?php if ($errorMessage): ?>
                    <div class="error-message">
                        Ogiltiga uppgifter. Försök igen.
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= csrf_token_input() ?>
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Användarnamn" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Lösenord" required autocomplete="current-password">
                    </div>
                    <button type="submit" name="login" class="btn-login">Logga in</button>
                </form>
            </div>

            <div class="public-screen-link">
                <a class="btn-open-bar" href="views/bar-view.php">
                    Öppna Barvy
                </a>
            </div>
        <?php else: ?>
            <div class="logout-wrapper">
                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" style="display: inline;">
                    <?= csrf_token_input() ?>
                    <button type="submit" name="logout-account" class="btn-logout">
                        Logga ut
                    </button>
                </form>
            </div>

            <div class="views-grid">
                <a href="views/startup-view.php" class="view-card startup">
                    <div class="view-card-icon">🚀</div>
                    <h3>Uppstart</h3>
                    <p>Starta ny pub och gå direkt till lagervy eller kassörvy i ett steg.</p>
                </a>

                <a href="views/cashier-view.php" class="view-card cashier">
                    <div class="view-card-icon">💰</div>
                    <h3>Kassörsvy</h3>
                    <p>Hantera beställningar och behandla kundtransaktioner.</p>
                </a>

                <a href="views/delivery-view.php" class="view-card delivery">
                    <div class="view-card-icon">🚚</div>
                    <h3>Leveransvy</h3>
                    <p>Hantera leveranser och servering av beställda produkter.</p>
                </a>

                <a href="views/milkshake-view.php" class="view-card milkshake">
                    <div class="view-card-icon">🥤</div>
                    <h3>Milkshakestation</h3>
                    <p>Förbered milkshakes och uppdatera beställningsstatus i realtid.</p>
                </a>

                <a href="views/toast-view.php" class="view-card toast">
                    <div class="view-card-icon">🍞</div>
                    <h3>Toaststation</h3>
                    <p>Hantera toastförberedelser och spåra orderns framsteg.</p>
                </a>

                <a href="views/bar-view.php" class="view-card bar">
                    <div class="view-card-icon">📊</div>
                    <h3>Barvy</h3>
                    <p>Översikt över alla beställningar och köksstatusvisning för att visa up på bardatorn.</p>
                </a>

                <a href="views/statistics-view.php" class="view-card stats">
                    <div class="view-card-icon">📈</div>
                    <h3>Statistik</h3>
                    <p>Se försäljning per smak, totaler och hantera rensning av orderhistorik.</p>
                </a>

                <a href="admin_action/inventory_manager.php" class="view-card inventory">
                    <div class="view-card-icon">📦</div>
                    <h3>Lagerhanterare</h3>
                    <p>Hantera menyalternativ och ingredienser.</p>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>