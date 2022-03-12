<?php

use CCClient\API;

class Node {

  public $name; // @todo
  public $accountNames;
  public $handshake;

  function __construct(
    public string $url,
    public string $acc='',
    public string $key=''
  ) {
    $this->name = '$node_name';// There's no api call to retreive the node.
    $this->handshake = $this->requester()->handshake();
    $this->accountNames = $this->requester()->accountNameAutocomplete();
  }

  function requester(bool $show = FALSE) : API {
    $requester = new API($this->url, $this->acc, $this->key);
    $requester->show = $show;
    return $requester;
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

  function selectAccountWidget($element_name, $default_val, $class, $all_option) {
    if ($this->isIsolated()) {
      $output[] = "<select name=\"$element_name\">";
      if ($all_option) {
        $output[] = '  <option value="">- All -</option>';
      }

      foreach ($this->accountNames as $acc_id) {
        $output[] = '  <option value="'.$acc_id.'"'.($default_val==$acc_id?'selected':'').'>'.$acc_id.'</option>';
      }
      $output[] = "</select>";
      return implode("\n", $output);
    }
    else {
      return '<input name = "'.$element_name.'" type="text" placeholder="ancestors/node/account" value="'. $default_val .'" class="'.$class.'" title="Reference any account in the tree using an absolute or relative address. Note that you are only entitled to insepct trunkwards nodes, though others may expose their data." />';
    }
  }

  function isIsolated() : bool {
    return array_search('200', $this->handshake) <> NULL;
  }

  function accountNameAutoComplete(string $chars = '', $show = FALSE) : array {
    try {
      $acc_ids = $this->requester($show)->accountNameAutocomplete($chars);
    }
    catch (CCError $e) {
      clientAddError('Failed to retrieve account names: '.$e->makeMessage());
      $acc_ids = [];
    }
    return $acc_ids;
  }

}
