<?php
require 'config.php';

$searchQuery = $_GET['search'] ?? '';

// Prepare a search query
if (!empty($searchQuery)) {
    $stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name, 'drink' AS type 
        FROM drink_combos d
        LEFT JOIN ingredients i ON d.id = i.drink_id
        LEFT JOIN directions dir ON d.id = dir.drink_id
        WHERE d.name LIKE :query OR i.ingredient LIKE :query OR dir.step LIKE :query
        UNION
        SELECT DISTINCT f.id, f.name, 'food' AS type
        FROM food_recipes f
        LEFT JOIN ingredients i ON f.id = i.food_id
        LEFT JOIN directions dir ON f.id = dir.food_id
        WHERE f.name LIKE :query OR i.ingredient LIKE :query OR dir.step LIKE :query
    ");
    $stmt->execute(['query' => "%$searchQuery%"]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recipes = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>King Cobra JFS Recipes</title>
</head>
<body>

<h1>King Cobra JFS Recipes</h1>

<!-- Search Form -->
<form method="GET" action="index.php">
    <input type="text" name="search" placeholder="Search for recipes..." value="<?= htmlspecialchars($searchQuery) ?>">
    <button type="submit">Search</button>
</form>

<!-- Display Search Results -->
<?php if (!empty($searchQuery)): ?>
    <h2>Search Results for "<?= htmlspecialchars($searchQuery) ?>"</h2>
    <ul>
        <?php if (empty($recipes)): ?>
            <li>No results found.</li>
        <?php else: ?>
            <?php foreach ($recipes as $recipe): ?>
                <li>
                    <?= htmlspecialchars($recipe['name']) ?>
                    - <a href="view_recipe.php?type=<?= $recipe['type'] ?>&id=<?= $recipe['id'] ?>">View</a>
                    - <a href="edit_recipe.php?type=<?= $recipe['type'] ?>&id=<?= $recipe['id'] ?>">Edit</a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
<?php endif; ?>

<h2>All Recipes</h2>

<h3>Drink Combos</h3>
<ul>
    <?php
    $drink_combos = $conn->query('SELECT * FROM drink_combos')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($drink_combos as $drink): ?>
        <li>
            <?= htmlspecialchars($drink['name']) ?>
            - <a href="view_recipe.php?type=drink&id=<?= $drink['id'] ?>">View</a>
            - <a href="edit_recipe.php?type=drink&id=<?= $drink['id'] ?>">Edit</a>
        </li>
    <?php endforeach; ?>
</ul>

<h3>Food Recipes</h3>
<ul>
    <?php
    $food_recipes = $conn->query('SELECT * FROM food_recipes')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($food_recipes as $food): ?>
        <li>
            <?= htmlspecialchars($food['name']) ?>
            - <a href="view_recipe.php?type=food&id=<?= $food['id'] ?>">View</a>
            - <a href="edit_recipe.php?type=food&id=<?= $food['id'] ?>">Edit</a>
        </li>
    <?php endforeach; ?>
</ul>

<a href="add_recipe.php">Add New Recipe</a>

</body>
</html>
