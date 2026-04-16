

<?php $__env->startSection('title', 'Kitchen Refacing Kanata, Ottawa | O.Reg. 332/12 | Zanini Renovations'); ?>
<?php $__env->startSection('meta_description', 'Zanini Renovations offers expert Kitchen Cabinet Refacing Ottawa in Kanata. Specialized in high-end finish carpentry and OBC-compliant framing. Get your CAD$ quote today in Kanata.'); ?>
<?php $__env->startSection('meta_keywords', config('seo.lsi_verbatim')); ?>
<?php $__env->startSection('canonical', url('/services/custom-kitchen-remodel')); ?>

<?php $__env->startPush('jsonld'); ?>
<script type="application/ld+json"><?php echo \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::kitchenGraph()); ?></script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  <?php
    $home = rtrim(url('/'), '/');
  ?>

  <main id="custom-kitchen-remodel" class="page-warranty page-service-kitchen">

    <section class="section-content-block section-primary-bg page-warranty-hero">
      <div class="container">
        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-light-color">Zanini Renovations</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h1 class="subheading-alt-style text-light-color page-warranty-h1">
              Custom Kitchen Remodel
            </h1>
            <p class="page-warranty-hero-lead text-light-color">
              Ottawa &amp; Carleton Place — finish carpentry, custom millwork, and O.Reg. 332/12 compliant structural work.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="section-content-block section-secondary-bg home-contact-palette page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-10 col-md-offset-1 col-sm-12 col-xs-12 warranty-prose">

            <p class="warranty-lead">
              Kitchen remodels, Kitchen Cabinet Refacing Ottawa, flush-mount cabinetry, and Custom Millwork Ottawa for homeowners from Kanata to Orléans — Red Seal Certified Carpenter, WSIB Clearance Certificate, Part 9 Housing &amp; Small Buildings.
            </p>

            <p class="text-center margin-top-32 margin-bottom-0 warranty-cta-buttons">
              <a href="<?php echo e($home); ?>#contact-location" class="btn btn-theme btn-square nav_menu">Get in touch</a>
            </p>

          </div>
        </div>
      </div>
    </section>

  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layouts.public', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\site\service-kitchen.blade.php ENDPATH**/ ?>