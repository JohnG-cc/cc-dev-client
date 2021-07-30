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
    ini_set('display_errors', 1);
    include 'Node.php';
    if ($_GET and isset($_GET['node'])) {
      $node = new Node($_GET['node'], $_GET['acc']??'', $_GET['key']??'');
      if ($options = $node->requester()->getOptions()) {
        require 'client.php';
        exit;
      }

  }?>
  <body>
    <form method="get">
      <p>The developer Client needs admin credentials:</p>
      <p>Node Url <input name="node" />
      <p>User ID <input name="acc" />
      <br />Key <input name="key" /></p>
      <input type="submit">
    </form>
  </body>
</html>