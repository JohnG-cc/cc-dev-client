<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Setup your new node</title>
    <link type="text/css" rel="stylesheet" href="style.css" media="all">
    </style>
    <script>
      function ratevisibility(nodename) {
        var rateElement=document.getElementById(nodename + "-rate");
        var x = document.getElementById(nodename + "-parent_url").value;
        if (x === "")// Oops this doesn't work. need to test for empty
          rateElement.style.display="hidden";
        else rateElement.style.display="inherit";
      }
    </script>
  </head><?php
require 'shared.php';
$node_name = $_SERVER['QUERY_STRING'];

if ($_POST) {
  foreach ($_POST as $node_name => $data) {
    if (is_array($data)) {
      $nodes[$node_name]->init(
        explode("\n", $data['accounts']),
        $data['payee_fee'],
        $data['payer_fee'],
        $data['parent_url'],
        $data['rate']
      );
    }
  }
}
elseif ($node_name) {// build the form
  check_connection(array_keys($nodes)); // this should cause no prob.
  $form[] = node_form($nodes[$node_name]);
}
else {
  foreach ($nodes as $node) {
  check_connection(array_keys($nodes)); // this should cause no prob.
    $form[] = "<label>$node->name</label><br />" . node_form($node);
  }
}
  ?>
  <body>
    <?php if (!empty($_POST)) : ?>
      <div class="messages"><h3>Messages</h3><?php print implode('<br />', $info); ?> </div>
      Everything is installed. Go to the <a href="/index.php">developer client</a>.
    <?php else: ?>
    <form id = "form1" method="post" class="front">
      <?php print implode('<hr>', $form); ?>
      <input type = "submit" name = "submit" value = "Complete Setup"></br />
    <?php endif; ?>
    </form>
  </body>
</html><?php

function check_connection(array $node_names) {
  global $nodes;
  if ($node_names) {
    $failed = [];
    foreach ($node_names as $node_name) {
      list($code) = $nodes[$node_name]->ping();
      if ($code <> 200) {
        $failed[] = $node_name;
      }
    }
    if ($failed) {
      clientAddError("Check the apache conf and /etc/hosts: failed to ping ".implode(' & ', $failed));
    }
    else {
      clientAddInfo('Successfully pinged all connected nodes.');
    }
  }
}

function node_form($node) {
  global $nodes;
  $last_char = substr($node->name, -1);
  $rate = 1 + is_numeric($last_char) ? $last_char : 0;
  $form[] = 'At least one account name, (one per line):<textarea name = "'.$node->name.'[accounts]" placeholder="alice
bob">alice'."\n".'bob</textarea>';
  $form[] = '<br />';
  $form[] = '<br />';
  $form[] = 'Percent payee fee (integer units or percent) <input name = "'.$node->name.'[payee_fee]" />';
  $form[] = '<br />';
  $form[] = 'Percent payer fee (integer units or percent) <input name = "'.$node->name.'[payer_fee]" />';
  $form[] = '<br />';
  $form[] = "A 'fees' account will be created if needed.";
  $form[] = '<br />';
  $form[] = "Rootwards node url <!--including protocol-->";
  $form[] = selectParent($nodes, $node->name) .'(optional)';
  $form[] = '<br />';
  $form[] = '<div id ="'.$node->name.'-rate" style="display:none;">';
  $form[] = 'Exchange rate with parent node <input id="rate" name="'.$node->name.'[rate]" value="'.$rate.'">';
  $form[] = '<br />';
  $form[] = "(Fraction or decimal the number of parent units in your currency.)";
  $form[] = "</div>";

  return implode("\n", $form);
}

function selectparent($nodes, $node_name) {
  unset($nodes[$node_name]);
  $options = ['', 'https://supergroup.creditcommons.net'];
  foreach ($nodes as $node){
    $options[]= $node->url;
  }
  $output = '<select id = "'.$node_name.'-parent_url" name="'.$node_name.'[parent_url]" onchange="ratevisibility(\''.$node_name.'\')">';
  foreach ($options as $node_url) {
    $output .= "<option value = \"{$node_url}\">$node_url</option>";
  }
  return $output .= "</select>";
}