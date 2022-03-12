<?php

namespace CCClient;
use GuzzleHttp\RequestOptions;
use CreditCommons\StandaloneEntry;
use CreditCommons\TransactionInterface;
use \CCClient\Transaction;

/**
 * Class for a non-ledger client to call to a credit commons accounting node.
 * This wraps the RestAPI in order to handle the authentication and errors
 * appropriate for the client.
 * Clients should instantiate this
 */
class API extends \CreditCommons\Leaf\API {

  /**
   * TRUE if this is the main query to be printed in the CCClient.
   * @var bool
   */
  public bool $show;

  function getWorkflows(): array {
    $results = [];
    $all_workflows = parent::getWorkflows();
    // The client doesn't want them keyed by hash.
    foreach ($all_workflows as $node => $wfs) {
      foreach ($wfs as $hash => $workflow) {
        $results[$workflow->id] = $workflow;
      }
    }
    return $results;
  }


  /**
   * @param array $fields
   * @param bool $full
   * @return Transactioninterface[]
   */
  public function filterTransactions(array $fields = [], bool $full = TRUE) : array {
    try {
      $results = parent::filterTransactions($fields, $full);
    }
    catch (CCError $e) {
      clientAddError('Failed to load pending transactions: '.$e->makeMessage() .' '. http_build_query($fields) );
      $results = [];
    }
    $filtered = [];
    $upcast_class = $full ? '\CCClient\Transaction::createFromJsonClass' : '\CreditCommons\StandaloneEntry::create';
    foreach ($results as $result) {
      $filtered[] = $upcast_class($result);
    }
    return $filtered;
  }

  /**
   * Upcast the results
   * @return
   *   Entry[] | TransactionInterface
   */
  public function getUpcastTransaction(string $uuid, bool $full = TRUE) : array|TransactionInterface {
    $result = parent::getTransaction($uuid, $full);
    if ($full) {
      return Transaction::create($result);
    }
    foreach ($result as $e) {
      $entries[] = StandaloneEntry::create($e);
    }
    return $entries;
  }

  /**
   * Print the request if needed.
   */
  protected function request(int $required_code, string $endpoint = '') {
    try {
      $result = parent::request($required_code, $endpoint);
    }
    catch(\Exception $e) {
      echo $e->getMessage();
      $result = [];
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
  protected function processResponse($response, int $required_code) {
    global $raw_result;
    $contents = $response->getBody()->getContents();// not prettified
    if ($this->show){
      $raw_result = $contents;
    }
    return json_decode($contents);
  }

}
