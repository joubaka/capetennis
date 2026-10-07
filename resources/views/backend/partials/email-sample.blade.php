<p><strong>{{ count($mailRecipients) }} email{{ count($mailRecipients) === 1 ? '' : 's' }}</strong> ready to queue.</p>
<p class="text-break"><strong>From:</strong> {{ $mailSender['from_name'] ?? auth()->user()->name }}<br><strong>Reply-to:</strong> {{ $mailSender['reply_to'] ?? auth()->user()->email }}</p>
<details class="border rounded p-3 mb-3"><summary>Review all {{ count($mailRecipients) }} recipients</summary><div class="mt-2" style="max-height:240px;overflow:auto">
@foreach($mailRecipients as $recipient)<div class="text-break">{{ $recipient['name'] ?? '' }} · {{ $recipient['email'] }}</div>@endforeach
</div></details>
@if($sample = collect($mailRecipients)->first())
<div class="card card-body mb-3"><h5>Example email</h5><p class="text-break"><strong>To:</strong> {{ $sample['email'] }}</p><h6>{{ $sample['subject'] }}</h6>
<iframe title="Example email to {{ $sample['email'] }}" sandbox="" srcdoc="{{ $sample['html'] ?? nl2br(e($sample['body'] ?? '')) }}" style="width:100%;height:360px;border:1px solid #ddd"></iframe></div>
@endif
