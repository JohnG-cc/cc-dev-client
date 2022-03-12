<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Credit Commons client</title>
    <link type="text/css" rel="stylesheet" href="style.css" media="all" />
    <script type="text/javascript" src="script.js"></script>
  </head>
  <?php
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
      <p>The developer client needs admin credentials:</p>
      <p>Node Url <input name="node" placeholder = "http://" />
      <p>User ID <input name="acc" />
      <br />Key <input name="key" /></p>
      <input type="submit">
    </form>
  </body>
</html>