<?php
// generate_token.php
$bytes  = random_bytes($_GET['bytes'] ?? 16);       // 16 Bytes = 128 Bit
$token  = bin2hex($bytes);        // hübsch als Hex-String
echo $token . PHP_EOL;