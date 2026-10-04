import { Toast } from 'bootstrap';

// The flash toasts are rendered with .show already applied, so every message is
// on screen before this module runs and survives its failure. The only thing
// left for JavaScript is the one behaviour CSS cannot express: the success toast
// dismisses itself after a few seconds. The close button is wired by Bootstrap's
// own data attribute handler, so it keeps working even if this module never
// loads.
//
// animation is off on purpose. show() on a toast that is already visible would
// otherwise add .showing, which Bootstrap renders at opacity 0, flashing the
// message out and back in.
document.querySelectorAll('[data-toast][data-bs-autohide="true"]').forEach((element) => {
    Toast.getOrCreateInstance(element, { animation: false }).show();
});
