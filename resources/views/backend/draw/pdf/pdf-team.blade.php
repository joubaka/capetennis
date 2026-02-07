<?php

use App\Helpers\Fixtures;



?>
<style>
    table,
    th,
    td {
        border: 1px solid black;
        border-collapse: collapse;
    }
</style>
<h1>{{$name}}</h1>
<div class="table-responsive">
    <table class="table table-bordered" id="schedule">
        <thead class="">
            <tr>
                <th width="3%">#</th>
                <th width="">Team</th>
                <th width="3%">vs</th>
                <th width="">Team</th>

                <th>Not Before</th>
                <th width='10%'>Venue</th>
                <th colspan="2">Result</th>
            </tr>
        </thead>
        <tbody class="table-border-bottom-0">

            @foreach($fixtures as $key => $fixture)

            @if($fixture->rank_nr == 1)
            <tr class="m-4">
                <td colspan="8"><span class=" ">
                        <h4>{{$fixture->region1Name->region_name}} vs {{$fixture->region2Name->region_name}}</h4>
                    </span></td>
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
                <td>{{$fixture->rank_nr}} </td>
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


                    @endif




                </td>
                <td>
                  
                </td>
            </tr>

            @endforeach
        </tbody>
    </table>
</div>