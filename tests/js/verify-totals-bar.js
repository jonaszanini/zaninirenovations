const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'http://127.0.0.1:8000';
const ARTIFACT_DIR = path.resolve('C:/Users/jonas/.gemini/antigravity-ide/brain/0583e04a-e4c3-45aa-8745-b550f608e508/artifacts/totals-bar-screenshots');

if (!fs.existsSync(ARTIFACT_DIR)) {
  fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
}

async function runTests() {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  const appErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const text = msg.text();
      if (!text.includes('plugins/paste') && !text.includes('Failed to load resource')) {
        appErrors.push(text);
      }
    }
  });
  page.on('pageerror', err => {
    appErrors.push(err.toString());
  });

  console.log('Logging in...');
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="email"]', 'admin@zanini.ca');
  await page.type('input[name="password"]', 'password');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle2' })
  ]);
  console.log('Logged in successfully.');

  const viewports = [
    { width: 1280, height: 900, name: 'desktop-1280' },
    { width: 390, height: 844, name: 'mobile-390' }
  ];
  const themes = ['light', 'dark'];
  const testUrls = [
    { name: 'standard', url: '/admin/quotations/standard/create' },
    { name: 'basement', url: '/admin/quotations/basement/create' }
  ];

  let allChecksPassed = true;

  for (const quoteType of testUrls) {
    for (const vp of viewports) {
      for (const theme of themes) {
        console.log(`\nTesting ${quoteType.name} at ${vp.name} [${theme}]...`);
        await page.setViewport({ width: vp.width, height: vp.height });
        await page.goto(`${BASE_URL}${quoteType.url}`, { waitUntil: 'networkidle2' });

        // Set theme
        await page.evaluate((th) => {
          if (th === 'dark') {
            document.documentElement.classList.add('admin-dark');
          } else {
            document.documentElement.classList.remove('admin-dark');
          }
        }, theme);

        await new Promise(r => setTimeout(r, 400));

        // Evaluate layout, spacer, overflow, touch targets
        const evaluation = await page.evaluate((isMobile) => {
          const bar = document.querySelector('.quote-totals-bar');
          const spacer = document.querySelector('.quote-totals-bar-spacer');
          const grandTotal = document.querySelector('[data-quote-grand], [data-totals-grand-display]');

          const hasBar = !!bar && window.getComputedStyle(bar).display !== 'none';
          const barHeight = bar ? bar.offsetHeight : 0;
          const spacerHeight = spacer ? spacer.offsetHeight : 0;

          // Horizontal overflow check
          const docWidth = document.documentElement.clientWidth;
          const scrollWidth = document.documentElement.scrollWidth;
          const hasHorizontalScroll = scrollWidth > docWidth + 1; // 1px threshold for subpixels

          // Touch target sizes
          const buttons = Array.from(document.querySelectorAll('.quote-totals-bar button, .quote-totals-bar a, .quote-totals-bar input[type="submit"]'));
          const smallTargets = [];
          buttons.forEach(btn => {
            const rect = btn.getBoundingClientRect();
            // Visible buttons on mobile must have >= 44px touch target (width or height or padding)
            if (rect.width > 0 && rect.height > 0) {
              if (isMobile && (rect.width < 43.5 || rect.height < 43.5)) {
                smallTargets.push({ tag: btn.tagName, class: btn.className, w: rect.width, h: rect.height });
              }
            }
          });

          return {
            hasBar,
            barHeight,
            spacerHeight,
            hasHorizontalScroll,
            scrollWidth,
            docWidth,
            smallTargets,
            grandTotalText: grandTotal ? grandTotal.textContent.trim() : null
          };
        }, vp.width <= 640);

        const shotPath = path.join(ARTIFACT_DIR, `${quoteType.name}-${vp.name}-${theme}.png`);
        await page.screenshot({ path: shotPath, fullPage: false });
        console.log(`Saved screenshot: ${shotPath}`);

        console.log(`Results: Bar visible: ${evaluation.hasBar}, Bar Height: ${evaluation.barHeight}px, Spacer Height: ${evaluation.spacerHeight}px, Scroll: ${evaluation.scrollWidth}/${evaluation.docWidth}, Grand Total: ${evaluation.grandTotalText}`);

        if (!evaluation.hasBar) {
          console.error(`FAIL: Sticky totals bar not found or visible on ${quoteType.name}`);
          allChecksPassed = false;
        }

        if (evaluation.hasHorizontalScroll) {
          console.error(`FAIL: Horizontal scroll detected: scrollWidth (${evaluation.scrollWidth}) > clientWidth (${evaluation.docWidth})`);
          allChecksPassed = false;
        }

        if (evaluation.smallTargets.length > 0) {
          console.error(`FAIL: Buttons below 44px touch target on mobile:`, evaluation.smallTargets);
          allChecksPassed = false;
        }

        // Test mobile tap expands panel
        if (vp.width <= 640) {
          console.log('Testing mobile flyout panel expansion...');
          await page.click('[data-quote-grand-toggle]');
          await new Promise(r => setTimeout(r, 200));
          const panelVisible = await page.evaluate(() => {
            const panel = document.querySelector('.quote-totals-mobile-panel');
            return panel && (panel.classList.contains('is-open') || window.getComputedStyle(panel).display !== 'none');
          });
          console.log(`Mobile flyout open on tap: ${panelVisible}`);
          if (!panelVisible) {
            console.error('FAIL: Mobile subtotals panel did not open on grand total tap');
            allChecksPassed = false;
          }
          const mobileOpenShot = path.join(ARTIFACT_DIR, `${quoteType.name}-mobile-panel-open-${theme}.png`);
          await page.screenshot({ path: mobileOpenShot });
        }
      }
    }
  }

  // Test live update within 1s without page reload
  console.log('\nTesting live update within 1s without page reload...');
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(`${BASE_URL}/admin/quotations/standard/create`, { waitUntil: 'networkidle2' });

  const initialTotal = await page.evaluate(() => {
    const el = document.querySelector('[data-quote-grand]');
    return el ? el.textContent.trim() : null;
  });
  console.log(`Initial total: ${initialTotal}`);

  // Change input value in standard quote
  const updated = await page.evaluate(async () => {
    // Fill in a quantity and unit price in standard quote
    const priceInput = document.querySelector('input[name*="unit_price"], input[name$="[unit_price]"]');
    const qtyInput = document.querySelector('input[name*="quantity"], input[name$="[quantity]"]');
    if (!priceInput) return { found: false, error: 'No price input found' };

    priceInput.value = '500';
    priceInput.dispatchEvent(new Event('input', { bubbles: true }));
    priceInput.dispatchEvent(new Event('change', { bubbles: true }));

    if (qtyInput) {
      qtyInput.value = '2';
      qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Wait up to 1000ms
    const start = Date.now();
    let newTotal = '';
    while (Date.now() - start < 1000) {
      await new Promise(r => setTimeout(r, 50));
      const el = document.querySelector('[data-quote-grand]');
      if (el && el.textContent.trim() !== '') {
        newTotal = el.textContent.trim();
        if (newTotal !== '$0.00' && (newTotal.includes('500') || newTotal.includes('1,000') || newTotal.includes('1,130') || newTotal.includes('1,243'))) {
          return { found: true, elapsed: Date.now() - start, newTotal };
        }
      }
    }
    return { found: true, elapsed: Date.now() - start, newTotal };
  });

  console.log('Live update test result:', updated);
  if (!updated.found || updated.elapsed > 1000) {
    console.error('FAIL: Live update did not complete within 1000ms');
    allChecksPassed = false;
  }

  // Test send to client modal open
  console.log('\nTesting Send to Client modal...');
  const modalOpened = await page.evaluate(async () => {
    const btn = document.querySelector('[data-quote-send-btn]');
    if (!btn) return { error: 'No send button' };
    btn.click();
    await new Promise(r => setTimeout(r, 200));
    const backdrop = document.querySelector('[data-quote-modal-backdrop]');
    const toInput = document.querySelector('[data-modal-to]');
    const isVisible = backdrop && !backdrop.hasAttribute('hidden') && window.getComputedStyle(backdrop).display !== 'none';
    return {
      backdropExists: !!backdrop,
      isVisible,
      hasToInput: !!toInput
    };
  });
  console.log('Send to Client modal result:', modalOpened);

  if (!modalOpened.isVisible) {
    console.error('FAIL: Send to client modal did not open');
    allChecksPassed = false;
  }

  const modalShot = path.join(ARTIFACT_DIR, 'send-to-client-modal-open.png');
  await page.screenshot({ path: modalShot });
  console.log(`Saved modal screenshot: ${modalShot}`);

  await browser.close();

  console.log('\n--- Application Errors ---');
  console.log(`Count: ${appErrors.length}`);
  if (appErrors.length > 0) {
    appErrors.forEach(err => console.error('App Error:', err));
  }

  if (allChecksPassed && appErrors.length === 0) {
    console.log('\n>>> ALL BROWSER & GEOMETRY VERIFICATIONS PASSED! <<<');
    process.exit(0);
  } else {
    console.error('\n>>> SOME CHECKS FAILED! <<<');
    process.exit(1);
  }
}

runTests().catch(err => {
  console.error('Fatal error in tests:', err);
  process.exit(1);
});
