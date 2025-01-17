<?php
require 'config.php';

// DEBUGGING (Optional)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Function to anonymize IP addresses while keeping the last two octets visible.
 * - IPv4: "192.168.1.25" → "xxx.xxx.1.25"
 * - IPv6: "2001:db8::abcd:1234" → "xxxx:xxxx:xxxx:xxxx:xxxx:xxxx::1234"
 */
function anonymizeIp($ip) {
    // 1) If empty or invalid, return 'Unknown IP'
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'Unknown IP';
    }

    // 2) Handle IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);  // e.g. [192, 168, 1, 25]
        // Keep only the last 2 octets
        // e.g., 192.168.1.25 => xxx.xxx.1.25
        return 'xxx.xxx.' . $parts[2] . '.' . $parts[3];
    }

    // 3) Handle IPv6
    // Use inet_pton -> inet_ntop to ensure the address is expanded/uncompressed.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = @inet_pton($ip);       // Convert to 128-bit binary form
        if ($packed === false) {
            // Fall back if something unexpected
            return 'Unknown IP';
        }
        $expanded = inet_ntop($packed);  // Convert back => uncompressed form
        // e.g. "2001:0db8:0000:0000:0000:ff00:0042:8329"

        $hextets = explode(':', $expanded); // 8 parts
        // Keep the last 2, anonymize the first 6
        // e.g. => xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:0042:8329
        for ($i = 0; $i < 6; $i++) {
            $hextets[$i] = 'xxxx';
        }
        return implode(':', $hextets);
    }

    // 4) Fallback if neither recognized nor empty
    return 'Unknown IP';
}

// 1. FETCH THE RECIPE
$type = $_GET['type'] ?? null;
$id   = $_GET['id']   ?? null;

if (!$type || !$id) {
    echo 'Invalid request.';
    exit;
}

// Fetch main recipe
try {
    if ($type === 'drink') {
        $stmt = $conn->prepare("SELECT * FROM drink_combos WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM food_recipes WHERE id = ?");
    }
    $stmt->execute([$id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        echo 'Recipe not found.';
        exit;
    }
} catch (PDOException $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}

// 2. FETCH INGREDIENTS
try {
    if ($type === 'drink') {
        $stmtIng = $conn->prepare("SELECT * FROM ingredients WHERE recipe_type = ? AND drink_id = ?");
    } else {
        $stmtIng = $conn->prepare("SELECT * FROM ingredients WHERE recipe_type = ? AND food_id = ?");
    }
    $stmtIng->execute([$type, $id]);
    $ingredients = $stmtIng->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Error fetching ingredients: ' . htmlspecialchars($e->getMessage());
    exit;
}

// 3. FETCH DIRECTIONS
try {
    if ($type === 'drink') {
        $stmtDir = $conn->prepare("SELECT * FROM directions WHERE recipe_type = ? AND drink_id = ? ORDER BY id ASC");
    } else {
        $stmtDir = $conn->prepare("SELECT * FROM directions WHERE recipe_type = ? AND food_id = ? ORDER BY id ASC");
    }
    $stmtDir->execute([$type, $id]);
    $directions = $stmtDir->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Error fetching directions: ' . htmlspecialchars($e->getMessage());
    exit;
}

$anonymizedIp = anonymizeIp($recipe['last_edited_ip'] ?? '');

// 4. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic fields
    $name        = $_POST['name']        ?? '';
    $description = $_POST['description'] ?? '';
    $comments    = $_POST['comments']    ?? '';
    $custom_css  = $_POST['custom_css']  ?? '';

    // Re-capture the editor's IP
    $editorIp    = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    error_log("Full IP of the editor: $editorIp");

    // Collect updated/added ingredients
    // Each ingredient: ['name' => X, 'quantity' => Y, 'unit' => Z]
    $updatedIngredients = $_POST['ingredients'] ?? [];

    // Collect updated/added directions
    // Each direction: ['step' => X]
    $updatedDirections = $_POST['directions'] ?? [];

    // OPTIONAL: If you want to sanitize CSS again:
    function sanitize_css($css) {
        // Remove any `<style>` tags
        $css = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $css);

        // Remove any expressions or URL functions that can be exploited
        $css = preg_replace('/expression\s*\(|url\s*\(|@import\s+url\s*\(/i', '', $css);

        // Remove JavaScript events (e.g., onload, onclick)
        $css = preg_replace('/javascript:/i', '', $css);

        // Optionally, implement more sophisticated sanitization or use a library
        return $css;
    }

    $custom_css = sanitize_css($custom_css);

    try {
        // 1) Update main recipe table
        if ($type === 'drink') {
            $updateStmt = $conn->prepare("
                UPDATE drink_combos
                SET name = ?, description = ?, comments = ?, custom_css = ?, last_edited_ip = ?
                WHERE id = ?
            ");
        } else {
            $updateStmt = $conn->prepare("
                UPDATE food_recipes
                SET name = ?, description = ?, comments = ?, custom_css = ?, last_edited_ip = ?
                WHERE id = ?
            ");
        }
        $updateStmt->execute([$name, $description, $comments, $custom_css, $editorIp, $id]);

        // 2) Delete existing ingredients for this recipe
        if ($type === 'drink') {
            $deleteIng = $conn->prepare("DELETE FROM ingredients WHERE recipe_type = ? AND drink_id = ?");
            $deleteIng->execute([$type, $id]);
        } else {
            $deleteIng = $conn->prepare("DELETE FROM ingredients WHERE recipe_type = ? AND food_id = ?");
            $deleteIng->execute([$type, $id]);
        }

        // 3) Re-insert updated/added ingredients
        foreach ($updatedIngredients as $ing) {
            $ingName     = trim($ing['name'] ?? '');
            $ingQuantity = trim($ing['quantity'] ?? '');
            $ingUnit     = trim($ing['unit'] ?? '');

            // If user left name/quantity blank, skip
            if ($ingName === '' || $ingQuantity === '') {
                continue;
            }
            if ($type === 'drink') {
                $insIng = $conn->prepare("
                    INSERT INTO ingredients (recipe_type, drink_id, ingredient, quantity, unit)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insIng->execute([$type, $id, $ingName, $ingQuantity, $ingUnit]);
            } else {
                $insIng = $conn->prepare("
                    INSERT INTO ingredients (recipe_type, food_id, ingredient, quantity, unit)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insIng->execute([$type, $id, $ingName, $ingQuantity, $ingUnit]);
            }
        }

        // 4) Delete existing directions for this recipe
        if ($type === 'drink') {
            $deleteDir = $conn->prepare("DELETE FROM directions WHERE recipe_type = ? AND drink_id = ?");
            $deleteDir->execute([$type, $id]);
        } else {
            $deleteDir = $conn->prepare("DELETE FROM directions WHERE recipe_type = ? AND food_id = ?");
            $deleteDir->execute([$type, $id]);
        }

        // 5) Re-insert updated/added directions
        foreach ($updatedDirections as $dir) {
            $stepText = trim($dir['step'] ?? '');
            if ($stepText === '') {
                continue;
            }
            if ($type === 'drink') {
                $insDir = $conn->prepare("
                    INSERT INTO directions (recipe_type, drink_id, step)
                    VALUES (?, ?, ?)
                ");
                $insDir->execute([$type, $id, $stepText]);
            } else {
                $insDir = $conn->prepare("
                    INSERT INTO directions (recipe_type, food_id, step)
                    VALUES (?, ?, ?)
                ");
                $insDir->execute([$type, $id, $stepText]);
            }
        }

        // After successful update, redirect (or show a success message)
        header("Location: edit_recipe.php?type=$type&id=$id&updated=1");
        exit;

    } catch (PDOException $e) {
        echo '<p style="color: red;">Error updating recipe: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Recipe: <?= htmlspecialchars($recipe['name']) ?></title>
    <style>
        /* A little styling for clarity */
        .ingredients, .directions {
            margin-bottom: 2rem;
        }
        .ingredients div, .directions div {
            margin-bottom: 0.5rem;
        }
        .remove-btn {
            color: red;
            margin-left: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<?php if (isset($_GET['updated'])): ?>
    <p style="color: green;">Recipe updated successfully!</p>
<?php endif; ?>

<h1>Edit Recipe</h1>
<p><em>Last edited by IP: <strong><?= htmlspecialchars($anonymizedIp) ?></strong></em></p>

<form method="POST">

    <!-- Basic Fields -->
    <label for="name">Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($recipe['name']) ?>" required><br><br>

    <label for="description">Description:</label><br>
    <textarea name="description" rows="5" cols="50" required><?= htmlspecialchars($recipe['description']) ?></textarea><br><br>

    <label for="comments">Custom Comments:</label><br>
    <textarea name="comments" rows="4" cols="50"><?= htmlspecialchars($recipe['comments']) ?></textarea><br><br>

    <label for="custom_css">Custom CSS:</label><br>
    <textarea name="custom_css" rows="8" cols="60"><?= htmlspecialchars($recipe['custom_css']) ?></textarea><br><br>

    <!-- Ingredients Editing -->
    <h2>Ingredients</h2>
    <div class="ingredients" id="ingredients">
        <?php foreach ($ingredients as $i => $ing): ?>
            <div>
                <input type="text" name="ingredients[<?= $i ?>][name]"
                       value="<?= htmlspecialchars($ing['ingredient']) ?>"
                       placeholder="Ingredient name" required>
                <input type="text" name="ingredients[<?= $i ?>][quantity]"
                       value="<?= htmlspecialchars($ing['quantity']) ?>"
                       placeholder="Quantity" required>
                <select name="ingredients[<?= $i ?>][unit]">
                    <?php
                    $selectedUnit = $ing['unit'] ?? '';
                    $units = ["", "ml", "g", "cups", "tsp", "tbsp", "pcs", "oz"];
                    ?>
                    <?php foreach($units as $u): ?>
                        <option value="<?= $u ?>" <?= ($u === $selectedUnit ? 'selected' : '') ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="remove-btn" onclick="removeItem(this)">[X]</span>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="addIngredient()">+ Add Ingredient</button>

    <br><br>

    <!-- Directions Editing -->
    <h2>Directions</h2>
    <div class="directions" id="directions">
        <?php foreach ($directions as $i => $dir): ?>
            <div>
            <textarea name="directions[<?= $i ?>][step]" rows="2" cols="60"
            ><?= htmlspecialchars($dir['step']) ?></textarea>
                <span class="remove-btn" onclick="removeItem(this)">[X]</span>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="addDirection()">+ Add Direction</button>

    <br><br>

    <button type="submit">Save Changes</button>
</form>

<p>
    <a href="view_recipe.php?type=<?= urlencode($type) ?>&id=<?= urlencode($id) ?>">View This Recipe</a> |
    <a href="index.php">Back to Home</a>
</p>

<script>
    /**
     * Dynamically add new ingredient row
     */
    function addIngredient() {
        const container = document.getElementById('ingredients');
        const index = container.querySelectorAll('div').length;
        // Build new row
        const div = document.createElement('div');
        div.innerHTML = `
        <input type="text" name="ingredients[${index}][name]" placeholder="Ingredient name" required>
        <input type="text" name="ingredients[${index}][quantity]" placeholder="Quantity" required>
        <select name="ingredients[${index}][unit]">
            <option value="">(Unit)</option>
            <option value="ml">ml</option>
            <option value="g">g</option>
            <option value="cups">cups</option>
            <option value="tsp">tsp</option>
            <option value="tbsp">tbsp</option>
            <option value="pcs">pcs</option>
            <option value="oz">oz</option>
        </select>
        <span class="remove-btn" onclick="removeItem(this)">[X]</span>
    `;
        container.appendChild(div);
    }

    /**
     * Dynamically add new direction row
     */
    function addDirection() {
        const container = document.getElementById('directions');
        const index = container.querySelectorAll('div').length;
        const div = document.createElement('div');
        div.innerHTML = `
        <textarea name="directions[${index}][step]" rows="2" cols="60"></textarea>
        <span class="remove-btn" onclick="removeItem(this)">[X]</span>
    `;
        container.appendChild(div);
    }

    /**
     * Remove an ingredient/direction row
     * This just removes the DOM element. The server will ignore it if not present in the form.
     */
    function removeItem(el) {
        el.parentElement.remove();
    }
</script>

</body>
</html>
