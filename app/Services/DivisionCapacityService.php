<?php

namespace App\Services;

use App\Models\CategoryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entry places include unresolved checkouts until their canonical cancellation. */
class DivisionCapacityService
{
    public function occupied(CategoryEvent $category, bool $currentRead = false): int
    {
        $entries = DB::table('category_event_registrations')
            ->where('category_event_id', $category->id)->whereNull('deleted_at')->whereNull('withdrawn_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled']))
            ->where('payment_status_id', 1)->select('registration_id');
        $holds = DB::table('registration_order_items as item')
            ->join('registration_orders as checkout', 'checkout.id', '=', 'item.order_id')
            ->where('item.category_event_id', $category->id)
            ->where(fn ($q) => $q->whereNull('checkout.status')->orWhereNotIn('checkout.status', ['cancelled', 'expired']))
            ->where(fn ($q) => $q->where(fn ($unpaid) => $unpaid->where('checkout.pay_status', 0)->where('checkout.payfast_paid', false))
                ->orWhereNotExists(fn ($existing) => $existing->selectRaw('1')->from('category_event_registrations as existing')
                    ->whereColumn('existing.registration_id', 'item.registration_id')))
            ->whereNotExists(function ($q) use ($category) {
                $q->selectRaw('1')->from('category_event_registrations as withdrawn')
                    ->whereColumn('withdrawn.registration_id', 'item.registration_id')
                    ->where('withdrawn.category_event_id', $category->id)
                    ->where(fn ($q) => $q->whereNotNull('withdrawn.withdrawn_at')->orWhereNotNull('withdrawn.deleted_at')
                        ->orWhereIn('withdrawn.status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled']));
            })->select('item.registration_id');
        $moves = DB::table('category_adjustments')->where('target_category_id', $category->id)
            ->where('status', 'pending');

        if ($currentRead) {
            // Locking reads see the latest committed admission after a category-lock wait,
            // even if this transaction already established a REPEATABLE READ snapshot.
            return $entries->lockForUpdate()->pluck('registration_id')
                ->merge($holds->lockForUpdate()->pluck('item.registration_id'))->unique()->count()
                + $moves->lockForUpdate()->pluck('id')->count();
        }

        return DB::query()->fromSub($entries->union($holds), 'occupied_places')->count() + $moves->count();
    }

    public function remaining(CategoryEvent $category): ?int
    {
        return $category->entry_limit === null ? null : max(0, (int) $category->entry_limit - $this->occupied($category));
    }

    /** Caller must hold the category lock through creation of its entry/order item. */
    public function assertAvailable(CategoryEvent $category): void
    {
        if ($category->entry_limit !== null && $this->occupied($category, true) >= (int) $category->entry_limit) {
            throw ValidationException::withMessages(['division' => ($category->category?->name ?? 'This division').' is full.']);
        }
    }
}
