<?php

require_once __DIR__ . "/../config/database.php";

$result = $conn->query("SELECT COUNT(*) AS total FROM buyers");
$row = $result->fetch_assoc();

echo "Database connected successfully!<br>";
echo "Buyers table is ready.<br>";
echo "Total buyers: " . (int) $row["total"];