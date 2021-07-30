<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Node maker</title>
    <link type="text/css" rel="stylesheet" href="style.css" media="all">
    <script type="text/javascript" src="script.js"></script>
    </style>
  </head><?php
require 'shared.php';
if ($_POST) {
  $mode = $_POST['mode'];
  $user = empty($_POST['db_user']) ? 'root' : trim($_POST['db_user']);
  $pass = trim($_POST['db_pass']);
  $server = trim($_POST['db_server']);

  if (isset($_POST['make1'])) {
    $node_name = trim($_POST['node_name']);
    // This works in a little more detail than makeNewNode
    if (substr($_POST['node_url'], 0, 4) != 'http') {
      $_POST['node_url'] = 'http://'.$_POST['node_url'];
    }
    (new Node($node_name, trim($_POST['node_url'])))
      ->generate($mode, $server, $user, $pass);
    restartServerMessage();
    clientAddInfo('Now <a href="initnodes.php?'.$node_name.'">set up</a> your node.');
  }
  elseif (isset($_POST['makemany'])) {
    $new_nodes = json_decode($_POST['new_nodes']);
    if (!$new_nodes) {
      clientAddError('Invalid json for new nodes');
    }
    foreach ($new_nodes as $node_name => $url) {
      (new Node($node_name, $url))->generate($mode, $server, $user, $pass);
    }
    restartServerMessage();
    clientAddInfo('Now <a href="initnodes.php">set up</a> your nodes.');
    clientAddInfo('N.B. In this demo every user is considered an admin!');
  }
}
else {
  $many_field = [
    'trunk' => $_SERVER['REQUEST_SCHEME'].'://trunk.localhost',
    'branch1' => $_SERVER['REQUEST_SCHEME'].'://branch1.localhost',
    'branch2' => $_SERVER['REQUEST_SCHEME'].'://branch2.localhost',
  ];
}?>
  <body>
    <?php if (isset($info)) :
      print '<div class="messages"><h3>Messages</h3>'.implode('<br />', $info).'</div>';
    else : ?>
    <div id="tabs">
      <div class="bigtab front" onclick="openTab(event,'.bigtab','.tabs','one-node')">One node</div>
      <?php if (empty($nodes)) : ?>
      <div class="bigtab" onclick="openTab(event,'.bigtab','.tabs','many-nodes')">Many nodes</div>
      <?php endif; ?>
    </div>

      <form method="post" id = "many-nodes" class="tabs">
        Database user<input name = "db_user" placeholder = "root"></br />
        Database password<input name = "db_pass" type = "password"></br />
        Database server<input name = "db_server" value="localhost" /></br />
        <textarea name="new_nodes" rows = "8" cols="100"><?php print json_encode($many_field,  JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); ?></textarea>
        </br />
        <input name = "mode" type="radio" value="link" checked>Link from template files</br />
        <input name = "mode" type="radio" value="copy" >Copy template files</br />
      <br /><input type = "submit"  name="makemany" value="Create nodes">
      </form>

      <form method="post" id = "one-node" class = "tabs front">
        Database user<input name = "db_user" placeholder = "root"></br />
        Database password<input name = "db_pass" type = "password" ></br />
        Database server<input name = "db_server" value="localhost" /></br />
        <br />
        Node name (one word)* <input name="node_name" /></br />
        (Dbname is generated from node name)<br />
        Node domain or subdomain including protocol <input name="node_url" placeholder="<?php print $_SERVER['REQUEST_SCHEME'] . '://mynode.domain.com'; ?>" /></br />
        </br />
        <input name = "mode" type="radio" value="link" checked>Link from template files</br />
        <input name = "mode" type="radio" value="copy" >Copy template files</br />
        <br /><input type = "submit" name="make1" value="Create node"></br />
      </form>

    <?php endif; ?>
  </body>
</html><?php

function restartServerMessage() {
  require 'ServerConfigurer.php';
  $server = ServerConfigurer::create();
  $server->setup();
  $server->showHosts();
}
