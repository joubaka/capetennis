

<?php $__env->startSection('title', 'Code of Conduct'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<h4 class="fw-bold py-3 mb-4">
  <span class="text-muted fw-light">Code of Conduct /</span> <?php echo e($agreement->title); ?> (<?php echo e($agreement->version); ?>)
</h4>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo e(session('error')); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo e(session('success')); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><?php echo e($agreement->title); ?></h5>
      </div>
      <div class="card-body" style="max-height: 500px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 1.5rem;">
        <?php echo $agreement->content; ?>

      </div>
    </div>

    
    <div class="card mb-4">
      <div class="card-body">
        <form id="agreement-form">
          <?php echo csrf_field(); ?>

          
          <div class="mb-3">
            <label class="form-label fw-bold">Select Player</label>
            <select id="player_id" name="player_id" class="form-select select2Basic" required>
              <option value="">-- Select Player --</option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($player->id); ?>"
                  data-minor="<?php echo e($player->isMinor() ? '1' : '0'); ?>">
                  <?php echo e($player->name); ?> <?php echo e($player->surname); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
          </div>

          
          <div id="agreement-status" class="mb-3" style="display:none;">
            <div id="status-accepted" class="alert alert-success" style="display:none;">
              <i class="ti ti-check"></i> This player has already accepted the current Code of Conduct.
            </div>
            <div id="status-pending" class="alert alert-warning" style="display:none;">
              <i class="ti ti-alert-triangle"></i> This player has not yet accepted the current Code of Conduct.
            </div>
          </div>

          
          <div id="guardian-fields" style="display: none;">
            <div class="alert alert-info">
              <i class="ti ti-info-circle"></i> This player is under 18. A parent or guardian must accept on their behalf.
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Guardian Name <span class="text-danger">*</span></label>
                <input type="text" name="guardian_name" id="guardian_name" class="form-control" placeholder="Full name">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Guardian Email <span class="text-danger">*</span></label>
                <input type="email" name="guardian_email" id="guardian_email" class="form-control" placeholder="email@example.com">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Guardian Phone</label>
                <input type="text" name="guardian_phone" id="guardian_phone" class="form-control" placeholder="Phone number">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Relationship <span class="text-danger">*</span></label>
                <input type="text" name="guardian_relationship" id="guardian_relationship" class="form-control" placeholder="e.g. Parent, Legal Guardian">
              </div>
            </div>
          </div>

          
          <div id="accept-section" style="display: none;">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="agree" name="agree">
              <label class="form-check-label" for="agree">
                I have read and agree to the Code of Conduct
              </label>
            </div>

            <button type="button" id="acceptBtn" class="btn btn-primary" disabled>
              <i class="ti ti-check"></i> Accept & Continue
            </button>
          </div>

        </form>

        
        <div id="form-errors" class="alert alert-danger mt-3" style="display: none;"></div>

        
        <div id="form-success" class="alert alert-success mt-3" style="display: none;">
          <i class="ti ti-check"></i> Code of Conduct accepted successfully!
        </div>
      </div>
    </div>

    
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Player Agreement Status</h5>
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Player</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="player-status-table">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr id="player-row-<?php echo e($player->id); ?>">
                <td><?php echo e($player->name); ?> <?php echo e($player->surname); ?></td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->hasAcceptedLatestAgreement()): ?>
                    <span class="badge bg-success">Accepted</span>
                  <?php else: ?>
                    <span class="badge bg-warning">Pending</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-body">
        <h6>Need Help?</h6>
        <p class="mb-0">If you have questions about the Code of Conduct, please contact us at <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>.</p>
      </div>
    </div>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(document).ready(function() {
    $('.select2Basic').select2();

    // When player selection changes
    $('#player_id').on('change', function() {
        var playerId = $(this).val();
        var isMinor = $(this).find(':selected').data('minor');

        // Reset state
        $('#agreement-status, #accept-section, #guardian-fields').hide();
        $('#status-accepted, #status-pending').hide();
        $('#form-errors, #form-success').hide();
        $('#agree').prop('checked', false);
        $('#acceptBtn').prop('disabled', true);

        if (!playerId) return;

        // Check agreement status via AJAX
        $.post('<?php echo e(route("agreements.check")); ?>', {
            _token: '<?php echo e(csrf_token()); ?>',
            player_id: playerId
        }, function(data) {
            $('#agreement-status').show();

            if (data.accepted) {
                $('#status-accepted').show();
                $('#accept-section').hide();
                $('#guardian-fields').hide();
            } else {
                $('#status-pending').show();
                $('#accept-section').show();

                if (data.is_minor) {
                    $('#guardian-fields').show();
                } else {
                    $('#guardian-fields').hide();
                    // Clear guardian fields
                    $('#guardian_name, #guardian_email, #guardian_phone, #guardian_relationship').val('');
                }
            }
        });
    });

    // Enable/disable accept button based on checkbox
    $('#agree').on('change', function() {
        $('#acceptBtn').prop('disabled', !this.checked);
    });

    // Accept agreement
    $('#acceptBtn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="ti ti-loader"></i> Processing...');

        $('#form-errors, #form-success').hide();

        $.ajax({
            url: '<?php echo e(route("agreements.accept")); ?>',
            method: 'POST',
            data: $('#agreement-form').serialize(),
            success: function(data) {
                if (data.success) {
                    $('#form-success').show();
                    $('#accept-section').hide();
                    $('#guardian-fields').hide();
                    $('#status-pending').hide();
                    $('#status-accepted').show();

                    // Update the status table
                    var playerId = $('#player_id').val();
                    $('#player-row-' + playerId + ' td:last').html('<span class="badge bg-success">Accepted</span>');

                    // Check if all players have accepted
                    var allAccepted = true;
                    $('#player-status-table .badge').each(function() {
                        if ($(this).hasClass('bg-warning')) {
                            allAccepted = false;
                        }
                    });

                    if (allAccepted) {
                        // Redirect to home or previous page after a short delay
                        setTimeout(function() {
                            var redirectUrl = '<?php echo e(session("url.intended", route("home"))); ?>';
                            window.location.href = redirectUrl;
                        }, 1500);
                    }
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="ti ti-check"></i> Accept & Continue');

                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var html = '<ul class="mb-0">';
                    $.each(errors, function(key, msgs) {
                        $.each(msgs, function(i, msg) {
                            html += '<li>' + msg + '</li>';
                        });
                    });
                    html += '</ul>';
                    $('#form-errors').html(html).show();
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    $('#form-errors').html(xhr.responseJSON.error).show();
                } else {
                    $('#form-errors').html('An error occurred. Please try again.').show();
                }
            }
        });
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\agreements\show.blade.php ENDPATH**/ ?>