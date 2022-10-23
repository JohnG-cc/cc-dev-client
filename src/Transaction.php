<?php

namespace CCClient;
use CreditCommons\Workflow;

/**
 * Class for the client to handle responses with Transactions.
 * @deprecated
 */
class Transaction extends \CreditCommons\Leaf\Transaction {

  /**
   * {@inheritDoc}
   */
  function getWorkflow() : Workflow {
    global $node;
    $workflows = $node->requester()->getWorkflows();
    //some kind of caching may be appropriate
    return $workflows[$this->type];
  }

  /**
   * {@inheritDoc}
   */
  function upcastEntries(array $rows, bool $additional = FALSE): void {
    // No need for upcasting on the client site.
    foreach ($rows as $row) {
      $this->entries[] = $row;
    }
  }

}

