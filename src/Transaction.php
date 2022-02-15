<?php

namespace CCClient;
use CreditCommons\Leaf\Transaction as LeafTransaction;
use CreditCommons\Workflow;

/**
 * Class for the client to handle responses with Transactions.
 */
class Transaction extends LeafTransaction{


  /**
   * Get the workflow from the twig.
   *
   * @return Workflow
   *
   */
  function getWorkflow() : Workflow {
    global $node;
    $workflows = $node->requester()->getWorkflows();
    //some kind of caching is appropriate
    return $workflows[$this->type];

  }

}
