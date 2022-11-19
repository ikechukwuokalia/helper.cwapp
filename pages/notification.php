<?php
namespace IO;
require_once "../../.appinit.php";

use Parsedown;
use \TymFrontiers\HTTP,
    \TymFrontiers\Generic,
    \TymFrontiers\Data,
    \TymFrontiers\InstanceError;

\require_login(true);
$errors = [];
$gen = new Generic;
$params = $gen->requestParam([
  "id" => ["id","int"],
  "rdt" => ["rdt","url"]
], $_GET, ["id"]);
if (!$params || !empty($gen->errors)) {
  $errs = (new InstanceError ($gen, false))->get("requestParam",true);
  foreach ($errs as $er) {
    $errors[] = $er;
  }
}
if( $params ):
  if (!$notice = (new Notice)::findById($params['id'])) {
    $errors[] = "Notification was not found with given reference.";
  } else if ($notice->user !== $session->name || ws_access($notice->user, $session->name)) {
    $errors[] = "Access to view notification was denied.";
  } else {
    if (!(bool)$notice->is_read) {
      $notice->is_read = true;
      $notice->update();
    }
    try {
      if ($formated = (new \Parsedown)->text($notice->message)) {
        $notice->message = $formated;
      } else if ($formated = \Michelf\Markdown::defaultTransform($notice->message)) {
        $notice->message = $formated;
      } else {
        $notice->message = "<p>{$notice->message}</p>";
      }
    } catch (\Throwable $th) {
      $errors[] = "Notification message misunderstood";
    }
  }
endif;
if (!empty($errors)) {
  HTTP\Header::badRequest(true, \implode(" | ", $errors));
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" manifest="/site.webmanifest">
  <head>
    <meta charset="utf-8">
    <title>Notification | <?php echo get_constant("PRJ_TITLE"); ?></title>
    <?php include get_constant("PRJ_INC_ICONSET"); ?>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0'>
    <meta name="keyword" content="">
    <meta name="description" content="Notifications">
    <meta name="author" content="<?php echo get_constant("PRJ_AUTHOR"); ?>">
    <meta name="creator" content="<?php echo get_constant("PRJ_CREATOR"); ?>">
    <meta name="publisher" content="<?php echo get_constant("PRJ_PUBLISHER"); ?>">
    <meta name="robots" content='nofollow'>
        <!-- Theming styles -->
    <link rel="stylesheet" href="/app/tymfrontiers/font-awesome-pro.soswapp/css/font-awesome-pro.min.css">
    <!-- Project styling -->
    <link rel="stylesheet" href="/assets/css/base.min.css">
    <link rel="stylesheet" href="/app/cataliwos/plugin.cwapp/css/theme.min.css">
    <link rel="stylesheet" href="/app/cataliws/helper.cwapp/css/helper.min.css">
  </head>
  <body class="theme-native">
    <input type="hidden" data-setup="page" data-name="notification" data-group="user">
    <input type="hidden" data-setup="dnav" data-group="user" data-clear-elem="#main-content" data-pos="fixed" data-container="body" data-get="/app/index/get/navigation" data-ini-top-pos="<?php echo get_constant('PRJ_HEADER_HEIGHT'); ?>" data-stick-on="#page-head">
    <?php include get_constant("PRJ_INC_HEADER"); ?>

    <section id="main-content">
      <div class="view-space">
        <div class="padding -p30">&nbsp;</div>
        <div class="grid-8-tablet grid-6-laptop center-tablet">
          <div class="bg-white drop-shadow theme-color native">
            <header class="color-bg paddn -pall -p30 fw-lighter">
              <h1><i class="fad fa-bell-on"></i> Notification</h1>
            </header>
            <div class="paddn -pall -p30">
              <h2><?php echo $notice->heading; ?></h2>
              <?php echo $notice->message; ?>
              <hr>
              <p class="align-center">
                <?php if ($params['rdt']) { echo "<a href=\"{$params['rdt']}\"><i class=fas fa-arrow-left></i> Go Bank </a>"; } ?>
              </p>
            </div>
          </div>
        </div>
        <br class="c-f">
      </div>
    </section>
    <?php include get_constant("PRJ_INC_FOOTER"); ?>
    <script src="/app/cataliwos/plugin.cwapp/js/jquery.js"></script>
    <script src="/app/cataliwos/plugin.cwapp/js/functions.js"></script>
    <script src="/app/cataliwos/plugin.cwapp/js/class-object.js"></script>
    <script src="/app/cataliwos/plugin.cwapp/js/theme.js"></script>
    <script src="/assets/js/base.min.js"></script>

    <script type="text/javascript">
    </script>
  </body>
</html>
