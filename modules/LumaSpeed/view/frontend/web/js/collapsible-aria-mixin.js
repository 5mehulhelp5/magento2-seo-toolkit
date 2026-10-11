/**
 * Luma's tabs (product info tabs, the mobile menu/account sections) put every title and its content side by
 * side under one element, and mage/collapsible makes that element role="tablist", the titles role="tab" and
 * the contents role="tabpanel". ARIA only allows tabs inside a tablist, so screen readers get a tablist full of
 * panels, menus and lists (Lighthouse/axe: aria-required-children).
 *
 * Without touching Luma's markup or CSS: the container loses its tablist role, and an empty element appended to
 * it becomes the tablist, owning the titles through aria-owns. Tabs stay tabs, panels stay panels, and the
 * panels now sit outside the tablist as ARIA requires.
 */
define(['jquery'], function ($) {
    'use strict';

    var OWNER = 'data-softcode-tablist',
        counter = 0;

    function fix(container) {
        var tabs, owner, ids;

        if (!container || !container.length || (container.attr('role') !== 'tablist' && !container.children('[' + OWNER + ']').length)) {
            return;
        }
        tabs = container.children('[role="tab"]');

        if (!tabs.length || !container.children('[role="tabpanel"]').length) {
            return;   // a plain tablist with tabs only is already valid
        }
        ids = tabs.map(function () {
            if (!this.id) {
                this.id = 'softcode-tab-' + (++counter);
            }

            return this.id;
        }).get().join(' ');

        owner = container.children('[' + OWNER + ']');

        if (!owner.length) {
            owner = $('<div ' + OWNER + '></div>').attr('role', 'tablist').appendTo(container);
        }
        owner.attr('aria-owns', ids);
        container.removeAttr('role');
    }

    return function (widget) {
        $.widget('mage.collapsible', widget, {
            _create: function () {
                var result = this._super();

                fix(this.header && this.header.parent());

                return result;
            },

            _processState: function () {
                var result = this._super();

                fix(this.header && this.header.parent());

                return result;
            }
        });

        return $.mage.collapsible;
    };
});
