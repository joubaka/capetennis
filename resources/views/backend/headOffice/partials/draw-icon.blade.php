<svg class="draws-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
  @switch($icon)
    @case('bracket')<path d="M4 4h5v6H4M4 14h5v6H4M9 7h6v10H9M15 12h5"/>@break
    @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
    @case('print')<path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7zM17 11h.01"/>@break
    @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>@break
    @case('calendar')<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4m8-4v4M4 11h16M8 15h2m4 0h2m-8 3h2"/>@break
    @case('dots')<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>@break
    @case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
    @case('lock')<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>@break
    @case('overview')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
    @case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/>@break
  @endswitch
</svg>
