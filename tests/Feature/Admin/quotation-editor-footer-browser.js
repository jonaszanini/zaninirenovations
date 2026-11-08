'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_FOOTER_EMAIL;
const password = process.env.QH_FOOTER_PASSWORD;
const base = process.env.QH_FOOTER_BASE;
const outPath = process.env.QH_FOOTER_OUT;
const shotsDir = process.env.QH_FOOTER_SHOTS || path.join(process.cwd(), 'storage/app/qh-footer-shots');

const pages = [
  { key: 'basement', href: '/admin/quotations/basement/create', painting: false },
  { key: 'millwork', href: '/admin/quotations/millwork/create', painting: false },
  { key: 'standard', href: '/admin/quotations/standard/create', painting: false },
  { key: 'fence', href: '/admin/quotations/fence/create', painting: false },
  { key: 'paving', href: '/admin/quotations/paving/create', painting: false },
  { key: 'painting', href: '/admin/quotations/painting/create', painting: true },
];

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

async function shotWithRetry(page, dest, fullPage) {
  let lastErr = null;
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      await page.screenshot({ path: dest, fullPage: !!fullPage });
      return dest;
    } catch (err) {
      lastErr = err;
      await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
    }
  }
  throw lastErr;
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(20000);
  page.setDefaultTimeout(20000);

  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (page.url().includes('/admin/login')) {
    await page.waitForSelector('input[name="email"]');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
  }

  const report = {};
  for (const spec of pages) {
    report[spec.key] = {};
    for (const width of [1280, 390]) {
      await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
      await page.goto(base + spec.href, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-qh-header]');
      await new Promise((r) => setTimeout(r, 150));
      const viewportShot = path.join(shotsDir, spec.key + '-' + width + '-viewport.png');
      const fullShot = path.join(shotsDir, spec.key + '-' + width + '-full.png');
      await shotWithRetry(page, viewportShot, false);
      await shotWithRetry(page, fullShot, true);
      const row = await page.evaluate(async () => {
        const visibleText = (el) => {
          if (!el) return '';
          const parts = [];
          const walk = (node) => {
            if (node.nodeType === 3) {
              parts.push(node.textContent || '');
              return;
            }
            if (node.nodeType !== 1) return;
            if (node.getAttribute('aria-hidden') === 'true') return;
            const cs = getComputedStyle(node);
            if (cs.display === 'none' || cs.visibility === 'hidden') return;
            [...node.childNodes].forEach(walk);
          };
          walk(el);
          return parts.join('').replace(/\s+/g, ' ').trim();
        };
        const accName = (el) => {
          if (!el) return '';
          const labelled = (el.getAttribute('aria-labelledby') || '')
            .split(/\s+/)
            .filter(Boolean)
            .map((id) => {
              const n = document.getElementById(id);
              return n ? visibleText(n) : '';
            })
            .join(' ')
            .trim();
          const aria = (el.getAttribute('aria-label') || '').trim();
          return labelled || aria || visibleText(el);
        };
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
        };
        const overlap = (a, b) => !!(a && b && a.right > b.left + 1 && b.right > a.left + 1 && a.bottom > b.top + 1 && b.bottom > a.top + 1);

        const footer = document.querySelector('[data-qh-footer]');
        const save = footer && footer.querySelector('[data-qh-footer-save]');
        const amount = footer && footer.querySelector('[data-qh-footer-amount]');
        const headerSave = document.querySelector('[data-qh-header] .qh-actions__save');
        const headerDraft = document.querySelector('[data-qh-header] .qh-actions__draft');
        const scripts = [...document.scripts].map((s) => s.src || '').join('\n');
        const vh = window.innerHeight;
        const footerBox = footer ? footer.getBoundingClientRect() : null;
        const saveBox = save ? save.getBoundingClientRect() : null;
        const amountBox = amount ? amount.getBoundingClientRect() : null;
        const saveLabelEl = save && save.querySelector('.qh-sticky-footer__save-label');
        let liveBefore = amount ? String(amount.textContent || '') : '';
        let liveAfter = liveBefore;
        if (amount) {
          window.__quoteGrandTotal = 1234.56;
          document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: 1234.56 } }));
          liveAfter = String(amount.textContent || '');
        }
        let headerClicks = 0;
        let draftClicks = 0;
        let nativeFooterSubmit = 0;
        if (save && headerSave) {
          const formEl = save.form || save.closest('form');
          const onHeader = function (e) {
            e.preventDefault();
            e.stopPropagation();
            headerClicks += 1;
          };
          const onFormSubmit = function (e) {
            e.preventDefault();
            nativeFooterSubmit += 1;
          };
          headerSave.addEventListener('click', onHeader, true);
          if (formEl) formEl.addEventListener('submit', onFormSubmit, true);
          save.click();
          headerSave.removeEventListener('click', onHeader, true);
          if (formEl) formEl.removeEventListener('submit', onFormSubmit, true);
        }
        if (headerDraft && headerSave) {
          const onHeader = function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            headerClicks += 1;
          };
          const onDraft = function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            draftClicks += 1;
          };
          headerSave.addEventListener('click', onHeader, true);
          headerDraft.addEventListener('click', onDraft, true);
          headerDraft.click();
          headerSave.removeEventListener('click', onHeader, true);
          headerDraft.removeEventListener('click', onDraft, true);
        }

        const amtLabel = document.querySelector('.q-ac-row--with-category .q-ac-row__amount label');
        const typeLabel = document.querySelector('.q-ac-row--with-category .q-ac-row__category label');
        const amtBox = box(amtLabel);
        const typeBox = box(typeLabel);
        const acCard = document.querySelector('[data-ac-card], .q-additional-costs-card');

        const status = document.getElementById('br-step-status');
        const overviewNum = document.querySelector('[data-br-step="overview"] .br-acc__num');
        const scopeNum = document.querySelector('[data-br-step="client_scope"] .br-acc__num');
        return {
          hasFooter: !!footer,
          footerCount: document.querySelectorAll('[data-qh-footer]').length,
          hasSave: !!save,
          hasAmount: !!amount,
          hasHeaderSave: !!headerSave,
          hasHeaderDraft: !!headerDraft,
          footerJs: scripts.indexOf('admin-quotation-editor-footer.js') !== -1,
          footerBottom: footerBox ? Math.round(footerBox.bottom) : null,
          viewportH: vh,
          saveInView: !!(saveBox && saveBox.width > 2 && saveBox.top >= -1 && saveBox.bottom <= vh + 1),
          amountInView: !!(amountBox && amountBox.width > 2 && amountBox.top >= -1 && amountBox.bottom <= vh + 1),
          pinned: !!(footerBox && Math.abs(footerBox.bottom - vh) <= 2),
          liveBefore,
          liveAfter,
          headerClicks,
          draftClicks,
          nativeFooterSubmit,
          headerSaveVisible: visibleText(headerSave),
          headerSaveAccName: accName(headerSave),
          headerSaveLabel: headerSave ? String(headerSave.textContent || '').replace(/\s+/g, ' ').trim() : '',
          headerDraftVisible: visibleText(headerDraft),
          headerDraftAccName: accName(headerDraft),
          headerDraftLabel: headerDraft ? String(headerDraft.textContent || '').replace(/\s+/g, ' ').trim() : '',
          headerSaveIntent: headerSave ? headerSave.getAttribute('data-qh-save-intent') : null,
          headerDraftIntent: headerDraft ? headerDraft.getAttribute('data-qh-save-intent') : null,
          footerSaveIntent: save ? save.getAttribute('data-qh-save-intent') : null,
          footerAria: save ? save.getAttribute('aria-label') : null,
          footerVisible: visibleText(save),
          footerAccName: accName(save),
          footerLabel: saveLabelEl ? String(saveLabelEl.textContent || '').trim() : '',
          stepStatus: status ? String(status.textContent || '').trim() : '',
          overviewNum: overviewNum ? String(overviewNum.textContent || '').trim() : '',
          scopeNum: scopeNum ? String(scopeNum.textContent || '').trim() : '',
          amountType: {
            hasRow: !!(amtLabel && typeLabel),
            amountText: amtLabel ? String(amtLabel.textContent || '').trim() : '',
            typeText: typeLabel ? String(typeLabel.textContent || '').trim() : '',
            overlap: overlap(amtBox, typeBox),
            amountBox: amtBox,
            typeBox: typeBox,
            cardWidth: acCard ? Math.round(acCard.getBoundingClientRect().width) : null,
          },
        };
      });
      if (spec.key === 'paving' && row.amountType && row.amountType.hasRow) {
        const costs = await page.$('.q-additional-costs-card, [data-ac-card]');
        if (costs) {
          await costs.evaluate((el) => el.scrollIntoView({ block: 'center' }));
          await new Promise((r) => setTimeout(r, 80));
          const costsShot = path.join(shotsDir, spec.key + '-' + width + '-additional-costs.png');
          await shotWithRetry(page, costsShot, false);
          row.costsShot = costsShot;
        }
      }
      if (spec.key === 'basement') {
        await page.evaluate(() => {
          const area = document.getElementById('br-area');
          if (!area) return;
          area.value = '800';
          area.dispatchEvent(new Event('input', { bubbles: true }));
          area.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.click('#br-next');
        await new Promise((r) => setTimeout(r, 250));
        const afterNext = await page.evaluate(() => {
          const status = document.getElementById('br-step-status');
          const next = document.getElementById('br-next');
          const active = document.querySelector('.br-acc.is-active');
          const vis = (el) => {
            if (!el) return '';
            return String(el.textContent || '').replace(/\s+/g, ' ').trim();
          };
          return {
            stepStatus: status ? String(status.textContent || '').trim() : '',
            nextLabel: vis(next),
            nextAccName: next ? ((next.getAttribute('aria-label') || '').trim() || vis(next)) : '',
            activeStep: active ? active.getAttribute('data-br-step') : '',
          };
        });
        row.afterNext = afterNext;
      }
      row.shot = fullShot;
      row.viewportShot = viewportShot;
      report[spec.key][String(width)] = row;
    }
  }

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
