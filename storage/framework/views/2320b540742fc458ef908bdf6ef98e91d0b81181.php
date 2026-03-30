<?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $photos = $project->photos->sortBy('sort_order');
    $galleryUrls = $photos->isNotEmpty()
      ? $photos->map(fn ($p) => $p->url())->values()->all()
      : ['/images/gallery_1.jpg'];
    $cover = $photos->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
  ?>
  <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">
    <div class="portfolio-container">
      <a
        href="#"
        class="portfolio-light-box text-center js-project-gallery"
        data-project-title="<?php echo e(e($project->title)); ?>"
        data-gallery='<?php echo json_encode($galleryUrls, 15, 512) ?>'
      >
        <figure class="portfolio-img">
          <img src="<?php echo e($coverSrc); ?>" alt="<?php echo e(e($project->title)); ?>">
        </figure>
        <div class="portfolio-info-box">
          <h4><?php echo e($project->title); ?></h4>
          <span><?php echo e($project->categoryLabel()); ?></span>
        </div>
      </a>
    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <div class="col-md-12 col-sm-12 text-center" style="padding: 3rem 1rem;">
    <p class="text-dark-color" style="margin:0;font-size:1.1rem;">New projects will appear here soon.</p>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/site/partials/latest-projects.blade.php ENDPATH**/ ?>