<?php
namespace IO;
require_once "../../.appinit.php";

use Parsedown;
use \TymFrontiers\HTTP,
    \TymFrontiers\Generic,
    \TymFrontiers\Data,
    \TymFrontiers\InstanceError;

\require_login(false);
$errors = [];
$gen = new Generic;
$params = $gen->requestParam([
  "id" => ["id","int"]
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
?>
<div id="fader-flow">
  <div class="view-space-midi">
    <div class="paddn -pall -p30">&nbsp;</div>
    <br class="c-f">
    <div class="grid-10-tablet grid-8-laptop  grid-6-desktop center-tablet">
      <div class="sec-div theme-color native bg-white drop-shadow">
        <header class="paddn -pall -p30 color-bg">
            <h1 class="fw-lighter"> <i class="fas fa-bell-on"></i> Notification</h1>
        </header>
        <div class="paddn -pall -p30">
          <?php if(!empty($errors)){ ?>
            <h3>Unresolved error(s)</h3>
            <ol>
              <?php foreach($errors as $err){  echo " <li>{$err}</li>"; } ?>
            </ol>
          <?php } else { ?>
            <h3><?php echo $notice->heading; ?></h3>
            <?php echo (new \Parsedown())->text($notice->message); ?>
          <?php } ?>
          <br class="c-f">
        </div>
      </div>
    </div>
    <br class="c-f">
  </div>
</div>

<script type="text/javascript">
</script>
