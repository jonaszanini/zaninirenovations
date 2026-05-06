

<?php $__env->startSection('meta_description', 'Read the Zanini Renovations Terms and Conditions for services, quotes, payments, client responsibilities, liability, warranty, and governing law.'); ?>
<?php $__env->startSection('meta_keywords', config('seo.lsi_verbatim')); ?>
<?php $__env->startSection('canonical', url('/terms-and-conditions')); ?>

<?php $__env->startPush('jsonld'); ?>
<script type="application/ld+json"><?php echo \App\Support\SeoStructuredData::toJsonLd(array_merge(['@context' => 'https://schema.org'], \App\Support\SeoStructuredData::breadcrumb([
    ['name' => 'Home', 'url' => url('/')],
    ['name' => 'Terms and Conditions', 'url' => url('/terms-and-conditions')],
]))); ?></script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  <?php
    $effectiveDate = 'May 6, 2026';
    $businessEmail = (string) config('mail.from.address');
  ?>

  <main id="terms-and-conditions" class="page-warranty">
    <section class="section-content-block section-primary-bg page-warranty-hero">
      <div class="container">
        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-light-color">Zanini Renovations</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h1 class="subheading-alt-style text-light-color page-warranty-h1">Terms and Conditions</h1>
            <p class="page-warranty-hero-lead text-light-color">
              Terms governing use of our website and engagement of our renovation services.
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
              Welcome to the Zanini Renovations website. By accessing this website or engaging our services, you agree to comply with and be bound by the following terms and conditions.
            </p>

            <h2 class="contact-title">1. Services Provided</h2>
            <p>
              Zanini Renovations provides professional carpentry and renovation services, including but not limited to custom cabinetry, framing, flooring, and interior/exterior renovations.
              All work is performed in accordance with the Ontario Building Code (O.Reg. 332/12) and local municipal bylaws.
            </p>

            <h2 class="contact-title">2. Quotes and Estimates</h2>
            <ul class="warranty-list">
              <li>All quotes provided are valid for a period of 30 days from the date of issue, unless otherwise stated.</li>
              <li>Quotes are based on a visible inspection. Any unforeseen structural issues, mold, or plumbing/electrical deficiencies discovered during demolition may result in additional costs, which will be documented via a Change Order.</li>
            </ul>

            <h2 class="contact-title">3. Payment Terms</h2>
            <ul class="warranty-list">
              <li>All prices and transactions are processed in CAD$.</li>
              <li>A deposit is required to secure a start date and procure materials.</li>
              <li>Progress payments and final balances are due according to the schedule outlined in your specific project contract.</li>
              <li>Late payments may be subject to interest charges as permitted under Ontario law.</li>
            </ul>

            <h2 class="contact-title">4. Client Responsibilities</h2>
            <ul class="warranty-list">
              <li><strong>Access:</strong> The client must provide clear and safe access to the work site during agreed-upon hours.</li>
              <li><strong>Permits:</strong> Unless explicitly stated in the project contract, the client is responsible for obtaining and paying for all necessary municipal building permits.</li>
              <li><strong>Work Area:</strong> The client is responsible for removing personal items and furniture from the work area unless otherwise agreed upon.</li>
            </ul>

            <h2 class="contact-title">5. Intellectual Property</h2>
            <p>
              All content on this website, including text, logos, and project photographs, is the exclusive property of Zanini Renovations.
              Unauthorized use or reproduction of these materials for commercial purposes is strictly prohibited.
            </p>

            <h2 class="contact-title">6. Limitation of Liability</h2>
            <p>
              Zanini Renovations maintains commercial general liability insurance. However, Zanini Renovations shall not be held liable for:
            </p>
            <ul class="warranty-list">
              <li>Indirect, incidental, or consequential damages arising from the use of this website.</li>
              <li>Delays caused by weather, supply chain disruptions, or third-party inspections.</li>
              <li>Pre-existing conditions of the property that were not disclosed or visible during the initial estimate.</li>
            </ul>

            <h2 class="contact-title">7. Warranty</h2>
            <p>
              We provide a warranty on workmanship for a period defined in your individual service contract. This warranty does not cover:
            </p>
            <ul class="warranty-list">
              <li>Normal wear and tear.</li>
              <li>Natural characteristics of wood (e.g., grain patterns, minor expansion/contraction).</li>
              <li>Materials manufactured by third parties, which are covered by their respective manufacturer warranties.</li>
            </ul>

            <h2 class="contact-title">8. Governing Law</h2>
            <p>
              These terms are governed by and construed in accordance with the laws of the Province of Ontario and the federal laws of Canada.
            </p>

            <h2 class="contact-title">9. Contact Information</h2>
            <p>
              For questions regarding these Terms and Conditions, please contact:
              <br>Zanini Renovations
              <br>Carleton Place, Ontario
              <br><a href="mailto:<?php echo e($businessEmail); ?>"><?php echo e($businessEmail); ?></a>
            </p>
          </div>
        </div>
      </div>
    </section>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layouts.public', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/site/terms-and-conditions.blade.php ENDPATH**/ ?>