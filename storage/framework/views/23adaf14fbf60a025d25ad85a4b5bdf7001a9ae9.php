<!DOCTYPE html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <meta name="contact-post-url" content="<?php echo e(url('/contact-email')); ?>">
  <title>Zanini Renovations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Zanini Renovations — carpentry and renovations in Carleton Place and the Ottawa region.'); ?>">
  <?php if (! empty(trim($__env->yieldContent('meta_keywords')))): ?>
  <meta name="keywords" content="<?php echo $__env->yieldContent('meta_keywords'); ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#024768">
  <meta name="robots" content="<?php echo $__env->yieldContent('meta_robots', 'index,follow'); ?>">
  <link rel="canonical" href="<?php echo $__env->yieldContent('canonical', url()->current()); ?>">
  <link rel="shortcut icon" href="<?php echo e(url('images/favicon.png')); ?>">

  <?php
    $seoTitle = 'Zanini Renovations';
    $seoDesc = trim($__env->yieldContent('meta_description')) ?: 'Zanini Renovations — licensed carpenter Ottawa, Carleton Place, finish & rough carpentry, O.Reg. 332/12 compliant work.';
    $seoDescOg = \Illuminate\Support\Str::limit($seoDesc, 300, '');
    $ogImagePath = ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
    $ogImageAbs = url($ogImagePath);
  ?>
  <meta property="og:locale" content="en_CA">
  <meta property="og:type" content="<?php echo $__env->yieldContent('og_type', 'website'); ?>">
  <meta property="og:site_name" content="Zanini Renovations">
  <meta property="og:title" content="<?php echo e($seoTitle); ?>">
  <meta property="og:description" content="<?php echo e($seoDescOg); ?>">
  <meta property="og:url" content="<?php echo e(url()->current()); ?>">
  <meta property="og:image" content="<?php echo e($ogImageAbs); ?>">
  <meta property="og:image:alt" content="<?php echo e($seoTitle); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
  <meta name="twitter:description" content="<?php echo e($seoDescOg); ?>">
  <meta name="twitter:image" content="<?php echo e($ogImageAbs); ?>">

  <?php if(filled(config('seo.google_site_verification'))): ?>
  <meta name="google-site-verification" content="<?php echo e(config('seo.google_site_verification')); ?>">
  <?php endif; ?>

  <?php if(filled(config('seo.ga4_measurement_id'))): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e(config('seo.ga4_measurement_id')); ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo e(config('seo.ga4_measurement_id')); ?>');
  </script>
  <?php endif; ?>

  <?php echo $__env->yieldPushContent('jsonld'); ?>

  <link href="<?php echo e(url('css/bootstrap.min.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/font-awesome.min.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/owl.carousel.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/owl.theme.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/animate.css')); ?>" rel="stylesheet" type="text/css">
  <link href="<?php echo e(url('css/venobox.css')); ?>" rel="stylesheet" type="text/css">
  
  <link rel="stylesheet" href="<?php echo e(url('css/styles.css')); ?>?v=<?php echo e(@filemtime(public_path('css/styles.css')) ?: time()); ?>" type="text/css">
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
<script src="<?php echo e(url('js/custom-scripts.js')); ?>?v=<?php echo e(@filemtime(public_path('js/custom-scripts.js')) ?: time()); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\site\layouts\public.blade.php ENDPATH**/ ?>