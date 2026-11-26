<script src="<?php echo e(asset('assets/js/camp-workflow.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/camp-workflow.js'))); ?>"></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
<script>
(() => {
 const errors = <?php echo json_encode($errors->messages(), 15, 512) ?>;
 const root = document.querySelector('[data-camp-workflow]');
 const marker = <?php echo json_encode(old('_camp_form'), 15, 512) ?>;
 const scope = Array.from(root.querySelectorAll('form')).find(form => form.action===marker);
 if (!scope) return;
 let first = null;
 Object.entries(errors).forEach(([key,messages]) => {
   const name = key.replace(/\.([^.]*)/g,'[$1]');
   const fields = Array.from(scope.querySelectorAll('[name]')).filter(field => field.name === name && field.type!=='hidden');
   fields.forEach((field,index) => {
     field.setAttribute('aria-invalid','true');
     const id = 'camp-error-'+key.replace(/[^a-z0-9]/gi,'-')+'-'+index;
     const error = document.createElement('div'); error.id=id; error.className='text-danger small mt-1'; error.textContent=messages.join(' ');
     field.insertAdjacentElement('afterend',error); field.setAttribute('aria-describedby',id);
     let parent=field.parentElement; while(parent && parent!==root){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}
     first ||= field;
   });
 });
 first?.focus();
})();
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><script>AppFeedback.success(<?php echo json_encode(session('status'), 15, 512) ?>);</script><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\practice-camps\feedback.blade.php ENDPATH**/ ?>