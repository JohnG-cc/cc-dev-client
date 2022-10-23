<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Credit Commons client</title>
    <link type="text/css" rel="stylesheet" href="/assets/style.css" media="all" />
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
    <script>
      // Of course printing passwords in url or the html is not advised!
      var ccuser = "<?php print $_GET['acc']??'';?>";
      var ccauth = "<?php print $_GET['key']??'';?>";
    </script>
    <script type="text/javascript" src="/assets/script.js"></script>
  </head><?php
    ini_set('display_errors', 1);
    require_once __DIR__ . '/vendor/autoload.php';
    include 'Node.php'; // why not a proper class?
    if ($_GET and isset($_GET['node'])) {
      try {
        $node = new Node($_GET['node'], $_GET['acc']??'', $_GET['key']??'');
        global $request_options;
        if ($request_options = $node->requester()->getOptions()) {
          require 'client.php';
          exit;
        }
      }
      catch (\CreditCommons\Exceptions\CCError $e) {
        echo "Unable to connect to ".$_GET['node'].': '.$e->makeMessage();
      }
      catch (\Throwable $e) {
        echo "Unable to connect to ".$_GET['node'].': '.$e->getMessage();
      }
    }
  ?>
  <body>
    <form method="get">
      <?php if (file_exists('./client.ini'))extract(parse_ini_file('./client.ini')); ?>
      <p>Enter credentials for a credit commons node:</p>
      <p>
        Node Url <input name="node" placeholder = "http://" <?php if (isset($url)): ?>value="<?php print $url; ?>" <?php endif; ?>/><br />
        User ID <input name="acc" value="<?php print $_GET['acc'] ?? '';?>" /><?php if (isset($user)): ?>Choose from <?php print $user; endif; ?><br />
        Key <input name="key" <?php if (isset($auth)): ?>value="<?php print $auth; ?>" <?php endif; ?> />
      </p>
      <input type="submit">
    </form>
  </body>
</html>
