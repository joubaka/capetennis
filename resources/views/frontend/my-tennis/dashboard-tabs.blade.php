<ul class="nav nav-pills nav-fill" id="my-tennis-tabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" id="my-tennis-overview-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-overview" type="button" role="tab" aria-controls="my-tennis-overview" aria-selected="true">Overview</button></li>
  @if($ratingPlayer ?? null)<li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-rating-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-rating" type="button" role="tab" aria-controls="my-tennis-rating" aria-selected="false">Player rating</button></li>@endif
  @if($selectedPlayer)<li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-history-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-history" type="button" role="tab" aria-controls="my-tennis-history" aria-selected="false">Entries &amp; history</button></li>@endif
  <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-manage-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-manage" type="button" role="tab" aria-controls="my-tennis-manage" aria-selected="false">Manage players</button></li>
</ul>
