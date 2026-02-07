  <div class="text-center">


      @if ($draw->drawFixtures->isNotEmpty())
          <div class="draw-preview-area">
              {{-- Include your SVG or HTML bracket here --}}
              @include('backend.draw.partials.draw-preview', ['draw' => $draw])
          </div>
      @else
          <p class="text-muted">No matches available to preview yet. Configure your settings and generate
              the draw.</p>
      @endif
  </div>
