@php
    use App\Http\Controllers\Backend\CavaliersTrialRosterController;
    use App\Services\CavaliersTrialCandidates;
@endphp
<section class="card mb-4" data-cavaliers-roster
    data-selected-limit="{{ CavaliersTrialRosterController::SELECTED_LIMIT }}"
    data-reserve-limit="{{ CavaliersTrialRosterController::RESERVE_LIMIT }}">
    <div class="card-body">
        <h4 class="card-title mb-1">Trials roster draft</h4>
        <p class="text-muted">Private working list. Review 32 selected players and five ordered reserves in each division.</p>
        <label class="form-label" for="cavaliers-roster-division">Division</label>
        <select class="form-select mb-3" id="cavaliers-roster-division" data-division>
            <option value="">Choose an age group and gender</option>
            @foreach ($event->eventCategories as $division)
                <option value="{{ $division->id }}"
                    data-draft-url="{{ route('backend.cavaliers-roster.show', [$event, $division]) }}"
                    data-search-url="{{ route('backend.cavaliers-roster.players', [$event, $division]) }}"
                    @if ((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT)
                        data-candidate-url="{{ route('backend.cavaliers-roster.candidates', [$event, $division]) }}"
                        data-setup-url="{{ route('backend.cavaliers-roster.setup', [$event, $division]) }}"
                    @endif>
                    {{ $division->category->name }}
                </option>
            @endforeach
        </select>
        <p class="small mb-2" data-status role="status" aria-live="polite"></p>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3 d-none" data-reload>Reload saved draft</button>
        <div data-editor hidden>
            @if ((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT)
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="fw-semibold">Step 1 · Inter-Districts</span>
                    <button type="button" class="btn btn-outline-secondary" data-open-setup aria-expanded="false" aria-controls="cavaliers-roster-setup">Setup</button>
                    <button type="button" class="btn btn-outline-primary" data-review-candidates>Review candidates</button>
                </div>
                <form class="border rounded p-3 mb-3" id="cavaliers-roster-setup" data-candidate-setup hidden>
                    <div class="row g-3 mb-3">
                        <fieldset class="col-12 col-md-6" data-setup-fieldset>
                            <legend class="h6">Include players from</legend>
                            <p class="small text-muted">Only published rosters for this division are used.</p>
                            <div data-candidate-regions></div>
                        </fieldset>
                        <fieldset class="col-12 col-md-6" data-setup-fieldset>
                            <legend class="h6">Exclude results against</legend>
                            <p class="small text-muted">Matches involving these regions are left out of the band calculation. A region can still supply candidates.</p>
                            <div data-excluded-regions></div>
                        </fieldset>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Save setup</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-close-setup>Cancel</button>
                </form>
                <section class="border rounded p-3 mb-3" data-candidate-review aria-labelledby="cavaliers-candidate-heading" hidden>
                    <h5 id="cavaliers-candidate-heading" class="h6">Review Inter-Districts and Platteland candidates</h5>
                    <p class="small text-muted">Step 1 uses Inter-Districts results with the Platteland band rules. Step 2 adds locked Platteland reserves, then additional candidates in the saved order. Open selected places are checked by default. Check more players to add reserves.</p>
                    <p class="small text-muted" data-candidate-region-summary></p>
                    <p class="small text-warning" data-candidate-notice hidden></p>
                    <p class="small text-warning" data-platteland-notice hidden></p>
                    <ol class="list-group list-group-numbered mb-3" data-candidate-list></ol>
                    <p class="small mb-2" data-candidate-count aria-live="polite"></p>
                    <button type="button" class="btn btn-sm btn-primary" data-add-candidates>Add to draft</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-close-candidates>Close review</button>
                </section>
            @endif
            <form data-search class="row g-2 align-items-end mb-2">
                <div class="col-12 col-sm">
                    <label class="form-label" for="cavaliers-roster-search">Find a player</label>
                    <input class="form-control" id="cavaliers-roster-search" data-search-input type="search"
                        placeholder="Name or surname" minlength="2" maxlength="80" required autocomplete="off">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </div>
            </form>
            <ul class="list-group mb-3 d-none" data-search-results aria-label="Player search results"></ul>
            <div class="row g-3 mb-3">
                @foreach (['selected' => 'Selected', 'reserves' => 'Reserves'] as $key => $label)
                    <section class="col-12 col-lg-6" aria-label="{{ $label }}">
                        <h5 class="h6 d-flex flex-wrap gap-2 align-items-center">
                            {{ $label }} <span class="badge bg-label-primary" data-count="{{ $key }}"></span>
                        </h5>
                        <ol class="list-group list-group-numbered" data-list="{{ $key }}"></ol>
                        <p class="text-muted small mb-0" data-empty="{{ $key }}" hidden>No players added.</p>
                    </section>
                @endforeach
            </div>
            <form class="border rounded p-3 mb-3" data-swap hidden>
                <label class="form-label" for="cavaliers-roster-swap" data-swap-label>Swap with</label>
                <select class="form-select mb-2" id="cavaliers-roster-swap" data-swap-target required></select>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Swap players</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cancel-swap>Cancel</button>
                </div>
            </form>
            <button type="button" class="btn btn-primary" data-save disabled>Save draft</button>
        </div>
    </div>
</section>
<script defer src="{{ asset('assets/js/cavaliers-trial-roster.js') }}?v={{ filemtime(public_path('assets/js/cavaliers-trial-roster.js')) }}"></script>
