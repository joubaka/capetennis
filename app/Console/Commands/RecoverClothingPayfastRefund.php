<?php

namespace App\Console\Commands;

use App\Models\ClothingRefund;
use App\Models\User;
use App\Services\Clothing\ClothingPayfastRefundRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RecoverClothingPayfastRefund extends Command
{
    protected $signature = 'clothing:recover-payfast-refund {order} {refund} {--actor= : Super-user recording the reconciliation} {--apply : Mark the existing attempt ready for retry; never pays} {--confirmation= : Token from a fresh preview}';

    protected $description = 'Preview or reconcile an uncertain clothing refund using fresh signed PayFast history; sends no money or messages';

    public function handle(ClothingPayfastRefundRecoveryService $recovery): int
    {
        $refund = ClothingRefund::whereKey($this->argument('refund'))->where('clothing_order_id', $this->argument('order'))->firstOrFail();
        $actor = User::findOrFail($this->option('actor'));
        try {
            $result = $this->option('apply')
                ? $recovery->apply($refund, $actor, (string) $this->option('confirmation'))
                : $recovery->preview($refund, $actor);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info($this->option('apply') ? 'Reconciliation finished. No refund or wallet credit was sent.' : 'Preview only. No records changed. Confirmation expires in 10 minutes.');
        return self::SUCCESS;
    }
}
