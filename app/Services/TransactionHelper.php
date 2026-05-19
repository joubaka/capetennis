<?php



namespace App\Services;

use App\Domain\Payments\Services\PaymentTransactionService;

class TransactionHelper
{
    public $capetennisFee,
        $payfast_id,
        $payfast_status_id,
        $pf_transaction_id,
        $registration_id,
        $amount_net,
        $amount_gross,
        $amount_fee,
        $user_id,
        $player_id;


    public function __construct()
    {

        $this->capetennisFee = 15;
    }

    public function withdrawal($categoryEventRegistration)
    {


        $this->getTransactionDetails($categoryEventRegistration);


        $transaction = $this->createTransaction($categoryEventRegistration);

        $response['message'] = 'Transaction created succesfully';
        $response['transaction'] = $transaction;
        return $response;
    }

  public function getTransactionDetails($categoryEventRegistration)
  {
    $this->payfast_id = $categoryEventRegistration->payfast_id;
    $this->payfast_status_id = $categoryEventRegistration->payfast_status_id;
    $this->pf_transaction_id = $categoryEventRegistration->pf_transaction_id;
    $this->registration_id = $categoryEventRegistration->user_id;
    $this->user_id = $categoryEventRegistration->user_id;

    $pfTransaction = $categoryEventRegistration->pf_transaction;

    $numberEntries = 1; // default fallback
    if ($pfTransaction && $pfTransaction->order) {
      $items = $pfTransaction->order->items;
      $numberEntries = max(1, $items->count());
    }

    $this->player_id = optional($categoryEventRegistration->registration->players->first())->id;

    // Handle Admin-created or missing pfTransaction gracefully
    if ($categoryEventRegistration->payfast_id === 'Admin' || !$pfTransaction) {
      $this->amount_fee = 0;
      $this->amount_gross = 0;
      $this->amount_net = 0;
    } else {
      $this->amount_fee = $pfTransaction->amount_fee / $numberEntries;
      $this->amount_gross = ($pfTransaction->amount_gross / $numberEntries) * -1;
      $this->amount_net = $this->amount_gross + ($this->amount_fee * -1);
    }
  }

  public function createTransaction($categoryEventRegistration)
  {
        $user = Auth()->user();
        if (!$user) {
            throw new \RuntimeException('Authenticated user is required to create a withdrawal transaction.');
        }

        return app(PaymentTransactionService::class)->record([
            'categoryEventRegistration' => $categoryEventRegistration->id,
            'custom_int4' => $user->id,
            'custom_str4' => $user->username,
        ], PaymentTransactionService::WITHDRAWAL_BEFORE_DEADLINE_CONTEXT);
    }
}
