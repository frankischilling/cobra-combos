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
<li>
    <?= nl2br(htmlspecialchars($direction['step'])) ?>

</li>

<?php endforeach; ?>
</ol>

<a href="index.php">Back to Home</a>
</body>
</html>
