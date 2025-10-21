# Mune

This project requires administrator credentials for accessing the admin panel. Credentials are loaded from environment variables at runtime or can be set by editing `config/admin.php`.

## Setting credentials via environment variables

Set the following variables before running the application:

```bash
export ADMIN_USER="your_admin_username"
export ADMIN_PASS_HASH="$(php -r "echo password_hash('your_password', PASSWORD_DEFAULT);")"
```

`ADMIN_PASS_HASH` must contain a hash generated with `password_hash()`.

## Configuration file (optional)

If environment variables are not used, you can create or edit `config/admin.php` to return an array with `username` and `password_hash` keys. Example:

```php
<?php
return [
    'username' => 'admin',
    'password_hash' => '$2y$10$examplehashhere',
];
```

