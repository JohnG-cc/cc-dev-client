<?php

use CreditCommons\ClientAPI;

class Node {

  public $name;
  public $url;
  public $acc;
  private $key;

  /**
   * @param string $node_name
   * @param string $node_url
   */
  function __construct(string $node_url, $acc='', $key='') {
    $this->url = $node_url;
    $this->name = '@todo';// to be retrieved via the api
    $this->acc = $acc;
    $this->key = $key;
  }

  function requester() : ClientAPI {
    return new ClientAPI($this->url, $this->acc, $this->key);
  }

  function user() :string {
    return $this->acc ?: '&lt;anon&gt;';
  }

  /**
   * Get the html and javascript of the balance chart
   * @return array
   */
  function renderChart() : array {
    $map = $this->requester()->getAccountSummary();
    $id = "balance_map_$this->name";
    return [
      '<div id = "'.$id.'" style="width:24%"></div>',
      $this->getChart($id, $map)
    ];
  }

  /**
   * Get the javascript chart
   * @param string $id
   * @param array $accounts
   * @return string
   */
  function getChart(string $id, array $accounts) : string {
    $output = '
    var data = google.visualization.arrayToDataTable([
      ["Acc", "Balance", { role: "style" }],
      ["", 0, ""]
    ]);
    var options = {
      title: "Account balances on '.$this->name.'",
      height: "'.(count($accounts)*16 + 65).'",
      bar: {groupWidth: "95%"},
      legend: "none"
    };
    var chart = new google.visualization.BarChart(document.getElementById("'.$id.'"));
    chart.draw(data, options);
  ';
    $data = [];
    $policies = $this->requester()->filterTransactions();
    foreach ($accounts as $acc_name => $info) {
      $color = empty($policies[$acc_name]->url) ? 'black' : 'gray';
      $data[] = "['$acc_name', {$info->completed->balance}, '$color']";
    }
    if ($data) {
      $output = str_replace('["", 0, ""]', implode(",\n    ", $data), $output);
    }
    return $output;
  }

}
