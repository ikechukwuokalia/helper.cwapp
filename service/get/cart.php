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
  "id" => ["id","int"],
  "ws" => ["ws","pattern", "/^289([0-9]{8,11})$/"],
  "search" => ["search","text",3,25],
  "page" => ["page","int"],
  "limit" => ["limit","int"]
], $post, []);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError ($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
$params["user"] = $session->name;
$count = 0;
$cart = new ShoppingCart($params['user'], $params['ws']);
$cart->current_page = $page = (int)$params['page'] > 0 ? (int)$params['page'] : 1;

$data = new Data;
$ent_server = !empty($_COOKIE['wssrv']) ? $data->decodeDecrypt($_COOKIE['wssrv']) : false;
if (!$ent_server) $ent_server = get_constant("PRJ_SERVER_NAME");
$wsq = !empty($params['ws']) ? " AND crt.ws = '{$params['ws']}' " : "";
$cnd = " WHERE crt.`user` = '{$params['user']}' {$wsq} ";

$query = "SELECT crt.id, crt.`user`, crt.`item`, crt.`type`, crt.quantity, crt.`currency`, crt.`price`, 
                crt.`discount`, crt.`description`, crt._created,
                (
                  SELECT SUM(price)
                  FROM :db:.:tbl: AS crt
                  {$cnd}
                ) AS 'subtotal',
                (
                  SELECT COUNT(*)
                  FROM :db:.:tbl: AS crt
                  {$cnd}
                ) AS 'records' ";

$query .= "FROM :db:.:tbl: AS crt ";


$count = $cart->findBySql("SELECT COUNT(*) AS cnt FROM :db:.:tbl: AS crt {$cnd} ");
// echo $db->last_query;
$count = $cart->total_count = $count ? $count[0]->cnt : 0;

$cart->per_page = $limit = !empty($params['id']) ? 1 : (
    (int)$params['limit'] > 0 ? (int)$params['limit'] : 25
  );
$query .= $cnd;

$query .= " ORDER BY crt._created DESC ";
$query .= " LIMIT {$cart->per_page} ";
$query .= " OFFSET {$cart->offset()}";

$found = $cart->findBySql($query);
if( !$found ){
  die( \json_encode([
    "message" => "No result found.",
    "errors" => [],
    "status" => "0.2"
    ]) );
}
$tym = new \TymFrontiers\BetaTym;
$result = [
  'records' => (int)$found[0]->records,
  'subtotal' => (float)$found[0]->subtotal,
  'subtotalText' => \number_format($found[0]->subtotal, currency_decimals($found[0]->currency), ".", ","),
  'currency' => $found[0]->currency,
  'page'  => $cart->current_page,
  'pages' => $cart->totalPages(),
  'limit' => $limit,
  'previousPage' => $cart->hasPreviousPage() ? $cart->previousPage() : false,
  'nextPage' => $cart->hasNextPage() ? $cart->nextPage() : false
];
$result["message"] = "Request completed.";
$result["errors"] = [];
$result["status"] = "0.0";
foreach ($found as $crt) {
  $result["data"][] = [
    "id" => (int)$crt->id,
    "quantity" => (int)$crt->quantity,
    "price" => (float)$crt->price,
    "priceText" => \number_format($crt->price, currency_decimals($crt->currency), ".", ","),
    "discount" => (float)$crt->discount,
    "discountText" => \number_format($crt->discount, currency_decimals($crt->currency), ".", ","),
    "user" => $crt->user,
    "description" => Data::getLen($crt->description, 15),
    "created" => "{$tym->monthDay($crt->created())} {$tym->HMS($crt->created())}"
  ];
}
echo \json_encode($result);
exit;