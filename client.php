<?php

/**
 * Developer client for Credit Commons nodes
 * $node is already loaded
 * $options is already loaded
 */

use CCClient\Transaction;
use CreditCommons\Leaf\NewTransaction;
use CreditCommons\Exceptions\CCError;

$message_file = './messages.msg';
@unlink('../devel.log');
set_error_handler('display_errors_warnings');
$account_names = account_name_autocomplete();
?><body bgcolor="fafafa">

  Connected to: <?php print $node->url; ?> as <?php print $node->user(); ?>. <a href="index.php">Change...</a>
  <hr />
<?php
global $errorfield;
if ($_POST) {
  extract($_POST);
  if (isset($filterTransactions)) {
    get_filtered_transactions($_REQUEST, TRUE);
  }
  elseif (isset($getTransaction)) {
    try {
      retrieveTransaction($uuid, $format, TRUE);
    }
    catch (CCError $e) {
      clientAddError("Unable to retrieve transaction $uuid: ".$e->makeMessage() );
    }
  }
  elseif(isset($accountNameAutocomplete)) {
    account_name_autocomplete($fragment, TRUE);
  }
  elseif (isset($accountSummary)) {
    try {
      $node->requester(TRUE)->getAccountSummary($acc_id);
    }
    catch (CCError $e) {
      clientAddError("Failed to retrieve stats for account $acc_id: ".$e->makeMessage() );
    }
  }
  elseif (isset($accountLimits)) {
    try {
      $node->requester(TRUE)->getAccountLimits($acc_id);
    }
    catch (CCError $e) {
      clientAddError("Failed to get limits of account $acc_id: ".$e->makeMessage() );
    }
  }
  elseif(isset($accountHistory)) {
    // Get the balances and times.
    try {
      $node->requester(TRUE)->getAccountHistory($acc_id);
    }
    catch (CCError $e) {
      clientAddError("Failed to get history of account $acc_id: ".$e->makeMessage() );
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
      $node->requester(TRUE)->getTrunkwardNodeNames();
    }
    catch (CCError $e) {
      clientAddError('Failed to get trunkward node names: '.$e->makeMessage() );
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
    <script>
var coll = document.getElementsByClassName("collapsible");
var i;
for (i = 0; i < coll.length; i++) {
  coll[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var content = this.nextElementSibling;
    if (content.style.display === "block") {
      content.style.display = "none";
    } else {
      content.style.display = "block";
    }
  });
}</script>
  </body>
</html><?php
if (file_exists($message_file))@unlink($message_file);

/**
 * Render all the operations as a set of tabs
 */
function render_tabs() {
  global $node;
  // Get the operations this user is permitted for, in no particular order.\
  $operations = getNodeOperations();

  unset($operations['relayTransaction']);
  $operationIds = array_keys($operations);

  foreach ($operationIds as $op) {
    if (isset($_POST[$op])) {
      $front_op = $op;
      break;
    }
  }
  if (!isset($front_op)) {
    $front_op = reset($operationIds);
  }
  foreach ($operationIds as $method) {
    $output[] = get_method_form($method, $front_op==$method);
    $but_att = [
      'onclick' => ["openTab(event,'.littletab','.method','$method')"],
      'class' => ['littletab'],// front?
    ];
    if ($front_op==$method) {
      $but_att['class'][] = 'front';
    }
    $buttons[] = getDivTag($but_att, $method);
  }
  array_unshift($buttons, '<div style="display:inline-block; padding: 0.3em; font-size:85%;" title="These are the names of the endpoints corresponding to the openapi documentation">Methods >></div>');
  return getDivTag([], implode($buttons).implode("\n", $output).'<br />'.get_api_log());
}

/**
 * Render a method form as a subtab
 * @param type $method
 * @param type $is_front
 * @return type
 */
function get_method_form($method, $is_front) {
  global $raw_result;
  $attributes = $form_lines = [];
  MakeForm::$method($form_lines);
  $attributes['id'][] = $method;
  $attributes['class'][] = 'method';
  if ($is_front) {
    $attributes['class'][] ='front';
  }
  if ($method == 'stateChange') {
    return getDivTag($attributes, implode($form_lines));
  }
  else {
    $output = '';
    if ($raw_result and $is_front) {
      clientAddInfo('<p><strong>Response:</strong><br /><pre>'.json_prettify($raw_result).'</pre>');
    }
    return $output .= wrapInFormTags($attributes, implode($form_lines));
  }
}

class MakeForm {

  static function permittedEndPoints(&$output) {
    global $node;
    $output[] = '<h3>Available REST API endpoints for '.$node->user().'</h3>';
    $output[] = '<p>call the node with the REST OPTIONS method to see which endpoints you are permitted to use.</p>';
    $output[] = '<pre>'.print_r(getNodeOperations(), 1).'</pre>';
  }

  static function trunkwardNodes(&$output) {
    global $absoluteAddress;
    $output[] = '<h3>Trunkward nodes</h3>';
    $output[] = '<p>Get the the trunkwards nodes which comprise the absolute address of this node.</p>';
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
    $output[] = '<label class=required>Payee</label>';
    $output[] = selectAccount('payee', $payee??'alice', $errorfield == 'payee' ? "error" :'').'<br />';
    $output[] = '<label class=required>Payer</label>';
    $output[] = selectAccount('payer', $payer??'bob', $errorfield == 'payer' ? "error" : '').'<br />';

    $output[] = '<label class=required>Quantity</label>';
    $output[] = '<input name = "quant" type="number" placeholder = "0" value="'.($quant ?? '10') .'" class="'.($errorfield == 'quant' ? "error" : '').'" /><br />';
    $output[] = '<label>Description</label>';
    $output[] = '<input name = "description" type="text" placeholder="blah blah" value="'.($description ?? 'blah blah').'" /><br />';
    $output[] = '<input type = "submit" name = "newTransaction" value="Create" />';
  }

  static function stateChange(&$output) {
    global $stateChange, $node;
    if ($pending_uuids = get_filtered_transactions(['state' => 'pending'], TRUE)) {
      $output[] = '<h4>Incomplete Transactions:</h4>';
      foreach ($pending_uuids as $uuid) {
        try {
          $t = retrieveTransaction($uuid, 'full');
          // show only the pending transactions the current user doesn't have to sign.
          // Transactions which require action are shown at the top
          if (!isset($t->transitions->completed)) {
            $output[] = makeTransactionSentences([$t]);
          }
        }
        catch (CCError $e) {
          clientAddError("Unable to retrieve transaction $uuid: ".$e->makeMessage() );
        }
      }
    }
    if ($t_uuids = get_filtered_transactions(['state' => 'completed'])) {
      $output[] = '<h4>Completed Transactions:</h4>';
      foreach ($t_uuids as $uuid) {
        try {
          $completes[] = retrieveTransaction($uuid, 'full');
        }
        catch (CCError $e) {
          clientAddError("Unable to retrieve transaction $uuid. ".$e->getMessage());
        }
      }
      $output[] = makeTransactionSentences($completes);
    }
    // Get all the transactions on the ledger
    if ($e_uuids = get_filtered_transactions(['state' => 'erased'])) {
      $output[] = '<h4>Erased Transactions:</h4>';
      foreach ($e_uuids as $uuid) {
        try {
          $eraseds[] = retrieveTransaction($uuid, 'full');
        }
        catch (CCError $e) {
          clientAddError("Unable to retrieve transaction $uuid: ".$e->makeMessage() );
        }
      }
      $output[] = makeTransactionSentences($eraseds);
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
    $output[] = '<p><label>Format</label>';
    $output[] = '<br /><input type="radio" name="format" value="HTML">Full';
    $output[] = '<br /><input type="radio" name="format" value="flat">Flat</p>';
    $output[] = '<br /><input type = "submit" name = "filterTransactions" value="View" />';
  }

  static function filterTransactions(&$output) {
    global $payer, $payee, $involving, $uuid, $state, $before, $after, $description, $format, $filterTransactions;
    $output[] = '<h3>Filter Transactions</h3>';
    $output[] = '<p>See any transaction on this node (no access control for members)</p>';
    $output[] = '<br /><label>Payee</label>';
    $output[] = '<input type="textfield" name="payee"/>';
    $output[] = '<br /><label>Payer</label>';
    $output[] = '<input type="textfield" name="payer""/>';
    $output[] = '<br /><label>Either payee or payer</label>';
    $output[] = '<input type="textfield" name="involving" value="'.$involving.'"/>';
    $output[] = '<br /><label>UUID</label>';
    $output[] = '<input name = "uuid" type="text" placeholder="71d6bdcb-0b9c-442f-b6bb-01f3a5f95def" value="'.($uuid ?? '').'" />';
    $output[] = '<br /><label>state</label>';
    $output[] = '<select name = "state">';
    $output[] = '  <option value="">-- Any --</option>';
    $output[] = '  <option value="completed" '.($state == 'completed' ? "selected" : '').'>Completed</option>';
    $output[] = '  <option value="pending" '.($state == 'pending' ? "selected" : '').'>Pending</option>';
    $output[] = '  <option value="erased" '.($state == 'erased' ? "selected" : '').'>Erased</option>';
    $output[] = '  <option value="validated" '.($state == 'validated' ? "selected" : '').'>Validated (visible only to author)</option>';
    $output[] = '  <option value="timedout" '.($state == 'timedout' ? "selected" : '').'>Timed out</option>';
    $output[] = '</select>';
    $output[] = '<br /><label>Updated Before</label>';
    $output[] = '<input type="date" name="before" value = "'.$before.'"/>';
    $output[] = '<br /><label>Updated After</label>';
    $output[] = '<input type="date" name="after" value = "'.$after.'"/>';
    $output[] = '<br /><label>Description</label>';
    $output[] = '<input type="textfield" name="description" />';
    $output[] = '<br /><input type = "submit" name = "filterTransactions" value="View UUIDs" />';
    $output[] = ' (There may be more filters detailed in the API documentation)';
  }

  static function accountNameAutocomplete(&$output) {
    global $fragment, $accountNameAutocomplete, $node;
    $output[] = '<h3>View accounts</h3>';
    $output[] = '<p>Member can see all accounts on all the trunkwards ledgers, but leafwards ledger reveal accounts at their own discretion.</p>';
    $output[] = '<p>Put a fragment of an accountname or path</p>';
    $output[] = '<p>Fragment: <input name="fragment" value="'. $fragment .'" />';
    $output[] = '<input type = "submit" name = "accountNameAutocomplete" value = "Query"/></p>';
  }

  static function accountLimits(&$output) {
    global $accountLimits, $acc_id;
    $output[] = '<h3>Retrieve account balance limits</h3>';
    $output[] = '<br />Get limits for ';
    $output[] = selectAccount('acc_id', $acc_id);
    $output[] = '<br /><br /><input type = "submit" name = "accountLimits" value = "Retreive" />';
  }

  static function accountSummary(&$output) {
    global $acc_id, $accountSummary, $raw_result;
    $output[] = '<h3>View account(s) summary</h3>';
    $output[] = '<p>Trading statistics</p>';
    if ($accountSummary) {
      $stats = json_decode($raw_result);
      if ($acc_id) {
        $output[] = formatStats($acc_id, $stats);
      }
      else {
        foreach ($stats as $name => $stats) {
          $output[] = formatStats($name, $stats);
        }
      }
    }
    $output[] = '<br /><label class=required >Account name</label>';
    $output[] = selectAccount('acc_id', $acc_id, NULL, TRUE).'<br />';
    $output[] = 'Relative paths to accounts on trunkwards nodes (@todo + open branchwards nodes)';
    $output[] = '<br /><br /><input type = "submit" name = "accountSummary" value = "Show Account" />';
  }

  static function accountHistory(&$output) {
    global $acc_id, $accountHistory, $raw_result;
    $output[] = '<h3>View account history</h3>';
    if ($accountHistory and $raw_result) {
      $history = (array)json_decode($raw_result);
      if (count($history) > 2) {
        require_once 'nodeviz.php';
        $output[] = get_one_history_chart($acc_id, $history);
      }
    }
    $output[] = '<p>A list of balances and times, starting with account creation.</p>';
    $output[] = '<label class=required >Account name or path</label>';
    $output[] = selectAccount('acc_id', $acc_id).'<br />';
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

function getDivTag(array $attributes, $content) {
  $atts = [];
  foreach ($attributes as $at => $vals) {
    $atts[] = $at .'="'.implode(' ', $vals).'"';
  }
  return '<div '.implode(' ', $atts) .'>'.$content.'</div>';
}

function wrapInFormTags($attributes, $content) {
  foreach ($attributes as $at => $vals) {
    $atts[] = $at .'="'.implode(' ', $vals).'"';
  }
  return '<form method=post '.implode(' ', $atts) .'>'.$content.'</form>';
}

function selectAccount(string $element_name, $default_val = '', $class = '', $all_option = FALSE) {
  //$callback = is_isolated_node() ? 'selectAccountLocal': 'selectAccountTree';
  $callback = 'selectAccountLocal';
  return $callback($element_name, $default_val, $class, $all_option);
}

function selectAccountLocal(string $element_name, $default_val = '', $class = '', $all_option = FALSE) : string {
  global $account_names;
  $output[] = "<select name=\"$element_name\">";
  if ($all_option) {
    $output[] = '  <option value="">- All -</option>';
  }
  foreach ($account_names as $name) {
    $output[] = '  <option value="'.$name.'"'.($default_val==$name?'selected':'').'>'.$name.'</option>';
  }
  $output[] = "</select>";
  return implode("\n", $output);
}

function selectAccountTree(string $element_name, $default_val = '', $class = '', $all_option = FALSE) : string {
  return '<input name = "'.$element_name.'" type="text" placeholder="ancestors/node/account" value="'. $default_val .'" class="'.$class.'" title="Reference any account in the tree using an absolute or relative address. Note that you are only entitled to insepct trunkwards nodes, though others may expose their data." />';
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
  $gitlablink = "https://gitlab.com/credit-commons-software-stack/credit-commons-microservices/-/raw/master/docs/credit-commons-openapi-3.0.yml";
  $swaggerlink = 'https://app.swaggerhub.com/apis/matslats/creditcommons/0.2';
  $link = ' API: '
    . '<a href="'.$gitlablink.'" title="See the API formally described in OpenAPI format" target="_blank">Raw</a> | '
    . '<a href="'.$swaggerlink.'" title="Interact with the API in swaggerhub" target="_blank">Rendered</a>';
  $link .= ' <a class = "collapsible" title="API calls and latest responses">Log</a>';
  return $link;
}

function topTransactions() {
  global $node;
  if (empty($node->acc)) {
    return;
  }
  $transactions = [];
  // Show this user's actions at the top of the page.
  if (isset($_POST['newTransaction'])) {
    $fields = (object)[
      'payee' => trim($_POST['payee']),
      'payer' => trim($_POST['payer']),
      'quant' => trim($_POST['quant']),
      'description' => trim($_POST['description']), // this is optional
      'type' => $_POST['type'] // this is optional
    ];
    $newT = NewTransaction::create($fields);
    $result = $node->requester(TRUE)->submitNewTransaction($newT);
    $transactions[] = Transaction::createFromJsonClass($result);
  }
  // Any other initiated transactions
  $validated = get_filtered_transactions(['state'=> 'validated']);
  // unset the previous one.

  // for the current user to sign
  $pending = get_filtered_transactions(['state'=> 'pending']);

  foreach ($pending as $t) {
    if (isset($t->actions->completed)) {
      $transactions[] = $t;
    }
  }
  if ($transactions) {
    print '<div class="attention" title="This box is just to show transactions requiring the current user\'s attention">Validated and pending transactions of '.$_GET['acc'];
    print makeTransactionSentences($transactions) .'</div>';
  }
}

/**
 *
 * @param Transaction[] $transactions in full format.
 * @return string
 */
function makeTransactionSentences(array $transactions) :string {
  // This assumes the entries are in order with branchward nodes following trunkward nodes.
  // It is probably easier to handle if arrays of entries are returned
  $output = '';
  $template = '<div class = "@class @state">@payer will pay @payee @quant for \'@description\' @links</div>';
  $search = ['@class', '@state', '@payee', '@payer', '@quant', '@description', '@links'];
  foreach ($transactions as $transaction) {
    // put the links next to the first entry.
    $first = TRUE;
    foreach ($transaction->entries as $entry) {
      $replace = [
        $first ? "primary" : "dependent",
        $transaction->state,
        $entry->payee, // NB these are spoofed account objects, see CreditCommons\Entry::Create
        $entry->payer,
        $entry->quant,
        $entry->description,
        render_action_links($transaction)
      ];
      $output .= str_replace($search, $replace, $template);
      $first = FALSE;
    }
  }
  return $output;
}

/**
 * Render the transaction action links as forms which can post
 * @param string $uuid
 * @param array $labels
 *   action labels, keyed by target state.
 * @return string
 */
function render_action_links(Transaction $transaction) : string {
  global $node, $user;
  if ($actions = $transaction->transitions) {
    $output[] = '<form method="post" class="inline" action="">';
    $output[] = '<input type="hidden" name="uuid" value="'.$transaction->uuid.'">';
    $workflows = get_all_workflows();
    if (isset($workflows[$transaction->type])) {
      foreach ($workflows[$transaction->type]->actionLabels($transaction->state, $actions) as $target_state => $label) {
        $output[] = '<button type="submit" name="stateChange" value="'.$target_state.'" class="link-button">'.$label.'</button>';
      }
      $output[] = '</form>';
    }
    else {
      clientAddError("Missing workflow: $transaction->type:");
    }
  }
  else {
    $output[] = "<span title = \"".$_GET['acc']." is not permittions to do anything to this '$transaction->type' transaction\">(No transitions)</span>";
  }
  return implode($output);
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
  $message = str_replace('BoT', '<span title="Balance of Trade account">BoT</span>', $message);
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
    $message = str_replace('BoT', '<span title="Balance of Trade account">BoT</span>', $message);
    $info[] = '<font color="green">'.$message.'</font>';
  }
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

function retrieveTransaction(string $uuid, string $format, $show = FALSE) : Transaction {
  global $node;
  $json = $node->requester($show)->getTransaction($uuid, $format);
  // Upcast the results to the right format,
  if ($format == 'full') {
    $transaction = \CCClient\Transaction::create($json);
  }
  elseif ($format == 'entry') {
    $transaction = \CreditCommons\FlatEntry::create($json);
  }
  return $transaction;
}


function getNodeOperations() : array {
  // NB this is sometimes called more than once. could be cached.
  global $node;
  try {
    $operations = $node->requester()->getOptions();
  }
  catch (CCError $e) {
    clientAddError('Unable to retrive paths from node: '.$e->makeMessage() );
    $operations = [];
  }
  return $operations;
}

function get_filtered_transactions(array $filters, $show = FALSE) : array {
  global $node;
  try {
    $results = $node->requester($show)->filterTransactions($filters);
  }
  catch (CCError $e) {
    clientAddError('Failed to load pending transactions: '.$e->makeMessage() );
    $results = [];
  }
  return $results;
}

function account_name_autocomplete(string $chars = '', $show = FALSE) : array {
  global $node;
  try {
    $acc_ids = $node->requester($show)->accountNameAutocomplete($chars);
  }
  catch (CCError $e) {
    clientAddError('Failed to retrieve account names.'.$e->makeMessage());
    $acc_ids = [];
  }
  return $acc_ids;
}

function json_prettify(string $json) {
	$result      = '';
	$pos         = 0;
	$strLen      = strlen($json);
	$indentStr   = '  ';
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;

	for ($i=0; $i<=$strLen; $i++)
	{
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