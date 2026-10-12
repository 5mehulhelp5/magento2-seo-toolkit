/**
 * Two ARIA problems in Luma's Knockout-rendered checkout (Lighthouse/axe: list, aria-dialog-name):
 *  - the steps list <ol id="checkoutSteps"> gets <li role="presentation"> children, which a list may not have;
 *  - the order summary sidebar is an <aside role="dialog"> without a name, although its title is right there.
 * The checkout renders asynchronously, so this watches it and fixes both whenever they appear.
 */
define([], function () {
    'use strict';

    function fix(root) {
        var items = root.querySelectorAll('#checkoutSteps > li[role="presentation"]'),
            dialogs = root.querySelectorAll('[role="dialog"]:not([aria-label]):not([aria-labelledby])'),
            i, title;

        for (i = 0; i < items.length; i++) {
            items[i].removeAttribute('role');
        }
        for (i = 0; i < dialogs.length; i++) {
            title = dialogs[i].querySelector('.modal-title, .title, h1, h2, h3');

            if (title && title.textContent.trim()) {
                dialogs[i].setAttribute('aria-label', title.textContent.trim());
            }
        }
    }

    return function (config, element) {
        var root = element || document.getElementById('checkout') || document.body;

        fix(document);
        new MutationObserver(function () {
            fix(document);
        }).observe(root, {childList: true, subtree: true, attributes: true, attributeFilter: ['role']});
    };
});
