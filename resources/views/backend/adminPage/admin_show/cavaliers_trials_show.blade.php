<div>
    @if (auth()->user()?->hasRole('super-user') || (auth()->user()?->hasRole('admin') && auth()->user()->is_event_admin($event->id)))
        @include('backend.nominations.cavaliers-roster')
    @endif
</div>
