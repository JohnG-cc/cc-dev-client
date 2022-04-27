<?php

namespace CCClient;
use \CreditCommons\TransactionInterface;

/**
 * Class for the client to handle responses with Transactions.
 */
class TransactionSentence {

  const TEMPLATE = '<div class = "@class @state">@payer will pay @payee @quant for \'@description\' @links</div>';
  const TOKENS = ['@class', '@state', '@payee', '@payer', '@quant', '@description', '@links'];
  private array $workflows;

  function __construct(
    private TransactionInterface $transaction
  ) {
    $this->workflows = get_all_workflows();
  }

  function __toString() {
    // put the links next to the first entry.
    $first = TRUE;
    foreach ($this->transaction->entries as $entry) {
      $replace = [
        $first ? "primary" : "dependent",
        $this->transaction->state,
        $entry->payee, // NB these are spoofed account objects, see CreditCommons\Entry::Create
        $entry->payer,
        $entry->quant,
        $entry->description,
        $first ? $this->actionLinks() : ''
      ];
      $first = FALSE;
      $output[] = str_replace(SELF::TOKENS, $replace, SELF::TEMPLATE);
    }
    return implode($output);
  }


  /**
   * Render the transaction action links as forms which can post
   * @param string $uuid
   * @param array $labels
   *   action labels, keyed by target state.
   * @return string
   */
  function actionLinks() : string {
    global $node, $user;
    if ($actions = $this->transaction->transitions) {
      $output[] = '<form method="post" class="inline" action="">';
      $output[] = '<input type="hidden" name="uuid" value="'.$this->transaction->uuid.'">';

      if (isset($this->workflows[$this->transaction->type])) {
        foreach ($this->workflows[$this->transaction->type]->actionLabels($this->transaction->state, $actions) as $target_state => $label) {
          $output[] = '<button type="submit" name="stateChange" value="'.$target_state.'" class="link-button">'.$label.'</button>';
        }
        $output[] = '</form>';
      }
      else {
        clientAddError("Missing workflow: $this->transaction->type:");
      }
    }
    else {
      $type = $this->transaction->type;
      $output[] = "<span title = \"".$_GET['acc']." is not permittions to do anything to this '$type' transaction\">(No transitions)</span>";
    }
    return implode($output);
  }

}
