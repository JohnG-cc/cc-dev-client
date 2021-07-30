<?php

/**
 * Developer client for Credit Commons nodes
 * $node is already loaded
 * $options is already loaded
 */

use CreditCommons\Transaction;
use CreditCommons\NewTransaction;
use CreditCommons\Workflows;
use CreditCommons\Workflow;

$message_file = './messages.msg';
@unlink('../devel.log');
set_error_handler('display_errors_warnings');
$account_names = $node->requester()->accountNameAutocomplete();
?><body bgcolor="fafafa">

  Connected to: <?php print $node->url; ?> as <?php print $node->user(); ?>. <a href="index.php">Change...</a>
  <hr />
<?php
global $errorfield;
if ($_POST) {
  extract($_POST);
  if (isset($filterTransactions)) {
    $transactions = $node->requester()->filterTransactions($_REQUEST);
    if (!empty($_REQUEST['full'])) {
      //clientAddInfo(makeTransactionSentences($transactions));
      // not this because it puts a form within a form
      setResponse(makeTransactionSentences($transactions), 'green');
    }
    else {
      //clientAddInfo(implode('<br />', $transactions));
      setResponse("Transaction uuids:<br />" . implode('<br />', $transactions), 'green');
    }
  }
  elseif(isset($accountNameAutocomplete)) {
    $results = $node->requester()->accountNameAutocomplete($fragment);
    setResponse(implode("\n", $results));
  }
  elseif (isset($accountSummary)) {
    if ($name = $_POST['acc_name']) {
      if ($stats = $node->requester()->getAccountSummary($name)) {
        setResponse(formatStats($name, $stats), 'green');
      }
      else {
        setResponse("Unable to retrieve stats for '$name'", 'red');
      }
    }
    else {
      $formatted = '';
      foreach ($stats = $node->requester()->getAccountSummary() as $name => $stats) {
        $formatted .= formatStats($name, $stats);
      }
      setResponse($formatted, 'green');
    }
  }
  elseif (isset($accountLimits)) {
    setResponse($node->requester()->getAccountLimits($_POST['limitname']));
  }
  elseif(isset($accountHistory)) {
    // Get the balances and times.
    $name = $_POST['acc_name'];
    $history = $node->requester()->getAccountHistory($name);
    if (count($history) > 2) {
      setResponse($history, 'green');
    }
    else {
      setResponse("No history for '$name'", 'green');
    }
  }
  elseif (isset($stateChange) and isset($uuid)) {
    //Permission is assumed, at least here in the client.
    if ($node->requester()->transactionChangeState($uuid, $stateChange)) {
      clientAddInfo('Transaction saved');
    }
  }
  elseif (isset($workflows)) {
    $wf = $node->requester()->getWorkflows();
    setResponse((array)$wf, 'green');
  }
  elseif (isset($handshake)) {
    list($code, $shakes) = $node->requester()->handshake();
    if (empty($shakes)) {
      $shakes = 'No remote nodes connected to this node';
    }
    setResponse($shakes, 'green');
  }
  elseif (isset($absoluteAddress)) {
    $node_names = $node->requester()->getTrunkwardNodeNames();
    setResponse('/'.implode('/', array_reverse($node_names)), 'green');
  }
  elseif (isset($join)) {
    $code = $node->requester()->join($join_name, $join_url);
    if ($code == 200) {
      setResponse("The $join_name account was created", 'green');
    }
    elseif ($code == 404) {
      setResponse("Name already taken", 'red');
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
  $operations = $node->requester()->getOptions();
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
  $attributes = $output = [];
  MakeForm::$method($output);
  $fields = implode($output);
  $attributes['id'][] = $method;
  $attributes['class'][] = 'method';
  if ($is_front) {
    $attributes['class'][] ='front';
  }
  if ($method == 'stateChange') {
    return getDivTag($attributes, $fields);
  }
  else {
    return getFormTag($attributes, $fields);
  }
}

class MakeForm {

  static function permittedEndPoints(&$output) {
    global $node;
    $output[] = '<h3>Available REST API endpoints for '.$node->user().'</h3>';
    $output[] = '<p>call the node with the REST OPTIONS method to see which endpoints you are permitted to use.</p>';
    $output[] = '<pre>'.print_r($node->requester()->getOptions(), 1).'</pre>';
  }

  static function absoluteAddress(&$output) {
    global $absoluteAddress;
    $output[] = '<h3>Absolute address</h3>';
    $output[] = '<p>Get the the trunkwards nodes which comprise the absolute address of this node.</p>';
    if ($absoluteAddress) {
      $output[] = getResponse();
    }
    $output[] = '<input type = "submit" name = "absoluteAddress" value="Fetch" />';
  }

  static function newTransaction(&$output) {
    global $uuid, $newTransaction, $payer, $payee, $quant, $description, $errorfield, $type, $node;

    $output[] = '<h3>Register transaction</h3>';
    if ($newTransaction) {
      $output[] = $response = getResponse();
    }
    $workflows = $node->requester()->getWorkflows();
    $output[] = '<select name = "type">';
    // Show only the top level workflows, for simplicity
    foreach(reset($workflows) as $workflow) {
      $selected = $type == $workflow->id ? 'selected="selected"' : '';
      $output[] = '<option value="'.$workflow->id.'" '.$selected.'>'.$workflow->label . ': '.$workflow->summary.'</option>';
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
    if ($pending = $node->requester()->filterTransactions(['state'=> 'pending', 'full' => 1])) {
      $output[] = '<h4>Incomplete Transactions:</h4>';
      foreach ($pending as $t) {
        // show only the pending transactions the current user doesn't have to sign.
        // Transactions which require action are shown at the top
        if (!isset($t->actions->completed)) {
          $output[] = makeTransactionSentences([$t]);
        }
      }
    }
    if ($transactions = $node->requester()->filterTransactions(['full' => 1, 'state' => 'completed'])) {
      $output[] = '<h4>Completed Transactions:</h4>';
      $output[] = makeTransactionSentences($transactions);
    }
    // Get all the transactions on the ledger
    if ($transactions = $node->requester()->filterTransactions(['full' => 1, 'state' => 'erased'])) {
      $output[] = '<h4>Erased Transactions:</h4>';
      $output[] = makeTransactionSentences($transactions);
    }
    if (!$output) {
      $output[] = 'No transactions in the system yet...';
    }
  }


  static function filterTransactions(&$output) {
    global $payer, $payee, $involving, $uuid, $state, $before, $after, $description, $full, $filterTransactions;
    $output[] = '<h3>Filter Transactions</h3>';
    $output[] = '<p>See any transaction on this node (no access control for members)</p>';
    if ($filterTransactions) {
      $output[] = getResponse();
    }
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
    $output[] = '  <option value="validated" '.($state == 'validated' ? "selected" : '').'>Validated</option>';
    $output[] = '  <option value="timedout" '.($state == 'timedout' ? "selected" : '').'>Timed out</option>';
    $output[] = '</select>';
    $output[] = '<br /><label>Date Before</label>';
    $output[] = '<input type="date" name="before" value = "'.$before.'"/>';
    $output[] = '<br /><label>Date After</label>';
    $output[] = '<input type="date" name="after" value = "'.$after.'"/>';
    $output[] = '<br /><label>Description</label>';
    $output[] = '<input type="textfield" name="description" />';
    $output[] = '<br /><label>Show full transaction</label>';
    $output[] = '<input type="checkbox" name="full" '. ($full??'checked').'"/>';
    $output[] = '<br /><input type = "submit" name = "filterTransactions" value="View"/>';
    $output[] = ' (There may be more filters detailed in the API documentation)';
  }

  static function accountNameAutocomplete(&$output) {
    global $fragment, $accountNameAutocomplete, $node;
    $output[] = '<h3>View accounts</h3>';
    $output[] = '<p>Member can see all accounts on all the trunkwards ledgers, but leafwards ledger reveal accounts at their own discretion.</p>';
    if ($accountNameAutocomplete) {
      $output[] = getResponse();
    }
    $output[] = '<p>Put a fragment of an accountname or path</p>';
    $output[] = '<p>Fragment: <input name="fragment" value="'. $fragment .'" />';
    $output[] = '<input type = "submit" name = "accountNameAutocomplete" value = "Query"/></p>';
  }

  static function accountLimits(&$output) {
    global $accountLimits, $limitname;
    $output[] = '<h3>Retrieve account balance limits</h3>';
    if ($accountLimits) {
      $output[] = getResponse();
    }
    $output[] = '<br />Get limits for ';
    $output[] = selectAccount('limitname', $limitname);
    $output[] = '<br /><br /><input type = "submit" name = "accountLimits" value = "Retreive" />';
  }
  static function accountSummary(&$output) {
    global $acc_name, $accountSummary;
    $output[] = '<h3>View account</h3>';
    $output[] = '<p>Trading summary for an account</p>';
    if ($accountSummary){
      $output[] = getResponse();
    }
    $output[] = '<label class=required >Account name</label>';
    $output[] = selectAccount('acc_name', $acc_name, NULL, TRUE).'<br />';
    $output[] = 'Relative paths to accounts on rootwards nodes (@todo + open branchwards nodes)';
    $output[] = '<br /><br /><input type = "submit" name = "accountSummary" value = "Show Account" />';
  }

  static function accountHistory(&$output) {
    global $acc_name, $accountHistory, $history;
    $output[] = '<h3>View account history</h3>';
    if (isset($history) and @count($history) > 2) {
      $output[] = get_one_history_chart($acc_name, $history);
    }
    $output[] = '<p>A list of balances and times, starting with account creation.</p>';
    if ($accountHistory){
      $output[] = getResponse();
    }
    $output[] = '<label class=required >Account name or path</label>';
    $output[] = selectAccount('acc_name', $acc_name).'<br />';
    $output[] = '<br /><input type = "submit" name = "accountHistory" value = "Show History" />';
  }

  static function workflows(&$output) {
    global $workflows;
    $output[] = '<p>Workflows must be shared between nodes who would share transactions. Therefore workflows are read from rootwards nodes.</p>';
    if ($workflows) {
      $output[] = getResponse();
    }

    $output[] = '<input type = "submit" name = "workflows" value="View" />';
  }

  static function handshake(&$output) {
    global $handshake;
    $output[] = '<p>Check that connected ledgers are online and the hashes match.</p>';
    if ($handshake) {
      $output[] = getResponse();
    }
    $output[] = '<input type = "submit" name = "handshake" value="Handshakes" />';
  }

  static function join(&$output) {
    global $join_name, $join_url, $join;
    if ($join) {
      $output[] = getResponse();
    }
    $output[] = 'Name of new member';
    $output[] = '<input name = "join_name" placeholder="a..." value = "'.$join_name.'" /><br />';
    $output[] = 'Url of new member';
    $output[] = '<input name = "join_url" placeholder="https://..." value = "'.$join_url.'" /><br />';

    $output[] = '<input type = "submit" name = "join" value="Join" />';
  }

}

function getDivTag(array $attributes, $content) {
  $atts = [];
  foreach ($attributes as $at => $vals) {
    $atts[] = $at .'="'.implode(' ', $vals).'"';
  }
  return '<div '.implode(' ', $atts) .'>'.$content.'</div>';
}

function getFormTag($attributes, $content) {
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
  return '<input name = "'.$element_name.'" type="text" placeholder="ancestors/node/account" value="'. $default_val .'" class="'.$class.'" title="Reference any account in the tree using an absolute or relative address. Note that you are only entitled to insepct rootwards nodes, though others may expose their data." />';
}

function selectAccStatus(string $element_name, $default_val = '') {
  $output[] = "<select name=\"$element_name\">";
  $output[] = '  <option value="active"'.($default_val=='active'?'selected':'').'>Active</option>';
  $output[] = '  <option value="any"'.($default_val=='any'?'selected':'').'>Any</option>';
  $output[] = '  <option value="blocked"'.($default_val=='blocked'?'selected':'').'>Blocked</option>';
  $output[] = '</select>';
  return implode("\n", $output);
}


function setResponse($value, $col = 'green') {
  global $response;
  $response = "<font color=$col>".print_r($value, 1)."</font>";
}

/**
 *
 * @global string $response
 * @return string
 */
function getResponse() {
  global $response;
  if ($response) {
    $response = '<pre>'.$response.'</pre>';
  }
  return $response;
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
    $output = '<div class ="feedback" title="The request url and body, and any errors returned.">';
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
    $main = new NewTransaction (
      trim($_POST['payee']),
      trim($_POST['payer']),
      trim($_POST['quant']),
      trim($_POST['description']), // this is optional
      $_POST['type'] // this is optional
    );
    if ($transaction = $node->requester()->submitNewTransaction($main)) {
     //setResponse($transaction);
      $transactions[] = $transaction;
    }
  }
  // for the current user to sign
  if ($pending = $node->requester()->filterTransactions(['state'=> 'pending', 'full' => 1])) {
    foreach ($pending as $t) {
      if (isset($t->actions->completed)) {
        $transactions[] = $t;
      }
    }
  }
  if ($transactions) {
    print '<div class="attention" title="This box is just to show transactions requiring the current user\'s attention">Pending transactions for '.$_GET['acc'].' to sign:';
    print makeTransactionSentences($transactions) .'</div>';
  }
}

/**
 *
 * @param Transaction[] $transactions
 * @return string
 */
function makeTransactionSentences(array $transactions) :string {
  // This assumes the entries are in order with branchward nodes following rootward nodes.
  // It is probably easier to handle if arrays of entries are returned
  $output = '';
  $template = '<div class = "@class @state">@payer will pay @payee @quant for \'@description\' @links</div>';
  $search = ['@class', '@payee', '@payer', '@quant', '@description', '@links'];
  foreach ($transactions as $transaction) {
    $links = CreditCommons\Workflows::actionLabels($transaction->workflow, $transaction->state, $transaction->actions);
    // put the links next to the first entry.
    $first = TRUE;
    foreach ($transaction->entries as $entry) {
      $replace = [
        $first ? "primary" : "dependent",
        $entry->payee->id, // NB these are spoofed account objects, see CreditCommons\Entry::Create
        $entry->payer->id,
        $entry->quant,
        $entry->description,
        render_action_links($transaction->uuid, $links)
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
 * @param array $actions
 * @return string
 */
function render_action_links($uuid, array $links) : string {
  $output = [];
  if ($links) {
    $output[] = '<form method="post" class="inline" action="">';
    $output[] = '<input type="hidden" name="uuid" value="'.$uuid.'">';
    foreach ($links as $target_state => $label) {
      $output[] = '<button type="submit" name="stateChange" value="'.$target_state.'" class="link-button">'.$label.'</button>';
    }
  }
  $output[] = '</form>';
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
      $type = 'E_NOTICE';break;
    case E_WARNING:
      $type = 'E_WARNING';break;
    case E_USER_NOTICE:
      $type = 'E_USER_NOTICE';break;
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
  //echo $message; dfdf();
  clientAddError($message);
}
