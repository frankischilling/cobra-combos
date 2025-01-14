<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipe_type = $_POST['recipe_type'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $ingredients = $_POST['ingredients']; // Array of ingredients
    $directions = array_filter($_POST['directions'], fn($step) => !empty($step)); // Filter empty directions

    try {
        // Insert recipe
        if ($recipe_type === 'drink') {
            $stmt = $conn->prepare("INSERT INTO drink_combos (name, description) VALUES (?, ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO food_recipes (name, description) VALUES (?, ?)");
        }
        $stmt->execute([$name, $description]);
        $recipe_id = $conn->lastInsertId();

        // Insert ingredients
        foreach ($ingredients as $ingredient) {
            if (!empty($ingredient['name']) && !empty($ingredient['quantity'])) {
                if ($recipe_type === 'drink') {
                    $stmt = $conn->prepare("INSERT INTO ingredients (recipe_type, drink_id, ingredient, quantity, unit) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$recipe_type, $recipe_id, $ingredient['name'], $ingredient['quantity'], $ingredient['unit']]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO ingredients (recipe_type, food_id, ingredient, quantity, unit) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$recipe_type, $recipe_id, $ingredient['name'], $ingredient['quantity'], $ingredient['unit']]);
                }
            }
        }

        // Insert directions
        foreach ($directions as $step) {
            if ($recipe_type === 'drink') {
                $stmt = $conn->prepare("INSERT INTO directions (recipe_type, drink_id, step) VALUES (?, ?, ?)");
                $stmt->execute([$recipe_type, $recipe_id, $step]);
            } else {
                $stmt = $conn->prepare("INSERT INTO directions (recipe_type, food_id, step) VALUES (?, ?, ?)");
                $stmt->execute([$recipe_type, $recipe_id, $step]);
            }
        }

        echo "Recipe, ingredients, and directions added successfully!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Recipe</title>
</head>
<body>
<h1>Add New Recipe</h1>
<form method="POST">
    <label for="recipe_type">Recipe Type:</label>
    <select name="recipe_type" required>
        <option value="drink">Drink Combo</option>
        <option value="food">Food Recipe</option>
    </select><br>

    <label for="name">Recipe Name:</label>
    <input type="text" name="name" required><br>

    <label for="description">Description:</label><br>
    <textarea name="description" required></textarea><br>

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
    <button type="button" onclick="addIngredient()">Add Ingredient</button><br>

    <h2>Directions</h2>
    <div id="directions">
        <div>
            <label for="directions[0]">Step:</label>
            <textarea name="directions[0]"></textarea>
        </div>
    </div>
    <button type="button" onclick="addDirection()">Add Direction</button><br>

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
</script>
</body>
</html>