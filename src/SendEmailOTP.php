<?php
namespace IO;
require_once "../.appinit.php";
use TymFrontiers\Data,
    TymFrontiers\Generic,
    TymFrontiers\HTTP,
    TymFrontiers\MySQLDatabase,
    TymFrontiers\InstanceError,
    TymFrontiers\API;
use Bosh\User;
use Catali\OTP;

$data = new Data;
\header("Content-Type: application/json");
$post = \json_decode( \file_get_contents('php://input'), true); // json data
$post = !empty($post) ? $post : (
  !empty($_POST) ? $_POST : (
    !empty($_GET) ? $_GET : []
    )
);
$gen = new Generic;
$auth = new API\Authentication ($api_sign_patterns);
$http_auth = $auth->validApp ();
if( !$http_auth && ( empty($post['form']) || empty($post['CSRF_token']) ) ){
  HTTP\Header::unauthorized (false,'', Generic::authErrors ($auth,"Request [Auth-App]: Authetication failed.",'self',true));
}

$gen = new Generic();
$params = [
  "email" =>["email","email"],
  "name" =>["name","name"],
  "surname" =>["surname","name"],
  "MUST_EXIST" =>["MUST_EXIST","boolean"],
  "MUST_NOT_EXIST" =>["MUST_NOT_EXIST","boolean"],
  "code_variant" =>["code_variant","option",[
    Data::RAND_MIXED,
    Data::RAND_NUMBERS,
    Data::RAND_LOWERCASE,
    Data::RAND_UPPERCASE,
    Data::RAND_MIXED_LOWER,
    Data::RAND_MIXED_UPPER,
    ]],
    "code_length" =>["code_length","int", 8, 16],

  "form" => ["form","text",2,55],
  "CSRF_token" => ["CSRF_token","text",5,500]
];
$reqd = ["email"];
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
    "message" => "Request halted"
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

$email = $params['email'];

if( (bool)$params['MUST_EXIST'] && !User::valExist($params['email'],"email") ){
  echo \json_encode([
    "status" => "3.1",
    "errors" => ["Email: [{$params['email']}] not in record."],
    "message" => "Request halted."
  ]);
  exit;
}
if( (bool)$params['MUST_NOT_EXIST'] && User::valExist($params['email'],"email") ){
  echo \json_encode([
    "status" => "3.1",
    "errors" => ["Email: [{$params['email']}] already in use."],
    "message" => "Request halted."
  ]);
  exit;
}
if (empty($params['code_length'])) $params['code_length'] = 8;
if (empty($params['code_variant'])) $params['code_variant'] = Data::RAND_MIXED_UPPER;

$token = "";
if (!empty($params['code_length']) && !empty($params['code_variant'])) {
  // mannually generate token
  $token = Data::uniqueRand('', $params['code_length'], $params['code_variant']);
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
$otp = new OTP\ByEmail("", $conn);
if (!$otp->setSender(Generic::splitEmailName(get_constant("PRJ_AUTO_EMAIL")))) {
  die( \json_encode([
    "status" => "4.1",
    "errors" => ["Unable to set OTP sender."],
    "message" => "Request halted."
    ]));
}
$receiver = !empty($params['name']) ? "{$params['name']} {$params['surname']} <{$params['email']}>" : $params["email"];
if (!$otp->setReceiver(Generic::splitEmailName($receiver))) {
  die( \json_encode([
    "status" => "4.1",
    "errors" => ["Unable to set OPT recipient."],
    "message" => "Request halted."
    ]));
}
$reference = $otp->send((!empty($params['code_variant']) ? $params['code_variant'] : ''), \strtotime("+1 Day"));
$errors = [];
if ($reference == false) {
  $errs = (new InstanceError($otp))->get('send',true);
  if (!empty($errs)) $errors = "Failed to send OPT message";
  foreach ($errs as $err) {
    $errors[] = $err;
  }
}

if (!$errors) {
  die( \json_encode([
  "status" => "4." . \count($errors),
  "errors" => $errors,
  "message" => "Request incomplete."
  ]));
}

$conn->closeConnection();

echo \json_encode([
  "status" => "0.0",
  "errors" => [],
  "message" => "OTP code has been sent to your email..",
  "reference" => $reference,
  "email" => $email
]);
exit;
