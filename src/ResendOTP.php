<?php
namespace IO;
require_once "../.appinit.php";
use TymFrontiers\Data,
    TymFrontiers\Generic,
    TymFrontiers\HTTP,
    TymFrontiers\API,
    TymFrontiers\MySQLDatabase,
    TymFrontiers\InstanceError;
use Catali\OTP;

$data = new Data;
\header("Content-Type: application/json");
$post = \json_decode( \file_get_contents('php://input'), true); // json data
$post = !empty($post) ? $post : $_POST;
$gen = new Generic;
$auth = new API\Authentication ($api_sign_patterns);
$http_auth = $auth->validApp ();
if( !$http_auth && ( empty($post['form']) || empty($post['CSRF_token']) ) ){
  HTTP\Header::unauthorized (false,'', Generic::authErrors ($auth,"Request [Auth-App]: Authetication failed.",'self',true));
}
$params = [
    "reference" =>["reference","text", 3, 0],

    "form" => ["form","text",2,55],
    "CSRF_token" => ["CSRF_token","text",5,500]
  ];
$reqd = ["reference"];
if( !$http_auth ){
  $reqd[] = "CSRF_token";
  $reqd[] = "form";
}
$params = $gen->requestParam($params, $post, $reqd);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError ($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request failed"
  ]);
  exit;
}

if( !$http_auth ){
  if ( !$gen->checkCSRF($params["form"],$params["CSRF_token"]) ) {
    $errors = (new InstanceError ($gen, false))->get("checkCSRF",true);
    echo \json_encode([
      "status" => "3." . \count($errors),
      "errors" => $errors,
      "message" => "Request failed."
    ]);
    exit;
  }
}
  // opt \Dev connection
  $eml_server = get_constant("PRJ_EMAIL_SERVER");
  $dev_usr = db_cred($eml_server, "DEVELOPER");
  $conn = new MySQLDatabase(get_dbserver($eml_server), $dev_usr[0], $dev_usr[1]);
  if (!$conn || !$conn instanceof MySQLDatabase) {
    echo \json_encode([
      "status" => "4.1",
      "errors" => ["Failed to open database connection, contact Admin"],
      "message" => "Request failed."
    ]);
    exit;
  }
//
$otp = new OTP\ByEmail("", $conn);
if (!$sent_otp = $otp->findById($params['reference'])) {
  echo \json_encode([
    "status" => "4.1",
    "errors" => ["Record not found for given reference."],
    "message" => "Request failed."
  ]);
  exit;
}
if (!$sent_otp->resend()) {
  echo \json_encode([
    "status" => "5.1",
    "errors" => ["We could not resend email at this time, try again later."],
    "message" => "Request failed."
  ]);
  exit;
}
$conn->closeConnection();
echo \json_encode([
  "status" => "0.0",
  "errors" => [],
  "message" => "Check now, we have resent it."
]);
exit;
