<h1>Cape Tennis website error</h1>
<p>An unexpected server error was reported. The exception log below includes the message, complete stack trace and underlying exceptions, with sensitive details redacted.</p>
<dl>
@foreach (['reference', 'occurred_at', 'environment', 'exception', 'file', 'line', 'method', 'route', 'user_id', 'fingerprint'] as $field)
    <dt>{{ ucfirst(str_replace('_', ' ', $field)) }}</dt>
    <dd>{{ $diagnostic[$field] ?? 'Guest / unavailable' }}</dd>
@endforeach
</dl>
<h2>Source trace</h2>
<pre>@foreach ($diagnostic['trace'] as $frame){{ $frame['file'] }}:{{ $frame['line'] }}
@endforeach</pre>
<h2>Exception log</h2>
<pre style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $diagnostic['exception_log'] ?? 'Unavailable for an alert queued before this update.' }}</pre>
<p>SQL details, request values, credentials, URLs and trace arguments are redacted or omitted. This log covers the reported exception, not unrelated application log entries. Repeated errors from the same source are grouped for five minutes; alerts are limited to ten distinct errors per minute.</p>
