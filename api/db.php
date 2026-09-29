<?php
$host = "db.mcmqluhjtfkxexdwkxru.supabase.co";
$port = "5432";
$dbname = "postgres";
$user = "postgres";
$password = "LazyLegder123"; 

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(["error" => "Database connection error."]));
}
?>