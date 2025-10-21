# Mune Restaurant Ordering System

Mune is a simple PHP application for managing a restaurant menu and placing orders. Customers can browse a menu, add items to a cart and checkout. An admin dashboard provides basic order management.

## Environment Variables
Set these variables in your server environment or `.htaccess`:

- `DB_HOST` – Database host (default `localhost`)
- `DB_USER` – Database user (default `root`)
- `DB_PASS` – Database password
- `DB_NAME` – Database name (default `mune_db`)
- `ADMIN_USER` – Username for the admin area (default `admin`)
- `ADMIN_PASS` – Password for the admin area (default `admin123`)

## Initial Database Setup
1. Create a MySQL database matching `DB_NAME`.
2. Create the following tables (simplified example):

```sql
CREATE TABLE restaurants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    short_link_slug VARCHAR(100) NOT NULL,
    logo_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE menu_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1
);

CREATE TABLE menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_section_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1
);

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1
);

CREATE TABLE menu_item_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    option_group_name VARCHAR(255) NOT NULL,
    option_name VARCHAR(255) NOT NULL,
    additional_price DECIMAL(10,2) DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_car_type VARCHAR(255) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    order_status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price_per_item DECIMAL(10,2) NOT NULL,
    selected_options TEXT,
    sub_total DECIMAL(10,2) NOT NULL
);
```

Populate tables with your restaurant data to start using the system.

## Running the Application
1. Ensure PHP (recommended 8.3+) and MySQL are installed.
2. Configure the environment variables above.
3. Deploy to a web server or run locally:
   ```bash
   php -S localhost:8000
   ```
4. Visit `menu.php?slug=<your-restaurant-slug>` to view the menu.
5. Access `admin/login.php` to manage orders using the admin credentials.

You can test the database connection using `test_connection.php`.

## Security Considerations
- Store credentials in environment variables rather than the codebase.
- Disable or remove `test_connection.php` and other debug scripts in production.
- Always validate and sanitize user input on both client and server.
- Serve the application over HTTPS.
- Keep PHP and dependencies updated (the project ships with an `.htaccess` file targeting PHP 8.3).
