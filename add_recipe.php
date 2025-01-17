<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // We sanitize or validate *before* using the inputs
    $recipe_type = filter_input(INPUT_POST, 'recipe_type', FILTER_SANITIZE_STRING);
    $name        = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $comments    = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // For custom CSS, we run the existing sanitize_css function
    $custom_css = $_POST['custom_css'] ?? '';
    $ingredients = $_POST['ingredients'] ?? []; // array
    $directions  = $_POST['directions']  ?? []; // array

    // 1) Additional CSS sanitization
    function sanitize_css($css) {
        // Remove <style> tags
        $css = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $css);
        // Remove expression(), url(), @import() calls
        $css = preg_replace('/expression\s*\(|url\s*\(|@import\s+url\s*\(/i', '', $css);
        // Remove javascript: protocols
        $css = preg_replace('/javascript:/i', '', $css);
        return $css;
    }
    $sanitized_css = sanitize_css($custom_css);

    // 2) Directions: remove empty steps
    $directions = array_filter($directions, fn($step) => !empty(trim($step)));

    try {
        // 3) Insert recipe with prepared statements
        if ($recipe_type === 'drink') {
            $stmt = $conn->prepare("
                INSERT INTO drink_combos (name, description, comments, custom_css) 
                VALUES (:name, :description, :comments, :css)
            ");
        } else {
            $stmt = $conn->prepare("
                INSERT INTO food_recipes (name, description, comments, custom_css) 
                VALUES (:name, :description, :comments, :css)
            ");
        }

        // Bind parameters and execute
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':comments', $comments);
        $stmt->bindParam(':css', $sanitized_css);
        $stmt->execute();

        $recipe_id = $conn->lastInsertId();

        // 4) Insert ingredients
        foreach ($ingredients as $ingredient) {
            // a) Basic sanitization
            $ingName     = filter_var($ingredient['name']     ?? '', FILTER_SANITIZE_STRING);
            $ingQuantity = filter_var($ingredient['quantity'] ?? '', FILTER_SANITIZE_STRING);
            $ingUnit     = filter_var($ingredient['unit']     ?? '', FILTER_SANITIZE_STRING);

            // b) Only insert if not empty
            if (!empty(trim($ingName)) && !empty(trim($ingQuantity))) {
                if ($recipe_type === 'drink') {
                    $stmtIng = $conn->prepare("
                        INSERT INTO ingredients (recipe_type, drink_id, ingredient, quantity, unit) 
                        VALUES (:rtype, :rid, :ing, :qty, :unit)
                    ");
                    $stmtIng->bindParam(':rtype', $recipe_type);
                    $stmtIng->bindParam(':rid',   $recipe_id);
                } else {
                    $stmtIng = $conn->prepare("
                        INSERT INTO ingredients (recipe_type, food_id, ingredient, quantity, unit) 
                        VALUES (:rtype, :rid, :ing, :qty, :unit)
                    ");
                    $stmtIng->bindParam(':rtype', $recipe_type);
                    $stmtIng->bindParam(':rid',   $recipe_id);
                }
                $stmtIng->bindParam(':ing',  $ingName);
                $stmtIng->bindParam(':qty',  $ingQuantity);
                $stmtIng->bindParam(':unit', $ingUnit);
                $stmtIng->execute();
            }
        }

        // 5) Insert directions
        foreach ($directions as $step) {
            // a) Sanitize
            $step = filter_var($step, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            // b) Insert if not empty
            if (!empty(trim($step))) {
                if ($recipe_type === 'drink') {
                    $stmtDir = $conn->prepare("
                        INSERT INTO directions (recipe_type, drink_id, step) 
                        VALUES (:rtype, :rid, :step)
                    ");
                    $stmtDir->bindParam(':rtype', $recipe_type);
                    $stmtDir->bindParam(':rid',   $recipe_id);
                } else {
                    $stmtDir = $conn->prepare("
                        INSERT INTO directions (recipe_type, food_id, step) 
                        VALUES (:rtype, :rid, :step)
                    ");
                    $stmtDir->bindParam(':rtype', $recipe_type);
                    $stmtDir->bindParam(':rid',   $recipe_id);
                }
                $stmtDir->bindParam(':step', $step);
                $stmtDir->execute();
            }
        }

        echo "<p style='color: green;'>Recipe, ingredients, directions, comments, and custom CSS added successfully!</p>";

    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Recipe</title>
    <style>
        /* Basic styling for the custom CSS editor */
        #css-editor {
            width: 100%;
            height: 200px;
            background-color: #2e3440;
            color: #d8dee9;
            font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
            padding: 10px;
            border: 1px solid #4c566a;
            overflow-y: auto;
            white-space: pre-wrap; /* Changed from pre to pre-wrap to handle line breaks better */
            outline: none;
            border-radius: 4px;
            box-sizing: border-box;
        }
        #css-editor:focus {
            border-color: #81a1c1;
        }
        .keyword {
            color: #81a1c1;
        }
        .property {
            color: #88c0d0;
        }
        .value {
            color: #a3be8c;
        }
        .selector {
            color: #b48ead;
        }
    </style>
</head>
<body>
<h1>Add New Recipe</h1>
<form method="POST">
    <label for="recipe_type">Recipe Type:</label>
    <select name="recipe_type" required>
        <option value="drink">Drink Combo</option>
        <option value="food">Food Recipe</option>
    </select><br><br>

    <label for="name">Recipe Name:</label>
    <input type="text" name="name" required><br><br>

    <label for="description">Description:</label><br>
    <textarea name="description" required></textarea><br><br>

    <!-- Existing Comments Field -->
    <label for="comments">Custom Comments:</label><br>
    <textarea name="comments" rows="4" cols="50"></textarea><br><br>

    <!-- New Custom CSS Field -->
    <label for="custom_css">Custom CSS:</label><br>
    <div id="css-editor" contenteditable="true" oninput="syncCSS()" spellcheck="false">// Enter your custom CSS here
    </div>
    <textarea name="custom_css" id="custom_css" style="display:none;"></textarea><br><br>

    <h2>Ingredients</h2>
    <div id="ingredients">
        <div>
            <label for="ingredients[0][name]">Ingredient:</label>
            <input type="text" name="ingredients[0][name]" required>
            <label for="ingredients[0][quantity]">Quantity:</label>
            <input type="text" name="ingredients[0][quantity]" required>
            <label for="ingredients[0][unit]">Unit:</label>
            <select name="ingredients[0][unit]">
                <option value="">Select Unit</option>
                <option value="ml">ml</option>
                <option value="g">g</option>
                <option value="cups">cups</option>
                <option value="tsp">tsp</option>
                <option value="tbsp">tbsp</option>
                <option value="pcs">pcs</option>
                <option value="oz">oz</option>
            </select>
        </div>
    </div>
    <button type="button" onclick="addIngredient()">Add Ingredient</button><br><br>

    <h2>Directions</h2>
    <div id="directions">
        <div>
            <label for="directions[0]">Step:</label>
            <textarea name="directions[0]"></textarea>
        </div>
    </div>
    <button type="button" onclick="addDirection()">Add Direction</button><br><br>

    <button type="submit">Add Recipe</button>
</form>

<script>
    function addIngredient() {
        const index = document.querySelectorAll('#ingredients div').length;
        const div = document.createElement('div');
        div.innerHTML = `
            <label for="ingredients[${index}][name]">Ingredient:</label>
            <input type="text" name="ingredients[${index}][name]" required>
            <label for="ingredients[${index}][quantity]">Quantity:</label>
            <input type="text" name="ingredients[${index}][quantity]" required>
            <label for="ingredients[${index}][unit]">Unit:</label>
            <select name="ingredients[${index}][unit]">
                <option value="">Select Unit</option>
                <option value="ml">ml</option>
                <option value="g">g</option>
                <option value="cups">cups</option>
                <option value="tsp">tsp</option>
                <option value="tbsp">tbsp</option>
                <option value="pcs">pcs</option>
                <option value="oz">oz</option>
            </select>
        `;
        document.getElementById('ingredients').appendChild(div);
    }

    function addDirection() {
        const index = document.querySelectorAll('#directions div').length;
        const div = document.createElement('div');
        div.innerHTML = `
            <label for="directions[${index}]">Step:</label>
            <textarea name="directions[${index}]"></textarea>
        `;
        document.getElementById('directions').appendChild(div);
    }

    // Function to sync the content of the custom CSS editor to the hidden textarea
    function syncCSS() {
        const editorContent = document.getElementById('css-editor').innerText;
        document.getElementById('custom_css').value = editorContent;
        applySyntaxHighlighting();
    }

    // Function to handle tab and enter keys in the custom CSS editor
    document.getElementById('css-editor').addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            insertAtCursor('    '); // Insert four spaces for indentation
        } else if (e.key === 'Enter') {
            e.preventDefault();
            insertAtCursor('\n'); // Insert a single newline
        }
    });

    // Function to insert text at the cursor position
    function insertAtCursor(text) {
        const editor = document.getElementById('css-editor');
        const selection = window.getSelection();
        if (!selection.rangeCount) return;
        const range = selection.getRangeAt(0);
        range.deleteContents();

        if (text === '\n') {
            // Insert a single <br> for Enter key
            const br = document.createElement('br');
            range.insertNode(br);
            // Move the cursor after the <br>
            range.setStartAfter(br);
            range.setEndAfter(br);
        } else {
            const textNode = document.createTextNode(text);
            range.insertNode(textNode);
            // Move the cursor after the inserted text
            range.setStartAfter(textNode);
            range.setEndAfter(textNode);
        }

        selection.removeAllRanges();
        selection.addRange(range);
        applySyntaxHighlighting();
    }

    // Basic syntax highlighting for the custom CSS editor
    function applySyntaxHighlighting() {
        const editor = document.getElementById('css-editor');
        let text = editor.innerText;

        // Escape HTML characters
        let html = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

        // Replace newlines with <br>
        html = html.replace(/\n/g, '<br>');

        // Highlight selectors (e.g., .class, #id, element)
        html = html.replace(/([.#]?[a-zA-Z0-9_-]+)\s*{/g, '<span class="selector">$1</span> {');

        // Highlight properties and values
        html = html.replace(/([a-zA-Z-]+)\s*:/g, '<span class="property">$1</span>:');
        html = html.replace(/:\s*([^;]+);/g, ': <span class="value">$1</span>;');

        editor.innerHTML = html;
        placeCaretAtEnd(editor);
    }

    // Function to place caret at the end of the contenteditable div
    function placeCaretAtEnd(el) {
        el.focus();
        if (typeof window.getSelection != "undefined"
            && typeof document.createRange != "undefined") {
            var range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    // Initial syntax highlighting
    applySyntaxHighlighting();
</script>
</body>
</html>
<?php
require 'config.php';

// Set Content Security Policy to mitigate risks from custom CSS
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");

$type = $_GET['type'] ?? null;
$id = $_GET['id'] ?? null;

if (!$type || !$id) {
    echo 'Invalid request.';
    exit;
}

try {
    if ($type === 'drink') {
        $stmt = $conn->prepare("SELECT * FROM drink_combos WHERE id = ?");
    } elseif ($type === 'food') {
        $stmt = $conn->prepare("SELECT * FROM food_recipes WHERE id = ?");
    } else {
        echo 'Invalid recipe type.';
        exit;
    }

    $stmt->execute([$id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        echo 'Recipe not found.';
        exit;
    }

    // Fetch ingredients
    if ($type === 'drink') {
        $ingredientStmt = $conn->prepare("SELECT * FROM ingredients WHERE recipe_type = ? AND drink_id = ?");
    } else {
        $ingredientStmt = $conn->prepare("SELECT * FROM ingredients WHERE recipe_type = ? AND food_id = ?");
    }
    $ingredientStmt->execute([$type, $id]);
    $ingredients = $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch directions
    if ($type === 'drink') {
        $directionStmt = $conn->prepare("SELECT step FROM directions WHERE recipe_type = ? AND drink_id = ? ORDER BY id ASC");
    } else {
        $directionStmt = $conn->prepare("SELECT step FROM directions WHERE recipe_type = ? AND food_id = ? ORDER BY id ASC");
    }
    $directionStmt->execute([$type, $id]);
    $directions = $directionStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($recipe['name']) ?></title>
    <?php if (!empty($recipe['custom_css'])): ?>
        <style>
            <?= htmlspecialchars($recipe['custom_css']) ?>
        </style>
    <?php endif; ?>
</head>
<body>
<h1><?= htmlspecialchars($recipe['name']) ?></h1>
<p><strong>Description:</strong> <?= nl2br(htmlspecialchars($recipe['description'])) ?></p>

<!-- Display Comments -->
<?php if (!empty($recipe['comments'])): ?>
    <p><strong>Comments:</strong> <?= nl2br(htmlspecialchars($recipe['comments'])) ?></p>
<?php endif; ?>

<h2>Ingredients</h2>
<ul>
    <?php foreach ($ingredients as $ingredient): ?>
        <li>
            <?= htmlspecialchars($ingredient['ingredient']) ?>:
            <?= htmlspecialchars($ingredient['quantity']) ?>
            <?= htmlspecialchars($ingredient['unit'] ?? '') ?>
        </li>
    <?php endforeach; ?>
</ul>

<h2>Directions</h2>
<ol>
    <?php foreach ($directions as $direction): ?>
        <li><?= nl2br(htmlspecialchars($direction['step'])) ?></li>
    <?php endforeach; ?>
</ol>

<a href="index.php">Back to Home</a>
</body>
</html>
