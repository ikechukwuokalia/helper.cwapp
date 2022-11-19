<?php
namespace IO;
require_once "../../.appinit.php";
use \TymFrontiers\HTTP,
    \TymFrontiers\Generic,
    \TymFrontiers\Data,
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
if (!$notice = Notice::findById($params['id'])) {
  echo \json_encode([
    "status" => "3.1",
    "errors" => ["Notification was not found with given reference."],
    "message" => "Request halted"
  ]);
  exit;
}
if ($notice->user !== $session->name || ws_access($notice->user, $session->name)) {
  echo \json_encode([
    "status" => "2.1",
    "errors" => ["Access to delete notification was denied."],
    "message" => "Request halted"
  ]);
  exit;
} if (!$notice->delete()) {
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