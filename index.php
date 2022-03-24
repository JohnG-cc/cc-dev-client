<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Credit Commons client</title>
    <link type="text/css" rel="stylesheet" href="src/style.css" media="all" />
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
    <script>
      // Of course printing passwords in url or the html is not advised!
      var ccuser = "<?php print $_GET['acc'];?>";
      var ccauth = "<?php print $_GET['key'];?>";
    </script>
    <script type="text/javascript" src="src/script.js"></script>
  </head><?php
    ini_set('display_errors', 1);
    require_once __DIR__ . '/vendor/autoload.php';
    include 'Node.php';
    if ($_GET and isset($_GET['node'])) {
      try {
        $node = new Node($_GET['node'], $_GET['acc']??'', $_GET['key']??'');
        global $request_options;
        if ($request_options = $node->requester()->getOptions()) {
          require 'client.php';
          exit;
        }
      }
      catch (\Exception $e) {
        echo "Unable to connect to ".$_GET['node'].': '.$e->getMessage();
      }
    }
  ?>
  <body>
    <form method="get">
      <p>Enter credentials for a credit commons node:</p>
      <p>
        Node Url <input name="node" placeholder = "http://" /><br />
        User ID <input name="acc" /><br />
        Key <input name="key" />
      </p>
      <input type="submit">
    </form>
  </body>
</html>