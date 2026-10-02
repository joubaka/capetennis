<span>{{ $log->delivery_status_label }}</span>
@if($log->accepted_at)
    <small class="d-block text-muted">{{ $log->accepted_at->format('d M Y H:i:s') }}</small>
@endif
@if($log->transport_message_id)
    <small class="d-block text-muted text-break">Message ID: {{ $log->transport_message_id }}</small>
@endif
@if(in_array($log->status, ['failed', 'skipped'], true) && $log->error_message)
    <small class="d-block text-danger">{{ $log->error_message }}</small>
@endif
