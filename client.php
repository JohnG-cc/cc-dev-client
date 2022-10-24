<?php

/**
 * Developer client for Credit Commons nodes
 * $node is already loaded
 * $options is already loaded
 */

use CCClient\Transaction;
use CreditCommons\NewTransaction;
use CreditCommons\Exceptions\CCError;

$message_file = './messages.msg';
@unlink('../devel.log');
set_error_handler('display_errors_warnings');
set_exception_handler('print_exception_to_file')
?><body bgcolor="fafafa">
  Connected to: <?php print $node->url; ?> as <?php print $node->user(); ?>. <a href="index.php">Change...</a>
  <hr />
<?php
global $errorfield;
if ($_POST) {
  extract($_POST);
  if (isset($filterTransactions)) {
    get_filtered_transactions($_POST, TRUE);
  }
  if (isset($filterTransactionEntries)) {
    get_filtered_entries($_POST, TRUE);
  }
  elseif (isset($getTransaction)) {
    try {
      $node->requester(TRUE)->getUpcastTransaction($uuid);
    }
    catch (CCError $e) {
      clientAddError("Unable to retrieve transaction $uuid: ".$e->makeMessage() );
    }
  }
  elseif (isset($getEntries)) {
    try {
      $node->requester(TRUE)->getUpcastEntries($uuid);
    }
    catch (CCError $e) {
      clientAddError("Unable to retrieve transaction $uuid: ".$e->makeMessage() );
    }
  }
  elseif(isset($accountNameFilter)) {
    $node->accountNameFilter($fragment, TRUE);
  }
  elseif (isset($accountSummary)) {
    try {
      $node->requester(TRUE)->getAccountSummary($acc_path);
    }
    catch (\Exception $e) {
      clientAddError("Failed to retrieve stats for account $acc_path: ".$e->makeMessage() );
    }
  }
  elseif (isset($accountLimits)) {
    try {
      $node->requester(TRUE)->getAccountLimits($acc_path);
    }
    catch (CCError $e) {
      clientAddError("Failed to get limits of account $acc_path: ".$e->makeMessage() );
    }
  }
  elseif(isset($accountHistory)) {
    // Get the balances and times.
    try {
      $node->requester(TRUE)->getAccountHistory($acc_path);
    }
    catch (CCError $e) {
      clientAddError("Failed to get history of account $acc_path: ".$e->makeMessage() );
    }

  }
  elseif (isset($stateChange) and isset($uuid)) {
    try {
      $node->requester(TRUE)->transactionChangeState($uuid, $stateChange);
      clientAddInfo('Transaction saved');
    }
    catch (CCError $e) {
      clientAddError('Failed to change transaction state: '.$e->makeMessage() );
    }
  }
  elseif (isset($workflows)) {
    get_all_workflows(TRUE);
  }
  elseif (isset($handshake)) {
    try {
      $node->requester(TRUE)->handshake();
    }
    catch (CCError $e) {
      clientAddError('Failed to handshake node: '.$e->makeMessage() );
    }
  }
  elseif (isset($absoluteAddress)) {
    try {
      $node->requester(TRUE)->getAbsolutePath();
    }
    catch (CCError $e) {
      clientAddError('Failed to get trunkward node names: '.$e->makeMessage() );
    }
  }
  elseif (isset($convertPrice)) {
    try {
      $ratio = $node->requester(TRUE)->convertPrice($pricenode);
    }
    catch (CCError $e) {
      clientAddError('Failed to get price ratio: '.$e->makeMessage() );
    }
  }
}
?>
<?php $tabs = render_tabs();
print topTransactions(); ?>
  <?php if (isset($node)) : ?>
    <?php print showInfo();
    if (file_exists($message_file) and $m = unserialize(@file_get_contents($message_file))) : ?>
      <!-- print messages before generating the forms so we only get the messages deriving the POST, which was processed above. -->
      <a class="messages collapsible" title="Contents of Misc::message() function throughout the reference implementation">Under the hood</a>
      <div class="collapsible-content"><?php print implode("\n<br />", $m); ?></div>
    <?php endif; ?>
    <?php
    $log = get_devel_log();
    print $tabs ?>
  <?php endif; ?>
  <?php print $log; ?>
  </body>
</html><?php
if (file_exists($message_file))@unlink($message_file);

/**
 * Render all the operations as a set of tabs
 */
function render_tabs() {
  global $node, $request_options;

  unset($request_options['relayTransaction']);
  $operationIds = array_keys($request_options);

  foreach ($operationIds as $op) {
    if (isset($_POST[$op])) {
      $front_op = $op;
      break;
    }
  }
  if (!isset($front_op)) {
    $front_op = reset($operationIds);
  }
  $tab_rows[] = '<tr><td title="These are the names of the endpoints corresponding to the openapi documentation">Methods >></td></tr>';
  foreach ($operationIds as $method) {
    $output[] = get_method_form($method, $front_op==$method);
    $attr = [
      'onclick' => ["openTab(event,'.tab','.method','$method')"],
      'class' => ['tab'],// front?
    ];
    if ($front_op==$method) {
      $attr['class'][] = 'front';
    }
    $tab_rows[] = '<tr>'.makeHtmlBlock('td', $method, $attr).'</tr>';
  }
  $tab_rows[] = '<tr><td>'.get_api_log().'</td></tr>';
  $functions_table = '<table class="tabs">'.implode($tab_rows).'</table>';

  return $functions_table.implode("\n", $output);
}

/**
 * Render a method form as a subtab
 * @param string $method
 * @param bool $is_front
 * @return string
 */
function get_method_form(string $method, bool $is_front) : string {
  global $raw_result;
  $attributes = $form_lines = [];
  MakeForm::$method($form_lines);
  $attributes['id'][] = $method;
  $attributes['class'][] = 'method';
  if ($is_front) {
    $attributes['class'][] ='front';
  }
  if ($method == 'stateChange') {
    return makeHtmlBlock('div', implode($form_lines), $attributes);
  }
  else {
    if ($raw_result and $is_front) {
      clientAddInfo('<p><strong>Response:</strong><br /><pre>'.json_prettify($raw_result).'</pre>');
    }
    $attributes['method'][] = 'post';
    return makeHtmlBlock('form', implode($form_lines), $attributes);
  }
}

class MakeForm {

  static function convertPrice(&$output) {
    global $pricenode, $convertPrice;
    $output[] = '<h3>Convert price</h3>';
    $output[] = '<p>Get the ratio of the unit of value on a remote node compare the current node</p>';
    $output[] = '<input name = "pricenode" class = "required" value= "'. $pricenode .'" />';
    $output[] = '<input type = "submit" name = "convertPrice" value = "Create" />';
  }

  static function permittedEndPoints(&$output) {
    global $node, $request_options;
    $output[] = '<h3>Available REST API endpoints for '.$node->user().'</h3>';
    $output[] = '<p>call the node with the REST OPTIONS method to see which endpoints you are permitted to use.</p>';
    $output[] = '<pre>'.print_r($request_options, 1).'</pre>';
  }

  static function absolutePath(&$output) {
    global $absoluteAddress;
    $output[] = '<h3>Absolute path</h3>';
    $output[] = '<p>of this node in the credit commons tree.</p>';
    $output[] = '<input type = "submit" name = "absoluteAddress" value="Fetch" />';
  }


  static function newTransaction(&$output) {
    global $uuid, $newTransaction, $payer, $payee, $quant, $description, $errorfield, $type, $node;

    $output[] = '<h3>Register transaction</h3>';
    $output[] = '<select name = "type">';
    // Show only the top level workflows, for simplicity
    foreach(get_all_workflows() as $id => $workflow) {
      $selected = $type == $id ? 'selected="selected"' : '';
      $output[] = '<option value="'.$id.'" '.$selected.'>'.$workflow->label . ': '.$workflow->summary.'</option>';
    }
    $output[] = '</select>';
    $output[] = '<p>Create a transaction (as '.$_GET['acc'].')</p>';
    // Different account select widgets depending on if the node is isolated.
    $output[] = '<label class=required>Payee</label> ';
    $output[] = selectAccount('payee', $payee??'alice', $errorfield == 'payee' ? "error" :'').'<br />';
    $output[] = '<label class=required>Payer</label> ';
    $output[] = selectAccount('payer', $payer??'bob', $errorfield == 'payer' ? "error" : '').'<br />';

    $output[] = '<label class=required>Quantity</label>';
    $output[] = '<input name = "quant" type="number" placeholder = "0" value="'.($quant ?? '10') .'" class="'.($errorfield == 'quant' ? "error" : '').'" /><br />';
    $output[] = '<label>Description</label>';
    $output[] = '<input name = "description" type="text" placeholder="blah blah" value="'.($description ?? 'blah blah').'" /><br />';
    $output[] = '<input type = "submit" name = "newTransaction" value="Create" />';
  }

  static function stateChange(&$output) {
    global $stateChange, $node;
    if ($t_pending = get_filtered_transactions(['states' => ['pending']])) {
      $output[] = '<h4>Incomplete Transactions:</h4>';
      foreach ($t_pending as $t) {
        // show only the pending transactions the current user doesn't have to sign.
        // Transactions which require action are shown at the top
        if (!isset($t->transitions->completed)) {
          $output[] = makeTransactionSentences([$t]);
        }
      }
    }
    if ($t_completed = get_filtered_transactions(['states' => ['completed']])) {
      $output[] = '<h4>Completed Transactions:</h4>';
      $output[] = makeTransactionSentences($t_completed);
    }
    // Get all the transactions on the ledger

    if ($t_erased = get_filtered_transactions(['states' => ['erased']])) {
      $output[] = '<h4>Erased Transactions:</h4>';
      $output[] = makeTransactionSentences($t_erased);
    }
    if (!$output) {
      $output[] = 'No transactions yet...';
    }
  }

  static function getTransaction(&$output) {
    global $uuid, $getTransaction;
    $output[] = '<h3>Get a Transaction</h3>';
    $output[] = '<br /><label>Uuid</label>';
    $output[] = '<input type="textfield" name="uuid""/>';
    $output[] = '<br /><input type = "submit" name = "getTransaction" value="View" />';
  }
  static function getEntries(&$output) {
    global $uuid, $getEntries;
    $output[] = '<h3>Get entries of a transaction</h3>';
    $output[] = '<br /><label>Uuid</label>';
    $output[] = '<input type="textfield" name="uuid""/>';
    $output[] = '<br /><input type = "submit" name = "getEntries" value="View" />';
  }

  static function filterTransactions(&$output) {
    global $payer, $payee, $involving, $uuid, $states, $types, $before, $after, $description, $format, $filterTransactions;
    $types = (array)$types;
    $states = (array)$states;
    $output[] = '<h3>Filter Transactions</h3>';
    $output[] = '<p>See any transaction on this node (no access control for members)</p>';
    $output[] = '<label>Payee</label>';
    $output[] = '<input type="textfield" name="payee"/>';
    $output[] = '<p><label>Payer</label>';
    $output[] = '<input type="textfield" name="payer""/>';
    $output[] = '<p><label>Either payee or payer</label>';
    $output[] = '<input type="textfield" name="involving" value="'.$involving.'"/>';
    $output[] = '<p><label>Workflow states:</label>';
    $output[] = '<input type="checkbox" id="state-completed" name="states[completed]" value="completed"'.(in_array('completed', $states) ? "checked" : '').'>completed ';
    $output[] = '<input type="checkbox" id="state-pending" name="states[pending]" value="pending"'.(in_array('pending', $states) ? "checked" : '').'> pending';
    $output[] = '<input type="checkbox" id="state-erased" name="states[erased]" value="erased"'.(in_array('erased', $states) ? "checked" : '').'>erased';
    $output[] = '<input type="checkbox" id="state-validated" name="states[validated]" value="validated"'.(in_array('validated', $states) ? "checked" : '').'>validated';
    $output[] = '<p><label>Workflow types</label>';
    // todo read these from the workflow
    $output[] = '<input type="checkbox" id="type-bill" name="types[bill]" value="bill"'.(in_array('bill', $types) ? "checked" : '').'>Bill ';
    $output[] = '<input type="checkbox" id="type-credit" name="types[credit]" value="credit"'.(in_array('credit', $types) ? "checked" : '').'>Credit ';
    $output[] = '<input type="checkbox" id="type-3rdparty" name="types[3rdparty]" value="3rdparty"'.(in_array('3rdparty', $types) ? "checked" : '').'>3rdparty ';
    $output[] = '<p><label>Updated Before</label>';
    $output[] = '<input type="date" name="before" value = "'.$before.'"/>';
    $output[] = '<p><label>Updated After</label>';
    $output[] = '<input type="date" name="after" value = "'.$after.'"/>';
    $output[] = '<p><label>Description contains</label>';
    $output[] = '<input type="textfield" name="description" />';
    $output[] = '<p><input type = "submit" name = "filterTransactions" value="Full transactions" />';
    $output[] = '<p>(Check API documentation for a full list of filters)';
  }
  static function filterTransactionEntries(&$output) {
    global $payer, $payee, $involving, $uuid, $states, $types, $before, $after, $description, $format, $filterTransactions;
    $types = (array)$types;
    $states = (array)$states;
    $output[] = '<h3>Filter Transactions</h3>';
    $output[] = '<p>See any transaction on this node (no access control for members)</p>';
    $output[] = '<label>Payee</label>';
    $output[] = '<input type="textfield" name="payee"/>';
    $output[] = '<p><label>Payer</label>';
    $output[] = '<input type="textfield" name="payer""/>';
    $output[] = '<p><label>Either payee or payer</label>';
    $output[] = '<input type="textfield" name="involving" value="'.$involving.'"/>';
    $output[] = '<p><label>Workflow states:</label>';
    $output[] = '<input type="checkbox" id="state-completed" name="states[completed]" value="completed"'.(in_array('completed', $states) ? "checked" : '').'>completed ';
    $output[] = '<input type="checkbox" id="state-pending" name="states[pending]" value="pending"'.(in_array('pending', $states) ? "checked" : '').'> pending';
    $output[] = '<input type="checkbox" id="state-erased" name="states[erased]" value="erased"'.(in_array('erased', $states) ? "checked" : '').'>erased';
    $output[] = '<input type="checkbox" id="state-validated" name="states[validated]" value="validated"'.(in_array('validated', $states) ? "checked" : '').'>validated';
    $output[] = '<p><label>Workflow types</label>';
    // todo read these from the workflow
    $output[] = '<input type="checkbox" id="type-bill" name="types[bill]" value="bill"'.(in_array('bill', $types) ? "checked" : '').'>Bill ';
    $output[] = '<input type="checkbox" id="type-credit" name="types[credit]" value="credit"'.(in_array('credit', $types) ? "checked" : '').'>Credit ';
    $output[] = '<input type="checkbox" id="type-3rdparty" name="types[3rdparty]" value="3rdparty"'.(in_array('3rdparty', $types) ? "checked" : '').'>3rdparty ';
    $output[] = '<p><label>Updated Before</label>';
    $output[] = '<input type="date" name="before" value = "'.$before.'"/>';
    $output[] = '<p><label>Updated After</label>';
    $output[] = '<input type="date" name="after" value = "'.$after.'"/>';
    $output[] = '<p><label>Description contains</label>';
    $output[] = '<input type="textfield" name="description" />';
    $output[] = '<p><input type = "submit" name = "filterTransactionEntries" value="Standalone entries" />';
    $output[] = '<p>(Check API documentation for a full list of filters)';
  }

  static function accountNameFilter(&$output) {
    global $fragment;
    $output[] = '<h3>View accounts</h3>';
    $output[] = '<p>Member can see all accounts on all the trunkwards ledgers, but leafwards ledger reveal accounts at their own discretion.</p>';
    $output[] = '<p>Put a fragment of an accountname or path</p>';
    $output[] = '<p>Fragment: <input name="fragment" class="required" value="'. $fragment .'" autocomplete="off" />';
    $output[] = '<input type = "submit"  name = "accountNameFilter" value = "Query"/></p>';
  }

  static function accountLimits(&$output) {
    global $accountLimits, $acc_path;
    $output[] = '<h3>Retrieve account balance limits</h3>';
    $output[] = '<br />Get limits for ';
    $output[] = selectAccount('acc_path', $acc_path);
    $output[] = '<br /><br /><input type = "submit" name = "accountLimits" value = "Retreive" />';
  }

  static function accountSummary(&$output) {
    global $acc_path, $accountSummary, $raw_result;
    $output[] = '<h3>View account(s) summary</h3>';
    $output[] = '<p>Trading statistics</p>';
//    if ($accountSummary and $stats = json_decode($raw_result)->data) {
//      if (isset($stats->pending)) {
//        $output[] = formatStats($acc_path, $stats);
//      }
//      else {
//        foreach ($stats as $name => $stats) {
//          $output[] = formatStats($name, $stats);
//        }
//      }
//    }
    $output[] = '<br /><label class=required >Account name or path</label>';
    $output[] = selectAccount('acc_path', $acc_path, NULL, TRUE).'<br />';
    $output[] = 'Use relative paths to explore other nodes.';
    $output[] = "Leave blank for summaries of all active accounts on the current node. ";
    $output[] = '<br /><br /><input type = "submit" name = "accountSummary" value = "Show Account" />';
    $output[] =  "<br />(@todo + ajax autocomplete on remote nodes)";
  }

  static function accountHistory(&$output) {
    global $acc_path, $accountHistory, $raw_result;
    $output[] = '<h3>View account history</h3>';
    if ($accountHistory and $raw_result) {
      $history = (array)json_decode($raw_result);
      if (count($history) > 2) {
        require_once 'nodeviz.php';
        $output[] = get_one_history_chart($acc_path, $history);
      }
    }
    $output[] = '<p>A list of balances and times, starting with account creation.</p>';
    $output[] = '<label class=required >Account name or path</label>';
    $output[] = selectAccount('acc_path', $acc_path).'<br />';
    $output[] = '<br /><input type = "submit" name = "accountHistory" value = "Show History" />';
  }

  static function workflows(&$output) {
    global $workflows;
    $output[] = '<p>Workflows must be shared between nodes who would share transactions. Therefore workflows are read from trunkwards nodes and can be overriden by local translations</p>';
    $output[] = '<input type = "submit" name = "workflows" value="View" />';
  }

  static function handshake(&$output) {
    global $handshake;
    $output[] = '<p>Check that connected ledgers are online and the hashes match.</p>';
    $output[] = '<input type = "submit" name = "handshake" value="Handshakes" />';
  }
}

function makeHtmlBlock($tag_name, $content, array $attributes  = []) : string {
  $atts = [];
  foreach ($attributes as $at => $vals) {
    $atts[] = $at .'="'.implode(' ', $vals).'"';
  }
  return "<$tag_name ".implode(' ', $atts) .">$content</$tag_name>";
}

function selectAccount(string $element_name, $default_val = '', $class = '', $all_option = FALSE) {
  global $node;
  return $node->selectAccountWidget($element_name, $default_val, $class, $all_option);
}

function selectAccStatus(string $element_name, $default_val = '') {
  $output[] = "<select name=\"$element_name\">";
  $output[] = '  <option value="active"'.($default_val=='active'?'selected':'').'>Active</option>';
  $output[] = '  <option value="any"'.($default_val=='any'?'selected':'').'>Any</option>';
  $output[] = '  <option value="blocked"'.($default_val=='blocked'?'selected':'').'>Blocked</option>';
  $output[] = '</select>';
  return implode("\n", $output);
}


function get_devel_log() : string {
  global $log_file;
  $output = '';
  if (file_exists($log_file)) {
    $log = file_get_contents($log_file);
    if (strlen($log) > 10) {
      $output= '<div class="log" title="put Creditcommons\Misc::log($expression); anywhere in the code"><h3>Debug messages</h3>'.$log.'</div>';
    }
  }
  if (file_exists('../devel.log'))@unlink('../devel.log');
  return $output;
}

function showInfo() : string {
  global $info;
  $output = '';
  if ($info) {
    $output = '<div class ="feedback" title="The request and json response.">';
    $output .= implode("\n<br />", $info);
    $output .= '</div>';
  }
  return $output;
}

function get_api_log() : string {
  $gitlablink = "https://gitlab.com/credit-commons/cc-php-lib/-/blob/master/docs/credit-commons-v0.2.openapi3.yml";
  $link = '<a href="'.$gitlablink.'" title="See the API formally described in OpenAPI format" target="_blank">OpenAPI spec</a>';
  return $link;
}

function topTransactions() {
  global $node;
  if (empty($node->acc)) {
    return;
  }
  $top_transactions = [];
  // Show this user's actions at the top of the page.
  if (isset($_POST['newTransaction'])) {
    $fields = (object)[
      'payee' => trim($_POST['payee']),
      'payer' => trim($_POST['payer']),
      'quant' => trim($_POST['quant']),
      'description' => trim($_POST['description']), // this is optional
      'type' => $_POST['type'] // this is optional
    ];
    ini_set('display_errors', 1);
    $newT = NewTransaction::create($fields);
    // Depending on the transaction type/workflow, the result could be a
    // transaction in 'full' format, needing confirmation, or nothing.
    [$transaction_data, $transitions] = $node->requester(TRUE)->submitNewTransaction($newT);
    if ($transaction_data) {
      $transaction = \CCClient\Transaction::createFromJsonClass($transaction_data, $transitions);
      $top_transactions[] = $transaction;
      clientAddInfo($transaction);
    }
  }
  else {
    // Any other initiated transactions
    $top_transitions = get_filtered_transactions(['states'=> ['validated']]);
  }
  // For the current user to sign
  $pending = get_filtered_transactions(['states'=> ['pending']]);
  foreach ($pending as $t) {
    if (in_array('completed', $t->transitions)) {
      $top_transactions[] = $t;
    }
  }
  if ($top_transactions) {
    print '<div class="attention" title="This box is just to show transactions requiring the current user\'s attention">Validated and pending transactions of '.$_GET['acc'].':';
    print makeTransactionSentences($top_transactions) .'</div>';
  }
}

/**
 *
 * @param Transaction[] $transactions in full format.
 * @return string
 */
function makeTransactionSentences(array $transactions) : string {
  $output = '';
  foreach ($transactions as $t) {
    if ($t instanceOf \CCClient\Transaction) {
      $sentence = new \CCClient\TransactionSentence($t);
      $output .= $sentence;
    }
    else {
      $sentence = new CCClient\TransactionEntrySentence($t);
    }
  }
  return $output;
}


function formatStats($name, $data) {
  $output = '<table border=1 style="display:inline-block"><thead><tr><th>'.$name.'</th><th>Actual</th><th>Pending</th></tr></thead>';
  $output .='<tbody><tr><th>Balance</th><td>'.$data->completed->balance.'</td><td>'.$data->pending->balance.'</td></tr>';
  $output .='<tr><th>Trades</th><td>'.$data->completed->trades.'</td><td>'.$data->pending->trades.'</td></tr>';
  $output .='<tr><th>Entries</th><td>'.$data->completed->entries.'</td><td>'.$data->pending->entries.'</td></tr>';
  $output .='<tr><th>Volume</th><td>'.$data->completed->volume.'</td><td>'.$data->pending->volume.'</td></tr>';
  $output .='<tr><th>Gross Income</th><td>'.$data->completed->gross_in.'</td><td>'.$data->pending->gross_in.'</td></tr>';
  $output .='<tr><th>Gross Expenditure</th><td>'.$data->completed->gross_out.'</td><td>'.$data->pending->gross_out.'</td></tr>';
  $output .='<tr><th>Partners</th><td>'.$data->completed->partners.'</td><td>'.$data->pending->partners.'</td></tr>';
  return $output.'</tbody></table>';
}

function display_errors_warnings(int $errno , string $errstr, string $errfile, int $errline) {
  switch ($errno) {
    case E_NOTICE:
    case E_USER_NOTICE:
    case E_DEPRECATED:
      // This is used to show the API requests
      clientAddInfo($errstr);
      return;
    case E_WARNING:
      $type = 'E_WARNING';break;
    case E_USER_WARNING:
      $type = 'E_USER_WARNING';break;
    case E_USER_DEPRECATED:
      $type = 'E_USER_DEPRECATED';break;
    case E_USER_ERROR:
      $type = 'E_USER_ERROR';break;
    case E_RECOVERABLE_ERROR:
      $type = 'E_RECOVERABLE_ERROR';break;
    default:
      print_r(func_get_args());
  }
  $message = "$type: <strong>$errstr</strong> at $errfile:$errline";
  clientAddError($message);
}

function print_exception_to_file($exception) {
  file_put_contents('last_exception.log', print_r($exception, 1)); //temp
}

/**
 * Cache this request as it is needed frequently.
 * @global bool $show
 * @staticvar type $all_workflows
 * @return array
 */
function get_all_workflows(bool $show = FALSE) : array {
  global $node, $config;
  static $all_workflows;
  if (!isset($all_workflows)) {
    try {
      // These are reformatted by the CCClient\API
      $all_workflows = $node->requester($show)->getWorkflows();
    }
    catch (CCError $e) {
      clientAddError('Failed to retrieve workflows: '.$e->makeMessage() );
      $all_workflows = [];
    }
  }
  return $all_workflows;
}


function get_filtered_transactions(array $filters, $show = FALSE) : array {
  global $node;
  if (isset($filters['states']) and is_array($filters['states'])) {
    $filters['states'] = implode(',', $filters['states']);
  }
  if (isset($filters['types']) and is_array($filters['types'])) {
    $filters['types'] = implode(',', $filters['types']);
  }
  return $node->requester($show)->filterTransactions(array_filter($filters));
}

function get_filtered_entries(array $filters, $show = FALSE) : array {
  global $node;
  if (isset($filters['states']) and is_array($filters['states'])) {
    $filters['states'] = implode(',', $filters['states']);
  }
  if (isset($filters['types']) and is_array($filters['types'])) {
    $filters['types'] = implode(',', $filters['types']);
  }
    return $node->requester($show)->filterTransactionEntries(array_filter($filters));
}

function json_prettify(string $json) {
	$result      = '';
	$pos         = 0;
	$strLen      = strlen($json);
	$indentStr   = '  ';
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;
	for ($i=0; $i<=$strLen; $i++)	{
		// Grab the next character in the string.
		$char = substr($json, $i, 1);
		// Are we inside a quoted string?
		if ($char == '"' && $prevChar != '\\') {
			$outOfQuotes = !$outOfQuotes;
			// If this character is the end of an element,
			// output a new line and indent the next line.
		} else if(($char == '}' || $char == ']') && $outOfQuotes) {
			$result .= $newLine;
			$pos --;
			for ($j=0; $j<$pos; $j++) {
				$result .= $indentStr;
			}
		}
		// Add the character to the result string.
		$result .= $char;
		// If the last character was the beginning of an element,
		// output a new line and indent the next line.
		if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
			$result .= $newLine;
			if ($char == '{' || $char == '[') {
				$pos ++;
			}
			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}
		$prevChar = $char;
	}
	return $result;
}

/**
 *
 * @global string $info
 * @param mixed $message
 */
function clientAddError($message) {
  global $info;
  if (!is_string($message)) {
    $message = '<pre>'.print_r($message, 1).'</pre>';
  }
  $info[] = '<font color="red">'.$message.'</font>';
}

/**
 *
 * @global string $info
 * @param mixed $message
 */
function clientAddInfo($message) {
  global $info;
  if ($message) {
    if (!is_string($message)) {
      $message = '<pre>'.print_r($message, 1).'</pre>';
    }
    $info[] = '<font color="green">'.$message.'</font>';
  }
}
