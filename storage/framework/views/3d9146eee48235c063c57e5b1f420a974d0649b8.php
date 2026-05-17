

<?php $__env->startSection('meta_description', 'Read the Zanini Renovations Privacy Policy for how we collect, use, disclose, and protect personal information under PIPEDA.'); ?>
<?php $__env->startSection('meta_keywords', config('seo.lsi_verbatim')); ?>
<?php $__env->startSection('canonical', url('/privacy-policy')); ?>

<?php $__env->startPush('jsonld'); ?>
<script type="application/ld+json"><?php echo \App\Support\SeoStructuredData::toJsonLd(array_merge(['@context' => 'https://schema.org'], \App\Support\SeoStructuredData::breadcrumb([
    ['name' => 'Home', 'url' => url('/')],
    ['name' => 'Privacy Policy', 'url' => url('/privacy-policy')],
]))); ?></script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  <?php
    $effectiveDate = 'May 6, 2026';
    $businessEmail = (string) config('mail.from.address');
  ?>

  <main id="privacy-policy" class="page-warranty">
    <section class="section-content-block section-primary-bg page-warranty-hero">
      <div class="container">
        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-light-color">Zanini Renovations</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h1 class="subheading-alt-style text-light-color page-warranty-h1">Privacy Policy</h1>
            <p class="page-warranty-hero-lead text-light-color">
              How we collect, use, and protect personal information in accordance with PIPEDA.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="section-content-block section-secondary-bg home-contact-palette page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-10 col-md-offset-1 col-sm-12 col-xs-12 warranty-prose">
            <p class="warranty-lead" style="margin-bottom: 12px;">
              <strong>Effective Date:</strong> <?php echo e($effectiveDate); ?>

            </p>
            <p>
              At Zanini Renovations, we are committed to protecting the privacy and security of our clients&rsquo; personal information.
              This Privacy Policy outlines how we collect, use, and safeguard your data in accordance with the Personal Information Protection and Electronic Documents Act (PIPEDA).
            </p>

            <h2 class="contact-title">1. Information We Collect</h2>
            <p>
              We may collect personal information from you when you request a quote, book a service, or contact us through our website.
              This includes:
            </p>
            <ul class="warranty-list">
              <li>Contact information such as name, email address, and phone number.</li>
              <li>Physical address for project sites and service delivery.</li>
              <li>Project-specific details and photos.</li>
              <li>Payment and billing information.</li>
            </ul>

            <h2 class="contact-title">2. How We Use Your Information</h2>
            <p>Your information is used strictly for business purposes, including:</p>
            <ul class="warranty-list">
              <li>Providing accurate project estimates and quotes.</li>
              <li>Scheduling and performing carpentry and renovation services.</li>
              <li>Communicating project updates and responding to inquiries.</li>
              <li>Processing payments and maintaining financial records.</li>
              <li>Improving our website and service offerings.</li>
            </ul>

            <h2 class="contact-title">3. Consent</h2>
            <p>
              By providing your personal information, you consent to its collection and use as described in this policy.
              You may withdraw your consent at any time, though this may limit our ability to provide certain services.
            </p>

            <h2 class="contact-title">4. Disclosure to Third Parties</h2>
            <p>
              We do not sell or rent your personal information. We may share necessary details with trusted third parties only when required to complete your project, such as:
            </p>
            <ul class="warranty-list">
              <li>Subcontractors or specialized tradespeople.</li>
              <li>Permit offices or municipal inspectors.</li>
              <li>Payment processing and accounting software providers.</li>
            </ul>

            <h2 class="contact-title">5. Data Security</h2>
            <p>
              We implement appropriate physical, electronic, and managerial procedures to protect your data.
              This includes secure digital storage and restricted access to personal files.
            </p>

            <h2 class="contact-title">6. Access to Your Information</h2>
            <p>
              You have the right to access the personal information we hold about you and request corrections if necessary.
              To make a request, please contact us directly.
            </p>

            <h2 class="contact-title">7. Contact Us</h2>
            <p>
              If you have questions regarding this Privacy Policy, please contact:
              <br>Zanini Renovations
              <br>Carleton Place, Ontario
              <br>Email: <a href="mailto:<?php echo e($businessEmail); ?>"><?php echo e($businessEmail); ?></a>
            </p>

          </div>
        </div>
      </div>
    </section>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layouts.public', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\site\privacy-policy.blade.php ENDPATH**/ ?>