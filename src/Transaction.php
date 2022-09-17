<?php

namespace CCClient;
use CreditCommons\Leaf\Transaction as LeafTransaction;
use CreditCommons\Workflow;

/**
 * Class for the client to handle responses with Transactions.
 */
class Transaction extends LeafTransaction {

  /**
   * {@inheritDoc}
   */
  function getWorkflow() : Workflow {
    global $node;
    $workflows = $node->requester()->getWorkflows();
    //some kind of caching is appropriate
    return $workflows[$this->type];
  }

  /**
   * {@inheritDoc}
   */
  function upcastEntries(array $rows, bool $additional = FALSE): void {
    foreach ($rows as $row) {
      $this->entries[] = $row;//Entry::create($row, $this);
    }
  }

}

