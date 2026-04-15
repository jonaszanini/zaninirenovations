<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <meta name="contact-post-url" content="<?php echo e(url('/contact-email')); ?>">
  <title><?php echo $__env->yieldContent('title', 'Zanini Renovations'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Zanini Renovations — carpentry and renovations in Carleton Place and the Ottawa region.'); ?>">
  <meta name="theme-color" content="#024768">
  <link rel="shortcut icon" href="<?php echo e(url('images/favicon.png')); ?>">

  <link href="<?php echo e(url('css/bootstrap.min.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/font-awesome.min.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/owl.carousel.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/owl.theme.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/animate.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/venobox.css')); ?>" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="<?php echo e(url('css/styles.css')); ?>" type="text/css">
  <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body>

<div id="preloader">
  <span class="margin-bottom"><img src="<?php echo e(url('images/loader.gif')); ?>" alt="Loading......"></span>
</div>

<?php echo $__env->make('site.partials.public-header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<?php echo $__env->yieldContent('content'); ?>

<?php echo $__env->make('site.partials.public-footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<a id="backTop" role="button" aria-label="Back to top">Back To Top</a>

<script src="<?php echo e(url('js/jquery.min.js')); ?>"></script>
<script src="<?php echo e(url('js/bootstrap.min.js')); ?>"></script>
<script src="<?php echo e(url('js/wow.min.js')); ?>"></script>
<script src="<?php echo e(url('js/jquery.backTop.min.js')); ?>"></script>
<script src="<?php echo e(url('js/waypoints.min.js')); ?>"></script>
<script src="<?php echo e(url('js/waypoints-sticky.min.js')); ?>"></script>
<script src="<?php echo e(url('js/owl.carousel.min.js')); ?>"></script>
<script src="<?php echo e(url('js/jquery.stellar.min.js')); ?>"></script>
<script src="<?php echo e(url('js/jquery.counterup.min.js')); ?>"></script>
<script src="<?php echo e(url('js/venobox.min.js')); ?>"></script>
<script src="<?php echo e(url('js/custom-scripts.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\site\layouts\public.blade.php ENDPATH**/ ?>