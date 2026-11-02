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
  var announceTimer = null;
  var lastAnnouncedText = '';

  function fmtMoney(amount) {
    var num = Number(amount);
    if (!isFinite(num)) num = 0;
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
    return 'idemp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  function takeFormSnapshot() {
    var form = document.querySelector('form[data-quote-form], form.quote-main-form, #br-main-form, #fq-form, #sq-standard-form, #ld-form, #mw-main-form, #sh-main-form, #fl-form, #pt-form, #pv-form, #st-form, #tl-form, #tm-form');
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

    bindEvents: function () {
      var self = this;

      // Track form inputs
      document.addEventListener('input', function (e) {
        if (e.target.closest('form') && !e.target.closest('[data-quote-modal]')) {
          isFormDirty = true;
        }
      });
      document.addEventListener('change', function (e) {
        if (e.target.closest('form') && !e.target.closest('[data-quote-modal]')) {
          isFormDirty = true;
        }
      });

      // Clear dirty flag when user submits quotation save form
      document.addEventListener('submit', function (e) {
        if (e.target.matches('#br-main-form, #fq-form, #sq-standard-form, #ld-form, #mw-main-form, #sh-main-form, #fl-form, #pt-form, #pv-form, #st-form, #tl-form, #tm-form')) {
          isFormDirty = false;
        }
      });

      // Mobile total click to expand subtotals panel
      var grandToggle = document.querySelector('[data-quote-grand-toggle]');
      if (grandToggle && mobilePanel) {
        grandToggle.addEventListener('click', function (e) {
          if (window.innerWidth <= 768) {
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
      }

      // Send to client button
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-quote-send-btn], [data-qh-email]');
        if (btn) {
          // If it's a submit button inside an existing form without modal data, open modal
          if (modalBackdrop) {
            e.preventDefault();
            e.stopPropagation();
            self.openSendModal();
          }
        }
      });

      // Modal close buttons & backdrop click
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

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !modalBackdrop.hidden) {
            self.closeSendModal();
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
    },

    update: function (data) {
      if (!data) return;

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

      if (typeof data.materials !== 'undefined') {
        var matFormatted = fmtMoney(data.materials);
        if (matEl) matEl.textContent = matFormatted;
        if (mMatEl) mMatEl.textContent = matFormatted;
      }

      if (typeof data.labour !== 'undefined') {
        var labourFormatted = fmtMoney(data.labour);
        if (labourEl) labourEl.textContent = labourFormatted;
        if (mLabourEl) mLabourEl.textContent = labourFormatted;
      }

      if (typeof data.extras !== 'undefined') {
        var extrasNum = Number(data.extras) || 0;
        var extrasFormatted = fmtMoney(extrasNum);
        if (extrasEl) extrasEl.textContent = extrasFormatted;
        if (mExtrasEl) mExtrasEl.textContent = extrasFormatted;

        var showExtras = extrasNum > 0 || data.hasExtras;
        if (extrasWrap) extrasWrap.hidden = !showExtras;
        if (mExtrasWrap) mExtrasWrap.hidden = !showExtras;
      }

      if (typeof data.hst !== 'undefined') {
        var hstFormatted = fmtMoney(data.hst);
        if (hstEl) hstEl.textContent = hstFormatted;
        if (mHstEl) mHstEl.textContent = hstFormatted;
      }

      if (data.hstRate) {
        var rateLabel = 'HST (' + data.hstRate + '%)';
        if (hstLabelEl) hstLabelEl.textContent = rateLabel;
        if (mHstLabelEl) mHstLabelEl.textContent = rateLabel;
      }

      if (typeof data.grand !== 'undefined') {
        var grandFormatted = fmtMoney(data.grand);
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
        if (dirty || !hasEmail) {
          submitBtn.disabled = true;
        } else {
          submitBtn.disabled = false;
        }
      }

      modalBackdrop.hidden = false;
      var modal = modalBackdrop.querySelector('[data-quote-modal]');
      if (modal) {
        modal.focus();
      }
    },

    closeSendModal: function () {
      if (!modalBackdrop) return;
      modalBackdrop.hidden = true;
    },

    submitSend: function () {
      var self = this;
      if (!modalBackdrop) return;

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

      // Check draft confirmation
      var isDraft = barEl && barEl.dataset.isDraft === '1';
      if (isDraft && !confirmDraft) {
        if (errorAlert) {
          errorAlert.textContent = 'This quotation is marked as a draft. Please check the confirmation box to publish and send.';
          errorAlert.hidden = false;
        }
        return;
      }

      // Double-submit protection
      if (submitBtn) submitBtn.disabled = true;
      if (submitText) submitText.hidden = true;
      if (submitSpinner) submitSpinner.hidden = false;

      var idempotencyKey = generateUUID();
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
