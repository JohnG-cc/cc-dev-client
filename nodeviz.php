<?php

function get_one_balance_chart(Node $node) : string {
  list ($div, $js_charts) = $node->renderChart();
  return $div . balance_charts_template($js_charts);
}

function get_one_history_chart(string $acc_id, array $data) : string {
  $id = "history_chart_$acc_id";
  return chart_library() .
    populate_history_chart_template($acc_id, $id, $data) .
    '<div class = "history-chart" id = "'.$id.'"></div>';
}

/**
 *
 * @global array $nodes
 * @return array
 *   the html divs and the javascript
 */
function print_all_charts() : void {
  global $nodes;
  $tree = node_tree();

  $levels = $charts = [];
  $level = 1;
  $output = '<p>The following charts show the account balances on different ledgers.
    Each ledger is in principle, independent technologically, politically and monetarily.
    The left and right sides of each ledger are balanced, because credit = debt.</p>';
  $iterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($tree), RecursiveIteratorIterator::SELF_FIRST
  );
  foreach ($iterator as $node_name => $array) {
    $level = $iterator->getDepth();
    list ($div, $js_chart) = $nodes[$node_name]->renderChart();
    $charts[] = $js_chart;
    $levels[$level][] = $div;
  }
  print balance_charts_template(implode("\n", $charts));

  foreach (array_reverse($levels) as $l => $renders) {
    $output .=  "\n<div class=\"balance-charts level-$l\" align=\"center\">";
    $output .= implode("\n", $renders);
    $output .= "\n</div>";
  }
  $output .="<p>The upper ledgers have accounts for ordinary users, while the lower ledgers have accounts for ledgers on the level above.
    Just as a ledger represents an agreement to exchange between individual users, the lower ledgers represent agreements to exchange between groups of users.
    The grey lines represent the balances which are cryptographically tied to an adjacent ledger.
    The names of the grey accounts correspond but the numbers do not because each ledger has its own unit of value.
  </p>";
  print $output;
}



function balance_charts_template($js_charts) : string {
  return chart_library().'
    <script type="text/javascript">
      function drawBalanceCharts() {
        '.$js_charts.'
      }
      google.charts.setOnLoadCallback(drawBalanceCharts);
    </script>
  ';
}

function chart_library() {
  static $done = FALSE;
  if (!$done) {
    return '<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>'
    . '<script type="text/javascript">google.charts.load("current", {packages:["corechart"]});</script>';
  }
  $done = TRUE;
}

function node_tree() {
  die('@todo node_tree()');
  global $nodes;
  // recursively build the node tree
  $tree = [];
  foreach ($nodes as $name => $node){
    //$parents[$name] = $node->get('ledger', 'bot_account');
  }
  asort($parents);
  local_node_branches($parents, $tree);
  return $tree;
}


function populate_history_chart_template(string $acc_id, string $id, array $data) : string {
  $js = '<script>
google.charts.setOnLoadCallback($id);
function $id() {
  var data = new google.visualization.arrayToDataTable([
    ["Date", "value"],
    $rows
  ]);
  var options = {
    title: "History of $acc_id",
    width: 300,
    height: 200,
    legend: {position: "none"}
  }
  new google.visualization.LineChart(document.getElementById("$id")).draw(data, options);
}</script>';
  $js = str_replace('$acc_id', $acc_id, $js);
  $js = str_replace('$id', $id, $js);
  foreach ($data as $date => $balance) {
    $rows[] = "[new Date('$date'), $balance]";
  }
  return str_replace('$rows', implode(",\n    ", $rows), $js);

}

/**
 * Recursive function to build local tree.
 * @param type $parents
 * @param array $empty_branch
 * @param type $branchname
 */
function local_node_branches($parents, &$empty_branch, $branchname = '') {
  static $depth = 1;
  foreach ($parents as $node_name => $parent_name) {
    if ($parent_name == $branchname) {
      $empty_branch[$node_name] = [];
      local_node_branches($parents, $empty_branch[$node_name], $node_name);
    }
  }
  $depth++;
}

