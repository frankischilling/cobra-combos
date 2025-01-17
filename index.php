<?php
require 'config.php';

$drink_combos = $conn->query('SELECT * FROM drink_combos')->fetchAll(PDO::FETCH_ASSOC);
$food_recipes = $conn->query('SELECT * FROM food_recipes')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>King Cobra JFS Recipes</title>
</head>
<body>
<h1>King Cobra JFS Drink Combos</h1>
<ul>
<?php foreach ($drink_combos as $drink): ?>
    <li>
        <?= htmlspecialchars($drink['name']) ?>
        - <a href="view_recipe.php?type=drink&id=<?= $drink['id'] ?>">View</a>
        - <a href="edit_recipe.php?type=drink&id=<?= $drink['id'] ?>">Edit</a>
    </li>
<?php endforeach; ?>
</ul>

<h1>Food Recipes</h1>
<ul>
<?php foreach ($food_recipes as $food): ?>
    <li>
        <?= htmlspecialchars($food['name']) ?>
        - <a href="view_recipe.php?type=drink&id=<?= $food['id'] ?>">View</a>
        - <a href="edit_recipe.php?type=drink&id=<?= $food['id'] ?>">Edit</a>
    </li>
<?php endforeach; ?>
</ul>

<a href="add_recipe.php">Add New Recipe</a>
</body>
</html>
