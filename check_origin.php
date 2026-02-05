<?php
echo "<h2>Current Origin Info:</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "<br>";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'Not set') . "<br>";
echo "REQUEST_SCHEME: " . ($_SERVER['REQUEST_SCHEME'] ?? 'Not set') . "<br>";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'Not set') . "<br>";
echo "Full URL: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "<br>";
echo "<hr>";
echo "Your current origin is: <strong>http://" . $_SERVER['HTTP_HOST'] . "</strong>";
?>