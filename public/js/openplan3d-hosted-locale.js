/**
 * Hosted override for openPlan3D locale strings (cache-busted via ?v=, not immutable CDN).
 * Import map in editor/index.html redirects NzBhsCJS.js here; this module loads the real chunk.
 */
import {
  a as vendorTranslate,
  i as initLocale,
  l as localeStore,
  t as useTranslate
} from '/vendor/openplan3d-h1/_app/immutable/chunks/NzBhsCJS.js?zanini=vendor';

export function a(locale, key, vars) {
  if (key === 'wallProperties.entryHelp') {
    var bridge = typeof window !== 'undefined' ? window.OpenPlan3DEditorBridge : null;
    var unit = vars && vars.unit != null ? vars.unit : 'in';
    if (bridge && typeof bridge.hostedWallLengthEntryHelpText === 'function') {
      return bridge.hostedWallLengthEntryHelpText(unit);
    }
  }
  return vendorTranslate(locale, key, vars || {});
}

export { initLocale as i, localeStore as l, useTranslate as t };
