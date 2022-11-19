<?php
namespace IO;
require_once "../../.appinit.php";
use \TymFrontiers\HTTP,
    \TymFrontiers\Generic,
    \TymFrontiers\MultiForm,
    \TymFrontiers\InstanceError;

\header("Content-Type: application/json");
if (!$session->isLoggedIn()) HTTP\Header::unauthorized(false, "", ["Message"=> "Login required", "Error"=>"1.1"]);
$post = !empty($_POST) ? $_POST : $_GET;
$gen = new Generic;
$params = $gen->requestParam([
  "id" => ["id","int"]
], $post, ["id"]);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError ($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
if (!$cart = (new MultiForm(get_database(get_constant("PRJ_SERVER_NAME"), "base"), "shopping_cart", "id"))->findById($params['id'])) {
  echo \json_encode([
    "status" => "3.1",
    "errors" => ["Nothing was not found with given reference."],
    "message" => "Request halted"
  ]);
  exit;
}
if ($cart->user !== $session->name) {
  echo \json_encode([
    "status" => "2.1",
    "errors" => ["Access to delete cart item was denied."],
    "message" => "Request halted"
  ]);
  exit;
} if (!$cart->delete()) {
  echo \json_encode([
    "status" => "4.1",
    "errors" => ["Failed to delete at this time, please try again later."],
    "message" => "Request halted"
  ]);
  exit;
}
echo \json_encode([
  "status" => "0.0",
  "errors" => [],
  "message" => "Delete request was successful"
]);
exit;