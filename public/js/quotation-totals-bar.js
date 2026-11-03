/**
 * Quotation Totals Bar & Send-to-Client Confirmation Modal
 * Reusable component for all quotation edit screens.
 */
(function (window, document) {
  'use strict';

  var barEl = null;
  var modalBackdrop = null;
  var mobilePanel = null;
  var announcerEl = null;
  var initialFormSnapshot = null;
  var isFormDirty = false;
  var isSubmitting = false;
  var currentIdempotencyKey = null;
  var lastFocusedTrigger = null;
  var announceTimer = null;
  var lastAnnouncedText = '';
  var MOBILE_BREAKPOINT = 640;

  function round2(n) {
    var num = Number(n);
    if (!isFinite(num)) return 0;
    return Math.round((num + Number.EPSILON) * 100) / 100;
  }

  function normalizeRate(rate) {
    var num = Number(rate);
    if (!isFinite(num) || num < 0) return 0.13;
    return num > 1 ? num / 100 : num;
  }

  function fmtMoney(amount) {
    var num = round2(amount);
    return 'CA$' + num.toLocaleString('en-CA', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function generateUUID() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    return Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  function getQuoteScopeKey() {
    var sendUrl = (barEl && barEl.dataset.sendUrl) || '';
    var match = sendUrl.match(/quotations\/(\d+)\/send/i) || sendUrl.match(/client-projects\/(\d+)/i);
    if (match && match[1]) {
      return 'q' + match[1];
    }
    if (barEl && barEl.id) {
      return String(barEl.id).replace(/[^a-zA-Z0-9_-]/g, '');
    }
    return 'quote';
  }

  function ensureIdempotencyKey(forceNew) {
    if (!forceNew && currentIdempotencyKey) {
      return currentIdempotencyKey;
    }
    if (!forceNew && modalBackdrop && modalBackdrop.dataset.idempotencyKey) {
      currentIdempotencyKey = modalBackdrop.dataset.idempotencyKey;
      return currentIdempotencyKey;
    }
    currentIdempotencyKey = 'idem_' + getQuoteScopeKey() + '_' + generateUUID();
    if (modalBackdrop) {
      modalBackdrop.dataset.idempotencyKey = currentIdempotencyKey;
    }
    return currentIdempotencyKey;
  }

  function findMainQuoteForm() {
    if (barEl) {
      var saveBtn = barEl.querySelector('[data-quote-save-btn]');
      if (saveBtn && saveBtn.getAttribute('form')) {
        var byAttr = document.getElementById(saveBtn.getAttribute('form'));
        if (byAttr) return byAttr;
      }
    }
    return document.querySelector(
      'form[data-quote-form], form.quote-main-form, #br-main-form, #fq-form, #sq-standard-form, #sq-main-form, #ld-form, #mw-main-form, #sh-main-form, #fl-main-form, #fl-form, #pt-main-form, #pt-form, #pq-main-form, #pv-form, #st-form, #tl-form, #tm-main-form, #tm-form'
    );
  }

  function takeFormSnapshot() {
    var form = findMainQuoteForm();
    if (!form) return null;
    try {
      var fd = new FormData(form);
      var entries = [];
      fd.forEach(function (value, key) {
        if (key !== '_token' && key !== '_method') {
          entries.push(key + '=' + value);
        }
      });
      return entries.sort().join('&');
    } catch (e) {
      return null;
    }
  }

  function checkFormDirty() {
    if (isFormDirty) return true;
    if (!initialFormSnapshot) return false;
    var current = takeFormSnapshot();
    if (current && current !== initialFormSnapshot) {
      isFormDirty = true;
      return true;
    }
    return false;
  }

  function announceTotals(text) {
    if (!announcerEl || text === lastAnnouncedText) return;
    clearTimeout(announceTimer);
    announceTimer = setTimeout(function () {
      announcerEl.textContent = text;
      lastAnnouncedText = text;
    }, 800);
  }

  function getFocusableModalElements() {
    if (!modalBackdrop || modalBackdrop.hidden) return [];
    var modal = modalBackdrop.querySelector('[data-quote-modal]');
    if (!modal) return [];
    var nodes = modal.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    return Array.prototype.filter.call(nodes, function (el) {
      if (el.hidden || el.closest('[hidden]')) return false;
      var style = window.getComputedStyle ? window.getComputedStyle(el) : null;
      if (style && (style.display === 'none' || style.visibility === 'hidden')) return false;
      return true;
    });
  }

  var QuotationTotalsBar = {
    init: function () {
      barEl = document.querySelector('[data-quote-totals-bar]');
      if (!barEl) return;

      modalBackdrop = document.querySelector('[data-quote-modal-backdrop]');
      mobilePanel = document.querySelector('[data-quote-mobile-panel]');
      announcerEl = document.querySelector('[data-quote-live-announcer]');

      this.syncBarHeight();
      if (window.ResizeObserver) {
        var ro = new ResizeObserver(function () {
          QuotationTotalsBar.syncBarHeight();
        });
        ro.observe(barEl);
      }

      this.bindEvents();

      // Form snapshot
      setTimeout(function () {
        initialFormSnapshot = takeFormSnapshot();
      }, 500);
    },

    syncBarHeight: function () {
      if (!barEl) return;
      var h = barEl.offsetHeight || 68;
      document.documentElement.style.setProperty('--quote-sticky-bar-h', h + 'px');
      document.documentElement.style.setProperty('--br-sticky-bar-h', h + 'px');
      var spacers = document.querySelectorAll('.quote-totals-bar-spacer, .br-sticky-spacer');
      spacers.forEach(function (sp) {
        sp.style.height = h + 'px';
      });
    },

    /**
     * Single canonical calculation for Materials, Labour, Extras, HST, and Grand Total
     * across all 12 quotation types (mirrors App\Support\QuotationTotalsBarBreakdown::calculate).
     */
    calculateBreakdown: function (opts) {
      opts = opts || {};
      var rate = normalizeRate(opts.hstRate != null ? opts.hstRate : opts.hst_rate);
      var ratePct = round2(rate * 100);
      var rateLabelNum = Math.abs(ratePct - Math.round(ratePct)) < 1e-6 ? Math.round(ratePct) : ratePct;
      var hasExtrasFlag = !!(opts.hasExtras || opts.has_extras);

      if (opts.mode === 'paving') {
        var baseQuoteTotal = round2(opts.baseQuoteTotal != null ? opts.baseQuoteTotal : opts.base_quote_total);
        var labourBase = round2(opts.labourPreTax != null ? opts.labourPreTax : opts.labour_pre_tax);
        var labourHst = round2(labourBase * rate);
        var commuteTaxIn = Math.max(0, round2(opts.commuteTaxIn != null ? opts.commuteTaxIn : opts.commute_tax_in));
        var debrisTaxIn = Math.max(0, round2(opts.debrisTaxIn != null ? opts.debrisTaxIn : opts.debris_tax_in));
        var addMatTaxIn = Math.max(0, round2(opts.additionalMaterialsTaxIn != null ? opts.additionalMaterialsTaxIn : opts.additional_materials_tax_in));
        var addLabPre = Math.max(0, round2(opts.additionalLaborPreTax != null ? opts.additionalLaborPreTax : opts.additional_labor_pre_tax));
        var addLabTaxIn = Math.max(0, round2(opts.additionalLaborTaxIn != null ? opts.additionalLaborTaxIn : opts.additional_labor_tax_in));

        var pavingGrand = opts.grandTotal != null
          ? round2(opts.grandTotal)
          : (opts.grand_total != null
            ? round2(opts.grand_total)
            : round2(baseQuoteTotal + commuteTaxIn + debrisTaxIn + addMatTaxIn + addLabTaxIn));

        if (pavingGrand <= 0) {
          return {
            materials: 0,
            labour: 0,
            extras: 0,
            hasExtras: hasExtrasFlag,
            hst: 0,
            hstRate: rateLabelNum,
            grand: 0
          };
        }

        var exemptTaxIn = Math.max(0, round2(pavingGrand - labourBase - labourHst - commuteTaxIn - debrisTaxIn - addLabTaxIn));
        var pvMaterials = round2(exemptTaxIn / (1 + rate));
        var pvLabour = round2(labourBase + addLabPre);
        var pvCommutePre = commuteTaxIn > 0 ? round2(commuteTaxIn / (1 + rate)) : 0;
        var pvDebrisPre = debrisTaxIn > 0 ? round2(debrisTaxIn / (1 + rate)) : 0;
        var pvExtras = round2(pvCommutePre + pvDebrisPre);
        var pvHst = round2(pavingGrand - pvMaterials - pvLabour - pvExtras);

        return {
          materials: pvMaterials,
          labour: pvLabour,
          extras: pvExtras,
          hasExtras: hasExtrasFlag || pvExtras > 0,
          hst: pvHst,
          hstRate: rateLabelNum,
          grand: pavingGrand
        };
      }

      var matBase = round2(opts.materialsPreTax != null ? opts.materialsPreTax : opts.materials_pre_tax);
      var addMatPre = round2(opts.additionalMaterialsPreTax != null ? opts.additionalMaterialsPreTax : opts.additional_materials_pre_tax);
      var rawAddMatTaxIn = opts.additionalMaterialsTaxIn != null ? opts.additionalMaterialsTaxIn : opts.additional_materials_tax_in;
      var rawAddMatHst = opts.additionalMaterialsHst != null ? opts.additionalMaterialsHst : opts.additional_materials_hst;
      var addMatHst = rawAddMatHst != null
        ? round2(rawAddMatHst)
        : (rawAddMatTaxIn != null ? round2(round2(rawAddMatTaxIn) - addMatPre) : round2(addMatPre * rate));

      var labBase = round2(opts.labourPreTax != null ? opts.labourPreTax : opts.labour_pre_tax);
      var addLabPre2 = round2(opts.additionalLaborPreTax != null ? opts.additionalLaborPreTax : opts.additional_labor_pre_tax);
      var rawAddLabTaxIn = opts.additionalLaborTaxIn != null ? opts.additionalLaborTaxIn : opts.additional_labor_tax_in;
      var rawAddLabHst = opts.additionalLaborHst != null ? opts.additionalLaborHst : opts.additional_labor_hst;
      var addLabHst = rawAddLabHst != null
        ? round2(rawAddLabHst)
        : (rawAddLabTaxIn != null ? round2(round2(rawAddLabTaxIn) - addLabPre2) : round2(addLabPre2 * rate));

      var extrasPre = round2(opts.extrasPreTax != null ? opts.extrasPreTax : opts.extras_pre_tax);
      var commuteTaxIn2 = Math.max(0, round2(opts.commuteTaxIn != null ? opts.commuteTaxIn : opts.commute_tax_in));
      var commutePre = commuteTaxIn2 > 0 ? round2(commuteTaxIn2 / (1 + rate)) : 0;
      var commuteHst = round2(commuteTaxIn2 - commutePre);

      var debrisTaxIn2 = Math.max(0, round2(opts.debrisTaxIn != null ? opts.debrisTaxIn : opts.debris_tax_in));
      var debrisPre = debrisTaxIn2 > 0 ? round2(debrisTaxIn2 / (1 + rate)) : 0;
      var debrisHst = round2(debrisTaxIn2 - debrisPre);

      var materials = round2(matBase + addMatPre);
      var labour = round2(labBase + addLabPre2);
      var extras = round2(extrasPre + commutePre + debrisPre);

      var rawBaseHst = opts.baseHst != null ? opts.baseHst : opts.base_hst;
      var baseHst = rawBaseHst != null
        ? round2(rawBaseHst)
        : round2((matBase + labBase + extrasPre) * rate);

      var hst = round2(baseHst + addMatHst + addLabHst + commuteHst + debrisHst);
      var rawGrand = opts.grandTotal != null ? opts.grandTotal : opts.grand_total;
      var grand;

      if (rawGrand != null) {
        grand = round2(rawGrand);
        if (grand <= 0) {
          return {
            materials: 0,
            labour: 0,
            extras: 0,
            hasExtras: hasExtrasFlag,
            hst: 0,
            hstRate: rateLabelNum,
            grand: 0
          };
        }
        if (materials === 0 && labour === 0 && extras === 0) {
          labour = round2(grand / (1 + rate));
          hst = round2(grand - labour);
        } else {
          hst = round2(grand - materials - labour - extras);
        }
      } else {
        grand = round2(materials + labour + extras + hst);
      }

      return {
        materials: materials,
        labour: labour,
        extras: extras,
        hasExtras: hasExtrasFlag || extras > 0,
        hst: hst,
        hstRate: rateLabelNum,
        grand: grand
      };
    },

    updateFromBreakdown: function (opts) {
      var breakdown = this.calculateBreakdown(opts);
      this.update(breakdown);
      return breakdown;
    },

    bindEvents: function () {
      var self = this;

      // Track form inputs
      document.addEventListener('input', function (e) {
        if (e.target && e.target.closest && e.target.closest('form') && !e.target.closest('[data-quote-modal]')) {
          isFormDirty = true;
        }
      });
      document.addEventListener('change', function (e) {
        if (e.target && e.target.closest && e.target.closest('form') && !e.target.closest('[data-quote-modal]')) {
          isFormDirty = true;
        }
      });

      // Clear dirty flag when user submits quotation save form
      document.addEventListener('submit', function (e) {
        if (e.target && e.target.matches && e.target.matches('form[data-quote-form], form.quote-main-form, #br-main-form, #fq-form, #sq-standard-form, #sq-main-form, #ld-form, #mw-main-form, #sh-main-form, #fl-main-form, #fl-form, #pt-main-form, #pt-form, #pq-main-form, #pv-form, #st-form, #tl-form, #tm-main-form, #tm-form')) {
          isFormDirty = false;
        }
      });

      // Mobile total click to expand subtotals panel (<= 640px)
      var grandToggle = document.querySelector('[data-quote-grand-toggle]');
      if (grandToggle && mobilePanel) {
        grandToggle.addEventListener('click', function (e) {
          if (window.innerWidth <= MOBILE_BREAKPOINT) {
            e.preventDefault();
            var isOpen = !mobilePanel.hidden;
            mobilePanel.hidden = isOpen;
            grandToggle.setAttribute('aria-expanded', String(!isOpen));
          }
        });

        // Close mobile panel on click outside
        document.addEventListener('click', function (e) {
          if (mobilePanel && !mobilePanel.hidden) {
            if (!mobilePanel.contains(e.target) && !grandToggle.contains(e.target)) {
              mobilePanel.hidden = true;
              grandToggle.setAttribute('aria-expanded', 'false');
            }
          }
        });

        // Close mobile panel if viewport widens past mobile breakpoint
        window.addEventListener('resize', function () {
          if (window.innerWidth > MOBILE_BREAKPOINT && mobilePanel && !mobilePanel.hidden) {
            mobilePanel.hidden = true;
            grandToggle.setAttribute('aria-expanded', 'false');
          }
        });
      }

      // Send to client button
      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-quote-send-btn], [data-qh-email]') : null;
        if (btn && modalBackdrop) {
          e.preventDefault();
          e.stopPropagation();
          lastFocusedTrigger = btn;
          self.openSendModal();
        }
      });

      // Modal close buttons, save button, focus trap & backdrop click
      if (modalBackdrop) {
        modalBackdrop.querySelectorAll('[data-modal-close]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            self.closeSendModal();
          });
        });

        modalBackdrop.addEventListener('click', function (e) {
          if (e.target === modalBackdrop) {
            self.closeSendModal();
          }
        });

        var modalSaveBtn = modalBackdrop.querySelector('[data-modal-save-btn]');
        if (modalSaveBtn) {
          modalSaveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            self.closeSendModal();
            var barSaveBtn = (barEl && barEl.querySelector('[data-quote-save-btn]'))
              || document.querySelector('[data-hotkey-save]')
              || document.querySelector('[data-quote-save-btn]');
            if (barSaveBtn && !barSaveBtn.disabled) {
              barSaveBtn.click();
              return;
            }
            var form = findMainQuoteForm();
            if (form) {
              isFormDirty = false;
              if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
              } else {
                form.submit();
              }
            }
          });
        }

        document.addEventListener('keydown', function (e) {
          if (!modalBackdrop || modalBackdrop.hidden) return;
          if (e.key === 'Escape') {
            e.preventDefault();
            self.closeSendModal();
            return;
          }
          if (e.key === 'Tab') {
            var focusables = getFocusableModalElements();
            if (!focusables.length) {
              e.preventDefault();
              return;
            }
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            var active = document.activeElement;
            var modal = modalBackdrop.querySelector('[data-quote-modal]');
            var insideModal = modal && modal.contains(active);
            if (e.shiftKey) {
              if (!insideModal || active === first || active === modal) {
                e.preventDefault();
                last.focus();
              }
            } else {
              if (!insideModal || active === last) {
                e.preventDefault();
                first.focus();
              }
            }
          }
        });

        // Modal Send Submit
        var submitBtn = modalBackdrop.querySelector('[data-modal-submit-btn]');
        if (submitBtn) {
          submitBtn.addEventListener('click', function (e) {
            e.preventDefault();
            self.submitSend();
          });
        }
      }

      // Listen to CustomEvents
      document.addEventListener('quote:totals-update', function (e) {
        if (e.detail) {
          self.update(e.detail);
        }
      });

      document.addEventListener('quote:totals-updating', function (e) {
        self.setUpdating(e.detail && e.detail.updating);
      });

      document.addEventListener('quote:grand-total', function (e) {
        if (e.detail && typeof e.detail.total !== 'undefined') {
          self.update({ grand: e.detail.total });
        }
      });

      function keepFormControlsClearOfBar(targetEl) {
        if (!barEl || barEl.hidden) return;
        var barRect = barEl.getBoundingClientRect();
        if (!barRect || barRect.height <= 0 || barRect.top >= window.innerHeight) return;
        var safeBottom = barRect.top - 12;
        if (targetEl && targetEl.getBoundingClientRect) {
          var tr = targetEl.getBoundingClientRect();
          if (tr.height > 0 && tr.bottom > safeBottom && tr.bottom <= window.innerHeight + 8 && tr.top > 140) {
            window.scrollBy(0, Math.ceil(tr.bottom - safeBottom + 16));
          }
          return;
        }
        var form = findMainQuoteForm();
        if (!form) return;
        var controls = form.querySelectorAll('.cp-money-input');
        for (var i = 0; i < controls.length; i++) {
          var el = controls[i];
          if (el.disabled || el.readOnly) continue;
          var r = el.getBoundingClientRect();
          if (r.width < 2 || r.height < 2) continue;
          if (r.bottom > safeBottom && r.bottom <= window.innerHeight + 4 && r.top > 160) {
            window.scrollBy(0, Math.ceil(r.bottom - safeBottom + 20));
            break;
          }
        }
      }

      document.addEventListener('focusin', function (e) {
        if (barEl && e.target && !barEl.contains(e.target) && (!modalBackdrop || !modalBackdrop.contains(e.target))) {
          keepFormControlsClearOfBar(e.target);
        }
      });

      document.addEventListener('focusout', function (e) {
        if (barEl && e.target && !barEl.contains(e.target) && (!modalBackdrop || !modalBackdrop.contains(e.target))) {
          keepFormControlsClearOfBar(null);
        }
      });
    },

    update: function (data) {
      if (!data) return;

      // Normalize if full breakdown fields are present so lines always sum to grand total
      var hasAllParts = typeof data.materials !== 'undefined'
        && typeof data.labour !== 'undefined'
        && typeof data.grand !== 'undefined';

      var matVal = typeof data.materials !== 'undefined' ? round2(data.materials) : undefined;
      var labVal = typeof data.labour !== 'undefined' ? round2(data.labour) : undefined;
      var extVal = typeof data.extras !== 'undefined' ? round2(data.extras) : undefined;
      var hstVal = typeof data.hst !== 'undefined' ? round2(data.hst) : undefined;
      var grandVal = typeof data.grand !== 'undefined' ? round2(data.grand) : undefined;

      if (hasAllParts) {
        var extNum = extVal != null ? extVal : 0;
        if (grandVal <= 0) {
          matVal = 0;
          labVal = 0;
          extVal = 0;
          hstVal = 0;
          grandVal = 0;
        } else {
          hstVal = round2(grandVal - matVal - labVal - extNum);
        }
      }

      var matEl = document.querySelector('[data-quote-materials]');
      var labourEl = document.querySelector('[data-quote-labour]');
      var extrasEl = document.querySelector('[data-quote-extras]');
      var extrasWrap = document.querySelector('[data-quote-extras-wrap]');
      var hstEl = document.querySelector('[data-quote-hst]');
      var hstLabelEl = document.querySelector('[data-quote-hst-label]');
      var grandEls = document.querySelectorAll('[data-quote-grand], #quote-toolbar-grand-amount, #br-toolbar-grand-amount, #sq-toolbar-grand-amount');

      // Mobile panel elements
      var mMatEl = document.querySelector('[data-quote-mobile-materials]');
      var mLabourEl = document.querySelector('[data-quote-mobile-labour]');
      var mExtrasEl = document.querySelector('[data-quote-mobile-extras]');
      var mExtrasWrap = document.querySelector('[data-quote-mobile-extras-wrap]');
      var mHstEl = document.querySelector('[data-quote-mobile-hst]');
      var mHstLabelEl = document.querySelector('[data-quote-mobile-hst-label]');

      if (typeof matVal !== 'undefined') {
        var matFormatted = fmtMoney(matVal);
        if (matEl) matEl.textContent = matFormatted;
        if (mMatEl) mMatEl.textContent = matFormatted;
      }

      if (typeof labVal !== 'undefined') {
        var labourFormatted = fmtMoney(labVal);
        if (labourEl) labourEl.textContent = labourFormatted;
        if (mLabourEl) mLabourEl.textContent = labourFormatted;
      }

      if (typeof extVal !== 'undefined') {
        var extrasFormatted = fmtMoney(extVal);
        if (extrasEl) extrasEl.textContent = extrasFormatted;
        if (mExtrasEl) mExtrasEl.textContent = extrasFormatted;

        var showExtras = extVal > 0 || data.hasExtras;
        if (extrasWrap) extrasWrap.hidden = !showExtras;
        if (mExtrasWrap) mExtrasWrap.hidden = !showExtras;
      }

      if (typeof hstVal !== 'undefined') {
        var hstFormatted = fmtMoney(hstVal);
        if (hstEl) hstEl.textContent = hstFormatted;
        if (mHstEl) mHstEl.textContent = hstFormatted;
      }

      if (data.hstRate) {
        var rateLabel = 'HST (' + data.hstRate + '%)';
        if (hstLabelEl) hstLabelEl.textContent = rateLabel;
        if (mHstLabelEl) mHstLabelEl.textContent = rateLabel;
      }

      if (typeof grandVal !== 'undefined') {
        var grandFormatted = fmtMoney(grandVal);
        grandEls.forEach(function (el) {
          el.textContent = grandFormatted;
        });

        announceTotals('Grand total updated to ' + grandFormatted);
      }

      this.setUpdating(false);
    },

    setUpdating: function (isUpdating) {
      var el = document.querySelector('[data-quote-updating]');
      if (el) {
        el.hidden = !isUpdating;
      }
    },

    isDirty: function () {
      return checkFormDirty();
    },

    openSendModal: function () {
      if (!modalBackdrop) return;

      ensureIdempotencyKey(false);

      var unsavedAlert = modalBackdrop.querySelector('[data-modal-unsaved-alert]');
      var noEmailAlert = modalBackdrop.querySelector('[data-modal-no-email-alert]');
      var draftAlert = modalBackdrop.querySelector('[data-modal-draft-alert]');
      var errorAlert = modalBackdrop.querySelector('[data-modal-error-alert]');
      var submitBtn = modalBackdrop.querySelector('[data-modal-submit-btn]');
      var toInput = modalBackdrop.querySelector('[data-modal-to]');

      if (errorAlert) {
        errorAlert.hidden = true;
        errorAlert.textContent = '';
      }

      // Check unsaved changes
      var dirty = this.isDirty();
      if (unsavedAlert) {
        unsavedAlert.hidden = !dirty;
      }

      // Check client email
      var hasEmail = barEl && barEl.dataset.hasClientEmail === '1';
      if (toInput && toInput.value.trim() !== '') {
        hasEmail = true;
      }
      if (noEmailAlert) {
        noEmailAlert.hidden = hasEmail;
      }

      // Check draft
      var isDraft = barEl && barEl.dataset.isDraft === '1';
      if (draftAlert) {
        draftAlert.hidden = !isDraft;
      }

      // Block button if unsaved or missing email
      if (submitBtn) {
        submitBtn.disabled = !!(dirty || !hasEmail);
      }

      modalBackdrop.hidden = false;
      var focusables = getFocusableModalElements();
      if (focusables.length) {
        focusables[0].focus();
      } else {
        var modal = modalBackdrop.querySelector('[data-quote-modal]');
        if (modal) modal.focus();
      }
    },

    closeSendModal: function () {
      if (!modalBackdrop) return;
      modalBackdrop.hidden = true;
      if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
        try {
          lastFocusedTrigger.focus();
        } catch (e) {}
      }
    },

    submitSend: function () {
      var self = this;
      if (!modalBackdrop || isSubmitting) return;

      var sendUrl = barEl ? barEl.dataset.sendUrl : '';
      if (!sendUrl) {
        var form = document.querySelector('form[data-qh-email-form], #br-email-quote-form');
        if (form && form.action) {
          sendUrl = form.action;
        }
      }

      var errorAlert = modalBackdrop.querySelector('[data-modal-error-alert]');
      var submitBtn = modalBackdrop.querySelector('[data-modal-submit-btn]');
      var submitText = modalBackdrop.querySelector('[data-modal-submit-text]');
      var submitSpinner = modalBackdrop.querySelector('[data-modal-submit-spinner]');

      if (errorAlert) {
        errorAlert.hidden = true;
        errorAlert.textContent = '';
      }

      if (!sendUrl) {
        if (errorAlert) {
          errorAlert.textContent = 'Cannot send: this quotation is not linked to a client project yet. Please link it to a project first.';
          errorAlert.hidden = false;
        }
        return;
      }

      var toInput = modalBackdrop.querySelector('[data-modal-to]');
      var ccInput = modalBackdrop.querySelector('[data-modal-cc]');
      var subjectInput = modalBackdrop.querySelector('[data-modal-subject]');
      var messageInput = modalBackdrop.querySelector('[data-modal-message]');
      var draftCheck = modalBackdrop.querySelector('[data-modal-confirm-draft]');

      var to = toInput ? toInput.value.trim() : '';
      var cc = ccInput ? ccInput.value.trim() : '';
      var subject = subjectInput ? subjectInput.value.trim() : '';
      var message = messageInput ? messageInput.value.trim() : '';
      var confirmDraft = draftCheck ? draftCheck.checked : false;

      if (!to || to.indexOf('@') === -1) {
        if (errorAlert) {
          errorAlert.textContent = 'Please enter a valid recipient email address.';
          errorAlert.hidden = false;
        }
        if (toInput) toInput.focus();
        return;
      }

      if (!subject) {
        if (errorAlert) {
          errorAlert.textContent = 'Please enter an email subject.';
          errorAlert.hidden = false;
        }
        if (subjectInput) subjectInput.focus();
        return;
      }

      if (subject.length > 255) {
        if (errorAlert) {
          errorAlert.textContent = 'Email subject must not exceed 255 characters.';
          errorAlert.hidden = false;
        }
        if (subjectInput) subjectInput.focus();
        return;
      }

      // Check draft confirmation
      var isDraft = barEl && barEl.dataset.isDraft === '1';
      if (isDraft && !confirmDraft) {
        if (errorAlert) {
          errorAlert.textContent = 'This quotation is marked as a draft. Please check the confirmation box to publish and send.';
          errorAlert.hidden = false;
        }
        return;
      }

      // Double-submit protection (in-flight lock + quote-scoped idempotency key)
      isSubmitting = true;
      if (submitBtn) submitBtn.disabled = true;
      if (submitText) submitText.hidden = true;
      if (submitSpinner) submitSpinner.hidden = false;

      var idempotencyKey = ensureIdempotencyKey(false);
      var payload = {
        to: to,
        cc: cc,
        subject: subject,
        message: message,
        confirm_draft: confirmDraft ? 1 : 0,
        idempotency_key: idempotencyKey
      };

      fetch(sendUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
      })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, status: res.status, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          var errMsg = (result.data && (result.data.error || result.data.message)) || 'Failed to send quotation to client. Please try again.';
          throw new Error(errMsg);
        }

        // Rotate key only after a completed send so a future intentional re-send gets a fresh key
        ensureIdempotencyKey(true);

        // Success
        self.closeSendModal();

        // Update badge
        var badge = document.querySelector('[data-quote-sent-badge]');
        var dateEl = document.querySelector('[data-quote-sent-date]');
        var verEl = document.querySelector('[data-quote-sent-version]');
        if (badge) badge.hidden = false;
        if (dateEl && result.data.sent_at) dateEl.textContent = result.data.sent_at;
        if (verEl && result.data.version) verEl.textContent = result.data.version;

        // No longer draft
        if (barEl) barEl.dataset.isDraft = '0';

        // Toast
        if (window.AdminToast && window.AdminToast.show) {
          window.AdminToast.show(result.data.message || 'Quotation sent successfully to client.', 'success');
        } else {
          alert(result.data.message || 'Quotation emailed to client successfully.');
        }
      })
      .catch(function (err) {
        if (errorAlert) {
          errorAlert.textContent = err.message || 'An error occurred while sending. Please try again.';
          errorAlert.hidden = false;
        }
      })
      .finally(function () {
        isSubmitting = false;
        if (submitBtn) submitBtn.disabled = false;
        if (submitText) submitText.hidden = false;
        if (submitSpinner) submitSpinner.hidden = true;
      });
    }
  };

  window.QuotationTotalsBar = QuotationTotalsBar;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      QuotationTotalsBar.init();
    });
  } else {
    QuotationTotalsBar.init();
  }
})(window, document);
