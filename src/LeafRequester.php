<?php

namespace CCClient;

use CCClient\Transaction;
use CreditCommons\EntryDisplay;
use CreditCommons\Exceptions\CCError;
use CreditCommons\NewTransaction;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client;

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

  /**
   * {@inheritDoc}
   */
  public function handshake() : array {
    try {
      parent::handshake();
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function about(string $node_path) : \stdClass {
    try {
      return $this->request("about?node_path=$node_path")->data;
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
    }

    return new \stdClass();
  }


  /**
   * {@inheritDoc}
   */
  public function accountNameFilter(string $path_to_node = '', $limit = 10) : array {
    try {
      $names = parent::accountNameFilter($path_to_node, $limit);
    }
    catch (\Throwable $e) {
      clientAddError($ex->getMessage());
      clientAddError($e);
      $names = [];
    }
    return $names;
  }

  /**
   * {@inheritDoc}
   */
  public function getOptions() : array {
    try {
      return parent::getOptions();
    }
    catch (\Throwable $e) {print_r($e);exit;
      clientAddError($e->getMessage());
      clientAddError($e);
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  function getWorkflows(): array {
    try {
      $all_workflows = parent::getWorkflows();
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
    }
    $results = [];
    // The client doesn't want them keyed by hash.
    foreach ($all_workflows as $hash => $workflow) {
      $results[$workflow->id] = $workflow;
    }
    return $results;
  }

  /**
   * {@inheritDoc}
   */
  public function getAccountSummary(string $acc_path = '') : array {
    try {
      $results = parent::getAccountSummary($acc_path);
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
      $results = [];
    }
    return $results;
  }

  /**
   * {@inheritDoc}
   */
  public function submitNewTransaction(NewTransaction $new_transaction) : array {
    try {
      [$transaction, $transitions] =  parent::submitNewTransaction($new_transaction);
    }
    catch (CCError $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
      return[NULL, NULL];
    }
    catch (\Exception $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
      return[NULL, NULL];
    }
    return [$transaction, $transitions];
  }

  /**
   * {@inheritDoc}
   */
  function getAccountLimits(string $acc_path = '') : array {
    try {
      parent::getAccountLimits($acc_path);
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getAccountHistory(string $acc_path, int $samples = 0) : array {
    try {
      parent::getAccountHistory($acc_path, $samples);
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function filterTransactions(array $params = []): array {
    try {
      // we don't care about the pager $links (missing 3rd param)
      [$transactions, $transitions] = parent::filterTransactions($params);
    }
    catch (CCError $e) {
      clientAddError('Failed to load pending transactions: '.$e->getMessage() .' '. http_build_query($params) );
      clientAddError($e);
      $transactions = [];
    }
    $filtered = [];
    foreach ($transactions as $trans) {
      $actions = (array)$transitions->{$trans->uuid};
      $filtered[] = Transaction::createFromJsonClass($trans, $actions);
    }
    return $filtered;
  }

  /**
   * {@inheritDoc}
   */
  public function filterTransactionEntries(array $params = []): array {
    try {
      [$items, $links] = parent::filterTransactionEntries($params);
    }
    catch (CCError $e) {
      clientAddError('Failed to load pending transactions: '.$e->getMessage() .' '. http_build_query($params) );
      clientAddError($e);
      return [[], []];
    }
    $filtered = [];
    foreach ($items as $entry) {
      $filtered[] = \CreditCommons\EntryDisplay::create($entry);
    }
    return [$filtered, $links];
  }

  /**
   * Print the request if needed.
   */
  protected function request(string $endpoint = '/') :\stdClass|NULL {
    $parts = parse_url($this->baseUrl);
    if (isset($parts['path'])) {
      $endpoint = $parts['path'] .'/'.$endpoint;
      $this->baseUrl = $parts['scheme'].'://'.$parts['host'];
      if (isset($parts['port'])){
        $this->baseUrl .= ':'.$parts['port'];
      }
    }
    try{
      $client = new Client(['base_uri' => $this->baseUrl, 'timeout' => 1]);
      $response = $client->{$this->method}($endpoint, $this->options);
    }
    catch (\Throwable $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
      return NULL;
    }
    $result_contents = $response->getBody()->getContents();

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
      $this->processResponse($response);
      $this->show = FALSE;
    }
    if ($result_contents) return json_decode($result_contents);
    return new \stdClass;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse($response) : \stdClass|NULL {
    global $raw_result;
    $body = $response->getBody();
    $body->rewind();
    $result_contents = strval($body->getContents());
    if ($this->show){
      $raw_result = $result_contents;
    }
    return json_decode($result_contents);
  }

  /**
   * Upcast the result transactions. NB this NOT part of the API.
   * @return array
   *   The transactions and the transitions.
   */
  public function getUpcastTransaction(string $uuid) : array {
    try {
      [$transaction, $transitions] = parent::getTransaction($uuid);
    }
    catch (\Exception $e) {
      clientAddError($e->getMessage());
      return [NULL, []];
    }
    $transaction->scribe = 'trunkward node'; // This required property needs handling better.
    return [Transaction::create($transaction), $transitions];
  }

  /**
   * Upcast the result entries. NB this NOT part of the API.
   * @return array
   *   The transactions and the transitions.
   */
  public function getUpcastEntries(string $uuid) : array {
    try {
      $results = parent::getTransactionEntries($uuid);
    }
    catch (\Exception $e) {
      clientAddError($e->getMessage());
      clientAddError($e);
      return [];
    }
    foreach ($results as $en) {
      $entries[] = EntryDisplay::create($en);
    }
    return $entries;
  }

}
