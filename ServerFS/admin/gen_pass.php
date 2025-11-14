<?php
$pass = 'admin123';
$hash = password_hash($pass, PASSWORD_BCRYPT);
echo "<pre>New hash for admin123:\n\n$hash\n</pre>";
