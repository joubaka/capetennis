<h1><?php echo e($event->name); ?></h1>
<p><?php echo e($notification ? 'Camp invitation notification for' : 'You are invited to enter the camp:'); ?> <?php echo e($name); ?> · <?php echo e($division); ?></p>
<p><?php echo nl2br(e($message)); ?></p>
<p><?php echo e($event->start_date?->format('j F Y')); ?> – <?php echo e($event->end_date?->format('j F Y')); ?><br><?php echo e($event->venue_notes); ?></p>
<p><a href="<?php echo e($url); ?>"><?php echo e($notification ? 'View camp information' : 'View invitation and enter the camp'); ?></a></p>
<p><?php echo e($notification ? 'This is a notification copy. Entry and invitation decisions use the primary invitation.' : 'Your place is confirmed through the camp’s normal registration and payment process.'); ?></p>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\camp-invitation.blade.php ENDPATH**/ ?>