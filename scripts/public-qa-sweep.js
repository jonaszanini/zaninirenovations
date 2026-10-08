/**
 * Dev-only public-site interaction QA. Not used in CI.
 * Requires local Chrome + puppeteer-core, and a running `php artisan serve`.
 *
 *   NODE_PATH=/path/to/node_modules node scripts/public-qa-sweep.js
 *
 * Does not send real contact emails: POST /contact-email is aborted.
 */
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');
const http = require('http');

const BASE = process.env.QA_BASE || 'http://127.0.0.1:8000';
const OUT = process.env.QA_OUT || '/opt/cursor/artifacts/screenshots';
const CHROME = process.env.CHROME || '/usr/local/bin/google-chrome';
const REPORT = path.join(OUT, 'qa-report.json');

const PAGES = [
  '/',
  '/services',
  '/services/decks',
  '/services/fences-gates',
  '/services/basement-finishing',
  '/services/finish-carpentry-trim',
  '/services/custom-built-ins-closets',
  '/services/staircases',
  '/services/flooring-installation',
  '/services/drywall',
  '/services/kitchen-remodel',
  '/service-areas',
  '/service-areas/carleton-place',
  '/service-areas/kanata',
  '/service-areas/barrhaven',
  '/about',
  '/portfolio',
  '/portfolio/floating-deck-with-gravel-around',
  '/blog',
  '/blog/qa-deck-care-tips',
  '/reviews',
  '/contact',
  '/warranty',
];

const VIEWPORTS = [
  { name: '390', width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  { name: '1280', width: 1280, height: 900, isMobile: false, hasTouch: false, deviceScaleFactor: 1 },
];

const SCHEMES = ['light', 'dark'];

function delay(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function slug(p) {
  return (p === '/' ? 'home' : p.replace(/^\//, '').replace(/\//g, '-'));
}

function luminance(rgb) {
  const m = String(rgb).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (!m) return 1;
  const [r, g, b] = m.slice(1).map((n) => Number(n) / 255);
  const lin = (c) => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

async function fetchStatus(url) {
  return new Promise((resolve) => {
    const req = http.get(url, { timeout: 8000 }, (res) => {
      res.resume();
      resolve(res.statusCode);
    });
    req.on('error', () => resolve(0));
    req.on('timeout', () => {
      req.destroy();
      resolve(0);
    });
  });
}

async function newPage(browser, vp, scheme) {
  const page = await browser.newPage();
  await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: scheme }]);
  await page.setViewport(vp);
  const consoleErrors = [];
  const pageErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => pageErrors.push(String(err.message || err)));
  await page.setRequestInterception(true);
  page.on('request', (req) => {
    if (req.method() === 'POST' && req.url().includes('/contact-email')) {
      req.abort('blockedbyclient');
      return;
    }
    req.continue();
  });
  page._qa = { consoleErrors, pageErrors };
  return page;
}

async function collectStatic(page, urlPath) {
  return page.evaluate((pathName) => {
    const issues = [];
    const doc = document.documentElement;
    const overflowX = doc.scrollWidth - doc.clientWidth;
    if (overflowX > 2) {
      issues.push({ kind: 'overflow-x', detail: 'scrollWidth-clientWidth=' + overflowX });
    }

    const bodyBg = getComputedStyle(document.body).backgroundColor;
    const htmlBg = getComputedStyle(doc).backgroundColor;
    const htmlCs = getComputedStyle(doc).colorScheme || '';
    issues.push({ kind: 'theme-sample', detail: { bodyBg, htmlBg, colorScheme: htmlCs, path: pathName } });

    const brokenImgs = [...document.images]
      .filter((img) => {
        if (!img.getClientRects().length) return false;
        if (img.loading === 'lazy' && !img.complete) return false;
        return img.complete && img.naturalWidth === 0 && (img.currentSrc || img.src);
      })
      .map((img) => img.currentSrc || img.src)
      .slice(0, 12);
    brokenImgs.forEach((src) => issues.push({ kind: 'broken-image', detail: src }));

    const links = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href') || '');
    const tel = links.filter((h) => h.startsWith('tel:'));
    if (!tel.length && pathName !== '/blog/qa-deck-care-tips') {
      // some inner pages still have footer tel — always expect footer
    }

    const interactive = [...document.querySelectorAll('a, button, input, select, textarea, summary, [role="button"]')];
    const small = [];
    for (const el of interactive) {
      const s = getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden') continue;
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) continue;
      if (r.width + 0.5 < 40 || r.height + 0.5 < 40) {
        const cls = (typeof el.className === 'string' ? el.className : '').slice(0, 80);
        small.push({
          tag: el.tagName.toLowerCase(),
          cls,
          w: Math.round(r.width),
          h: Math.round(r.height),
          text: (el.getAttribute('aria-label') || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40),
        });
      }
    }
    const uniq = [];
    const seen = new Set();
    for (const t of small) {
      const k = t.tag + '|' + t.cls + '|' + t.w + 'x' + t.h;
      if (seen.has(k)) continue;
      seen.add(k);
      uniq.push(t);
    }
    if (uniq.length) issues.push({ kind: 'small-tap-targets', detail: uniq.slice(0, 15) });

    return {
      issues,
      hrefs: links,
      hasHamburger: !!document.querySelector('.main-header-2 .navbar-toggle'),
      hasServices: !!document.querySelector('#main-nav-collapse a.dropdown-toggle.nav_menu'),
      hasFaqAccordion: !!document.querySelector('#home-faq-accordion, .sa-faq__item, details.sa-faq__item'),
      hasGallery: !!document.querySelector('.js-project-gallery, .portfolio-light-box, .owl-carousel'),
      hasContactForm: !!document.querySelector('#contact-form'),
      title: document.title,
    };
  }, urlPath);
}

async function openHamburger(page) {
  const visible = await page.$eval('.main-header-2 .navbar-toggle', (el) => {
    const nodes = document.querySelectorAll('.main-header-2 .navbar-toggle');
    for (const n of nodes) {
      const s = getComputedStyle(n);
      if (s.display !== 'none' && n.offsetWidth > 0) return true;
    }
    return false;
  }).catch(() => false);
  if (!visible) return false;
  await page.evaluate(() => {
    const nodes = [...document.querySelectorAll('.main-header-2 .navbar-toggle')];
    const t = nodes.find((n) => getComputedStyle(n).display !== 'none' && n.offsetWidth > 0);
    if (t) t.click();
  });
  await page.waitForFunction(() => {
    const el = document.querySelector('#main-nav-collapse');
    return el && el.classList.contains('in');
  }, { timeout: 6000 });
  await delay(250);
  return true;
}

async function interactMenu(page, vpName) {
  const issues = [];
  try {
    if (vpName === '390') {
      const opened = await openHamburger(page);
      if (!opened) {
        issues.push({ kind: 'menu', detail: 'hamburger did not open' });
        return issues;
      }
      await page.click('#main-nav-collapse a.dropdown-toggle.nav_menu[data-toggle="dropdown"]');
      await delay(300);
      const sub = await page.evaluate(() => {
        const dd = document.querySelector('#main-nav-collapse li.dropdown');
        const menu = dd && dd.querySelector('.dropdown-menu');
        const collapse = document.querySelector('#main-nav-collapse');
        return {
          collapseOpen: !!(collapse && collapse.classList.contains('in')),
          dropdownOpen: !!(dd && dd.classList.contains('open')),
          menuH: menu ? menu.getBoundingClientRect().height : 0,
          itemColor: menu && menu.querySelector('a') ? getComputedStyle(menu.querySelector('a')).color : null,
        };
      });
      if (!sub.collapseOpen || !sub.dropdownOpen || sub.menuH < 20) {
        issues.push({ kind: 'menu-services', detail: sub });
      }
    } else {
      await page.click('#main-nav-collapse a.dropdown-toggle.nav_menu[data-toggle="dropdown"]');
      await delay(250);
      const open = await page.evaluate(() => {
        const dd = document.querySelector('#main-nav-collapse li.dropdown');
        const menu = dd && dd.querySelector('.dropdown-menu');
        return {
          open: !!(dd && dd.classList.contains('open')),
          h: menu ? menu.getBoundingClientRect().height : 0,
        };
      });
      if (!open.open || open.h < 20) issues.push({ kind: 'desktop-dropdown', detail: open });
    }
  } catch (e) {
    issues.push({ kind: 'menu-exception', detail: String(e.message || e) });
  }
  return issues;
}

async function interactFaq(page) {
  const issues = [];
  try {
    const details = await page.$('details.sa-faq__item, .sa-faq details');
    if (details) {
      await details.evaluate((el) => el.click());
      await delay(200);
      const open = await details.evaluate((el) => el.open || el.hasAttribute('open'));
      if (!open) issues.push({ kind: 'faq-details', detail: 'did not open' });
      return issues;
    }
    const acc = await page.$('#home-faq-accordion a.accordion-toggle, #home-faq-accordion [data-toggle="collapse"]');
    if (acc) {
      await acc.evaluate((el) => el.click());
      await delay(400);
      const shown = await page.evaluate(() => {
        const pan = document.querySelector('#home-faq-accordion .panel-collapse.in, #home-faq-accordion .panel-collapse.collapsing');
        return !!pan;
      });
      if (!shown) issues.push({ kind: 'faq-accordion', detail: 'home FAQ did not expand' });
    }
  } catch (e) {
    issues.push({ kind: 'faq-exception', detail: String(e.message || e) });
  }
  return issues;
}

async function interactGallery(page) {
  const issues = [];
  try {
    const item = await page.$('.js-project-gallery');
    if (!item) return issues;
    await item.evaluate((el) => el.click());
    try {
      await page.waitForFunction(() => {
        const vis = document.querySelector('.project-gallery-modal');
        if (!vis) return false;
        const display = getComputedStyle(vis).display;
        return vis.classList.contains('in') || display === 'block';
      }, { timeout: 4000 });
    } catch (_) {
      const open = await page.evaluate(() => {
        const vis = document.querySelector('.project-gallery-modal');
        return {
          hasIn: !!(vis && vis.classList.contains('in')),
          display: vis ? getComputedStyle(vis).display : 'none',
          ariaHidden: vis ? vis.getAttribute('aria-hidden') : null,
        };
      });
      issues.push({ kind: 'gallery', detail: open });
      return issues;
    }
    const close = await page.$('.project-gallery-modal .close, .project-gallery-close, [data-dismiss="modal"]');
    if (close) await close.evaluate((el) => el.click());
    await delay(200);
  } catch (e) {
    issues.push({ kind: 'gallery-exception', detail: String(e.message || e) });
  }
  return issues;
}

async function interactContact(page) {
  const issues = [];
  try {
    const form = await page.$('#contact-form');
    if (!form) return issues;
    await page.evaluate(() => {
      const f = document.querySelector('#contact-form');
      if (f && typeof f.requestSubmit === 'function') f.requestSubmit();
      else if (f) f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });
    await delay(250);
    const emptyState = await page.evaluate(() => {
      const errs = [...document.querySelectorAll('#contact-form span.error')].map((e) => e.textContent.trim());
      return errs;
    });
    if (!emptyState.length) {
      issues.push({ kind: 'contact-validation', detail: 'empty submit produced no error spans' });
    }
    await page.evaluate(() => {
      const set = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.value = v;
      };
      set('user_name', 'QA Robot');
      set('user_email', 'not-an-email');
      set('location_city', 'Carleton Place');
      set('email_subject', 'QA');
      set('email_message', 'QA message');
    });
    await page.evaluate(() => {
      const f = document.querySelector('#contact-form');
      if (f && typeof f.requestSubmit === 'function') f.requestSubmit();
      else if (f) f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });
    await delay(250);
    const emailErr = await page.evaluate(() => {
      const el = document.getElementById('user_email');
      const span = el && el.nextElementSibling && el.nextElementSibling.classList.contains('error')
        ? el.nextElementSibling.textContent
        : '';
      return span;
    });
    if (!emailErr) issues.push({ kind: 'contact-email-validation', detail: 'invalid email not flagged' });
  } catch (e) {
    issues.push({ kind: 'contact-exception', detail: String(e.message || e) });
  }
  return issues;
}

function isInternal(href) {
  if (!href) return false;
  if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return false;
  if (href.startsWith('http://') || href.startsWith('https://')) {
    try {
      const u = new URL(href);
      return u.hostname === '127.0.0.1' || u.hostname === 'localhost';
    } catch {
      return false;
    }
  }
  return href.startsWith('/');
}

function normalizeInternal(href) {
  if (href.startsWith('http')) {
    const u = new URL(href);
    return u.pathname + u.search;
  }
  return href.split('#')[0] || '/';
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const findings = [];
  const allHrefs = new Set();
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
  });

  const deepPaths = new Set(['/', '/contact', '/services/fences-gates', '/service-areas/carleton-place', '/portfolio/floating-deck-with-gravel-around', '/blog', '/reviews', '/warranty', '/about']);

  try {
    for (const vp of VIEWPORTS) {
      for (const scheme of SCHEMES) {
        for (const urlPath of PAGES) {
          const page = await newPage(browser, vp, scheme);
          const label = `${slug(urlPath)}-${vp.name}-${scheme}`;
          try {
            const resp = await page.goto(BASE + urlPath, { waitUntil: 'domcontentloaded', timeout: 25000 });
            await delay(600);
            await page.evaluate(() => Promise.all(
              [...document.images]
                .filter((img) => !img.complete && img.loading !== 'lazy')
                .map((img) => new Promise((resolve) => {
                  img.addEventListener('load', resolve, { once: true });
                  img.addEventListener('error', resolve, { once: true });
                }))
            )).catch(() => {});
            const status = resp ? resp.status() : 0;
            if (status >= 400) findings.push({ page: urlPath, vp: vp.name, scheme, kind: 'http-status', detail: status });

            const stat = await collectStatic(page, urlPath);
            for (const h of stat.hrefs) allHrefs.add(h);
            for (const iss of stat.issues) {
              if (iss.kind === 'theme-sample') {
                if (scheme === 'dark') {
                  const lum = Math.min(luminance(iss.detail.bodyBg), luminance(iss.detail.htmlBg));
                  if (lum < 0.5 && !/light/.test(iss.detail.colorScheme || '')) {
                    findings.push({ page: urlPath, vp: vp.name, scheme, kind: 'dark-os-leak', detail: iss.detail });
                  }
                }
                continue;
              }
              if (iss.kind === 'small-tap-targets' && vp.name !== '390') continue;
              findings.push({ page: urlPath, vp: vp.name, scheme, ...iss });
            }
            for (const msg of page._qa.consoleErrors) {
              if (/Failed to load resource/.test(msg) && /favicon/.test(msg)) continue;
              findings.push({ page: urlPath, vp: vp.name, scheme, kind: 'console-error', detail: msg });
            }
            for (const msg of page._qa.pageErrors) {
              findings.push({ page: urlPath, vp: vp.name, scheme, kind: 'pageerror', detail: msg });
            }

            const doDeep = deepPaths.has(urlPath) && scheme === 'light';
            if (doDeep) {
              const menuIss = await interactMenu(page, vp.name);
              menuIss.forEach((i) => findings.push({ page: urlPath, vp: vp.name, scheme, ...i }));
              if (vp.name === '390') {
                const hamOpen = await page.evaluate(() => {
                  const el = document.querySelector('#main-nav-collapse');
                  return el && el.classList.contains('in');
                });
                if (hamOpen) {
                  await page.screenshot({ path: path.join(OUT, `qa-menu-${slug(urlPath)}-390.png`) }).catch(() => {});
                }
              }
              const faqIss = await interactFaq(page);
              faqIss.forEach((i) => findings.push({ page: urlPath, vp: vp.name, scheme, ...i }));
              const galIss = await interactGallery(page);
              galIss.forEach((i) => findings.push({ page: urlPath, vp: vp.name, scheme, ...i }));
              if (urlPath === '/contact' || urlPath === '/') {
                const formIss = await interactContact(page);
                formIss.forEach((i) => findings.push({ page: urlPath, vp: vp.name, scheme, ...i }));
              }
            }

            const overflow = findings.filter((f) => f.page === urlPath && f.vp === vp.name && f.scheme === scheme && f.kind === 'overflow-x');
            if (overflow.length && vp.name === '390' && scheme === 'light') {
              await page.screenshot({ path: path.join(OUT, `qa-overflow-${slug(urlPath)}-390.png`) }).catch(() => {});
            }
          } catch (e) {
            findings.push({ page: urlPath, vp: vp.name, scheme, kind: 'load-exception', detail: String(e.message || e) });
          } finally {
            await page.close().catch(() => {});
          }
          process.stdout.write('.');
        }
      }
    }

    // Internal link statuses (once).
    const internals = [...allHrefs]
      .filter(isInternal)
      .map(normalizeInternal)
      .filter((p) => p && !p.startsWith('/admin') && !p.startsWith('/storage/'));
    const unique = [...new Set(internals)];
    for (const p of unique) {
      const code = await fetchStatus(BASE + p);
      if (code === 0 || code >= 400) {
        findings.push({ page: p, vp: 'any', scheme: 'any', kind: 'broken-link', detail: code });
      }
    }

    // Representative screenshots
    const shotPage = await newPage(browser, VIEWPORTS[0], 'dark');
    await shotPage.goto(BASE + '/', { waitUntil: 'networkidle0', timeout: 25000 });
    await delay(500);
    await shotPage.screenshot({ path: path.join(OUT, 'qa-home-390-dark-os.png') });
    await shotPage.close();

    const shotD = await newPage(browser, VIEWPORTS[1], 'dark');
    await shotD.goto(BASE + '/services/fences-gates', { waitUntil: 'networkidle0', timeout: 25000 });
    await delay(400);
    await shotD.screenshot({ path: path.join(OUT, 'qa-fences-1280-dark-os.png') });
    await shotD.close();

    const shotC = await newPage(browser, VIEWPORTS[0], 'light');
    await shotC.goto(BASE + '/contact', { waitUntil: 'networkidle0', timeout: 25000 });
    await delay(400);
    await openHamburger(shotC).catch(() => {});
    await shotC.screenshot({ path: path.join(OUT, 'qa-contact-menu-390.png') });
    if (await shotC.$('#contact-form')) {
      await shotC.evaluate(() => {
        const f = document.querySelector('#contact-form');
        if (f && typeof f.requestSubmit === 'function') f.requestSubmit();
        else if (f) f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      });
      await delay(300);
      await shotC.evaluate(() => document.getElementById('contact-form')?.scrollIntoView());
      await delay(150);
      await shotC.screenshot({ path: path.join(OUT, 'qa-contact-validation-390.png') });
    }
    await shotC.close();

    const shotP = await newPage(browser, VIEWPORTS[0], 'light');
    await shotP.goto(BASE + '/portfolio/floating-deck-with-gravel-around', { waitUntil: 'networkidle0', timeout: 25000 });
    await delay(400);
    const g = await shotP.$('.js-project-gallery');
    if (g) {
      await g.evaluate((el) => el.click());
      await shotP.waitForFunction(() => {
        const vis = document.querySelector('.project-gallery-modal');
        return vis && (vis.classList.contains('in') || getComputedStyle(vis).display === 'block');
      }, { timeout: 4000 }).catch(() => {});
      await delay(300);
      await shotP.screenshot({ path: path.join(OUT, 'qa-gallery-lightbox-390.png') });
    }
    await shotP.close();

    const shotF = await newPage(browser, VIEWPORTS[0], 'light');
    await shotF.goto(BASE + '/service-areas/carleton-place', { waitUntil: 'networkidle0', timeout: 25000 });
    await delay(400);
    const sum = await shotF.$('details.sa-faq__item summary');
    if (sum) {
      await sum.click();
      await delay(200);
      await shotF.evaluate(() => document.querySelector('.sa-faq')?.scrollIntoView());
      await delay(150);
      await shotF.screenshot({ path: path.join(OUT, 'qa-faq-open-390.png') });
    }
    await shotF.close();
  } finally {
    await browser.close().catch(() => {});
  }

  const summary = {};
  for (const f of findings) {
    summary[f.kind] = (summary[f.kind] || 0) + 1;
  }
  const report = { count: findings.length, summary, findings };
  fs.writeFileSync(REPORT, JSON.stringify(report, null, 2));
  console.log('\nWrote', REPORT);
  console.log(JSON.stringify(summary, null, 2));
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
