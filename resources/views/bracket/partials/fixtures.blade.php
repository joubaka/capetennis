<div class="table-responsive">
  <table class="table table-sm">
    <thead>
    <tr>
      <th scope="col">Fixture Id</th>
      <th  scope="col">Time</th>

      <th scope="col">Registration 1</th>
      <th  scope="col"></th>
      <th scope="col">Registration 2</th>
      <th  scope="col">Result</th>
      <th  scope="col">Actions</th>
    </tr>
    </thead>



    <tbody class="table-border-bottom-0">
    @foreach($bracket->fixtures as $key => $fixture)
      <tr data-id="{{$fixture->id}}" id="{{$fixture->id}}">
        <td> #{{$fixture->id}}<br>{{$fixture->bracket->name}} </td>
        <td>
          @auth
            <button data-bs-toggle="modal" data-bs-target="#change-schedule-modal" class="btn timeVenue" data-id="{{$fixture->oop}}" data-fixture="{{$fixture}}" >
              <div class="badge bg-label-secondary">
                {{$fixture->oop ? $fixture->oop->time:'not Scheduled'}}<br>
                <span class="badge bg-label-primary">{{$fixture->oop ? $fixture->oop->venue->name:'not Scheduled'}}</span>

              </div>
            </button>

          @else
            <div class="notAuth">
            <div class="badge bg-label-secondary">
              {{$fixture->oop ? $fixture->oop->time:'not Scheduled'}}<br>
              <span class="badge bg-label-primary">{{$fixture->oop ? $fixture->oop->venue->name:'not Scheduled'}}</span>

            </div>
</div>
          @endauth



        </td>


        <td  class=" p1 bg-label-{{ $bracket->getWinnerRegistration($fixture->id,$fixture->registration1_id)}} registration1" data-id="{{$fixture->registrations1 ? $fixture->registrations1->id:''}}">
          @if($fixture->registration1_id > 0)
            {{$fixture->registrations1['players'][0]['name'].' '.$fixture->registrations1['players'][0]['surname']}}

          @elseif(is_null($fixture->registration1_id))

          {{$bracket->getFixtureFrom($fixture)}}
          @else
            BYE
          @endif
        </td>
        <td>
          @if($fixture->fixtureResults->count() > 0)
            <span class="badge bg-label-primary">vs</span>
          @else
            <button type="button" data-id="{{$fixture}}" data-reg1="{{$fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''}} " data-reg2="{{$fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:''}} " class="btn btn-sm btn-success insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal" >vs</button></td>
        @endif
        <td class="p2 bg-label-{{ $bracket->getWinnerRegistration($fixture->id,$fixture->registration2_id)}} registration2" data-id="{{$fixture->registrations2 ? $fixture->registrations2->id:''}}">
          @if($fixture->registration2_id > 0)
            {{$fixture->registrations2['players'][0]['name'].' '.$fixture->registrations2['players'][0]['surname']}}
          @elseif(is_null($fixture->registration2_id))

          @else
            BYE
          @endif
        </td>
        <td class="resultTd">

          @if($fixture->fixtureResults->count() > 0)
            {{$bracket->result($fixture->id)}}
          @else

            <button type="button" data-id="{{$fixture}}" data-reg1="{{$fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''}} " data-reg2="{{$fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:''}} " class="btn btn-sm btn-success insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal">Insert Score</button></td>

        @endif


        <td>

          @if($fixture->fixtureResults->count() > 0)
            <button  data-id="{{$fixture->id}}" class="deleteFixture btn btn-xs btn-danger" ><i class="ti ti-trash me-1" ></i> Delete</button>
          @else


          @endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@include('backend.draw._modals.change-schedule-modal')
