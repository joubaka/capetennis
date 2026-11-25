@php
  $registrationTermsContent = \App\Models\SiteSetting::get('terms_conditions_content', '');
@endphp
<section class="registration-terms mb-3" aria-label="Registration terms and refunds">
  @if(trim(strip_tags($registrationTermsContent)) !== '')
    <details class="mb-3">
      <summary class="fw-semibold">Cape Tennis Terms &amp; Conditions</summary>
      <div class="mt-2">{!! $registrationTermsContent !!}</div>
    </details>
  @endif
  @include('frontend.partials.registration-refund-terms')
</section>
