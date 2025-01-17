<?php
require 'config.php';

// Set Content Security Policy to mitigate risks from custom CSS
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");

// Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to anonymize IPs
/**
 * Anonymize IP addresses by only revealing the last 2 octets (IPv4)
 * or the last 2 hextets (IPv6).
 *
 * Examples:
 *   IPv4: 192.168.1.25  → xxx.xxx.1.25
 *   IPv4: 10.0.45.198   → xxx.xxx.45.198
 *   IPv6: 2001:db8::42:8329 → xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:42:8329
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

    $anonymizedIp = anonymizeIp($recipe['last_edited_ip'] ?? '');

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
<?php if (empty($ingredients)): ?>
    <p>No ingredients listed for this recipe.</p>
<?php else: ?>
    <ul>
        <?php foreach ($ingredients as $ingredient): ?>
            <li><?= htmlspecialchars($ingredient['ingredient']) ?>: <?= htmlspecialchars($ingredient['quantity']) ?> <?= htmlspecialchars($ingredient['unit'] ?? '') ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Directions</h2>
<?php if (empty($directions)): ?>
    <p>No directions listed for this recipe.</p>
<?php else: ?>
    <ol>
        <?php foreach ($directions as $direction): ?>
            <li><?= nl2br(htmlspecialchars($direction['step'])) ?></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<p>Last edited by IP: <strong><?= htmlspecialchars($anonymizedIp) ?></strong></p>
<a href="index.php">Back to Home</a>
</body>
</html>
