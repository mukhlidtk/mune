<?php
return [
    'username' => getenv('ADMIN_USER') ?: null,
    'password_hash' => getenv('ADMIN_PASS_HASH') ?: null,
];
?>
