<?php
/* --- 1. Startup View Bootstrap --- */

require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

require_login();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* 2. Helpers */
$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];
$activePubName = $pubTracking['active_pub_name'];
ensure_pub_menu_links($conn, $activePubId);

/* 3. Open Order Guard */
function get_open_order_count($conn, $activePubId) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS open_count FROM orders WHERE event_id = ? AND status <> 'Delivered'");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : ['open_count' => 0];
    mysqli_stmt_close($stmt);

    return (int) ($row['open_count'] ?? 0);
}

$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

/* 4. Actions (Start New Pub) */
if (isset($_POST['start_new_pub'])) {
    $pubName = trim($_POST['new_msp_name'] ?? '');

    if ($pubName === '') {
        $feedback = ['type' => 'error', 'message' => 'MSP namn är obligatoriskt.'];
    } else {
        $openOrderCount = get_open_order_count($conn, $activePubId);

        if ($openOrderCount > 0) {
            $feedback = [
                'type' => 'error',
                'message' => "Du kan inte starta en ny pub förrän alla beställningar är levererade. Kvarvarande beställningar: $openOrderCount.",
            ];
        } else {
            mysqli_begin_transaction($conn);

            try {
                $stmt = mysqli_prepare($conn, "UPDATE sales_events SET is_active = 0, ended_at = NOW() WHERE is_active = 1");
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                $stmt = mysqli_prepare($conn, "INSERT INTO sales_events (event_name, is_active) VALUES (?, 1)");
                mysqli_stmt_bind_param($stmt, 's', $pubName);
                mysqli_stmt_execute($stmt);
                $activePubId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                ensure_pub_menu_links($conn, $activePubId);

                mysqli_commit($conn);
                $activePubName = $pubName;
                $feedback = ['type' => 'success', 'message' => 'Ny pub startad och menyn är redo.'];
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $feedback = ['type' => 'error', 'message' => 'Kunde inte starta en ny pub.'];
            }
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uppstart</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        /* --- 5. Layout & Theme --- */
        :root {
            --bg: #f3f4f6;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --primary: #2563eb;
            --danger: #dc2626;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text-main);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        h1 {
            margin: 1.25rem 0 0.5rem;
            font-size: 2rem;
        }

        h2 {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 1.35rem;
        }

        .subtitle {
            margin-bottom: 1.5rem;
            color: var(--text-sub);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        input[type="text"] {
            flex: 1;
            min-width: 240px;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        button,
        .link-btn {
            display: inline-block;
            text-decoration: none;
            text-align: center;
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .quick-links {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }

        .link-btn {
            background: #eef2ff;
            color: #1e3a8a;
            border: 1px solid #c7d2fe;
            transition: all 0.2s;
        }

        .link-btn:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .feedback {
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
            margin-bottom: 0.9rem;
            font-weight: 500;
        }

        .feedback.success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .feedback.error {
            background: var(--error-bg);
            color: var(--error-text);
        }
    </style>
</head>
<body>
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="container">
        <h1>Guide: Kom igång</h1>
        <p class="subtitle">Aktiv pub: <strong><?= htmlspecialchars($activePubName) ?></strong></p>

        <?php if ($feedback): ?>
            <div class="feedback <?= $feedback['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($feedback['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Steg 1: Starta Event -->
        <section class="card">
            <h2>📋 Steg 1: Starta ett nytt MSP event</h2>
            <p style="color: var(--text-sub); margin-bottom: 1rem;">Börja med att skapa ett nytt pub-event när ni ska sälja. Detta håller ordning på alla beställningar för kvällen.</p>
            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_token_input() ?>
                <div class="row">
                    <input type="text" name="new_msp_name" placeholder="Namn på MSP (t.ex. MSP LP4 2026)" required>
                    <button type="submit" name="start_new_pub" class="btn-primary">Starta MSP</button>
                </div>
            </form>
        </section>

        <!-- Steg 2: Hantera Lager -->
        <section class="card">
            <h2>🏪 Steg 2: Hantera smaker</h2>
            <p style="color: var(--text-sub); margin-bottom: 1rem;">Aktivera de milkshakes och toasts ni vill sälja idag. Här kan ni också lägga till nya smaker och redigera befintliga.</p>
            <div class="quick-links">
                <a class="link-btn" href="../admin_action/inventory_manager.php">Öppna Lagerhanterare</a>
            </div>
        </section>

        <!-- Steg 3: Huvudvyer -->
        <section class="card">
            <h2>🎯 Steg 3: Använda huvudvyerna</h2>
            <p style="color: var(--text-sub); margin-bottom: 1rem;">Dessa vyer används under själva försäljningen. Öppna dem på separata skärmar/flikar för olika roller:</p>
            <div class="quick-links">
                <a class="link-btn" href="cashier-view.php">💰 Kassörsvy</a>
                <a class="link-btn" href="milkshake-view.php">🥤 Milkshake-vy</a>
                <a class="link-btn" href="toast-view.php">🍞 Toast-vy</a>
                <a class="link-btn" href="delivery-view.php">📦 Leveransvy</a>
                <a class="link-btn" href="bar-view.php">🍺 Barvy</a>
            </div>
            <div style="margin-top: 1rem; padding: 0.75rem; background: #f0f9ff; border-radius: 6px; font-size: 0.9rem;">
                <strong>Tips:</strong> Kassören tar emot beställningar, köket gör milkshakes/toasts, och baren levererar färdiga produkter till kunder.
            </div>
        </section>

        <!-- Steg 4: Övriga sidor -->
        <section class="card">
            <h2>📊 Andra användbara sidor</h2>
            <p style="color: var(--text-sub); margin-bottom: 1rem;">Ytterligare funktioner för uppföljning och redigering:</p>
            <div style="display: grid; gap: 0.75rem;">
                <div style="padding: 0.75rem; background: #f9fafb; border-radius: 6px; border-left: 3px solid var(--primary);">
                    <strong>📈 Statistik</strong> - Översikt av försäljning och orderstatistik för aktivt event
                    <div style="margin-top: 0.5rem;">
                        <a class="link-btn" href="statistics-view.php" style="font-size: 0.85rem; padding: 0.5rem 0.7rem;">Öppna Statistik</a>
                    </div>
                </div>
                <div style="padding: 0.75rem; background: #f9fafb; border-radius: 6px; border-left: 3px solid var(--primary);">
                    <strong>🏆 Leaderboard</strong> - Topplista över mest sålda produkter för aktivt event
                    <div style="margin-top: 0.5rem;">
                        <a class="link-btn" href="leaderboard-view.php" style="font-size: 0.85rem; padding: 0.5rem 0.7rem;">Öppna Leaderboard</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
