<h1>Cape Tennis website error</h1>
<p>An unexpected server error was reported. Use the time and source location below to find the full error in the restricted application logs.</p>
<dl>
@foreach (['reference', 'occurred_at', 'environment', 'exception', 'file', 'line', 'method', 'route', 'user_id', 'fingerprint'] as $field)
    <dt>{{ ucfirst(str_replace('_', ' ', $field)) }}</dt>
    <dd>{{ $diagnostic[$field] ?? 'Guest / unavailable' }}</dd>
@endforeach
</dl>
<h2>Source trace</h2>
<pre>@foreach ($diagnostic['trace'] as $frame){{ $frame['file'] }}:{{ $frame['line'] }}
@endforeach</pre>
<p>For privacy, exception messages, SQL, request values, URLs, headers, cookies and trace arguments are omitted. Repeated errors from the same source are grouped for five minutes; alerts are limited to ten distinct errors per minute.</p>
