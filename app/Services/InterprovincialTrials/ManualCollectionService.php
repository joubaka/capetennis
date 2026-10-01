<?php
namespace App\Services\InterprovincialTrials;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{RegistrationOrder, RegistrationManualReceipt, TrialPaymentProof, User, InterprovincialTrialInvitation};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ManualCollectionService {
    public function __construct(private RegistrationPaymentService $payments) {}
    public function uploadProof(RegistrationOrder $order, User $payer, UploadedFile $file): TrialPaymentProof {
        abort_unless((int) $order->user_id === (int) $payer->id, 403);
        if (! $file->isValid() || ! in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true) || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['proof' => 'Upload a PDF, JPEG or PNG no larger than 5 MB.']);
        }
        $path = $file->store('trial-payment-proofs', 'local');
        try {
            return DB::transaction(function () use ($order, $payer, $file, $path) {
                $locked = RegistrationOrder::lockForUpdate()->findOrFail($order->id);
                $invitation = InterprovincialTrialInvitation::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->user_id === (int) $payer->id, 403);
                abort_unless($invitation->event->isInterprovincialTrials() && $invitation->status === InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT && ! $locked->pay_status && ! $locked->payfast_handed_off_at && (float) $locked->wallet_reserved === 0.0, 422);
                abort_unless(filled(\App\Models\TrialProgramme::where('event_id', $invitation->event_id)->first()?->bank_details), 422, 'The regional EFT account must be configured first.');
                return TrialPaymentProof::create(['order_id' => $locked->id, 'event_id' => $invitation->event_id, 'payer_id' => $payer->id, 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            });
        } catch (\Throwable $exception) { Storage::disk('local')->delete($path); throw $exception; }
    }
    public function verifyProof(TrialPaymentProof $proof, User $operator, string $reference): RegistrationManualReceipt {
        return $this->payments->finalizeManualPayment($proof->order, $operator, 'eft', $reference, $proof->id);
    }
    public function markPaid(RegistrationOrder $order, User $operator, string $reference): RegistrationManualReceipt {
        return $this->payments->finalizeManualPayment($order, $operator, 'manual', $reference);
    }
    public function rejectProof(TrialPaymentProof $proof, User $operator, string $reason): void {
        app(TrialProgrammeService::class)->authorize(\App\Models\Event::findOrFail($proof->event_id), $operator);
        abort_unless(trim($reason) !== '', 422);
        DB::transaction(function () use ($proof, $operator, $reason) {
            RegistrationOrder::whereKey($proof->order_id)->lockForUpdate()->firstOrFail();
            $proof = TrialPaymentProof::lockForUpdate()->findOrFail($proof->id);
            abort_unless($proof->status === 'pending', 422);
            $proof->update(['status' => 'rejected', 'reviewed_by_user_id' => $operator->id, 'reviewed_at' => now()]);
            activity('registration-payment')->performedOn($proof)->causedBy($operator)->withProperties(['reason' => $reason])->log('Trials payment proof rejected; checkout remains unpaid');
        });
    }
    public function authorizeProof(TrialPaymentProof $proof, User $actor): void {
        if ((int) $proof->payer_id !== (int) $actor->id) {
            app(TrialProgrammeService::class)->authorize(\App\Models\Event::findOrFail($proof->event_id), $actor);
        }
    }
}
