<?php
namespace Catali;
require_once "../../.appinit.php";
use \TymFrontiers\Generic,
    \TymFrontiers\InstanceError;

\header("Content-Type: application/json");
$post = !empty($_POST) ? $_POST : $_GET;
$gen = new Generic;
$params = $gen->requestParam([
  "email" =>["email","email"],
  "otp" =>["otp","username", 3, 28, [], "mixed", [" ", "-", "_", "."]]
], $post, ["otp", "email"]);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError ($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
$params['otp'] = \str_replace([" ", "-", "_", "."],"", $params["otp"]);
try {
  $otp = new OTP\ByEmail();
} catch (\Throwable $th) {
  echo \json_encode([
    "status" => "4.1",
    "errors" => [$th->getMessage()],
    "message" => "Request halted"
  ]);
  exit;
} if ($otp) {
  if (!$otp->verify($params["email"], $params['otp'])) {
    echo \json_encode([
      "status" => "3.1",
      "errors" => ["OTP code is invalid/expired."],
      "message" => "Request failed"
    ]);
    exit;
  }
}

echo \json_encode([
  "status" => "0.0",
  "errors" => [],
  "message" => "OTP code is valid",
  "data" => [
    "otp" => $params['otp']
  ]
]);
exit;