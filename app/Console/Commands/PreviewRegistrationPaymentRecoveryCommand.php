<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Models\RegistrationOrder;
use Illuminate\Console\Command;

class PreviewRegistrationPaymentRecoveryCommand extends Command
{
    protected $signature = 'registrations:recovery-preview {orders* : Exact registration order IDs}';
    protected $description = 'Preview erroneous free registration orders without changing data';

    public function handle(RegistrationPaymentRecoveryService $service): int
    {
        $ids = collect($this->argument('orders'))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $rows = [];
        foreach ($ids as $id) {
            $order = RegistrationOrder::query()->find($id);
            if (! $order) { $rows[] = [$id, 'excluded', 'not found', '', '', '', '', '', '']; continue; }
            $result = $service->inspect($order);
            $first = $result['order']->items->first();
            $email = (string) $result['order']->user?->email;
            $masked = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email) ?: '(missing)';
            $players = $result['order']->items->map(fn ($item) => trim(($item->player?->name ?? '').' '.($item->player?->surname ?? '')))->filter()->join(' / ');
            $event = $first?->category_event?->event;
            $rows[] = [$id, $result['eligible'] ? 'eligible' : 'excluded', implode(', ', $result['reasons']), $masked, $event?->name, $players, $first?->category_event?->category?->name, 'R'.number_format($result['amount'], 2), $event?->email ?: '(not set)'];
        }
        $this->table(['Order', 'Result', 'Reason', 'Recipient', 'Event', 'Player', 'Category', 'Amount', 'Convener contact'], $rows);
        if (collect($rows)->every(fn ($row) => $row[1] === 'eligible')) {
            $this->line('Confirmation hash: '.$service->previewBatchHash($ids));
        } else {
            $this->warn('No confirmation hash issued because one or more orders were excluded.');
        }
        return self::SUCCESS;
    }
}
