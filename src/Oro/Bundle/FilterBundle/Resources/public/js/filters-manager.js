define(function(require) {
    'use strict';

    var FiltersManager;
    var DROPDOWN_TOGGLE_SELECTOR = '[data-toggle=dropdown]';
    var $ = require('jquery');
    var _ = require('underscore');
    var __ = require('orotranslation/js/translator');
    var mediator = require('oroui/js/mediator');
    var tools = require('oroui/js/tools');
    var BaseView = require('oroui/js/app/views/base/view');
    var MultiselectDecorator = require('./multiselect-decorator');
    var filterWrapper = require('./datafilter-wrapper');

    /**
     * Defines parent element for dropdown-menu by toggle element
     * (this method is taken from Bootstrap-Dropdown)
     *
     * @param {jQuery} $toggle
     * @returns {*|jQuery|HTMLElement}
     */
    function getDropdownMenuParent($toggle) {
        var $parent;
        var selector = $toggle.attr('data-target');
        if (!selector) {
            selector = $toggle.attr('href');
            selector = selector && /#/.test(selector) && selector.replace(/.*(?=#[^\s]*$)/, ''); //strip for ie7
        }
        $parent = selector && $(selector);
        if (!$parent || !$parent.length) {
            $parent = $toggle.parent();
        }
        return $parent;
    }

    /**
     * View that represents all grid filters
     *
     * @export  orofilter/js/filters-manager
     * @class   orofilter.FiltersManager
     * @extends BaseView
     *
     * @event updateList    on update of filter list
     * @event updateFilter  on update data of specific filter
     * @event disableFilter on disable specific filter
     */
    FiltersManager = BaseView.extend({
        /**
         * List of filter objects
         *
         * @type {Object}
         * @property
         */
        filters: null,

        /**
         * Template selector
         */
        templateSelector: '#filter-container',

        /**
         * Template
         */
        template: null,

        /**
         * Filter list input selector
         *
         * @property
         */
        filterSelector: '[data-action=add-filter-select]',

        /**
         * Add filter button hint
         *
         * @property
         */
        addButtonHint: __('oro_datagrid.label_add_filter'),

        /**
         * Select widget object
         *
         * @property {oro.MultiselectDecorator}
         */
        selectWidget: null,

        /**
         * ImportExport button selector
         *
         * @property
         */
        buttonSelector: '.ui-multiselect.filter-list',

        /** @property */
        events: {
            'change [data-action=add-filter-select]': '_onChangeFilterSelect',
            'click .reset-filter-button': '_onReset',
            'click a.dropdown-toggle': '_onDropdownToggle'
        },

        /**
         * Initialize filter list options
         *
         * @param {Object} options
         * @param {Object} [options.filters]
         * @param {String} [options.addButtonHint]
         */
        initialize: function(options) {
            var filterListeners;

            this.template = _.template($(this.templateSelector).html());

            this.filters = {};

            if (options.filters) {
                _.extend(this.filters, options.filters);
            }

            filterListeners = {
                'update': this._onFilterUpdated,
                'disable': this._onFilterDisabled
            };

            if (tools.isMobile()) {
                filterListeners.updateCriteriaClick = this._onUpdateCriteriaClick;
                $('body').on('click.' + this.cid, DROPDOWN_TOGGLE_SELECTOR, _.bind(this._onBodyClick, this));
            }

            _.each(this.filters, function(filter) {
                if (filter.wrappable) {
                    _.extend(filter, filterWrapper);
                }

                this.listenTo(filter, filterListeners);
            }, this);

            if (options.addButtonHint) {
                this.addButtonHint = options.addButtonHint;
            }

            FiltersManager.__super__.initialize.apply(this, arguments);
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            $('body').off('.' + this.cid);
            _.each(this.filters, function(filter) {
                filter.dispose();
            });
            delete this.filters;
            if (this.selectWidget) {
                this.selectWidget.dispose();
                delete this.selectWidget;
            }
            FiltersManager.__super__.dispose.call(this);
        },

        /**
         * Triggers when filter is updated
         *
         * @param {oro.filter.AbstractFilter} filter
         * @protected
         */
        _onFilterUpdated: function(filter) {
            this.trigger('updateFilter', filter);
        },

        /**
         * Triggers when filter is disabled
         *
         * @param {oro.filter.AbstractFilter} filter
         * @protected
         */
        _onFilterDisabled: function(filter) {
            this.trigger('disableFilter', filter);
            this.disableFilter(filter);
        },

        /**
         * Returns list of filter raw values
         */
        getValues: function() {
            var values = {};
            _.each(this.filters, function(filter) {
                if (filter.enabled) {
                    values[filter.name] = filter.getValue();
                }
            }, this);

            return values;
        },

        /**
         * Sets raw values for filters
         */
        setValues: function(values) {
            _.each(values, function(value, name) {
                if (_.has(this.filters, name)) {
                    this.filters[name].setValue(value);
                }
            }, this);
        },

        /**
         * Triggers when filter select is changed
         *
         * @protected
         */
        _onChangeFilterSelect: function() {
            this.trigger('updateList', this);
            this._processFilterStatus();
        },

        /**
         * Enable filter
         *
         * @param {oro.filter.AbstractFilter} filter
         * @return {*}
         */
        enableFilter: function(filter) {
            return this.enableFilters([filter]);
        },

        /**
         * Disable filter
         *
         * @param {oro.filter.AbstractFilter} filter
         * @return {*}
         */
        disableFilter: function(filter) {
            return this.disableFilters([filter]);
        },

        /**
         * Enable filters
         *
         * @param filters []
         * @return {*}
         */
        enableFilters: function(filters) {
            if (_.isEmpty(filters)) {
                return this;
            }
            var optionsSelectors = [];

            _.each(filters, function(filter) {
                filter.enable();
                optionsSelectors.push('option[value="' + filter.name + '"]:not(:selected)');
            }, this);

            var options = this.$(this.filterSelector).find(optionsSelectors.join(','));
            if (options.length) {
                options.attr('selected', true);
            }

            if (optionsSelectors.length) {
                this.selectWidget.multiselect('refresh');
            }

            return this;
        },

        /**
         * Disable filters
         *
         * @param filters []
         * @return {*}
         */
        disableFilters: function(filters) {
            if (_.isEmpty(filters)) {
                return this;
            }
            var optionsSelectors = [];

            _.each(filters, function(filter) {
                filter.disable();
                optionsSelectors.push('option[value="' + filter.name + '"]:selected');
            }, this);

            var options = this.$(this.filterSelector).find(optionsSelectors.join(','));
            if (options.length) {
                options.removeAttr('selected');
            }

            if (optionsSelectors.length) {
                this.selectWidget.multiselect('refresh');
            }

            return this;
        },

        /**
         * Render filter list
         *
         * @return {*}
         */
        render: function() {
            var $container = $(this.template({filters: this.filters}));
            this.setElement($container);

            var fragment = document.createDocumentFragment();

            _.each(this.filters, function(filter) {
                filter.render();
                if (!filter.enabled) {
                    filter.hide();
                }
                fragment.appendChild(filter.$el.get(0));
            }, this);

            this.trigger('rendered');

            if (_.isEmpty(this.filters)) {
                $container.hide();
            } else {
                $container.find('.filter-container').append(fragment);
                this._initializeSelectWidget();
            }

            return this;
        },

        /**
         * Initialize multiselect widget
         *
         * @protected
         */
        _initializeSelectWidget: function() {
            this.selectWidget = new MultiselectDecorator({
                element: this.$(this.filterSelector),
                parameters: {
                    multiple: true,
                    selectedList: 0,
                    selectedText: this.addButtonHint,
                    classes: 'filter-list select-filter-widget',
                    open: $.proxy(function() {
                        this.selectWidget.onOpenDropdown();
                        this._setDropdownWidth();
                        this._updateDropdownPosition();
                    }, this)
                }
            });

            this.selectWidget.setViewDesign(this);
            this.$('.filter-list span:first').replaceWith(
                '<a class="add-filter-button" href="javascript:void(0);">' + this.addButtonHint +
                    '<span class="caret"></span></a>'
            );
        },

        /**
         * Set design for select dropdown
         *
         * @protected
         */
        _setDropdownWidth: function() {
            var widget = this.selectWidget.getWidget();
            var requiredWidth = this.selectWidget.getMinimumDropdownWidth() + 24;
            widget.width(requiredWidth).css('min-width', requiredWidth + 'px');
            widget.find('input[type="search"]').width(requiredWidth - 30);
        },

        /**
         * Activate/deactivate all filter depends on its status
         *
         * @protected
         */
        _processFilterStatus: function() {
            var activeFilters = this.$(this.filterSelector).val();

            _.each(this.filters, function(filter, name) {
                if (!filter.enabled && _.indexOf(activeFilters, name) !== -1) {
                    this.enableFilter(filter);
                } else if (filter.enabled && _.indexOf(activeFilters, name) === -1) {
                    this.disableFilter(filter);
                }
            }, this);

            this._updateDropdownPosition();
        },

        /**
         * Set dropdown position according to current element
         *
         * @protected
         */
        _updateDropdownPosition: function() {
            var button = this.$(this.buttonSelector);
            var buttonPosition = button.offset();
            var widgetWidth = this.selectWidget.getWidget().outerWidth();
            var windowWidth = $(window).width();
            var widgetLeftOffset = buttonPosition.left;
            if (buttonPosition.left + widgetWidth > windowWidth) {
                widgetLeftOffset = buttonPosition.left + button.outerWidth() - widgetWidth;
            }

            this.selectWidget.getWidget().css({
                top: buttonPosition.top + button.outerHeight(),
                left: widgetLeftOffset
            });
        },

        /**
         * Reset button click handler
         */
        _onReset: function() {
            mediator.trigger('datagrid:doReset:' + this.collection.inputName);
        },

        /**
         * Dropdown button toggle handler
         * @param e
         * @private
         */
        _onDropdownToggle: function(e) {
            var $dropdown = this.$('.dropdown');
            e.preventDefault();
            e.stopPropagation();
            if (!$dropdown.hasClass('oro-open')) {
                // closes other dropdown-menus
                $(DROPDOWN_TOGGLE_SELECTOR).each(function() {
                    getDropdownMenuParent($(this)).removeClass('open');
                });
            }
            $dropdown.toggleClass('oro-open');
        },

        /**
         * Handles click on body element
         * closes the filters-dropdown if event target does not belong to the view element
         *
         * @param {jQuery.Event} e
         * @protected
         */
        _onBodyClick: function(e) {
            if (!_.contains($(e.target).parents(), this.el)) {
                this.closeDropdown();
            }
        },

        /**
         * Closes dropdown on mobile
         */
        closeDropdown: function() {
            this.$('.dropdown').removeClass('oro-open');
        },

        /**
         * On mobile closes filter box if value is changed
         */
        _onUpdateCriteriaClick: function(filter) {
            filter.once('update', this.closeDropdown, this);
            _.defer(_.bind(filter.off, filter, 'update', this.closeDropdown, this));
        }
    });

    return FiltersManager;
});
