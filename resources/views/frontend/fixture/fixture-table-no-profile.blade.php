<?php

use App\Helpers\Fixtures;



?>

<h3>{{$fixtures[0]->draw->drawName}} {{$fixtures[0]->draw->age}} {{$fixtures[0]->draw_type->drawTypeName}}<a class="btn btn-danger btn-sm ms-2" href="{{ url()->previous() }}">Back</a></h3>
<div class="table-responsive">
    <table class="table" id="schedule">
        <thead class="table-dark">
            <tr>
                <th width="2%">#</th>
                <th width="5%">Team</th>
                <th>vs</th>
                <th width="5%">Team</th>
                <th>Score</th>
                <th>Not Before</th>
                <th>Venue</th>

            </tr>
        </thead>
        <tbody class="table-border-bottom-0">
            @foreach($fixtures as $key => $fixture)


            @if($fixture->rank_nr == 1)
            <tr class="m-4">
                <td colspan="8"><span class=" ">
                        <h4>{{$fixture->region1Name->region_name}} vs {{$fixture->region2Name->region_name}}
                            {{$fixture->draw_type->drawTypeName}}
                        </h4>
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
            <tr>
              <td>{{$fixture->rank_nr}} </td>

              @php
                  $winner1 = Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success' : '';
                  $winner2 = Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success' : '';
                  $profile1 = $fixture->region1Name->no_profile == 1;
                  $profile2 = $fixture->region2Name->no_profile == 1;
              @endphp

              @if($fixture->team1->count() == 1)
                  @if($profile1 && $profile2)
                      <!-- No profile for both teams -->
                      <td class="{{ $winner1 }}">
                          <span class="badge bg-label-primary p1">{{ Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr) }}
                              ({{$fixture->region1Name->short_name}})</span>
                      </td>
                      <td>vs</td>
                      <td class="{{ $winner2 }}">
                          <span class="badge bg-label-primary p2">{{ Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr) }}
                              ({{$fixture->region2Name->short_name}})</span>
                      </td>
                  @elseif($profile2)
                      <!-- Region 1 has profile, Region 2 does not -->
                      <td class="{{ $winner1 }}">
                          <span class="badge bg-label-primary p1">{{ $fixture->team1[0]->getFullNameAttribute() }}
                              ({{$fixture->region1Name->short_name}})</span>
                      </td>
                      <td>vs</td>
                      <td class="{{ $winner2 }}">
                          <span class="badge bg-label-primary p2">{{ Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr) }}
                              ({{$fixture->region2Name->short_name}})</span>
                      </td>
                  @elseif($profile1)
                      <!-- Region 2 has profile, Region 1 does not -->
                      <td class="{{ $winner1 }}">
                          <span class="badge bg-label-primary p1">{{ Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr) }}
                              ({{$fixture->region1Name->short_name}})</span>
                      </td>
                      <td>vs</td>
                      <td class="{{ $winner2 }}">
                          <span class="badge bg-label-primary p2">{{ $fixture->team2[0]->getFullNameAttribute() }}
                              ({{$fixture->region2Name->short_name}})</span>
                      </td>
                  @else
                      <!-- Both teams have profiles -->
                      <td class="{{ $winner1 }}">
                          <span class="badge bg-label-primary p1">{{ $fixture->team1[0]->getFullNameAttribute() }}
                              ({{$fixture->region1Name->short_name}})</span>
                      </td>
                      <td>vs</td>
                      <td class="{{ $winner2 }}">
                          <span class="badge bg-label-primary p2">{{ $fixture->team2[0]->getFullNameAttribute() }}
                              ({{$fixture->region2Name->short_name}})</span>
                      </td>
                  @endif
              @else
                  @if($fixture->fixture_type == 2)
                      @if($profile1 && $profile2)
                          <!-- No profile for both teams -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr) }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr) }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @elseif($profile2)
                          <!-- Region 1 has profile, Region 2 does not -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ $fixture->team1[0]->getFullNameAttribute() }}/{{ $fixture->team1[1]->getFullNameAttribute() }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr) }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @elseif($profile1)
                          <!-- Region 2 has profile, Region 1 does not -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr) }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ $fixture->team2[0]->getFullNameAttribute() }}/{{ $fixture->team2[1]->getFullNameAttribute() }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @endif
                  @else
                      @if($profile1 && $profile2)
                          <!-- No profile for both teams -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileMixedTeam($fixture, 1, $fixture->rank_nr) }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileMixedTeam($fixture, 2, $fixture->rank_nr) }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @elseif($profile2)
                          <!-- Region 1 has profile, Region 2 does not -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ $fixture->team1[0]->getFullNameAttribute() }}/{{ $fixture->team1[1]->getFullNameAttribute() }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileMixedTeam($fixture, 2, $fixture->rank_nr) }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @elseif($profile1)
                          <!-- Region 2 has profile, Region 1 does not -->
                          <td class="{{ $winner1 }}">
                              <span class="badge bg-label-primary">{{ Fixtures::getNoProfileMixedTeam($fixture, 1, $fixture->rank_nr) }}
                                  ({{$fixture->region1Name->short_name}})</span>
                          </td>
                          <td>vs</td>
                          <td class="{{ $winner2 }}">
                              <span class="badge bg-label-primary">{{ $fixture->team2[0]->getFullNameAttribute() }}/{{ $fixture->team2[1]->getFullNameAttribute() }}
                                  ({{$fixture->region2Name->short_name}})</span>
                          </td>
                      @endif
                  @endif
              @endif

              <!-- Team Results and Match Time -->
              <td>
                  @if($fixture->teamResults->count() > 0)
                      <span>
                          @foreach($fixture->teamResults as $result)
                              {{$result->team1_score}} - {{$result->team2_score}}
                          @endforeach
                      </span>
                  @endif
              </td>
              <td>
                  @if($fixture->draw->oop_published == 1)
                      @if($fixture->schedule)
                          <span class="badge bg-label-warning">{{ date('D d M @ H:i', strtotime($fixture->schedule->time)) }}</span>
                      @else
                          <span class="badge bg-label-danger"></span>
                      @endif
                  @else
                      <span class="badge bg-label-danger"></span>
                  @endif
              </td>
              <td>
                  @if($fixture->draw->oop_published == 1)
                      @if($fixture->schedule)
                          <span class="badge bg-label-warning">{{$fixture->schedule->venue->name}}</span>
                      @endif
                  @else
                      <span class="badge bg-label-danger"></span>
                  @endif
              </td>
          </tr>


            @endforeach
        </tbody>
    </table>
</div>
