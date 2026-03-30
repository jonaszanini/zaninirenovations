<?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $cover = $project->photos->sortBy('sort_order')->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
  ?>
  <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">
    <div class="portfolio-container">
      <a class="portfolio-light-box text-center venobox" href="<?php echo e($coverSrc); ?>" title="<?php echo e($project->title); ?>">
        <figure class="portfolio-img">
          <img src="<?php echo e($coverSrc); ?>" alt="<?php echo e($project->title); ?>">
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