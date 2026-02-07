@if (!empty($bracketHtml))
    <div class="draw-bracket-preview mt-4">
        {!! $bracketHtml !!}
    </div>
@endif
@if ($draw->drawFixtures->isNotEmpty())
    <div class="fixtures-preview-area">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Id</th>
                        <th>Match #</th>
                        <th>Round</th>
                        <th>Group</th>
                        <th>Player 1</th>
                        <th>Player 2</th>
                        <th>Status</th>
                        <th>Result</th>

                    </tr>
                </thead>

                <tbody>
                    @foreach ($draw->drawFixtures->where('stage', '!=', 'RR')->filter(fn($f) => $f->registration1_id !== 0 && $f->registration2_id !== 0)->sortBy(['round']) as $match)
                        @php
                            $player1 =
                                optional(optional($match->registration1)->players)->first()?->full_name ??
                                ($match->hint_registration1_id ?? 'TBD');
                            $player2 =
                                optional(optional($match->registration2)->players)->first()?->full_name ??
                                ($match->hint_registration2_id ?? 'TBD');
                            $bracket = $match->bracket?->name ?? ($match->bracket_id ?? '-');

                            // Get CSS class for bracket ID
                            $rowClass = $bracketColors[$match->draw_group_id] ?? '';
                        @endphp

                        <tr class="{{ $rowClass }}">
                            <td>{{ $match->id }}</td>
                            <td>{{ $match->match_nr ?? '-' }}</td>
                            <td>{{ $match->stage ?? '-' }}</td>
                            <td>{{ $match->draw_group_id }}</td>

                            <td>{{ $player1 }}</td>
                            <td>{{ $player2 }}</td>


                            <td>{{ $match->match_status == 2 ? 'finished' : 'pending' }}</td>
                            <td>
                                @if ($match->fixtureResults->isNotEmpty())
                                    @php
                                        $resultText = $match->fixtureResults
                                            ->map(function ($set) use ($match) {
                                                return $match->registration1_id === $set->winner_registration
                                                    ? "{$set->registration1_score}-{$set->registration2_score}"
                                                    : "{$set->registration2_score}-{$set->registration1_score}";
                                            })
                                            ->implode(', ');
                                    @endphp
                                    {{ $resultText }}
                                @else
                                    <span class="text-muted">

                                        <button class="btn btn-sm btn-outline-primary set-result-btn"
                                            data-bs-toggle="modal" data-bs-target="#tennisResultModal"
                                            data-fixture-id="{{ $match->id }}" data-player1="{{ $player1 }}"
                                            data-player2="{{ $player2 }}">
                                            Insert Result
                                        </button>

                                    </span>
                                @endif
                            </td>



                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
@else
    <p class="text-muted">No fixtures available yet.</p>
@endif
