# King Cobra JFS Recipes Archive

Welcome to the **King Cobra JFS Recipes Archive**, a PHP-based web application designed to store and display **drink combos** and **food recipes** inspired by King Cobra JFS. This application utilizes MySQL to manage recipe data, including ingredients, step-by-step directions, custom comments, and personalized styling through custom CSS.

![Screenshot](/screenshots/screenshot.png)

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [Usage](#usage)
  - [Adding a New Recipe](#adding-a-new-recipe)
  - [Viewing Recipes](#viewing-recipes)
- [Database Schema](#database-schema)
- [Security Considerations](#security-considerations)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

## Features

- **Add and Store Recipes:** Easily add new **drink combos** and **food recipes** with detailed information.
- **Custom CSS Styling:** Personalize the appearance of each recipe using custom CSS with support for tab and enter key bindings.
- **Syntax Highlighting:** Enjoy real-time syntax highlighting in the custom CSS editor for better readability and ease of use.
- **Ingredient Management:** Manage ingredients with quantities and units (e.g., ml, cups, tsp).
- **Step-by-Step Directions:** Provide clear, ordered directions for each recipe.
- **Responsive Interface:** A user-friendly and responsive web interface for seamless interaction across devices.
- **Security Measures:** Robust sanitization and security protocols to prevent malicious inputs, especially in custom CSS.

## Requirements

- **PHP** (version 7.4 or higher)
- **MySQL** (version 5.7 or higher)
- **Web Server:** Apache, Nginx, or any compatible web server.
- **PDO Extension:** Enabled for MySQL.

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/king-cobra-jfs-recipes.git](https://github.com/frankischilling/cobra-combo
cd cobra-combo
```

### 2. Set Up the MySQL Database

Follow these steps to configure the MySQL database:

1. **Log in to MySQL:**

   ```bash
   mysql -u your_username -p
   ```

2. **Create the Database:**

   ```sql
   CREATE DATABASE drink_combos;
   USE drink_combos;
   ```

3. **Create the Tables:**

   Run the following SQL commands to set up the required tables:

   ```sql
   -- Create the drink_combos table with comments and custom_css
   CREATE TABLE drink_combos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL,
       description TEXT,
       comments TEXT, -- Field for custom comments
       custom_css TEXT, -- Field for custom CSS
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- Create the food_recipes table with comments and custom_css
   CREATE TABLE food_recipes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL,
       description TEXT,
       comments TEXT, -- Field for custom comments
       custom_css TEXT, -- Field for custom CSS
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

### 4. Set Up the Web Server

Ensure your web server points to the project directory. For a local development server, you can use PHP's built-in server:

```bash
php -S localhost:8000
```

## Running the Application

Start your local development server as shown above and visit the application in your browser:

```
http://localhost:8000
```

## Usage

### Adding a New Recipe

1. Click on **"Add New Recipe"**.
2. Select the **Recipe Type** (**Drink Combo** or **Food Recipe**).
3. Fill in the **Recipe Name** and **Description**.
4. Optionally, add **Custom Comments** for additional information.
5. **Custom CSS:**
   - Enter your custom CSS in the editor.
   - Use the `Tab` key for indentation and the `Enter` key for single line breaks.
   - Enjoy real-time syntax highlighting to enhance readability.
6. Add **Ingredients** by filling in the name, quantity, and unit.
   - Click **"Add Ingredient"** to include more ingredients.
7. Add **Directions** by entering each step.
   - Click **"Add Direction"** to include more steps.
8. Submit the form to save the recipe.

### Viewing Recipes

On the homepage, recipes are categorized under **Drink Combos** and **Food Recipes**. Click on **"View"** next to a recipe to see its detailed information, including:

- **Description**
- **Custom Comments**
- **Ingredients**
- **Directions**
- **Custom CSS Styling**

## Database Schema

- **`drink_combos`**: Stores drink combo details.
  - **`id`**: Primary key.
  - **`name`**: Name of the drink combo.
  - **`description`**: Description of the drink combo.
  - **`comments`**: Custom comments or additional information.
  - **`custom_css`**: Custom CSS to style the drink combo page.
  - **`created_at`**: Timestamp of creation.

- **`food_recipes`**: Stores food recipe details.
  - **`id`**: Primary key.
  - **`name`**: Name of the food recipe.
  - **`description`**: Description of the food recipe.
  - **`comments`**: Custom comments or additional information.
  - **`custom_css`**: Custom CSS to style the food recipe page.
  - **`created_at`**: Timestamp of creation.

- **`ingredients`**: Stores ingredients for recipes.
  - **`id`**: Primary key.
  - **`recipe_type`**: Type of recipe (`drink` or `food`).
  - **`drink_id`**: Foreign key referencing `drink_combos(id)` if `recipe_type` is `drink`.
  - **`food_id`**: Foreign key referencing `food_recipes(id)` if `recipe_type` is `food`.
  - **`ingredient`**: Name of the ingredient.
  - **`quantity`**: Quantity of the ingredient.
  - **`unit`**: Unit of measurement.

- **`directions`**: Stores step-by-step directions for recipes.
  - **`id`**: Primary key.
  - **`recipe_type`**: Type of recipe (`drink` or `food`).
  - **`drink_id`**: Foreign key referencing `drink_combos(id)` if `recipe_type` is `drink`.
  - **`food_id`**: Foreign key referencing `food_recipes(id)` if `recipe_type` is `food`.
  - **`step`**: Description of the step.

## Security Considerations

Allowing users to input custom CSS introduces potential security risks, such as Cross-Site Scripting (XSS) attacks. To mitigate these risks, the following measures have been implemented:

### 1. **CSS Sanitization**

The application sanitizes user-inputted CSS to remove potentially harmful code:

- **Removal of `<style>` Tags:**
  Prevents users from injecting entire style blocks.
  
- **Disallowing Dangerous Functions:**
  Strips out CSS functions like `expression()`, `url()`, and `@import` that can be exploited.

- **Elimination of JavaScript URLs:**
  Removes `javascript:` protocols to prevent script execution via CSS.

```php
function sanitize_css($css) {
    // Remove any `<style>` tags
    $css = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $css);

    // Remove any expressions or URL functions that can be exploited
    $css = preg_replace('/expression\s*\(|url\s*\(|@import\s+url\s*\(/i', '', $css);

    // Remove JavaScript events (e.g., onload, onclick)
    $css = preg_replace('/javascript:/i', '', $css);

    return $css;
}
```

### 2. **Content Security Policy (CSP)**

A Content Security Policy is set to restrict the types of content that can be loaded and executed:

```php
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");
```

**Note:** Using `'unsafe-inline'` allows inline styles, which is necessary for applying custom CSS but reduces CSP effectiveness. For enhanced security, consider implementing nonce-based or hash-based CSP in the future.

### 3. **Escaping Output**

All user-generated content is escaped using `htmlspecialchars()` to prevent HTML injection.

```php
<?= htmlspecialchars($drink['name']) ?>
```

### 4. **User Authentication and Authorization**

**Future Enhancement:** Implement user authentication to control who can add or edit recipes, ensuring that only authorized users can modify content.

### 5. **Regular Security Audits**

Conduct periodic reviews of the codebase to identify and fix potential vulnerabilities.

## Contributing

Contributions are welcome! Whether it's reporting bugs, suggesting features, or submitting pull requests, your input is valuable to improving the application.

1. **Fork the Repository**
2. **Create a Feature Branch**

   ```bash
   git checkout -b feature/YourFeature
   ```

3. **Commit Your Changes**

   ```bash
   git commit -m "Add Your Feature"
   ```

4. **Push to the Branch**

   ```bash
   git push origin feature/YourFeature
   ```

5. **Open a Pull Request**

## License

This project is licensed under the [GNU v3 License](LICENSE). See the [LICENSE](LICENSE) file for details.

## Credits

Inspired by **King Cobra JFS**.

---
