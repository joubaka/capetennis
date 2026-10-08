<div id="draw-container">

</div>


<script>
    window.drawData = <?php echo json_encode($fixtureMap, 15, 512) ?>;   // global once
    window.isDrawLocked = <?php echo json_encode($isDrawLocked, 15, 512) ?>;
    buildAllDraws();                        // just call the already-loaded function
</script>
 <h2>Testtttttt</h2>

<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\playoff_svg.blade.php ENDPATH**/ ?>