<?php

namespace CCClient;
use GuzzleHttp\RequestOptions;

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
   * Print the request if needed.
   */
  protected function request(int $required_code, string $endpoint = '') {
    $result = parent::request($required_code, $endpoint);
    if ($this->show) {
      // See client.php display_errors_warnings() to see how this is handled specially.
      $url = "$this->baseUrl/$endpoint";
      if (!empty($this->options[RequestOptions::QUERY])) {
        $url .= '?'.http_build_query($this->options[RequestOptions::QUERY], NULL, '&', PHP_QUERY_RFC3986);
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
  protected function processResponse($response) {
    global $raw_result;
    $contents = $response->getBody()->getContents();// not prettified
    if ($this->show){
      $raw_result = $contents;
    }
    return json_decode($contents);
  }

}
