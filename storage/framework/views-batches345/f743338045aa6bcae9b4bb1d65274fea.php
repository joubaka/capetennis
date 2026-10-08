
<div class="row g-3">

  <div class="col-md-6">
    <label class="form-label">Expense Type <span class="text-danger">*</span></label>
    <select name="expense_type" class="form-select" required>
      <option value="">Select type...</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $expenseTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($key); ?>" <?php echo e(old('expense_type', $expense?->expense_type) == $key ? 'selected' : ''); ?>>
          <?php echo e($label); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
  </div>

  <div class="col-md-6">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($multiPaidBy)): ?>
      <label class="form-label">Paid by (Event Director(s))</label>
      <select name="paid_by_convenor_ids[]" id="expensePaidBySelect" class="form-select" multiple>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($c->id); ?>">
            <?php echo e($c->user->name ?? 'Unknown'); ?>

            (<?php echo e($c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role))); ?>)
          </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <small class="text-muted">Select one or more directors. One expense record will be created per person.</small>
    <?php else: ?>
      <label class="form-label">Paid by (Event Director)</label>
      <select name="paid_by_convenor_id" class="form-select">
        <option value="">— No event director assigned —</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($c->id); ?>"
                  <?php echo e(old('paid_by_convenor_id', $expense?->paid_by_convenor_id) == $c->id ? 'selected' : ''); ?>>
            <?php echo e($c->user->name ?? 'Unknown'); ?>

            (<?php echo e($c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role))); ?>)
          </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <div class="col-md-6 vc-col-wrapper<?php echo e(old('recipient_name', $expense?->recipient_name) ? '' : ' d-none'); ?>">
    <label class="form-label">Venue Convenor / Recipient</label>
    <div class="vc-field-wrapper">
      <div class="input-group">
        <select name="recipient_name" class="form-select vc-select"
                data-vc-store-url="<?php echo e(route('admin.events.finances.venue-convenor.store', $event)); ?>"
                data-vc-destroy-url="<?php echo e(route('admin.events.finances.venue-convenor.destroy', ['venueConvenor' => '__ID__'])); ?>">
          <option value="">— none —</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueConvenors ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($vc->name); ?>" data-vc-id="<?php echo e($vc->id); ?>"
                    <?php echo e(old('recipient_name', $expense?->recipient_name) == $vc->name ? 'selected' : ''); ?>>
              <?php echo e($vc->name); ?>

            </option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <button type="button" class="btn btn-outline-success vc-add-btn" title="Add venue convenor">
          <i class="ti ti-plus"></i>
        </button>
        <button type="button" class="btn btn-outline-danger vc-remove-btn d-none" title="Remove selected venue convenor">
          <i class="ti ti-minus"></i>
        </button>
      </div>
      <div class="vc-add-form mt-2 d-none">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control vc-add-name" placeholder="New convenor name" maxlength="150">
          <button type="button" class="btn btn-success vc-add-save-btn">
            <i class="ti ti-check me-1"></i>Save
          </button>
          <button type="button" class="btn btn-outline-secondary vc-add-cancel-btn">Cancel</button>
        </div>
      </div>
    </div>
    <small class="text-muted">
      Person paid to convene a venue, or other payee.
      <a href="#" class="vc-hide-link ms-2 text-muted"><i class="ti ti-x"></i> Remove</a>
    </small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Description</label>
    <input type="text" name="description" class="form-control"
           value="<?php echo e(old('description', $expense?->description)); ?>"
           placeholder="Optional description">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!old('recipient_name', $expense?->recipient_name)): ?>
      <small><a href="#" class="vc-show-link text-muted"><i class="ti ti-user-plus"></i> Add venue convenor / payee</a></small>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <div class="col-md-4">
    <label class="form-label">Quantity</label>
    <input type="number" name="quantity" class="form-control"
           step="0.01" min="0"
           value="<?php echo e(old('quantity', $expense?->quantity)); ?>"
           placeholder="e.g. 96">
  </div>

  <div class="col-md-4">
    <label class="form-label">Unit Price (R)</label>
    <input type="number" name="unit_price" class="form-control"
           step="0.01" min="0"
           value="<?php echo e(old('unit_price', $expense?->unit_price)); ?>"
           placeholder="e.g. 100.00">
  </div>

  <div class="col-md-4">
    <label class="form-label">Amount (R) <span class="text-danger">*</span></label>
    <input type="number" name="amount" class="form-control"
           step="0.01" min="0" required
           value="<?php echo e(old('amount', $expense?->amount)); ?>"
           placeholder="0.00">
    <small class="text-muted">Auto-calculated when Quantity × Price is filled in.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Budget Amount (R)</label>
    <input type="number" name="budget_amount" class="form-control"
           step="0.01" min="0"
           value="<?php echo e(old('budget_amount', $expense?->budget_amount)); ?>"
           placeholder="Estimated budget">
  </div>

  <div class="col-md-6">
    <label class="form-label">Date</label>
    <input type="date" name="date" class="form-control"
           value="<?php echo e(old('date', $expense?->date?->format('Y-m-d') ?? now()->format('Y-m-d'))); ?>">
  </div>

  <div class="col-12">
    <label class="form-label">Receipt / Voucher</label>
    <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense?->receipt_path): ?>
      <small class="text-muted">
        Current: <a href="<?php echo e(asset('storage/'.$expense->receipt_path)); ?>" target="_blank">
          <i class="ti ti-paperclip"></i> View receipt
        </a>
      </small>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\_expense_fields.blade.php ENDPATH**/ ?>