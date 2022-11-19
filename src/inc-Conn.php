<?php
namespace IO;
use \TymFrontiers\MySQLDatabase;
if (empty($server_name)) $server_name = "CWS";
if (!$access = db_cred($server_name, "DEVELOPER")) {
  echo \json_encode([
    "status" => "4.1",
    "errors" => ["Failed to load connection credentials."],
    "message" => "Request halted"
  ]);
  exit;
} 
$conn = false;
try {
  $conn = new MySQLDatabase(get_dbserver($server_name), $access[0], $access[1], get_database($server_name, "developer"));
} catch (\Throwable $th) {
  echo \json_encode([
    "status" => "4.2",
    "errors" => ["Database connection failed!", $th->getMessage()],
    "message" => "Request halted."
  ]);
  exit;
}
if (empty($conn) || !$conn) {
  echo \json_encode([
    "status" => "4.1",
    "errors" => ["Failed to initialize database connection."],
    "message" => "Request halted."
  ]);
  exit;
}