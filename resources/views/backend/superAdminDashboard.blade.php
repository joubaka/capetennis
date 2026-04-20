@php
  // This view is no longer used. Redirect to the canonical Super Admin dashboard.
  abort(redirect()->route('backend.superadmin.index'));
@endphp
