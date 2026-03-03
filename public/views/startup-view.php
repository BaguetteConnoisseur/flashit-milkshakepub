<?php
require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

if (!$loggedIn) {
    header("Location: " . WWW_ROOT . "/index.php");
    exit;
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function escape($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];
$activePubName = $pubTracking['active_pub_name'];
ensure_pub_menu_links($conn, $activePubId);

function get_open_order_count($conn, $activePubId) {
    $query = "
        SELECT COUNT(*) AS open_count
        FROM orders
        WHERE event_id = $activePubId
          AND status <> 'Delivered'
    ";
    $result = mysqli_query($conn, $query);
    $row = $result ? mysqli_fetch_assoc($result) : ['open_count' => 0];

    return (int) ($row['open_count'] ?? 0);
}

$feedback = null;

if (isset($_POST['start_new_pub'])) {
    $pubName = trim($_POST['new_pub_name'] ?? '');

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
                mysqli_query($conn, "UPDATE sales_events SET is_active = 0, ended_at = NOW() WHERE is_active = 1");
                $safePubName = escape($conn, $pubName);
                mysqli_query($conn, "INSERT INTO sales_events (event_name, is_active) VALUES ('$safePubName', 1)");

                $activePubId = (int) mysqli_insert_id($conn);
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

        .subtitle {
            margin-bottom: 1rem;
            color: var(--text-sub);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
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
        <h1>Uppstart</h1>
        <p class="subtitle">Aktiv pub: <strong><?= htmlspecialchars($activePubName) ?></strong></p>

        <?php if ($feedback): ?>
            <div class="feedback <?= $feedback['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($feedback['message']) ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Starta ny pub</h2>
            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                <div class="row">
                    <input type="text" name="new_pub_name" placeholder="MSP namn" required>
                    <button type="submit" name="start_new_pub" class="btn-primary">Starta</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Snabbval</h2>
            <div class="quick-links">
                <a class="link-btn" href="../admin_action/inventory_manager.php">Lagerhanterare</a>
                <a class="link-btn" href="cashier-view.php">Kassörsvy</a>
                <a class="link-btn" href="bar-view.php">Barvy</a>
                <a class="link-btn" href="statistics-view.php">Statistik</a>
            </div>
        </section>
    </div>
</body>
</html>
