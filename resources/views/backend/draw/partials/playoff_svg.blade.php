<div id="draw-container">

</div>

{{-- Same <script> that builds the SVG, BUT NO const declarations that clash.
     Just read fixtureMap from a data-attribute or JSON-encoded script tag below. --}}
<script>
    window.drawData = @json($fixtureMap);   // global once
    window.isDrawLocked = @json($isDrawLocked);
    buildAllDraws();                        // just call the already-loaded function
</script>
 <h2>Testtttttt</h2>
{{-- (Optional) Fixtures table markup here --}}
