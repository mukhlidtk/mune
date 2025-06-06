# Mune Ordering System

This repository contains a simple PHP-based menu and order management system. The project expects a MySQL database and a configuration file in `config/database.php`.

## Setup
1. Copy or edit `config/database.php` and set your database credentials or export the environment variables `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`.
2. Ensure PHP 8.3 or newer is installed on the server. The `.htaccess` file sets the PHP handler when using cPanel.
3. Import the required database schema (not included) and make sure tables referenced in the code exist.
4. Access `menu.php?slug=YOUR_SLUG` in the browser to view the menu for a restaurant.

## Files
- `menu.php` – displays the restaurant menu and allows adding items to a cart.
- `checkout.php` – shows the order summary and collects customer details.
- `place_order.php` – processes the order and writes it to the database.
- `order_confirmation.php` – thanks the user after placing an order.
- `test_connection.php` – quick script to test the MySQL connection.

All pages are written in Arabic and expect UTF-8 encoding.
