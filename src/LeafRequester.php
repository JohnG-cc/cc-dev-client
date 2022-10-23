<?php

namespace CCClient;

use GuzzleHttp\RequestOptions;
use CreditCommons\StandaloneEntry;
use CCClient\Transaction;
use CreditCommons\Exceptions\CCError;

/**
 * Class for a non-ledger client to call to a credit commons accounting node.
 * This wraps the RestAPI in order to handle the authentication and errors
 * appropriate for the client.
 * Clients should instantiate this
 */
class LeafRequester extends \CreditCommons\Leaf\LeafRequester {

  /**
   * TRUE if this is the main query to be printed in the CCClient.
   * @var bool
   */
  public bool $show;


  public function handshake() : array {
    try {
      return parent::handshake();
    }
    catch (Throwable $ex) {
      clientAddError($ex->makeMessage());
      return [];
    }
  }

  public function accountNameFilter(string $path_to_node = '', $limit = 10) : array {
    try {
      return parent::accountNameFilter($path_to_node, $limit);
    }
    catch (Throwable $ex) {
      clientAddError($ex->makeMessage());
    }
  }

  public function getOptions() : array {
    try {
      return parent::getOptions();
    }
    catch (Exception $ex) {
      clientAddError($ex->makeMessage());
    }
  }

  function getWorkflows(): array {
    $results = [];
    $all_workflows = parent::getWorkflows();
    // The client doesn't want them keyed by hash.
    foreach ($all_workflows as $hash => $workflow) {
      $results[$workflow->id] = $workflow;
    }
    return $results;
  }

  /**
   * @param array $params
   * @return array
   *   Transactioninterface[], transitions & links
   */
  public function filterTransactions(array $params = []): array {
    try {
      // we don't care about the pager $links (missing 3rd param)
      [$transactions, $transitions] = parent::filterTransactions($params);
    }
    catch (CCError $e) {
      clientAddError('Failed to load pending transactions: '.$e->makeMessage() .' '. http_build_query($params) );
      $transactions = [];
    }
    $filtered = [];
    foreach ($transactions as $trans) {
      foreach ($trans->entries as &$row) {
        $row->author = $this->nodeName;
      }
      $actions = (array)$transitions->{$trans->uuid};
      $filtered[] = \CCClient\Transaction::createFromJsonClass($trans, $actions);
    }
    return $filtered;
  }


  public function filterTransactionEntries(array $params = []): array {
    try {
      [$items, $links] = parent::filterTransactionEntries($params);
    }
    catch (CCError $e) {
      clientAddError('Failed to load pending transactions: '.$e->makeMessage() .' '. http_build_query($params) );
      $results = [];
    }
    $filtered = [];
    foreach ($items as $entry) {
      $filtered[] = \CreditCommons\StandaloneEntry::create($entry);
    }
    return [$filtered, $links];
  }

  /**
   * Upcast the result transactions. NB this NOT part of the API.
   * @return array
   *   The transactions and the transitions.
   */
  public function getUpcastTransaction(string $uuid) : array {
    [$transaction, $transitions] = parent::getTransaction($uuid);
    $transaction->scribe = 'trunkward node'; // This required property needs handling better.
    return [Transaction::create($transaction), $transitions];
  }

  /**
   * Upcast the result entries. NB this NOT part of the API.
   * @return array
   *   The transactions and the transitions.
   */
  public function getUpcastEntries(string $uuid) : array {
    $entries = parent::getTransactionEntries($uuid);
    foreach ($entries as $en) {
      $entries[] = StandaloneEntry::create($en);
    }
    return [$entries];
  }

  /**
   * Print the request if needed.
   */
  protected function request(int $required_code, string $endpoint = '/') :\stdClass|NULL {
    try {
      $result = parent::request($required_code, $endpoint);
    }
    catch(CCError $e) {
      echo '<font color=red>'.$e->makeMessage().'</font>';
      $result = NULL;
    }
    catch(\Throwable $e) {
      echo '<font color=red>'.$e->getMessage().'</font>';
      $result = NULL;
    }
    if ($this->show) {
      // See client.php display_errors_warnings() to see how this is handled specially.
      $url = "$this->baseUrl/$endpoint";
      if (!empty($this->options[RequestOptions::QUERY])) {
        $url .= '?'.http_build_query($this->options[RequestOptions::QUERY]);// , '', '&', PHP_QUERY_RFC3986
      }
      clientAddInfo("<strong>URL:</strong> ".strtoupper($this->method) ." ".$url);
      clientAddInfo("<strong>Headers:</strong> ".print_r($this->options[RequestOptions::HEADERS], 1));
      if (isset($this->options[RequestOptions::BODY])) {
        clientAddInfo("<strong>Body:</strong> ".$this->options[RequestOptions::BODY]);
      }
      $this->show = FALSE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse($response, int $required_code) : \stdClass|NULL {
    global $raw_result;
    $contents = $response->getBody()->getContents();// not prettified
    if ($this->show){
      $raw_result = $contents;
    }
    return json_decode($contents);
  }

}
