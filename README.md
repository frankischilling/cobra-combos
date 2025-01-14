### README for King Cobra JFS Recipes Archive

# King Cobra JFS Recipes Archive

Welcome to the **King Cobra JFS Recipes Archive**, a PHP-based web application designed to store and display **drink combos** and **food recipes** inspired by King Cobra JFS. This application uses MySQL to manage recipe data, including ingredients and step-by-step directions.

## Features

- Add and store **drink combos** and **food recipes**.
- View detailed recipes with **ingredients** and **directions**.
- Supports ingredient measurements with units (e.g., ml, cups, tsp).
- Responsive web interface for managing recipes.

## Requirements

- **PHP** (version 7.4 or higher)
- **MySQL** (version 5.7 or higher)
- **Apache** or any compatible web server
- **PDO Extension** enabled for MySQL

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/your-username/king-cobra-jfs-recipes.git
cd king-cobra-jfs-recipes
```

### 2. Set Up the MySQL Database

Follow these steps to configure the MySQL database:

1. **Log in to MySQL**:
   ```bash
   mysql -u your_username -p
   ```

2. **Create the Database**:
   ```sql
   CREATE DATABASE drink_combos;
   USE drink_combos;
   ```

3. **Create the Tables**:
   Run the following SQL commands to set up the required tables:

   ```sql
   -- Create the drink_combos table
   CREATE TABLE drink_combos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL,
       description TEXT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- Create the food_recipes table
   CREATE TABLE food_recipes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL,
       description TEXT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- Create the ingredients table
   CREATE TABLE ingredients (
       id INT AUTO_INCREMENT PRIMARY KEY,
       recipe_type ENUM('drink', 'food') NOT NULL,
       drink_id INT(11) DEFAULT NULL,
       food_id INT(11) DEFAULT NULL,
       ingredient VARCHAR(255) NOT NULL,
       quantity VARCHAR(50),
       unit VARCHAR(20),
       FOREIGN KEY (drink_id) REFERENCES drink_combos(id) ON DELETE CASCADE,
       FOREIGN KEY (food_id) REFERENCES food_recipes(id) ON DELETE CASCADE
   );

   -- Create the directions table
   CREATE TABLE directions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       recipe_type ENUM('drink', 'food') NOT NULL,
       drink_id INT(11) DEFAULT NULL,
       food_id INT(11) DEFAULT NULL,
       step TEXT NOT NULL,
       FOREIGN KEY (drink_id) REFERENCES drink_combos(id) ON DELETE CASCADE,
       FOREIGN KEY (food_id) REFERENCES food_recipes(id) ON DELETE CASCADE
   );
   ```

### 3. Configure the Application

Edit the `config.php` file with your MySQL credentials:
```php
<?php
$host = 'localhost';
$dbname = 'drink_combos';
$username = 'your_username';
$password = 'your_password';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
?>
```

### 4. Run the Application

Start your local development server:
```bash
php -S localhost:8000
```

Visit the application in your browser:
```
http://localhost:8000
```

## Usage

### Adding a New Recipe

1. Click **"Add New Recipe"**.
2. Fill in the recipe name, description, and ingredients (with units).
3. Add step-by-step directions.
4. Submit the form to save the recipe.

### Viewing Recipes

On the homepage, click a recipe to view its detailed ingredients and directions.

## Database Schema

- **`drink_combos`**: Stores drink combo details.
- **`food_recipes`**: Stores food recipe details.
- **`ingredients`**: Stores ingredients for recipes with their quantities and units.
- **`directions`**: Stores step-by-step directions for recipes.

## Contributing

Contributions are welcome! Feel free to submit issues or pull requests to enhance the functionality.

## License

This project is licensed under the GNU v3 License. See the [LICENSE](LICENSE) file for details.

## Credits

Inspired by King Cobra JFS.  
