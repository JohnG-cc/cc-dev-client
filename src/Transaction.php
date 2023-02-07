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
    // The workflow doesn't exist on the leaf so must be retreived from the node.
    $workflows = $node->requester()->getWorkflows();
    //some kind of caching may be appropriate
    return $workflows[$this->type];
  }

}
