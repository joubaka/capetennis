<div class="card">
    <div class="card-header event-header">
        <h3 class="text-center">Team Event: {{$event->name}} </h3>
    </div>
    <div class="card-body">

    </div>
</div>



<div class="nav-tabs-shadow nav-align-top">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-home" aria-controls="navs-top-home" aria-selected="true">Home</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-draws" aria-controls="navs-top-draws" aria-selected="false">Draws</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-convenors" aria-controls="navs-top-convenors" aria-selected="false">Convenors</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-setup" aria-controls="navs-top-setup" aria-selected="false">Setup</button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="navs-top-home" role="tabpanel">
            @include('backend.headOffice._partials.tabs.home')
        </div>
        <div class="tab-pane fade" id="navs-top-draws" role="tabpanel">
            @include('backend.headOffice._partials.tabs.draws')

        </div>
        <div class="tab-pane fade" id="navs-top-convenors" role="tabpanel">
            @include('backend.headOffice._partials.tabs.convenors')

        </div>
        <div class="tab-pane fade" id="navs-top-setup" role="tabpanel">
            @include('backend.headOffice._partials.tabs.setup')

        </div>
    </div>
    </div>
</div>