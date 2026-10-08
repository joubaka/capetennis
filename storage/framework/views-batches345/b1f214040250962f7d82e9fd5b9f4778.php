<?php $__env->startSection('title', 'Order a Hoodie'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <h2 class="my-4">Order Your JTA Hoodie</h2>

    <div class="row">
        <!-- Left: Hoodie Order Form -->
        <div class="col-md-8">
            <form method="POST" action="<?php echo e(route('hoodie.submit')); ?>">
                <?php echo csrf_field(); ?>

                <div id="hoodie-entries">
                    <div class="hoodie-row row mb-3">
                        <div class="col-md-5">
                            <label>Hoodie</label>
                            <select name="items[]" class="form-select hoodie-select" required>
                                <option value="">Please select a hoodie</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($item->id); ?>"
                                        data-price="<?php echo e($item->price); ?>"
                                        data-image="<?php echo e(asset('storage/hoodies/' . 'hoodie2025')); ?>">
                                    <?php echo e($item->item_type_name); ?> – R<?php echo e($item->price); ?>

                                </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Size</label>
                            <select name="sizes[]" class="form-select size-select" required>
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-row">Remove</button>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary my-2" id="add-row">+ Add Another Hoodie</button>

                <div class="mb-3">
                    <strong>Total: R<span id="totalAmount">0.00</span></strong>
                </div>

                <div class="mb-3">
                    <label>Your Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                 <div class="mb-3">
                    <label>Town/Branch</label>
                    <input type="text" name="town" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Proceed to PayFast</button>
            </form>
        </div>

        <!-- Right: Static Hoodie Image -->
        <div class="col-md-4 d-flex align-items-start justify-content-center">
            <img src="<?php echo e(asset('storage/hoodies/hoodie2025.png')); ?>"
                 alt="JTA Hoodie Example"
                 class="img-fluid rounded shadow"
                 style="max-height: 400px;">
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(document).ready(function () {
    function bindSizeDropdowns(row) {
        row.find('.hoodie-select').on('change', function () {
            const itemId = $(this).val();
            const sizeSelect = row.find('.size-select');

            sizeSelect.html('<option>Loading...</option>');

            if (!itemId) {
                sizeSelect.html('<option value="">Please select a hoodie first</option>');
                calculateTotal();
                return;
            }

            $.ajax({
                url: APP_URL + '/hoodie/sizes/' + itemId,
                type: 'GET',
                success: function (sizes) {
                    sizeSelect.empty();
                    sizeSelect.append('<option value="">Please select</option>');
                    $.each(sizes, function (index, size) {
                        sizeSelect.append('<option value="' + size.id + '">' + size.size + '</option>');
                    });
                },
                error: function () {
                    sizeSelect.html('<option>Error loading sizes</option>');
                }
            });

            calculateTotal();
        });
    }

    function calculateTotal() {
        let total = 0;
        $('.hoodie-select').each(function () {
            const selected = $(this).find('option:selected');
            const price = parseFloat(selected.data('price'));
            if (!isNaN(price)) {
                total += price;
            }
        });
        $('#totalAmount').text(total.toFixed(2));
    }

    $('#add-row').click(function () {
        const newRow = $('#hoodie-entries .hoodie-row:first').clone();
        newRow.find('select').val('');
        $('#hoodie-entries').append(newRow);
        bindSizeDropdowns(newRow);
        calculateTotal();
    });

    $(document).on('click', '.remove-row', function () {
        if ($('.hoodie-row').length > 1) {
            $(this).closest('.hoodie-row').remove();
            calculateTotal();
        }
    });

    // Initial bind
    bindSizeDropdowns($('.hoodie-row'));
    calculateTotal();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\orders\hoodie-form.blade.php ENDPATH**/ ?>