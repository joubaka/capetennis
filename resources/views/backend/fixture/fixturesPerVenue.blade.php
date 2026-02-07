<?php

use App\Helpers\Fixtures;

?>
@php
    $firstFix = $fixtures->first();
@endphp
@php
    $ids = $fixtures->pluck('id')->toArray();
@endphp
<h3><a class="btn btn-danger btn-sm ms-2" href="{{ url()->previous() }}">Back</a>
    <a class="btn btn-primary btn-sm" href="{{route('fixture.create.pdf.venue',['fixtures' => $ids])}}">Create PDF</a>

</h3>
<div class="table-responsive">

    <table class="table" id="schedule">
        <thead class="table-dark">
            <tr>
                <th width="2%">Match #</th>
                <th width="2%">Rank #</th>
                <th>Fix #</th>
                <th width="5%">Team</th>
                <th>vs</th>
                <th width="5%">Team</th>

                <th>Not Before</th>
                <th>Venue</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody class="table-border-bottom-0">

            @foreach($fixtures as $key => $fixture)

                @if($fixture->rank_nr == 1)
                    <tr class="m-4">

                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                    </tr>
                @endif
                <tr id='{{$fixture->id}}'>
                <td> {{$key+1}} </td>
                <td>{{$fixture->rank_nr}}</td>
                <td>{{$fixture->id}}</td>
<!-- check if team has players -->

                @if($fixture->team1->count() == 1)

                                        @if ($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1)

                                                    <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p1"> {{Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)}}
                                                            ({{$fixture->region1Name->short_name}})</span>
                                                    </td>

                                                    <td>vs</td>
                                                    <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p2 "> {{Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)}}
                                                            ({{$fixture->region2Name->short_name}})</span>
                                                    </td>

                                        @elseif ($fixture->region2Name->no_profile == 1)

                                                    <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p1">{{$fixture->team1[0]->getFullNameAttribute()}}
                                                            ({{$fixture->region1Name->short_name}})</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p2"> {{Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)}}
                                                            ({{$fixture->region2Name->short_name}})</span>
                                                    </td>
                                        @elseif($fixture->region1Name->no_profile == 1)

                                                    <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p1"> {{Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)}}
                                                            ({{$fixture->region1Name->short_name}})</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p2">{{$fixture->team2[0]->getFullNameAttribute()}}
                                                            ({{$fixture->region2Name->short_name}})</span>
                                                    </td>


                                        @else

                                                <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p1">{{$fixture->team1[0]->getFullNameAttribute()}}
                                                            ({{$fixture->region1Name->short_name}})</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p2">{{$fixture->team2[0]->getFullNameAttribute()}}
                                                            ({{$fixture->region2Name->short_name}})</span>
                                                    </td>





                                        @endif

                @else

                            @if($fixture->fixture_type == 2)

                                                @if ($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1)

                                                <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary"> {{Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)}}
                                                                ({{$fixture->region1Name->short_name}})</span>
                                                        </td>

                                                        <td>vs</td>
                                                        <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary"> {{Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)}}
                                                                ({{$fixture->region2Name->short_name}})</span>
                                                        </td>

                                                @elseif ($fixture->region2Name->no_profile == 1)
                                                        <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary">{{$fixture->team1[0]->getFullNameAttribute()}}/{{$fixture->team1[1]->getFullNameAttribute()}}
                                                                ({{$fixture->region1Name->short_name}})</span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary"> {{Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)}}
                                                                ({{$fixture->region2Name->short_name}})</span>
                                                        </td>
                                                @elseif($fixture->region1Name->no_profile == 1)

                                                        <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary"> {{Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)}}
                                                                ({{$fixture->region1Name->short_name}})</span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary">{{$fixture->team2[0]->getFullNameAttribute()}}/{{$fixture->team2[1]->getFullNameAttribute()}}
                                                                ({{$fixture->region2Name->short_name}})</span>
                                                        </td>
                                                        @else


                                                         <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary">
                                                            {{$fixture->team1[0]->getFullNameAttribute()}}/{{$fixture->team1[1]->getFullNameAttribute()}}
                                                            </span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                            <span class="badge bg-label-primary">
                                                            {{$fixture->team2[0]->getFullNameAttribute()}}/{{$fixture->team2[1]->getFullNameAttribute()}}

                                                            </span>
                                                        </td>


                                                @endif


                            @else

                                            @if ($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1)
                                                <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary"> {{Fixtures::getNoProfileMixedTeam($fixture,1,$fixture->rank_nr)}}
                                                        ({{$fixture->region1Name->short_name}})</span>
                                                </td>

                                                <td>vs</td>
                                                <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary"> {{Fixtures::getNoProfileMixedTeam($fixture,2,$fixture->rank_nr)}}
                                                        ({{$fixture->region2Name->short_name}})</span>
                                                </td>

                                            @elseif ($fixture->region2Name->no_profile == 1)
                                                <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary">{{$fixture->team1[0]->getFullNameAttribute()}}/{{$fixture->team1[1]->getFullNameAttribute()}}
                                                        ({{$fixture->region1Name->short_name}})</span>
                                                </td>
                                                <td>vs</td>
                                                <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary"> {{Fixtures::getNoProfileMixedTeam($fixture,2,$fixture->rank_nr)}}
                                                        ({{$fixture->region2Name->short_name}})</span>
                                                </td>
                                            @elseif($fixture->region1Name->no_profile == 1)

                                                <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary"> {{Fixtures::getNoProfileMixedTeam($fixture,1,$fixture->rank_nr)}}
                                                        ({{$fixture->region1Name->short_name}})</span>
                                                </td>
                                                <td>vs</td>
                                                <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                    <span class="badge bg-label-primary">{{$fixture->team2[0]->getFullNameAttribute()}}/{{$fixture->team1[1]->getFullNameAttribute()}}
                                                        ({{$fixture->region2Name->short_name}})</span>
                                                </td>
                                            @else

                                            <td class="{{Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p1">{{$fixture->team1[0]->getFullNameAttribute()}}/{{$fixture->team1[1]->getFullNameAttribute()}}
                                                            ({{$fixture->region1Name->short_name}})</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="{{Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''}}">
                                                        <span class="badge bg-label-primary p2">{{$fixture->team2[0]->getFullNameAttribute()}}/{{$fixture->team2[1]->getFullNameAttribute()}}
                                                            ({{$fixture->region2Name->short_name}})</span>
                                                    </td>

                                            @endif


                            @endif




                @endif

                    <td><span class="time {{$fixture->schedule ? 'badge bg-label-warning':'badge bg-label-danger'}}">{{$fixture->schedule ? date('D d M @ H:i', strtotime($fixture->schedule->time)):''}}</span> </td>
                    <td><span class="venue {{$fixture->schedule ? 'badge bg-label-secondary':'badge bg-label-danger'}}">{{$fixture->schedule ? $fixture->schedule->venue->name:''}}</span></td>
                    <td class="resultTd">
                    @if($fixture->teamResults->count() > 0)
                        <span>
                            @foreach($fixture->teamResults as $key => $result)
                            {{$result->team1_score}} - {{$result->team2_score}}
                                    @if($key < (count($fixture->teamResults)-1) )
                                        ,
                                    @endif
                            @endforeach

                        </span>
                    @else

                    <button type="button" data-id="{{$fixture}}" data-reg1="{{$fixture->team1->count() > 1 ? $fixture->team1[0]->getFullNameAttribute().'/'.$fixture->team1[1]->getFullNameAttribute():$fixture->team1[0]->getFullNameAttribute()}} " data-reg2="{{$fixture->team2->count() > 1 ? $fixture->team2[0]->getFullNameAttribute().'/'.$fixture->team2[1]->getFullNameAttribute():$fixture->team2[0]->getFullNameAttribute()}} " class="btn btn-sm btn-secondary insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal">Insert Score</button><!-- Modal Trigger -->


                    @endif




                </td>
                <td>
                    <div class="dropdown">
                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                        <div class="dropdown-menu">

                            <form action="{{route('draw.delete.result',$fixture->id)}}" method="post">
                                @csrf
                                <button type="submit" class="dropdown-item" href="javascript:void(0);"><i class="ti ti-trash me-1"></i>Delete</button>

                            </form>

                            <button data-bs-toggle="modal" data-bs-target="#change-schedule-modal" type="button" data-id="{{$fixture}}" class="dropdown-item change-schedule" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Change Schedule</button>

                            <button data-bs-toggle="modal" data-bs-target="#change-player-modal" type="button" data-id="{{$fixture}}" class="dropdown-item change-players" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Change Players</button>
                        </div>
                    </div>
                </td>
            </tr>

            @endforeach
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="change-player-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Change Players</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form action="{{route('fixture.update.players')}}">
                    <div class="mb-3">
                        <label for="defaultSelect" class="form-label">Player 1</label>
                        <select name="player1" id="player1" class="form-select">
                            <option>Default select</option>
                            @foreach($players as $player)
                            <option value="{{$player->id}}">{{$player->name}} {{$player->surname}}</option>
                            @endforeach

                        </select>
                    </div>
                    <div>vs</div>
                    <div class="mb-3">
                        <label for="defaultSelect" class="form-label">Player 2</label>
                        <select name="player2" id="player2" class="form-select">
                            <option>Default select</option>
                            @foreach($players as $player)
                            <option value="{{$player->id}}">{{$player->name}} {{$player->surname}}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="fixture" id="fixutureValue">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary save-fixture-names">Save changes</button>
                    </div>
                </form>

            </div>

        </div>
    </div>
</div>
