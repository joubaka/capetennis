<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div><h1 class="h4 mb-1">CTR marketing portal</h1><p class="text-muted mb-0">Plan academy outreach, maintain contacts and prepare campaign drafts.</p></div>
  <span class="badge bg-label-warning">Draft workspace · sending unavailable</span>
</div>
<nav class="nav nav-pills flex-wrap gap-2 mb-4" aria-label="Marketing portal">
  @foreach(['contacts' => 'Coach contacts', 'campaigns' => 'Campaign drafts', 'calculator' => 'Stage One calculator', 'applications' => 'Host proposals'] as $key => $label)
    @php($routeName = 'superadmin.marketing.'.$key.($key === 'calculator' ? '' : '.index'))
    <a class="nav-link {{ request()->routeIs('superadmin.marketing.'.$key.'*') || ($key === 'calculator' && request()->routeIs('superadmin.marketing.calculate')) ? 'active' : '' }}" href="{{ route($routeName) }}">{{ $label }}</a>
  @endforeach
</nav>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())
  <div class="alert alert-danger" role="alert"><strong>Please check the details.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
