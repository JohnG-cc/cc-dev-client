<?php

use CreditCommons\ClientAPI;

class Node {

  public $name;
  public $url;

  /**
   * @param string $node_name
   * @param string $node_url
   */
  function __construct(string $node_name, string $node_url) {
    $this->name = $node_name;
    $this->url = $node_url;
    $this->creditCommonsClient()->handshake();
  }

  function creditCommonsClient() {
    return new ClientAPI($this->url, $_SESSION['user'] == 'anon' ? '' : $_SESSION['user'], 123);
  }

  static function load($url) {
    return new static ($url, $url);
  }

  /**
   * Find and load all the nodes from the adjacent directories.
   * @return Node[]
   */
  static function loadAll() : array {
    $nodes = [];
    $dir = new DirectoryIterator('../');
    foreach ($dir as $fileinfo) {
      if ($fileinfo->isFile()) {
        continue;
      }
      if ($fileinfo->getFilename() == 'node_template') {
        continue;
      }
      $ledgerServicePath = realpath($fileinfo->getPathName() .'/ledgerService');
      if (is_dir($ledgerServicePath)) {
        $vars = parse_ini_file($ledgerServicePath .'/ledger.ini');
        $nodes[$fileinfo->getFilename()] = new Node($fileinfo->getFilename(), $vars['node_url']);
      }
    }
    // Alphabetical order?
    ksort($nodes);
    return $nodes;
  }

  /**
   * Create a new node from the template, including db and db credentials.
   *
   * @param string $mode
   * @param string $user
   * @param string $pass
   * @return bool
   *   TRUE on success.
   */
  function __generate(string $mode, string $server = 'localhost', string $user = 'root', string $pass = '') : bool {
    global $nodes;
    // Copy (or make links to) all the files.
    if (!$this->copyFromTemplate($mode)) {
      clientAddError("unable to replicate directory at ".$node_dir);
      return FALSE;
    }
    //Because the vHosts and hosts file aren't set up yet, these settings must
    //be changed directly, not using the Requester and the API
    $ledgerInifile = "../$this->name/ledgerService/ledger.ini";
    $lines = file($ledgerInifile, FILE_IGNORE_NEW_LINES);
    setConfig($lines, 'db_server', $server);
    setConfig($lines, 'db_user', $user);
    setConfig($lines, 'db_pass', $pass);
    setConfig($lines, 'db_name', $this->getDbName());
    setConfig($lines, 'node_url', $this->url);
    file_put_contents($ledgerInifile, implode("\n", $lines));
    $this->makeDb($this->getDbName());
    $nodes[$this->name] = $this;
    return TRUE;
  }

  /**
   *
   * @param array $accounts
   * @param string $payee_fee
   * @param string $payer_fee
   * @param string $trunkward_url
   * @param type $rate
   */
  function init(array $accounts, $payee_fee, $payer_fee, $trunkward_url, $rate = 1) {
    if (!empty($payer_fee) or !empty($payee_fee)) {
      $this->addFees('fees', $payee_fee, $payer_fee);
    }
    foreach (array_filter($accounts) as $name) {
      $this->addAccount(trim($name));
    }
    if ($trunkward_url) {
      if (!is_numeric($rate) and count($div = explode('/', $rate)) == 2) {
        $rate = $div[0]/$div[1];
      }
      elseif (!is_numeric($rate)) {
        echo "Rate '$rate' must be numeric or expressed as a fraction e.g. 2/7";
        exit;
      }
      $this->addAccountOnRootwardsNode($trunkward_url, $rate);
    }
    else {
      clientAddInfo("$this->name has no trunkwards node");
    }
  }

  /**
   * Create a database for the new node
   * @param string $node_name
   * @param string $node_url
   * @param array $settings
   * @return string
   */
  function makeDb($db_name) : string {
    $user = $this->get('ledger', 'db_user');
    $pass = $this->get('ledger', 'db_pass');
    $connection = new mysqli('localhost', $user, $pass);
    $connection->query("DROP DATABASE $db_name");
    $connection->query("CREATE DATABASE $db_name");
    Db::connect($db_name, $user, $pass);
    foreach (explode(';', file_get_contents('../node_template/ledgerService/db.sql')) as $q) {
      if ($query = trim($q)) {
        Db::query($query);
      }
    }
    clientAddInfo("Database $db_name created.");
    return $db_name;
  }

  /**
   * @param string $node_url
   * @return string
   */
  function getDbName() : string {
    $name = str_replace(['.', '-', ' ', '/'], '', $this->name);
    return 'credcom_'.strtoLower($name);
  }

  /**
   * Write values to a node service ini file.
   * @param array $values
   * @param string $service
   * @return boolean
   *   TRUE on success
   */
  private function set(array $values, $service) {
    $result = $this->getRequester($service)->setConfig($values);
    if (!$result) {
      foreach($values as $key => $val) {
        clientAddInfo("Set $key to '$val'");
      }
      return TRUE;
    }
    else {
      foreach($result as $message) {
        clientAddInfo($message);
      }
    }
    return FALSE;
  }

  /**
   * @param string $service
   * @param string $name
   * @return string
   */
  function get(string $service, string $name) : string {
    $fName = "../$this->name/{$service}.ini";
    $vars = parse_ini_file($fName);
    return $vars[$name];
  }

  /**
   * Get the virtualHost name of this node.
   * @return string
   */
  public function getHostname() {
    return parse_url($this->url)['host'];
  }

  /**
   * @param string $dir
   *   The name of the directory
   * @param string $mode
   *   copy or link, name of the php function
   * @return bool
   *   TRUE on success
   */
  function copyFromTemplate($mode = 'copy') :bool {
    chdir('../');
    if (is_dir($this->name)) {
      if (!self::deleteDir()) {
        clientAddError("wasn't able to delete $this->name directory in ".getcwd());
        return FALSE;
      }
    }
    if (!mkdir($this->name)) {
      clientAddError("wasn't able to create $this->name directory in ".getcwd());
      return FALSE;
    }
    $linkable_extensions = ['php', 'htaccess', 'htm', 'sql'];
    $files = new RecursiveDirectoryIterator('node_template', FilesystemIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($files);
    foreach ($iterator as $fileinfo) {
      $new = str_replace('node_template', $this->name, $fileinfo->getRealPath());
      if (!is_dir(dirname($new))){
        mkdir(dirname($new));
      }
      $op = in_array($fileinfo->getExtension(), $linkable_extensions) ? $mode : 'copy';
      $op($fileinfo->getPathname(), $new);
    }
    chdir('ccclient');
    return TRUE;
  }

  /**
   * Check the url is a credit commons node, and then add it as a trunkward account.
   *
   * @param string $url
   */
  function addAccountOnRootwardsNode($url, $rate = 1, $local = FALSE) : void {
    global $nodes;
    if ($trunkwards_node_name = $this->checkRootwards($url) and isset($nodes[$trunkwards_node_name]) ) {
      die('todo addAccountOnRootwardsNode');
      $ini = ['bot_account' => $trunkwards_node_name, 'bot_rate' => $rate];
      $this->set($ini, 'ledger');
      $this->addAccount($trunkwards_node_name, $url);
      $nodes[$trunkwards_node_name]->addAccount($this->name, $this->url);
      // @todo change the above to this.
      $code = $this->creditCommonsClient()->join($trunkwards_node_name, 'secret', $url);
      if ($code != 200) {
        clientAddInfo("Unable to create new account on trunkwards node");
      }
      //handshake the new account just to test.
      list ($code, $details) = $this->creditCommonsClient()->handshake();
      if ($code == 200) {
        clientAddInfo("Successfully connected to the trunkward node with rate ".$rate);
      }
      else {
        clientAddInfo("There was a $code problem connecting to the trunkward node");
      }
    }
    else {
      clientAddError("The trunkwards node $trunkwards_node_name does not exist.");
    }
  }

  public function ping() {
    return $this->creditCommonsClient()->handshake();
  }

  /**
   * Test the new (remote) url and retrive the node name
   * @param string $url
   * @return string
   *   The name of the trunkwards node.
   */
  private function checkRootwards(string $url) {
    // Peer certificate CN=`cavesoft.net' did not match expected CN=`ledger.demo.credcom.dev
    stream_context_set_default([
      'ssl' => [
        'peer_name' => 'generic-server',
        'verify_peer' => FALSE,
        'verify_peer_name' => FALSE,
        'allow_self_signed'=> TRUE
      ]
    ]);
    // We can't use the requester here because the client requester only requests
    // from the current node. Also because this request is to the top level of
    // the node, not to a service.
    if (@file_get_contents($url)) {
      foreach ($http_response_header as $header) {
        if (preg_match('/^Node-name: ?(.*)$/', $header, $matches)) {
          clientAddInfo('Rootwards node '.$url. ' is online');
          return $matches[1];
        }
      }
      clientAddError('Rootwards node did not return Node-Name header');
      print_r($http_response_header);
      return;
    }
    clientAddError('Could not ping trunkwards node '.$url);
  }

  /**
   *
   * @param string $name
   *   The desired name of the fees account
   * @param string $payee_fee
   *   a fixed number or percentage of the transaction to charge
   * @param string $payer_fee
   *   a fixed number or percentage of the transaction to charge
   */
  function addFees(string $name, string $payee_fee, string $payer_fee) {
    $this->addAccount($name);
    $ini = ['fees_account' => $name];
    // blogic.ini state the fees and fees acount
    if ($payee_fee) {
      $ini['payee_fee'] = $payee_fee;
    }
    if ($payer_fee) {
      $ini['payer_fee'] = $payer_fee;
    }
    $this->set($ini, 'blogic');
  }

  /**
   * Add an account to a node
   * @param string $name
   * @param string $url
   * @todo This doesn't use the API
   */
  function addAccount($name, $url='') {
    if ($this->getRequester('AccountStore')->join($name)) {
      if ($url) {
        $this->getRequester('AccountStore')->override($name, ['url' => $url]);
      }
      clientAddInfo("Created account $name on $this->name");
    }
  }

  /**
   * Empty the ledger database.
   */
  function truncate() {
    Db::connect(
      $this->get('ledger', 'db_name'),
      $this->get('ledger', 'db_user'),
      $this->get('ledger', 'db_pass')
    );
    Db::query('TRUNCATE TABLE temp');
    Db::query('TRUNCATE TABLE transactions');
    Db::query('TRUNCATE TABLE entries');
    Db::query('TRUNCATE TABLE log');
  }

  /**
   * Get the html and javascript of the balance chart
   * @return array
   */
  function renderChart() : array {
    $map = $this->creditCommonsClient()->getAccountSummary();
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
    $policies = $this->creditCommonsClient()->filterTransactions();
    foreach ($accounts as $acc_name => $info) {
      $color = empty($policies[$acc_name]->url) ? 'black' : 'gray';
      $data[] = "['$acc_name', {$info->completed->balance}, '$color']";
    }
    if ($data) {
      $output = str_replace('["", 0, ""]', implode(",\n    ", $data), $output);
    }
    return $output;
  }

  /**
  * Thanks to https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
  * @return bool
  *   TRUE on success
  */
 function deleteDir($dirPath = '') : bool {
   if (!$dirPath) {
     $dirPath = $this->name;
   }
   if (!is_dir($dirPath)) {
     throw new InvalidArgumentException("$dirPath must be a directory");
   }
   if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
     $dirPath .= '/';
   }
   $files = new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS);
   foreach ($files as $file) {
     if ($file->isDir()) {
       if (!self::deleteDir($file->getPathname())) {
         clientAddError('unable to delete directory '.$file);
         return FALSE;
       }
     }
     else {
       if (!unlink($file->getPathname())) {
         clientAddError('unable to delete file '.$file);
         return FALSE;
       }
     }
   }
   return rmdir($dirPath);
 }
}
