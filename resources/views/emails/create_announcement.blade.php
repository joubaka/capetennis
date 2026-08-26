<x-mail::message>
@include('emails._capez-header')
# New Announcement added to {{$datas['event']}}

{!!$datas['message']!!}




</x-mail::message>
