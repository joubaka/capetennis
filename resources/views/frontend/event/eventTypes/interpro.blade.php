<style>
.file-item:hover {
  background-color: #f8f9fa;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.card .btn-outline-primary:hover {
  background-color: #0d6efd;
  color: #fff;
}
</style>
{{-- resources/views/frontend/event/eventTypes/interpro.blade.php --}}
<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')

   
      {{-- 🔹 MObile version only --}}
      @include('frontend.event.partials.interpro-draws-mobile')

      {{-- 🔹 Regions & Teams Tabs --}}
      @include('frontend.event.partials.event-region-directory', ['regions' => $regions, 'directoryAlert' => true])
    </div>


    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      @include('frontend.event.partials.event-about')

   

      @include('frontend.event.partials.event-documents')

      {{-- Scorers need a venue-first entry point on every screen size. --}}
      @include('frontend.event.partials._venue-scoring')

      {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      @include('frontend.event.partials.interpro-draws-desktop')

      {{-- 🔹 My Clothing Orders --}}
      @includeWhen(isset($myClothingOrders) && $myClothingOrders->isNotEmpty(),
          'frontend.event.partials._my_clothing_orders')

    </div>
  </div>
</div>
