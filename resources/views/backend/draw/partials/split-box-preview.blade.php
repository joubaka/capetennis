<div class="row">

  @foreach ($splitBoxes as $boxNum => $registrations)
    <div class="col-md-6 mb-3">
      <div class="border rounded bg-light p-3">
        <strong>Box {{ $boxNum }}</strong>
        <ul class="mb-0">
          @foreach ($registrations as $reg)
            <li>{{ $reg->displayName() }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endforeach
</div>
