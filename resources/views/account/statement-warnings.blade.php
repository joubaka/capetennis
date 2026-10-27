@if(!empty($warnings))
<div class="statement-notice" role="status">
    <strong>History notice</strong>
    @foreach($warnings as $warning)
        <p class="mb-1">{{ $warning }}</p>
    @endforeach
</div>
@endif
