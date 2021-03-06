define(function(require) {
    'use strict';

    var MobileEmailNotificationView;
    var $ = require('jquery');
    var BaseView = require('oroui/js/app/views/base/view');

    MobileEmailNotificationView = BaseView.extend({
        /**
         * @type {number}
         */
        countNewEmail: null,

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            MobileEmailNotificationView.__super__.initialize.apply(this, options);
            this.countNewEmail = parseInt(options.countNewEmail);
        },

        /**
         * @inheritDoc
         */
        render: function() {
            var $emailsMenuItem = $('#user-menu .oro-email-user-emails a');
            this.$counter = $('<span class="email-count"/>').appendTo($emailsMenuItem);
            this.setCount(this.countNewEmail);
        },

        remove: function() {
            this.$counter.remove();
            MobileEmailNotificationView.__super__.remove.apply(this);
        },

        setCount: function(count) {
            this.countNewEmail = count = parseInt(count);
            if (count === 0) {
                count = '';
            } else {
                count = '(' + (count > 10 ? '10+' : count) + ')';
            }
            this.$counter.html(count);
            $('#user-menu .dropdown-toggle').toggleClass('has-new-emails', Boolean(this.countNewEmail));
        },

        showNotification: function() {
            // nothing to do in mobile version
        }
    });

    return MobileEmailNotificationView;
});
