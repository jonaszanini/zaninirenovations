<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class StandardQuotationEditorJsTest extends TestCase
{
    public function test_rewrite_attr_renumbers_nested_material_indexes(): void
    {
        $fn = $this->extractJsFunction('rewriteAttr');
        $this->assertNotSame('', $fn);
        $this->assertStringContainsString('/\\[materials\\]\\[(?:\\d+|__I__)]/g', $fn);
        $this->assertStringContainsString("'[materials][' + i + ']'", $fn);

        $script = <<<JS
{$fn}
const el = {
  value: 'works[0][materials][1][assembly_covered_layouts]',
  getAttribute: function (attr) { return attr === 'name' ? this.value : null; },
  setAttribute: function (attr, next) { if (attr === 'name') this.value = next; }
};
rewriteAttr(el, 'name', 0, 0);
if (el.value !== 'works[0][materials][0][assembly_covered_layouts]') {
  console.error(el.value);
  process.exit(1);
}
rewriteAttr(el, 'name', 0, 2);
if (el.value !== 'works[0][materials][2][assembly_covered_layouts]') {
  console.error(el.value);
  process.exit(1);
}
JS;

        $this->assertNode($script);
    }

    public function test_b8_confirm_remove_uses_admin_dialog(): void
    {
        $fn = $this->extractJsFunction('confirmRemove');
        $this->assertNotSame('', $fn);

        $script = <<<JS
const calls = [];
const window = {
  AdminDialog: {
    confirm: function (message, opts) {
      calls.push({ message: message, opts: opts });
      return Promise.resolve(true);
    }
  },
  confirm: function () { throw new Error('window.confirm should not run'); }
};
{$fn}
let confirmed = false;
confirmRemove('Remove Job 2?', 'Remove job', function () { confirmed = true; });
if (calls.length !== 1) { console.error('expected one dialog'); process.exit(1); }
if (calls[0].message !== 'Remove Job 2?') { console.error(calls[0]); process.exit(1); }
if (calls[0].opts.title !== 'Remove job' || calls[0].opts.danger !== true || calls[0].opts.confirmLabel !== 'Remove') {
  console.error(calls[0].opts);
  process.exit(1);
}
JS;

        $this->assertNode($script);
    }

    public function test_b9_restore_open_jobs_runs_real_js(): void
    {
        $js = $this->editorJs();
        $this->assertTrue((bool) preg_match('/var SCROLL_MAX_AGE_MS = ([^;]+);/', $js, $age));

        $script = <<<JS
const SCROLL_MAX_AGE_MS = {$age[1]};
const store = {};
const sessionStorage = {
  getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; },
  setItem: function (k, v) { store[k] = String(v); }
};
const form = { getAttribute: function (name) { return name === 'data-quote-id' ? '7' : null; } };
function makeCard(open, invalid) {
  const classes = new Set(open ? ['is-open'] : []);
  return {
    classList: {
      contains: function (c) { return classes.has(c); },
      toggle: function (c, on) { if (on) classes.add(c); else classes.delete(c); }
    },
    querySelector: function (sel) {
      if (sel === '.sq-work-toggle') return { setAttribute: function () {} };
      if (sel === '.admin-form-error, .is-invalid') return invalid ? {} : null;
      return null;
    },
    isOpen: function () { return classes.has('is-open'); }
  };
}
let cards = [makeCard(false, false), makeCard(false, true), makeCard(false, false), makeCard(false, false)];
function workCards() { return cards; }
{$this->extractJsFunction('openJobsKey')}
{$this->extractJsFunction('setWorkOpen')}
{$this->extractJsFunction('saveOpenJobs')}
{$this->extractJsFunction('restoreOpenJobs')}
sessionStorage.setItem(openJobsKey(), JSON.stringify({ open: [0, 2], t: Date.now() }));
restoreOpenJobs();
const opened = cards.map(function (c, i) { return c.isOpen() ? i : null; }).filter(function (i) { return i !== null; });
if (JSON.stringify(opened) !== JSON.stringify([0, 1, 2])) {
  console.error('restore mismatch', opened);
  process.exit(1);
}
sessionStorage.setItem(openJobsKey(), JSON.stringify({ open: [3], t: Date.now() - SCROLL_MAX_AGE_MS - 1 }));
cards = [makeCard(false, false), makeCard(false, false), makeCard(false, false), makeCard(false, false)];
restoreOpenJobs();
const expired = cards.some(function (c) { return c.isOpen(); });
if (expired) {
  console.error('expired store should not restore');
  process.exit(1);
}
JS;

        $this->assertNode($script);
    }

    private function extractJsFunction(string $name): string
    {
        $js = $this->editorJs();
        $this->assertTrue(
            (bool) preg_match('/function '.preg_quote($name, '/').'\([^)]*\) \{.*?\n  \}/s', $js, $m),
            $name.' should exist in admin-standard-quotation.js'
        );

        return $m[0];
    }

    private function assertNode(string $script): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sq-js-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' 2>&1', $output, $code);
        @unlink($tmp);

        $this->assertSame(0, $code, implode("\n", $output));
    }

    private function editorJs(): string
    {
        $js = (string) file_get_contents(public_path('js/admin-standard-quotation.js'));
        $this->assertNotSame('', $js);

        return $js;
    }
}
