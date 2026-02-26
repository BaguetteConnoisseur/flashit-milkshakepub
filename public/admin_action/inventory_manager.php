<?php
require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");

if (isset($_POST['logout-account'])) {
    session_destroy();  
    header("Location: " . WWW_ROOT . "/index.php");
    exit;
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- DATA FETCHING ---

// Fetch Milkshakes
$query = "SELECT milkshake_id AS item_id, name, description, ingredients, color, 'milkshake' AS type FROM milkshakes";
$result = mysqli_query($conn, $query);
$milkshakeInventory = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Fetch Toasts
$query = "SELECT toast_id AS item_id, name, description, ingredients, color, 'toast' AS type FROM toasts";
$result = mysqli_query($conn, $query);
$toastInventory = mysqli_fetch_all($result, MYSQLI_ASSOC);

// --- FORM HANDLING ---

// Add milkshake
if (isset($_POST['add-milkshake'])) {
    $milkshakeName = htmlspecialchars($_POST['milkshake-name']);
    $description = htmlspecialchars($_POST['description']);
    $ingredients = htmlspecialchars($_POST['ingredients']);
    $color = htmlspecialchars($_POST['color']);

    if (!empty($milkshakeName) && !empty($description) && !empty($ingredients) && !empty($color)) {
        $milkshakeName = mysqli_real_escape_string($conn, $milkshakeName);
        $description = mysqli_real_escape_string($conn, $description);
        $ingredients = mysqli_real_escape_string($conn, $ingredients);
        $color = mysqli_real_escape_string($conn, $color);
        
        $query = "INSERT INTO milkshakes (name, description, ingredients, color) VALUES ('$milkshakeName', '$description', '$ingredients', '$color')";
        mysqli_query($conn, $query);
        
        // Use Javascript redirect to prevent form resubmission on refresh
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

// Add toast
if (isset($_POST['add-toast'])) {
    $toastName = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);
    $ingredients = htmlspecialchars($_POST['ingredients']);
    $color = htmlspecialchars($_POST['color']);

    if (!empty($toastName) && !empty($description) && !empty($ingredients) && !empty($color)) {
        $toastName = mysqli_real_escape_string($conn, $toastName);
        $description = mysqli_real_escape_string($conn, $description);
        $ingredients = mysqli_real_escape_string($conn, $ingredients);
        $color = mysqli_real_escape_string($conn, $color);
        
        $query = "INSERT INTO toasts (name, description, ingredients, color) VALUES ('$toastName', '$description', '$ingredients', '$color')";
        mysqli_query($conn, $query);

        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

// Remove toast
if (isset($_POST['remove-toast'])) {
    $removeId = intval($_POST['toast-id'] ?? 0);
    if ($removeId > 0) {
        $delQuery = "DELETE FROM toasts WHERE toast_id = $removeId";
        mysqli_query($conn, $delQuery);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

// Remove milkshake
if (isset($_POST['remove-milkshake'])) {
    $removeId = intval($_POST['milkshake-id'] ?? 0);
    if ($removeId > 0) {
        $delQuery = "DELETE FROM milkshakes WHERE milkshake_id = $removeId";
        mysqli_query($conn, $delQuery);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        :root {
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --danger: #ef4444;
            --accent-milkshake: #3b82f6;
            --accent-toast: #f97316;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
        }

        h1, h2 { margin-top: 0; font-weight: 700; color: var(--text-main); }
        h1 { margin-bottom: 2rem; text-align: center; }
        h2 { font-size: 1.25rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }

        /* Grid Layout */
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .grid-container { grid-template-columns: 1fr; }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px 16px; color: var(--text-sub); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--bg); vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        
        /* Specific Column Widths */
        td:nth-child(2) { font-weight: 600; color: var(--text-main); width: 18%; } /* Name */
        td:nth-child(3) { color: var(--text-sub); width: 25%; } /* Desc */
        td:nth-child(4) { font-size: 0.85rem; color: var(--text-sub); width: 25%; } /* Ingredients */
        td:nth-child(5) { width: 10%; text-align: center; } /* Color */
        td:nth-child(6) { width: 10%; } /* Action */

        /* Buttons */
        .btn-remove {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fee2e2;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-remove:hover { background: var(--danger); color: white; border-color: var(--danger); }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-sub); }
        input[type="text"], textarea {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #f9fafb;
            color: var(--text-main);
            transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }

        /* Accents */
        .milkshake-section h2 { color: var(--accent-milkshake); border-bottom-color: var(--accent-milkshake); }
        .toast-section h2 { color: var(--accent-toast); border-bottom-color: var(--accent-toast); }

        .btn-milkshake { background: var(--accent-milkshake); }
        .btn-toast { background: var(--accent-toast); }

    </style>
</head>
<body style="display: none;">
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <h1>Inventory Manager</h1>

    <div class="grid-container">
        
        <section class="card milkshake-section">
            <h2>Milkshake Inventory</h2>
            <div class="table-wrapper">
                <?php if (empty($milkshakeInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">No milkshakes found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Ingredients</th>
                                <th>Color</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milkshakeInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto;"></div></td>
                                <td>
                                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                        <input type="hidden" name="milkshake-id" value="<?= $item['item_id'] ?>">
                                        <input type="submit" name="remove-milkshake" class="btn-remove" value="Remove" onclick="return confirm('Delete this milkshake?');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card toast-section">
            <h2>Toast Inventory</h2>
            <div class="table-wrapper">
                <?php if (empty($toastInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">No toasts found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Ingredients</th>
                                <th>Color</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($toastInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto;"></div></td>
                                <td>
                                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                        <input type="hidden" name="toast-id" value="<?= $item['item_id'] ?>">
                                        <input type="submit" name="remove-toast" class="btn-remove" value="Remove" onclick="return confirm('Delete this toast?');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card milkshake-section">
            <h2>Add New Milkshake</h2>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <div class="form-group">
                    <label for="milkshake-name">Name</label>
                    <input id="milkshake-name" name="milkshake-name" type="text" required maxlength="255" placeholder="e.g. Chocolate Supreme">
                </div>
                <div class="form-group">
                    <label for="m-desc">Description</label>
                    <textarea id="m-desc" name="description" rows="3" required placeholder="Short description for the menu..."></textarea>
                </div>
                <div class="form-group">
                    <label for="m-ing">Ingredients (semi-colon separated)</label>
                    <textarea id="m-ing" name="ingredients" rows="2" required placeholder="e.g. Milk; Chocolate Ice Cream; Cocoa"></textarea>
                </div>
                <div class="form-group">
                    <label for="m-color">Color</label>
                    <input id="m-color" name="color" type="color" required value="#ffffff">
                </div>
                <input name="add-milkshake" type="submit" class="btn-submit btn-milkshake" value="Add Milkshake">
            </form>
        </section>

        <section class="card toast-section">
            <h2>Add New Toast</h2>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <div class="form-group">
                    <label for="toast-name">Name</label>
                    <input id="toast-name" name="name" type="text" required maxlength="255" placeholder="e.g. Ham & Cheese">
                </div>
                <div class="form-group">
                    <label for="t-desc">Description</label>
                    <textarea id="t-desc" name="description" rows="3" required placeholder="Short description for the menu..."></textarea>
                </div>
                <div class="form-group">
                    <label for="t-ing">Ingredients (semi-colon separated)</label>
                    <textarea id="t-ing" name="ingredients" rows="2" required placeholder="e.g. Sourdough; Ham; Cheddar; Butter"></textarea>
                </div>
                <div class="form-group">
                    <label for="t-color">Color</label>
                    <input id="t-color" name="color" type="color" required value="#ffffff">
                </div>
                <input name="add-toast" type="submit" class="btn-submit btn-toast" value="Add Toast">
            </form>
        </section>

    </div>
    <?php include(SHARED_PATH . "/public_footer.php"); ?>

    <script>
        // Password protection
        function checkPassword() {
            const password = prompt("Enter password:");
            if (password === "admin") {
                document.body.style.display = "block";
            } else {
                alert("Incorrect password. Access denied.");
                // Redirect to home page or keep prompting
                window.location.href = "../../index.php";
            }
        }

        // Run password check when page loads
        window.addEventListener('load', function() {
            checkPassword();
        });
    </script>

</body>
</html>