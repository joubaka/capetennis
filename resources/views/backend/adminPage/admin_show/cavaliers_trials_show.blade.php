<div>
    @if (auth()->user()?->hasRole('super-user') || (auth()->user()?->hasRole('admin') && auth()->user()->is_event_admin($event->id)))
        @include('backend.nominations.cavaliers-roster')
    @endif

    <details class="mb-3">
        <summary class="fw-semibold">Existing event nominations</summary>
        <p class="text-muted mt-2">This is the separate nomination list used by the event page.</p>
        @include('backend.nominations.view')
    </details>
</div>

@include('backend.adminPage.admin_show.tabs.modals.nominationModal')
