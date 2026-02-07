
<div class="row ">
    
     <!-- @foreach($ranking as $key=> $player)
<p>  {{$key+1}} {{$player['name']}} ({{$player['region']}}) - {{$player['points']}} points</p>
           @endforeach -->
    @foreach($playerFixtures as $key => $team)
    <div class="col-md-4 col-sm-12 ">
        <h3>{{$key}}</h3>
        <div class=" m-2">
            <table class="table table-xs">
                <colgroup>
                    <col class="small-col"> <!-- This sets a small width for the first column -->
                    <col class="small-col"> <!-- Normal column width -->
                   
                    <col class="small-col"> <!-- Normal column width -->
                </colgroup>
                <thead>
                    
                    <th>Rank</th>
                    <th>Player</th>
                <th>Opponent</th>

                    
                </thead>

                <tbody class="table-border-bottom-0">
                    @foreach($team as $rank => $playerDetails)
                    <tr>
                        <td>{{$rank+1}}</td>
                        <td>{{$playerDetails['name']}}</td>

                        <td>
                            <table class="table table-xs table-responsive">
                                <thead>
                                    <th>Opponent</th>
                                    <th>Result</th>
                                    <th>Score</th>
                                </thead>
                                <tbody>
                                    <tr>@foreach($playerDetails['results']['opponents'] as $k => $opponent)
                                        <td> @if(isset($opponent))
                                            {{$opponent['player']['name']}} {{$opponent['player']['surname']}}
                                            @endif
                                        </td>
                                        <td class="{{$playerDetails['results']['w/l'][$k] == 1 ? 'bg-label-success':'bg-label-danger'}}">{{$playerDetails['results']['w/l'][$k] == 1 ? 'Won':'Lost'}}</td>
                                        <td>
                                            @foreach($opponent['score'] as $match)

                                            @foreach($match as $set)
                                            {{$set->team1_score}} - {{$set->team2_score}}
                                            @endforeach
                                            @endforeach
                                        </td>

                                    </tr>
                                    @endforeach
                                </tbody>

                            </table>




                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
          
        </div>
    </div>
  @endforeach
</div>