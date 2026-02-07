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