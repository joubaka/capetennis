<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="alert alert-banner mb-0 rounded-0"
     :class="{'bg-success': style == 'success', 'bg-danger': style == 'danger', 'bg-secondary': style != 'success' && style != 'danger'}"
     role="alert"
     x-data="{show: true, style: '<?php echo e($style); ?>', message: '<?php echo e($message); ?>'}"
     x-show="show && message"
     x-init="
        document.addEventListener('banner-message', event => {
            style = event.detail.style;
            message = event.detail.message;
            show = true;
        });
     " style="display: none;">
     <div class="d-flex justify-content-between align-items-center">
         <div>
             <span class="badge rounded-pill py-2" :class="{'bg-success': style == 'success', 'bg-danger': style == 'danger'}">
               <svg x-show="style == 'success'" class="h-px-20 w-px-20 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
             </svg>
             <svg x-show="style == 'danger'" class="h-px-20 w-px-20 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
             </svg>
             <svg x-show="style != 'success' && style != 'danger'" class="h-px-20 w-px-20 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
             </svg>
             </span>
             <span class="text-white" x-text="message"></span>
         </div>

         <div class="text-end">
             <button type="button"
                     class="btn btn-icon btn-sm"
                     :class="{'btn-success': style == 'success', 'btn-danger': style == 'danger'}"
                     aria-label="Dismiss"
                     x-on:click="show = false">
                     <svg class="h-px-20 w-px-20 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                   </svg>
             </button>
         </div>
     </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\components\banner.blade.php ENDPATH**/ ?>