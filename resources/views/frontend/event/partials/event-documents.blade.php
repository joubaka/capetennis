      {{-- 🔹 Documents --}}
      <div class="card mb-4 shadow-sm {{ $documentCardClass ?? '' }}">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <h6 class="card-title text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> {{ $documentTitle ?? 'Documents' }}
          </h6>

          @if(! ($documentsReadOnly ?? false) && auth()->user()?->hasAnyRole(['super-user', 'admin']))
          @can('event.manage', $event)
            @if(!empty($documentUploadModal))
              <button type="button" class="btn btn-sm btn-outline-primary" style="min-height:44px" data-bs-toggle="modal" data-bs-target="{{ $documentUploadModal }}">Upload</button>
            @else
            <form action="{{ route('file.store') }}" method="POST" enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2 mb-0">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">

              <label class="btn btn-sm btn-outline-primary mb-0" style="min-height:44px">
                <i class="ti ti-upload me-1"></i> Upload
                <input type="file" name="myFile" class="visually-hidden" aria-label="Upload document"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>

            </form>
            @endif
          @endcan
          @endif

        </div>

        <div class="card-body pb-2">
          @if(!empty($documentDescription))
            <p class="text-muted small">{{ $documentDescription }}</p>
          @endif

          @forelse($event->files as $file)
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">

              <div class="d-flex align-items-center flex-grow-1" style="min-width: 0">
                <i class="ti ti-file-description text-primary fs-5 me-2"></i>
                <a href="{{ route('events.documents.show', [$event, $file]) }}" @if($documentNewWindow ?? true) target="_blank" rel="noopener" @endif
                   class="fw-semibold text-dark text-decoration-none text-break">
                  {{ $file->name }}
                </a>
              </div>

              <div class="d-flex align-items-center">
                @if(! ($documentsReadOnly ?? false) && auth()->user()?->hasAnyRole(['super-user', 'admin']))
                @can('event.manage', $event)
                  <button type="button" data-id="{{ $file->id }}"
                          class="btn btn-sm btn-icon btn-outline-danger deleteFileButton"
                          style="min-width:44px;min-height:44px"
                          data-bs-toggle="tooltip" title="Delete" aria-label="Delete {{ $file->name }}">
                    <i class="ti ti-trash"></i>
                  </button>
                @endcan
                @endif
              </div>
            </div>
          @empty
            <div class="text-muted">{{ $documentEmptyMessage ?? 'No documents uploaded yet.' }}</div>
          @endforelse

        </div>

      </div>


