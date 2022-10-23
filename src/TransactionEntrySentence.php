<?php

namespace CCClient;

use CreditCommons\StandaloneEntry;

/**
 * Class for the client to handle responses with Transactions.
 */
class TransactionEntrySentence {

  const TEMPLATE = '<div class = "@state">@payer will pay @payee @quant for \'@description\'</div>';
  const TOKENS = ['@state', '@payee', '@payer', '@quant', '@description'];

  function __construct(
    private StandaloneEntry $entry
  ) {
  }

  function __toString() {
    return str_replace(
      SELF::TOKENS,
      [
        $this->entry->state,
        $entry->entry->payee,
        $entry->entry->payer,
        $entry->entry->quant,
        $entry->description
      ],
      SELF::TEMPLATE
    );
  }

}
