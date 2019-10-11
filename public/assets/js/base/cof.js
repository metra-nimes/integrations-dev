/**
 * Convertful Options Framework
 *
 * @requires jQuery.fn.cfMod
 */
!function($, undefined){
	'use strict';
	if (window.$cof === undefined) window.$cof = {};
	if (window.$cof.mixins === undefined) window.$cof.mixins = {};

	/**
	 * Class mutator, allowing on, off, and trigger class instance events
	 * @type {{}}
	 */
	$cof.mixins.Events = {
		/**
		 * Attach a handler to an event for the class instance
		 * @param {String} handle A string containing event type, such as 'beforeShow' or 'change'
		 * @param {Function} fn A function to execute each time the event is triggered
		 */
		on: function(handle, fn){
			if (this.$$events === undefined) this.$$events = {};
			if (this.$$events[handle] === undefined) this.$$events[handle] = [];
			this.$$events[handle].push(fn);
			return this;
		},
		/**
		 * Remove a previously-attached event handler from the class instance
		 * @param {String} handle A string containing event type, such as 'beforeShow' or 'change'
		 * @param {Function} [fn] The function that is to be no longer executed.
		 * @chainable
		 */
		off: function(handle, fn){
			if (this.$$events === undefined || this.$$events[handle] === undefined) return this;
			if (fn !== undefined){
				var handlerPos = $.inArray(fn, this.$$events[handle]);
				if (handlerPos !== -1){
					this.$$events[handle].splice(handlerPos, 1);
				}
			} else {
				this.$$events[handle] = [];
			}
			return this;
		},
		/**
		 * Execute all handlers and behaviours attached to the class instance for the given event type
		 * @param {String} eventType A string containing event type, such as 'beforeShow' or 'change'
		 * @param {Array} extraParameters Additional parameters to pass along to the event handler
		 * @chainable
		 */
		trigger: function(eventType, extraParameters){
			if (this.$$events === undefined || this.$$events[eventType] === undefined || this.$$events[eventType].length === 0) return this;
			var eventsCount = this.$$events[eventType].length; //prevent loop then add event inside event
			for (var index = 0; index < eventsCount; index++){
				this.$$events[eventType][index].apply(this, extraParameters);
			}
			return this;
		}
	};

	/**
	 * $cof.Field class
	 * Boundable events: beforeShow, afterShow, change, beforeHide, afterHide
	 * @param row
	 * @param noInit bool Don't init on load. Instead the field will be inited on beforeShow event
	 * @constructor
	 */
	$cof.Field = function(row, noInit){
		this.$row = $(row);
		if (this.$row.data('cof_field')) return this.$row.data('cof_field');
		this.type = this.$row.cfMod('type');
		this.name = this.$row.data('name');
		this.$input = this.$row.find('input[name="' + this.name + '"], textarea[name="' + this.name + '"], select[name="' + this.name + '"]');
		this.inited = false;

		// Overloading by a certain type's declaration, moving parent functions to "parent" namespace: init => parentInit
		if ($cof.Field[this.type] !== undefined){
			for (var fn in $cof.Field[this.type]){
				if (!$cof.Field[this.type].hasOwnProperty(fn)) continue;
				if (this[fn] !== undefined){
					var parentFn = 'parent' + fn.charAt(0).toUpperCase() + fn.slice(1);
					this[parentFn] = this[fn];
				}
				this[fn] = $cof.Field[this.type][fn];
			}
		}

		this.$row.data('cof_field', this);

		if (noInit !== undefined && noInit){
			// Init on the first show
			var initEvent = function(){
				this.init();
				this.trigger('afterInit');
				this.inited = true;
				this.off('beforeShow', initEvent);
			}.bind(this);
			this.on('beforeShow', initEvent);
		} else {
			this.init();
			this.trigger('afterInit');
			this.inited = true;
		}
	};
	$.extend($cof.Field.prototype, $cof.mixins.Events, {
		init: function(){
			this.$input.on('change', function(){
				this.trigger('change', [this.getValue()]);
			}.bind(this));
		},
		deinit: function(){
		},
		getValue: function(){
			return this.$input.val();
		},
		setValue: function(value, quiet){
			quiet = quiet || false;
			this.$input.val(value);
			this.render();
			if (!quiet)
				this.trigger('change', [value]);
		},
		getBuilderPreview: function(){
			if (this.builder_preview === undefined){
				var $builder_preview = this.$row.find('> .cof-form-row-builderpreview');
				this.builder_preview = $builder_preview.length ? $builder_preview[0].onclick() : {};
			}

			return (this.builder_preview instanceof Array) ? this.builder_preview : [this.builder_preview];
		},
		render: function($elm){
		},
		clearError: function(){
			this.$row.removeClass('check_wrong');
			this.$row.find('> .cof-form-row-field > .cof-form-row-state').html('');
		},
		showError: function(message){
			this.$row.addClass('check_wrong');
			this.$row.find('> .cof-form-row-field > .cof-form-row-state').html(message);
		},
		affect: function(influenceValue){
		}
	});

	/**
	 * $cof.Field type: alert
	 */
	$cof.Field['alert'] = {
		init: function(){
			new CFAlert(this.$row);
		},
		getValue: function(){
			return null;
		},
		setValue: function(){
			return null;
		}
	};

	/**
	 * $cof.Field type: formfields
	 */
	$cof.Field['formfields'] = {
		init: function(){
			this.$container = this.$row.find('.cof-formfields');
			this.$list = this.$container.find('.cof-formfields-list');
			this._events = {};
			// Button to add items
			this.$addBtn = {};
			this.$container.find('.cof-list-add .cof-list-item').each(function(_, btn){
				var $btn = $(btn),
					type = $btn.data('type');
				this.$addBtn[type] = $btn.on('click', this.addItem.bind(this, {type:type}));
			}.bind(this));
			// Add Field Dropdown
			this._events = {
				toggleDropdown: function(){
					if ( ! this.$addList.hasClass('is-hidden')) return this.hideDropdown();
					this.$addList.removeClass('is-hidden');
					$cf.$window.on('mouseup touchstart touchstart', this._events.hideDropdown);
				}.bind(this),
				hideDropdown: function(e){
					if (this.$addContainer[0].contains(e.target)) return;
					e.stopPropagation();
					e.preventDefault();
					this.hideDropdown();
				}.bind(this)
			};
			this.$addContainer = this.$container.find('.cof-list-add');
			this.$container.find('.cof-list-add > .g-btn').on('click', this._events.toggleDropdown);
			this.$addList = this.$container.find('.cof-list-add > .cof-list');
			this.$container.on('click', '.g-action.action_edit', function(e){
				var $item = $(e.target).closest('.cof-formfields-item');
				this.maybeInitItem($item.data('name'));
				$item.toggleClass('is-active');
			}.bind(this));
			this.$container.on('click', '.g-actions > .g-action.action_delete', function(e){
				this.removeItem($(e.target).closest('.cof-formfields-item').data('name'));
			}.bind(this));
			// Items and their fieldsets
			this.$items = {};
			this.$list.children('.cof-formfields-item').each(function(_, item){
				this.$items[item.getAttribute('data-name')] = $(item);
			}.bind(this));
			this.items = {};
			// Sortable behavior
			this.sortable = dragula([this.$list[0]], {
				direction: 'vertical',
				moves: function (el, container, handle) {
					return (handle.classList.contains('cof-formfields-item-head') || handle.classList.contains('cof-formfields-item-head-title'));
				}
			});
			this.sortable
				.on('drag', function () {
					this.shouldToggle = false;
					this.prevValueJson = JSON.stringify(this.getValue());
				}.bind(this))
				.on('drop', function () {
					var value = this.getValue();
					if (this.prevValueJson === JSON.stringify(value)) return;
					this.maybeTriggerChange();
					this.trigger('itemsReorder', [value]);
				}.bind(this));

			// Toggling on head click (but not on drag)
			this.$container.on('mousedown mouseup', '.cof-formfields-item-head', function(e){
				if (e.target.className.indexOf('g-action') !== -1) return;
				if (e.type === 'mousedown'){
					this.shouldToggle = true;
				}
				else if (e.type === 'mouseup' && this.shouldToggle){
					var $item = $(e.target).closest('.cof-formfields-item');
					this.maybeInitItem($item.data('name'));
					$item.toggleClass('is-active');
				}
			}.bind(this));
		},
		hideDropdown: function(){
			this.$addList.addClass('is-hidden');
			$cf.$window.off('mouseup touchstart touchstart', this._events.hideDropdown);
		},
		isSingularType: function(type){
			return (this.$addBtn[type] && this.$addBtn[type].data('singular'));
		},

		addItem: function(values){
			var type = values.type;
			var source = values.source || '';
			this.hideDropdown();
			var existingAllFields = this._getWidgetAllFields() || [];
			if (this.isSingularType(type)){
				if (existingAllFields.indexOf(type) !== -1 && source !== 'html_import') return;
				// Cannot be added twice
				this.$addBtn[type].css('cssText','display: none !important');
			}
			// Generating new unique name based on index
			var name,
				index = '';
			while (existingAllFields.indexOf(name = type + index) !== -1){
				index = (index === '') ? 2 : (index + 1);
			}
			// if only "type" setting pass
			if (Object.keys(values).length === 1){
				values = $.extend({}, arrayPath($cf.builder.defaults, 'form.fields.'+type, {}));
				values.name = type + (index === '' ? '' :  index );
				values.label = values.label + (index === '' ? '' :  ' '+index );
			}
			if(values.name !== undefined)
			{
				// Generate unique key with timestamp for items object
				name = values.name+'_'+new Date().getTime().toString().substr(-6);
			}
			this.$items[name] = this.$container.find('.cof-formfields-templates .cof-formfields-item[data-type="'+type+'"]')
				.clone()
				.attr('data-name', name)
				.appendTo(this.$list);
			// Fix labels;
			var $fixLabels = this.$items[name].find('.cof-switcher');
			$.each($fixLabels, function (i, el) {
				var $el = $(el),
					$fixLabel,
					// If all the same there will be errors then change to: unique_index = '_'+((new Date()).getTime()).toString(16)
					unique_index = '_'+name+'_'+i;
				$fixLabel = $el.find('input[type="checkbox"]');
				$fixLabel.attr('id', $fixLabel.attr('id') + unique_index);
				$fixLabel = $el.find('label');
				$fixLabel.attr('for', $fixLabel.attr('for') + unique_index);
			});

			this.maybeInitItem(name);
			this.items[name].setValues(values);
			//this.syncItemTitle(name);
			this.trigger('itemAdd', [name]);
		},
		maybeInitItem: function(name){
			if (this.items[name] !== undefined) return;
			this.items[name] = new $cof.Fieldset(this.$items[name].find('.cof-formfields-item-body'));
			this.items[name].on('change', function(key, value){
				// Set item title after change
				if (!this._preventChanges){
					this.syncItemTitle(name);
					this.maybeTriggerChange();
					this.trigger('itemChange', [name, key, value]);
				}

				if (key === 'name'){
					// Allow only small a-z letters digit and underscore
					// TODO uncomment later
					//value = ('' + value).toLowerCase().replace(/[^a-z0-9_]/, '');
					//this.items[name].setValue('name', value, true);

					if (!value.length)
						this.items[name].fields['name'].showError('Field name can\'t be empty');
					else
						this.items[name].fields['name'].clearError();
				}
			}.bind(this));
		},
		syncItemTitle: function(name){
			var title = '';
			$.each(this.items[name].fields, function(fieldName, field){
				if (field.type === 'switcher' || field.name === 'name' || ! this.items[name].fieldIsVisible(fieldName))
					return true; // continue loop

				var value = field.getValue();

				// field not init yet, stop
				if (value === undefined)
					return true;

				if (field.type === 'varlist')
				{
					value = arrayFlatten(value);
					if (value.length)
						value = value[0];
				}

				title = $cf.helpers.stripTags('' + value );
				return false; //break loop
			}.bind(this));

			if (title === ''){
				// set type as title
				title = name.replace(/[0-9]+$/, '');
				title = title.charAt(0).toUpperCase() + title.slice(1)
			}

			this.$items[name].find('.cof-formfields-item-head-title').text(title);
		},
		removeItem: function(name){
			if (Object.keys(this.$items).length <= 1) return;
			this.$items[name].remove();
			delete this.$items[name];
			delete this.items[name];
			var type = name.replace(/[0-9]+$/, '');
			if (this.isSingularType(type)) this.$addBtn[type].show();
			if (!this._preventChanges) this.maybeTriggerChange();
			this.trigger('itemRemove', [name]);
		},
		getItemValue: function(name){
			this.maybeInitItem(name);
			var values = this.items[name].getValues();
			values.type = this.$items[name].cfMod('type');
			if (! values.name && ! this.isSingularType(values.type))
				values.name = name;
			return values;
		},
		setItemValue: function(name, key, value, quiet){
			this.maybeInitItem(name);
			this.items[name].setValue(key, value, quiet);
		},
		getValue: function(){
			// Storing as a ordered object (list of key-value tuples)
			var value = [];
			this.$list.children('.cof-formfields-item').each(function(_, item){
				var name = $(item).data('name');
				value.push(this.getItemValue(name));
			}.bind(this));
			return value;
		},
		setValue: function(value){
			this._preventChanges = true;
			var oldValue = this.getValue();
			if (JSON.stringify(value) === JSON.stringify(oldValue)) {
				this._preventChanges = false;
				return;
			}
			$.each(this.$addBtn, function (item) {
				this.$addBtn[item].removeAttr('style');
			}.bind(this));
			this.items = {};
			this.$items = {};
			this.$list.empty();
			$.each(value, function (_, itemValues) {
				this.addItem(itemValues);
			}.bind(this));
			this._preventChanges = false;
		},
		/**
		 * Trigger forms changes, but not too often: changing on first call, then not sooner than in a 1000ms after the
		 * last action
		 */
		maybeTriggerChange: function(){
			var lastChangeTime = (this.lastChangeTime || 0),
				curChangeTime = Date.now();
			this.lastChangeTime = curChangeTime;
			clearTimeout(this.nextTriggerChangeTimer);

			// Throttling too often requests
			if (curChangeTime - lastChangeTime < 1000){
				this.nextTriggerChangeTimer = setTimeout(this.maybeTriggerChange.bind(this), 1000);
				return;
			}

			this.trigger('change', [this.getValue()]);
		},

		_getTypeByName: function(name){
			return name.replace(/[0-9]+$/, '');
		},

		/**
		 * Getting all custom widget fields
		 * @return array
		 */
		_getWidgetAllFields: function(){
			var fields = [],
				builder = $cf.builder;
			$.each(builder.getValue('data'), function(type, item){
				if (this.getElmType(type) === 'form'){
					$.each(item.fields, function(_, field) {
						if (field.name !== undefined){
							fields.push(field.name);
						}
					});
				}
			}.bind(builder));
			return fields;
		}
	};

	/**
	 * $cof.Field type: chance
	 */
	$cof.Field['chance'] = {
		init: function() {
			// Variables
			this._data = [];
			this._value = '';
			this._options = this._genRangeOptions(0, 100, 10);
			// Elements
			this.$container = this.$row.find('.cof-chance');
			this.$header = this.$container.find('.cof-chance-header');
			this.$list = this.$container.find(' > .cof-chance-list');
			this.$template = this.$container.find(' > .cof-chance-row');
			// Handlers
			this._events = {
				/**
				 * Change data
				 * @param {object} e
				 */
				change: function(e) {
					var $target = $(e.currentTarget),
						id = $target.closest('.cof-chance-row').data('id');
					this._data[id][$target.attr('name')] = $target.val();
					if ($target.is('select')) {
						this._calcGravityPrecent();
						this.trigger('change', []);
						$target.blur();
					}
					this.trigger('change',[this.getValue()]);
				}.bind(this),
				/**
				 * Getting a focus (Required for Spin to Win)
				 * @param {object} e jQueryEventObject
				 */
				focus: function(e) {
					var $row = $(e.currentTarget),
						$target = $(e.target);
					if ($target.attr('name') === 'text')
						$target[0].scrollLeft = $target[0].scrollWidth;
					this.trigger('chance.focus', [ $row.data('id') ]);
				}.bind(this),
				/**
				 * Input event
				 * @param {object} e
				 */
				input: function(e) {
					var $target = $(e.currentTarget),
						value = $target.val();
					$target.val(value).attr('title', value);
					this._events.change(e);
				}.bind(this),
				/**
				 * Add row
				 */
				add: function() {
					this.addRow();
					this.trigger('change', [this.getValue()]);
				}.bind(this),
				/**
				 * Delete row
				 * @param {object} e
				 */
				deleteRow: function(e) {
					if(this.getValue().length < 3) return;
					var $row = $(e.currentTarget).closest('.cof-chance-row');
					delete this._data[$row.data('id')];
					$row.remove();
					this._calcGravityPrecent();
					this.trigger('change', [this.getValue()]);
				}.bind(this)
			};
			// Watch Events
			this.$container.on('click', '.type_add', this._events.add);
		},
		/**
		 * Add row
		 * @param {number} id
		 * @param {object} params
		 */
		addRow: function(id, params){
			var $row = this.$template.clone(),
				params = params || {};
			if(id === undefined) {
				id = this._data.length;
			}
			this._data[id] = {};
			$row
				.data('id', id)
				.removeClass('is-hidden')
				.find('select, input[type="text"]')
				.each(function(_, el) {
					var $el = $(el),
						name = $el.attr('name');
					if($el.is('select')) $el.html(this._options).val(100);
					if(params.hasOwnProperty(name)) {
						$el.val(params[name]);
						if($el.is('input')) $el.attr('title', params[name]);
					}
					this._data[id][name] = $el.val();
				}.bind(this));
			$row
				.on('click', 'a.action_delete', this._events.deleteRow)
				.on('input', 'input', this._events.input)
				.on('change', 'select', this._events.change)
				.on('click', this._events.focus)
			this.$list.append($row);
			this._calcGravityPrecent();
		},
		/**
		 * Generate a range of numbers
		 * @param {Number} from
		 * @param {Number} to
		 * @param {Number} step
		 * @return {string}
		 */
		_genRangeOptions: function(from, to, step) {
			var length = ~~((to - from) / step) + 1,
				result = '';
			for (var i = 0; i < length; i++) {
				var value = (from + i) * step;
				result += '<option value="'+value+'">'+value+'</option>';
			}
			return result;
		},
		/**
		 * Gravity percent calculations
		 */
		_calcGravityPrecent: function() {
			var sum = 0,
				$rows = this.$list.find('.cof-chance-row select');
			for (var k in this._data) {
				if (this._data[k].hasOwnProperty('gravity')) {
					sum += parseInt(this._data[k].gravity);
				}
			}
			$rows.each(function(_, select) {
				var $select = $(select),
					value = parseInt($select.val()),
					precent = ((value / sum) * 100),
					digits = (precent.toString().split('.')[1]) ? 1 : 0;
				$select
					.closest('.cof-chance-row-select')
					.attr('data-precent', value +'/'+ precent.toFixed(digits) + '%');
			});
		},
		/**
		 * Get value
		 * @raturn {array}
		 */
		getValue: function() {
			return this._data.filter(function (item) {
				return item !== undefined;
			});
		},
		/**
		 * Set value
		 * @param {object} value
		 * @param {boolean} quite
		 */
		setValue: function(value, quite) {
			this.$list.html('');
			$.each(value, this.addRow.bind(this));
			if ( ! quite)
				this.trigger('change', [value]);
		},
	};

	/**
	 * $cof.Field type: palette
	 */
	$cof.Field['palette'] = {
		init: function() {
			// ELements
			this.$container = this.$row.find('.cof-palette');
			this.$colors = this.$container.find('.cof-palette-row-colors');
			// Handlers
			this._events = {
				selected: function(e) {
					var $target = $(e.currentTarget);
					if ($target.hasClass('active')) return;
					var value = $target.get(0).onclick();
					this.$colors.removeClass('active');
					$target.addClass('active');
					this.$container.data('value', value);
					this.trigger('change', [value]);
				}.bind(this),
				/**
				 * Copy color to clipboard
				 * @param {object} e jQueryEventObject
				 */
				copyToClipboard: function(e) {
					var $target = $(e.currentTarget);
					if( ! $target.closest('.cof-palette-row-colors').hasClass('active')) {
						return;
					}
					$cf.copyToClipboard($target.data('color'));
				},
			};
			// Watch events
			this.$colors
				.on('click', this._events.selected)
				.on('click', '[data-color]', this._events.copyToClipboard);
		},
		/**
		 * Get value
		 * @return {array}
		 */
		getValue: function() {
			return this.$container.data('value') || [];
		},
		/**
		 * Set value
		 * @param {array} value
		 * @param {boolean} quite
		 */
		setValue: function(value, quite) {
			var _value = JSON.stringify(value);
			this.$colors
				.removeClass('active')
				.each(function(_, palette) {
					var paletteValue = palette.onclick();
					if (_value === JSON.stringify(paletteValue)) {
						$(palette).addClass('active');
					}
				});
			if ( ! quite)
				this.trigger('change', [value]);
		},
	};

	/**
	 * $cof.Field type: automations
	 */
	$cof.Field['automations'] = {
		init: function() {
			this.reinit();
			this._events = {
				changeWrapperTitle: function() {
					// Counter
					var count = Object.keys(this.getValue()).length;
					this.$wrapperTitle
						.trigger('refreshCounter', [{
							counter: count
						}]);
				}.bind(this),
				chooseOrCloseShortcodes: function(e){
					if (e.target.className === 'g-shortcodes-item') {
						var $el = $(e.target);
						$el.closest('.g-shortcodes').prev()
							.val($el.data('value'))
							// Properly sending a signal that the value is changed
							.trigger('change');
					}
					this.$row.find('.g-shortcodes').removeClass('is-active');
					$cf.$window.off('click', this._events.chooseOrCloseShortcodes);
				}.bind(this)
			};

			this.on('change', this._events.changeWrapperTitle);
			this.on('itemChange', this._events.changeWrapperTitle);
			this._events.changeWrapperTitle();

			// There's only one list for the whole automations that we put and show on demand
			this.$shortcodesList = this.$container.find('.g-shortcodes-list:last').detach();
			this.$shortcodeInput = null;
			this.$container.on('click', '.g-shortcodes-btn', function(e){
				var $btn = $(e.currentTarget),
					$shortcodesContainer = $btn.closest('.g-shortcodes');
				if ($shortcodesContainer.hasClass('is-active')) return $shortcodesContainer.removeClass('is-active');
				this.$shortcodeInput = $shortcodesContainer.prev();
				this.$shortcodesList.insertAfter($btn);
				// set correct width
				this.$shortcodesList.width(this.$shortcodeInput.outerWidth());
				// Showing ALL values (no need to filter them. hiding the current value!)
				$shortcodesContainer.addClass('is-active');
				if ($btn.closest('.cof-automations-item').data('type') !== 'send_data')
				{
					var form_names = [],
						shortcode_value;
					$('.conv-form input').each(function(){
						form_names.push($(this).attr('name'));
					});
					$shortcodesContainer.find('.g-shortcodes-list:last .g-shortcodes-item').each(function(){
						shortcode_value = $(this).data('value').replace(/\s|\{|\}/g,"");
						if ($.inArray(shortcode_value, form_names ) !== -1)
						{
							$(this).remove();
						}
					})
				}
				$cf.$window.on('click', this._events.chooseOrCloseShortcodes);
				// To prevent window event from executing
				e.stopPropagation();
			}.bind(this));
		},
		reinit: function() {
			this.$container = this.$row
				.find('.cof-automations');

			this.$addContainer = this.$container.find('.cof-list-add');
			this.$action = this.$addContainer.find('> .g-btn.action_dropdown');
			this.$dropdown = this.$addContainer.find('> .cof-automations-dropdown');
			this.$list = this.$container.find('> .cof-automations-list');
			this.$list
				.on('click', '.g-actions > .g-action.action_delete', function(e) {
					this.removeItem($(e.target)
						.closest('.cof-automations-item')
						.data('name'));
				}.bind(this));
			this.$wrapperTitle = this.$row
					.closest('.cof-form-wrapper')
					.find('.cof-form-wrapper-title');

			this.$dropdown
				.select2({
					dropdownCssClass: 'cof-automations-dropdown-results',
					templateResult: function(state){
						if (state.element && state.element.className.indexOf('is-locked') !== -1){
							return $('<a class="g-action action_disabled type_label" href="/premium/upgrade/" target="_blank">'+state.text+'</a>');
						}
						return state.text;
					},
					matcher: function modelMatcher(params, data){
						data.parentText = data.parentText || "";

						if ($.trim(params.term) === '') {
							return data;
						}

						if (data.children && data.children.length > 0) {
							var match = $.extend(true, {}, data);

							for (var c = data.children.length - 1; c >= 0; c--) {
								var child = data.children[c];
								child.parentText += data.parentText + " " + data.text;

								var matches = modelMatcher(params, child);

								if (matches == null) {
									match.children.splice(c, 1);
								}
							}

							if (match.children.length > 0) {
								return match;
							}

							return modelMatcher(params, match);
						}

						var original = (data.parentText + ' ' + data.text).toUpperCase();
						var term = params.term.toUpperCase();

						if (original.indexOf(term) > -1) {
							return data;
						}

						return null;
					},
				})
				.on("select2:close", function () {
					this.$addContainer.removeClass('is-active');
				}.bind(this))
				.on('select2:select', function(e) {
					var option = arrayPath(e, 'params.data.element');
					if (option && option.className.indexOf('is-locked') !== -1){
						// Quick workaround
						var win = window.open('/premium/upgrade/', '_blank');
						win.focus();
						return false;
					}
					this.addItem(this.$dropdown.val(), undefined);
					this.$dropdown.val('').trigger('change');
				}.bind(this));

			this.$dropdown.val('').trigger('change');

			// Items and their fieldsets
			this.$items = {};
			this.items = {};

			this.$list.children('.cof-automations-item').each(function(_, item){
				var $item = $(item),
					name = $item.data('name');
				this.$items[name] = $item;
				this.maybeInitItem(name);
			}.bind(this));

			// Sortable behavior
			this.sortable = dragula([this.$list[0]], {
				direction: 'vertical',
				moves: function (el, container, handle) {
					return (handle.classList.contains('cof-automations-item-head') || handle.classList.contains('cof-automations-item-head-title'));
				}
			});
			this.sortable
				.on('drag', function () {
					this.shouldToggle = false;
					this.prevValueJson = JSON.stringify(this.getValue());
				}.bind(this))
				.on('drop', function () {
					var value = this.getValue();
					if (this.prevValueJson === JSON.stringify(value)) return;
					this.maybeTriggerChange();
					this.trigger('itemsReorder', [value]);
				}.bind(this));

			// Toggling on head click (but not on drag)
			this.$container.on('mousedown mouseup', '.cof-automations-item-head', function(e){
				if (e.target.className.indexOf('g-action') !== -1) return;
				if (e.type === 'mousedown'){
					this.shouldToggle = true;
				}
				else if (e.type === 'mouseup' && this.shouldToggle){
					var $item = $(e.target).closest('.cof-automations-item');
					this.maybeInitItem($item.data('name'));
					$item.toggleClass('is-active');
				}
			}.bind(this));

			this.$action.on('click', function () {
				this.$addContainer.addClass('is-active');
				this.$dropdown.select2("open");
			}.bind(this));

			this.maybeAddShortcodesButtons();

			// Watching the refresh buttons
			this.watchRefresh();
		},
		addItem: function(type, values) {
			var existingItems = [];
			this.$list
				.children('.cof-automations-item')
				.each(function(_, item){
					existingItems.push($(item).data('name'));
				}.bind(this));
			// Generating new unique name based on index
			var name,
				index = '';
			while (existingItems.indexOf(name = type + index) !== -1){
				index = (index === '') ? 2 : (index + 1);
			}

			var automationTemplate = this.$container
				.find('.cof-automations-templates .cof-automations-item[data-type="'+type+'"]');

			// stop if not found tempalte;
			if ( ! automationTemplate.length)
				return;

			// Preventing re-connection of default automation
			if (automationTemplate.data('default') && this.$items.hasOwnProperty(type))
				return;

			this.$items[name] = automationTemplate
				.clone()
				.attr('data-name', name)
				.appendTo(this.$list);

			if (values === undefined){
				values = {};
				values.name = values.name+ ' '+ (index === '' ? '' : index );
			}
			this.maybeInitItem(name);
			this.items[name].setValues(values);
			if (!this._preventChanges)
				this.maybeTriggerChange();
			this.trigger('itemAdd', [name]);
			this.watchRefresh();
			this._events.changeWrapperTitle();

			// scroll to new automation
			if (!this._preventChanges)
				this.$container.closest('.cof-form-wrapper-cont').animate({ scrollTop: this.$container.closest('.cof-form-wrapper-cont')[0].scrollHeight }, 'fast');
		},
		maybeInitItem: function(name) {
			if (this.items[name] !== undefined) return;
			(this.items[name] = new $cof.Fieldset(this.$items[name].find('.cof-automations-item-body')))
				.on('change', function(key, value) {
					if (key === 'name'){
						// Updating item head
						this.$items[name]
							.find('.cof-automations-item-head-title')
							.text(value);
					}
					if (!this._preventChanges) this.maybeTriggerChange();
					this.trigger('itemChange', [name, key, value]);
				}.bind(this));
		},
		removeItem: function(name){
			if (this.$items[name] === undefined)
				return;

			this.$items[name].remove();
			delete this.$items[name];
			delete this.items[name];
			if (!this._preventChanges)
				this.maybeTriggerChange();
			this.trigger('itemRemove', [name]);
		},
		getItemValue: function(name){
			this.maybeInitItem(name);
			return this.items[name].getValues();
		},
		setItemValue: function(name, key, value, quiet){
			this.maybeInitItem(name);
			this.items[name].setValue(key, value, quiet);
			if (quiet && key === 'name'){
				// Updating item head
				this.$items[name].find('.cof-automations-item-head-title').text(value);
			}
		},
		getValue: function() {
			// Storing an array of data to keep order
			var value = [];
			this.$list.children('.cof-automations-item').each(function(i, item){
				var name = $(item).data('name');
				value[i] = $.extend(this.getItemValue(name), {
					name: name
				});
			}.bind(this));
			return value;
		},
		setValue: function(value) {
			this._preventChanges = true;
			var oldValue = this.getValue();
			if (JSON.stringify(value) === JSON.stringify(oldValue)) {
				this._preventChanges = false;
				return;
			}
			$.each(this.$addBtn, function (item) {
				this.$addBtn[item].removeAttr('style');
			}.bind(this));
			this.items = {};
			this.$items = {};
			this.$list.empty();
			$.each(value, function (name, itemValues) {
				var type = (itemValues.name || name).replace(/[0-9]+$/, '');
				this.addItem(type, itemValues);
			}.bind(this));
			this._events.changeWrapperTitle();
			this._preventChanges = false;
			this.maybeAddShortcodesButtons();
		},
		/**
		 * Trigger forms changes.
		 */
		maybeTriggerChange: function(){
			this.$row.removeClass('check_wrong');
			this.trigger('change', [this.getValue()]);
		},
		/**
		 * Watching the refresh buttons
		 */
		watchRefresh: function() {
			this.$list.find('[class$=-control-refresh]:not(.watch)').each(function(_, element) {
				$(element).addClass('watch').on('click', function(event) {
					var $target = $(event.target);
					if($target.hasClass('loading')) return;
					var name = $target
						.addClass('loading')
						.closest('.cof-automations-item')
						.data('name');
					var paramName = Object.keys(this.getItemValue(name))[0];
					var widgetId = this.$container.closest('.type_widget').data('id');
					this.refreshAutomations(name, paramName, widgetId);
				}.bind(this));
			}.bind(this));
		},
		/**
		 * Update meta data
		 * @param name
		 * @param paramName
		 * @param widgetId
		 */
		refreshAutomations: function(name, paramName, widgetId){
			$.post('/api/widget/fetch_automation_options', {
				automation_name: name,
				param_name: paramName,
				widget_id: widgetId
			}, function(Res){
				this.$list
					.find('[class$=-control-refresh].watch')
					.removeClass('loading');
				if (!Res.success || Res.data.length === 0){
					return;
				}

				//this.$container.find('[data-name^="' + name + '"] .cof-automations-item-body').html(Res.data);
				var values = this.getValue();
				this.$container.find('.cof-automations-item[data-type="'+name.replace(/\d+$/, '')+'"]').each(function(_, item){
					var name = $(item).data('name');
					if (name)
						values[name] = this.getItemValue(name);
				}.bind(this));
				this.$container.find('.cof-automations-item[data-type="'+name.replace(/\d+$/, '')+'"] .cof-automations-item-body').html(Res.data);
				this.reinit();
				// Save Items values;
				this.setValue(values);
			}.bind(this), 'json');
		},
		maybeAddShortcodesButtons: function(){
			// This adds shortcodes buttons to all relevant fields except varlists, which are rendered by its templates
			this.$container.find('.i-haveshortcodes input[name="field_value"]').each(function(_, input){
				var $input = $(input);
				if ( ! $input.next().hasClass('g-shortcodes')){
					this.$container.find('> .g-shortcodes').clone().insertAfter($input).show();
				}
			}.bind(this));
		}
	};

	/**
	 * $cof.Field type: imgselect
	 */
	$cof.Field['imgselect'] = {
		init: function(){
			this.$container = this.$row.find('.cof-imgselect:first');
			this._events = {
				imageClick: function(e){
					var target = e.target.parentNode,
						newValue = (target.getAttribute('data-value') || '');
					if (newValue !== this.getValue()) this.setValue(newValue);
				}.bind(this)
			};
			this.$container.on('click.cof-imgselect', '> .cof-imgselect-image > img', this._events.imageClick);
			this.$container.find('.cof-imgselect-change').on('click', function(){
				this.setValue('');
			}.bind(this));
		},
		render: function(){
			var value = this.getValue();
			this.$container.toggleClass('has_selected', value !== '');
			this.$container.find('.cof-imgselect-image.selected').removeClass('selected');
			this.$container.find('.cof-imgselect-image[data-value="' + value + '"]').addClass('selected');
		}
	};

	/**
	 * $cof.Field type: oauth
	 */
	$cof.Field['oauth'] = {
		init: function(){
			this._events = {
				setValue: function(value){
					this.setValue(value);
					if (value !== ''){
						this.trigger('submit', [value]);
					}
				}.bind(this),
				openWindow: this.openWindow.bind(this)
			};
			this.$oauth = this.$row.find('.cof-oauth');
			this.$btn = this.$oauth.find('.g-btn');
			this.url = this.$oauth.data('url');
			this.$btn.on('click', this._events.openWindow);
			this.value = JSON.parse(this.$input.val());
			this.$input = this.$row.find('input[name="oauth"]');
		},
		setValue: function(value, quite){
			this.value = value;
			this.$input.val(JSON.stringify(value));
			this.render();
			if (!quite)
				this.trigger('change', [value]);
		},
		getValue: function(){
			return this.value;
		},
		openWindow: function(){
			window.cfSetOauthToken = this._events.setValue;
			var size = this.$oauth.data('size').split('x');
			this.window = window.open(this.url, 'IntegrationOAuth', 'location=0,status=0,width=' + size[0] + ',height=' + size[1]);
		}
	};

	/**
	 * $cof.Field type: radio
	 */
	$cof.Field['radio'] = {
		init: function() {
			if (this.$row.hasClass('style_inline')) {
				$.each(this.$row.find('.cof-radio'), function() {
					var $this = $(this);
					$this
						.attr('title', $this.find('.cof-radio-text')
						.text());
				});
			}
			this.$input
				.filter('[type=radio]')
				.on('change', function() {
					var value = this.getValue();
					this._activateRadio(value);
					this.trigger('change', [value]);
				}.bind(this));
		},
		getValue: function(){
			return $('[name="' + this.$input.attr('name') + '"]:checked', this.$row)
				.val();
		},
		setValue: function(value, quite){
			this._activateRadio(value);
			if (!quite)
				this.trigger('change', [value]);
		},
		_activateRadio: function(value) {
			this.$input
				.removeAttr('checked')
				.filter('[value="' + value + '"]')
				.prop('checked', true)
				.attr('checked', true)
				.parent()
				.addClass('active')
				.siblings()
				.removeClass('active');
		}
	};

	/**
	 * $cof.Field type: ruleset
	 */
	$cof.Field['ruleset'] = {
		init: function(){
			this.$inputs = {};
			this.value = '';
			this._events = {
				change: function(e) {
					var $target = $(e.currentTarget),
						$row = $target.closest('.cof-ruleset-row');
					if ($row.length && $row.data('name') === 'scroll') {
						var type = $row.find('[data-value].selected').data('value'),
							$input = $row.find('input[data-name="value"]');
						if (type === '%' && $input.val() > 100) $input.val(100).trigger('input');
					}
					var value = this.getValue();
					if (JSON.stringify(value) !== this.value){
						// Preventing excess event firings
						this.trigger('change', [value]);
						this.value = JSON.stringify(value);
					}
				}.bind(this)
			};
			this.$row.find('.cof-ruleset-row').each(function(_, row){
				var $row = $(row),
					name = $row.data('name'),
					$checkbox = $row.find('input[type="checkbox"]:first'),
					$datepicker = $row.find('.b-datepicker');
				this.$inputs[name] = {active: $checkbox};
				$checkbox.on('change', function(e){
					this._events.change(e);
					var ruleName = $(e.currentTarget).closest('.cof-ruleset-row').data('name'),
						value = JSON.parse(this.value);
					this.trigger((value[ruleName] && value[ruleName]['active']) ? 'rule_add' : 'rule_remove', [ruleName]);
				}.bind(this));
				$row.on('input', 'input[type="text"].width_autosize', function(e) {
					e.target.style.width = getInputValueWidth.call(e.target) + 'px';
				});
				$row.find('input[type="text"], select, .type_dropdown').each(function(_, elm){
					var $elm = $(elm);
					if ($elm.is('.type_dropdown')) {
						var field = new $cof.Field(elm);
						this.$inputs[name][$elm.data('name')] = field.on('change', this._events.change);
						return;
					}
					this.$inputs[name][$elm.data('name')] = $elm;
					$elm.on('change keyup', this._events.change);
					if ($elm.is('.width_autosize')) {
						var pid = setTimeout(function() {
							$elm.attr('size', 50).trigger('input');
							clearTimeout(pid);
						}, 1);
					}
				}.bind(this));
				$datepicker.each(function(_, dpicker){
					var $dpicker = $(dpicker),
						$input = $dpicker.find('input[type="hidden"]');
					this.$inputs[name][$input.attr('name')] = $input;
					(new CDatepicker($dpicker)).bind('change', this._events.change);
				}.bind(this));
			}.bind(this));
		},
		getValue: function(){
			var value = {};
			$.each(this.$inputs, function(rowKey, fields){
				value[rowKey] = {};
				$.each(fields, function(elmKey, $elm) {
					var _value = '';
					if($elm instanceof $cof.Field) { _value = $elm.getValue(); }
					else if ($elm.is('input[type="checkbox"]')) { _value = $elm.is(':checked') ? 1 : 0; }
					else { _value = $elm.val(); }
					value[rowKey][elmKey] = _value;
				});
			});
			return value;
		},
		setValue: function(value){
			$.each(value, function(rowKey, fields){
				if (this.$inputs[rowKey] === undefined) return;
				$.each(fields, function(elmKey, elmValue){
					if (this.$inputs[rowKey][elmKey] === undefined) return;
					var $elm = this.$inputs[rowKey][elmKey];
					if($elm instanceof $cof.Field) $elm.setValue(elmValue);
					else if ($elm.is('input[type="checkbox"]')) $elm.prop('checked', elmValue);
					else $elm.val(elmValue);
				}.bind(this));
			}.bind(this));
			this.value = JSON.stringify(value);
		}
	};

	/**
	 * $cof.Field type: ruleset2
	 */
	$cof.Field['ruleset2'] = {
		init: function(){
			// Variables
			this._value = '';
			this._conditions = [];
			this._datepickers =[];
			this._select2 = [];
			// Events handlers
			this._events = {
				/**
				 * Change data
				 */
				change: function(){
					var value = this.getValue();
					if (! $cf.helpers.equals(this._value, value)) {
						this.trigger('change', [value]);
						this._value = value;
					}
				}.bind(this),
				/**
				 * Change row
				 * @param {number} group
				 */
				changeRow: function(group) {
					var length = this.$row.find('.type_group:first .cof-ruleset-row').length;
					this.$row.find('.action_separate_condition').toggleClass('is-hidden', ! length);
					this.$row.find('.type_button > .action_condition').toggleClass('is-hidden', !! length);
					this.$row.find('.type_group[data-group="'+group+'"]')
						.toggleClass('type_label', !! length)
						.find('.action_condition')
						.toggleClass('is-hidden', ! length);
				}.bind(this),
				/**
				 * Show popup
				 * @param {object} e jQueryEventObject
				 */
				showPopupConditions: function(e) {
					var $target = $(e.currentTarget),
						popup = this.popupShowOnlyIf;
					popup.setGroup($target.data('action-group') || 0);
					popup.$box.find('input').val('').trigger('input');
					popup.show();
				}.bind(this),
				/**
				 * Filter by name
				 * @param {object} e jQueryEventObject
				 */
				conditionFilter: function(e) {
					var value = (e.currentTarget.value || '').toLowerCase(),
						popup = this.popupShowOnlyIf,
						$conditions = popup.$box.find('[data-text]'),
						$noResult = popup.$box.find('.b-popoup-ruleset-noresult');
					$conditions
						.toggleClass('is-hidden', !! value)
						.parent('div')
						.toggleClass('is-hidden', !! value);
					$noResult.addClass('is-hidden');
					if ( ! value) return;
					var $matcher = $conditions
						.filter('[data-text^="'+value+'"], [data-text*="'+value+'"]')
						.removeClass('is-hidden')
						.each(function(_, item) {
							var $item = $(item);
							$item
								.parent()
								.removeClass('is-hidden')
								.find('.b-popoup-ruleset-conditions-group-title')
								.removeClass('is-hidden');
							if($item.is('.b-popoup-ruleset-conditions-group-title')) {
								$item
									.siblings()
									.removeClass('is-hidden');
							}
						});
					$noResult.toggleClass('is-hidden', !! $matcher.length);
				}.bind(this),
				/**
				 *	Selected condition
				 * @param {object} e jQueryEventObject
				 */
				selectedCondition: function(e) {
					var key = $(e.currentTarget).data('value'),
						popup = this.popupShowOnlyIf,
						group = popup.getGroup();
					this.addRow(group, key);
					this._events.change();
					popup.hide();
				}.bind(this),
				/**
				 *	Refresh data
				 * @param {object} e jQueryEventObject
				 */
				refresh: function(e) {
					if ($(e.target).parent('.i-refreshable').hasClass('type_wp-meta')) {
						this._fetchWPMeta(e);
					} else {
						this._fetchAutomationOptions(e);
					}
				}.bind(this),
			};
			// Elements
			this.$tpls = this.$row.find('.cof-ruleset-tpls > div');
			// Popups
			this.popupShowOnlyIf = new CFPopup('.b-popup.for_show_only_if');
			this.popupShowOnlyIf.$box
				.on('input', 'input[type="text"]', this._events.conditionFilter)
				.on('click', '[data-value]', this._events.selectedCondition)
				.find('.b-popoup-ruleset-conditions')
				.append('<div class="b-popoup-ruleset-noresult">No results found</div>');
			// API
			this.popupShowOnlyIf = $.extend(this.popupShowOnlyIf, {
				_group: 0,
				setGroup: function(group) {
					this._group = parseInt(group);
				},
				getGroup: function() {
					return parseInt(this._group);
				}
			});
			// Watch events
			this.$row.on('click', '.action_condition', this._events.showPopupConditions);
			this.$row.on('click', '.action_separate_condition', this._events.showPopupConditions);
			// Sortable
			this.sortableConditions = dragula([this.$row.find('.type_group').get(0)], {
				direction: 'vertical',
				isContainer: function (el) {
					var list = el.classList;
					return list.contains('cof-ruleset-list') || list.contains('cof-ruleset-or');
				},
				accepts: function (el, target) {
					var list = target.classList;
					return list.contains('cof-ruleset-list') || list.contains('cof-ruleset-or');
				},
				moves: function(/*el, container, handle*/) {
					return true;
				}
			});
			this.sortableConditions.on('drop', function(el, target, source) {
				var $el = $(el),
					id = $el.data('id'),
					$target = $(target),
					$targetGroup = $target.closest('.type_group'),
					targetGroup = $targetGroup.data('group'),
					sourceGroup = $(source).closest('.type_group').data('group');
				// Creating a new group when moving to a condition `OR`
				if ($target.hasClass('cof-ruleset-or')) {
					targetGroup = this._conditions.length;
					var $group = this._createGroup($targetGroup, targetGroup);
					$group
						.find('.cof-ruleset-list')
						.append($target.find('.cof-ruleset-row'));
					$target.empty();
				}
				// Moving objects between groups
				if (targetGroup !== sourceGroup) {
					var condition = this._conditions[sourceGroup][id];
					this._conditions[targetGroup][id] = $.extend(true, {}, condition);
					this._detachCondition.call(this, sourceGroup, id);
				}
				this.trigger('change', [this.getValue()]);
			}.bind(this));
		},
		/**
		 * Get unique id
		 */
		genID: function () {
			// Math.random should be unique because of its seeding algorithm.
			// Convert it to base 36 (numbers + letters), and grab the first 9 characters
			// after the decimal.
			return '_' + Math.random().toString(36).substr(2, 9);
		},
		/**
		 * Create new group
		 * @param {object} $target
		 * @param {number} group
		 */
		_createGroup: function($target, group) {
			var $group = this.$row.find('.type_group:first').clone();
			$group.attr('data-group', group).find('.cof-ruleset-list, .cof-ruleset-or').empty();
			$group.find('.action_condition').attr('data-action-group', group);
			$target.after($group);
			this.sortableConditions.containers[group] = $group.get(0);
			this._conditions[group] = {};
			this.$row.find('.action_separate_condition').data('action-group', this._conditions.length + 1);
			return $group;
		},
		/**
		 * Add row
		 * @param {number} group
		 * @param {string} name
		 */
		addRow: function(group, name) {
			var $tpl = this.$tpls.filter('[data-name="'+name+'"]'),
				$group = this.$row.find('[data-group="'+group+'"]');
			// Create new group
			if ( ! $group.length) {
				var $target = this.$row.find('.type_group:last');
				$group = this._createGroup($target, group);
			}
			// Add view
			if ($tpl.length) {
				$tpl = $tpl.clone();
				$group
					.find('.cof-ruleset-list')
					.append($tpl);
				this._attachCondition(group, $tpl);
			}
			return $tpl;
		},
		/**
		 * Attach condition from group
		 * @param {number} group
		 * @param {object} $row
		 */
		_attachCondition: function(group, $row) {
			if (this._conditions[group] === undefined) {
				this._conditions[group] = {};
			}
			var id = this.genID();
			$row.data('id', id);
			this._conditions[group][id] = {
				id: $row.data('name')
			};
			// Input width autosize
			$row.on('input', 'input[type="text"].width_autosize', function(e) {
				e.target.style.width = getInputValueWidth.call(e.target) + 'px';
			});
			// Input, select, dropdown
			$row.find('input[type="text"], select, .type_dropdown').each(function(_, elm){
				var $elm = $(elm);
				if ($elm.is('.type_dropdown')) {
					var field = new $cof.Field(elm);
					this._conditions[group][id][$elm.data('name')] = field.on('change', this._events.change);
					return;
				}
				this._conditions[group][id][$elm.data('name')] = $elm;
				$elm.on('change keyup', this._events.change);
				if ($elm.is('.width_autosize')) {
					var pid = setTimeout(function() {
						$elm.attr('size', 50).trigger('input');
						clearTimeout(pid);
					}, 1);
				}
			}.bind(this));
			// Select2 Geo
			$row.find('.cof-select2[class*="geotype_"] > select').each(function (_, elm) {
				var $elm = $(elm),
					geoType = $elm.parent().cfMod('geotype');
				this._select2[id] = $elm.select2({
					dropdownCssClass: 'width_350',
					ajax: {
						url: '/api/geoip_units/list',
						delay: 250,
						data: function (params) {
							return {
								q: params.term,
								type: geoType
							};
						},
						processResults: function (response) {
							if (!response || !response.success) return {};
							var data = response.data,
								results = [];
							$.each(data, function (k, v) {
								results.push({
									id: k,
									text: v
								});
							});

							return {
								results: results
							};
						},
						cache: true
					}
				});
			}.bind(this));
			$row.find('.b-datepicker').each(function(_, dpicker){
				var $dpicker = $(dpicker),
					$input = $dpicker.find('input[type="hidden"]');
				this._conditions[group][id][$input.attr('name')] = $input;
				this._datepickers[id] = (new CDatepicker($dpicker)).bind('change', this._events.change);
			}.bind(this));
			$row.on('click', '.action_delete', this.removeRow.bind(this, $row))
				.on('click', '.cof-form-row-control-refresh', this._events.refresh);
			this._events.changeRow(group);
		},
		/**
		 * Remove row
		 * @param {object} $row
		 */
		removeRow: function($row) {
			var id = $row.data('id'),
				group = $row.closest('.type_group').data('group') || 0;
			if (this._conditions[group] === undefined || ! this._conditions[group].hasOwnProperty(id)) {
				return;
			}
			$row.remove();
			this._detachCondition(group, id);
			this._events.change();
		},
		/**
		 * Detach condition from group
		 * @param {number} group
		 * @param {string} id
		 */
		_detachCondition: function(group, id) {
			delete this._conditions[group][id];
			delete this._datepickers[id];
			delete this._select2[id];
			if (this.$row.find('.type_group').length > 1 && ! Object.keys(this._conditions[group]).length) {
				this.$row.find('[data-group="'+group+'"]').remove();
				delete this._conditions[group];
				delete this.sortableConditions.containers[group];
			}
			this._events.changeRow(group);
		},
		/**
		 * Refresh WP meta
		 * @param {object} e jQueryEventObject
		 */
		_fetchWPMeta: function(e) {
			var $target = $(e.currentTarget),
				$row = $target.closest('.cof-ruleset-row');
			if ($row.hasClass('loading')) return;
			var siteId = $cf.builder.siteId,
				params = {
					refresh: 1
				};
			$row.addClass('loading');
			$.get('/api/site/meta/'+siteId, params, function(res){
				$row.removeClass('loading');
				if ( ! res.success) return console.error(res.errors);
				var $rows = this.$row.find('.cof-ruleset-row[data-name^="platformVar:"]');
				$.each(res.data, function(key, data) {
					switch (key) {
						case 'post_types':
							key = 'postType';
							break;
						case 'user_roles':
							key = 'userRoles';
							break;
					}
					$row = $rows.filter('[data-name="platformVar:'+key+'"]');
					if ($row.length) {
						var $dropdown = $row.find('.i-refreshable'),
							oldValue = $dropdown.find('[data-value].selected').data('value'),
							options = $.map(data, function(name, key){
								return '<a href="javascript:void(0)" data-value="' + $cf.helpers.escapeHtml(key) + '">' +
									$cf.helpers.escapeHtml(name) +
								'</a>';
							});
						$dropdown
							.find('.cof-dropdown-list')
							.empty()
							.html(options.join(''));
						if (oldValue) {
							$dropdown.find('[data-value="'+oldValue+'"]');
						} else {
							$dropdown.find('[data-value]:first').trigger('click');
						}
					}
				});
			}.bind(this), 'json');
		},
		/**
		 * Refresh automation option
		 * @param {object} e jQueryEventObject
		 */
		_fetchAutomationOptions: function(e) {
			var $target = $(e.currentTarget),
				$row = $target.closest('.cof-ruleset-row');
			if ($row.hasClass('loading')) return;
			var params = {
				widget_id: $cf.builder.widgetId,
				param_name: $row.data('name')
			};
			$row.addClass('loading');
			$.post('/api/widget/fetch_showif_options', params, function(res) {
				$row.removeClass('loading');
				if ( ! res.success) return console.error(res.errors);
				$.each(res.data || {}, function(selectName, selectOptions) {
					var optionsHtml = selectOptions.map(function(option) {
						return '<a href="javascript:void(0)" data-value="' + $cf.helpers.escapeHtml(option[0]) + '">' +
							$cf.helpers.escapeHtml(option[1]) +
						'</a>';
					});
					$row.find('.cof-dropdown[data-name="' + selectName + '"] > .cof-dropdown-list').html(optionsHtml);
				});
			}, 'json');
		},
		/**
		 * Get values
		 */
		getValue: function() {
			var result = [];
			this._conditions.map(function(items, group) {
				var conditions = [];
				$('[data-group="'+group+'"] .cof-ruleset-row', this.$row).each(function(_, item) {
					var id = $(item).data('id'),
						values = {};
					$.each(items[id], function(key, input) {
						if (key === 'id') values[key] = input;
						else if (input instanceof $cof.Field) values[key] = input.getValue() || '';
						else values[key] = input[0].value;
					});
					conditions.push(values);
				});
				result.push({conditions: conditions});
			}.bind(this));
			return result;
		},
		/**
		 * Set value
		 * @param {array} value
		 */
		setValue: function(value) {
			if($cf.helpers.equals(this._value, value))
				return;

			$.each(value, function(group, item) {
				$.each(item.conditions, function(_, condition) {
					// append new row
					var $row = this.addRow(group, condition.id),
						id = $row.data('id');
					if( ! this._conditions[group] || ! this._conditions[group].hasOwnProperty(id))
						return;
					$.each(this._conditions[group][id], function(key, input) {
						if(key === 'id' && input === 'date')
							this._datepickers[id].setValue(condition.value);
						else if (input instanceof $cof.Field)
							input.setValue(condition[key]);
						else if (input instanceof jQuery)
							input.val(condition[key]).trigger('change.select2');
					}.bind(this));
				}.bind(this));
			}.bind(this));
			this._events.change();
		},
	};

	/**
	 * $cof.Field type: select
	 */
	$cof.Field['select'] = {
		init: function(){
			this.$input.on('change keyup', function(){
				this.trigger('change', [this.getValue()]);
			}.bind(this));
		},
		affect: function(influenceValue){
			if (influenceValue.indexOf(this.getValue()) < 0 && influenceValue.length === 0) {
				this.setValue('',true);
				this.$input.closest('form').find('button')
					.addClass('is-disabled')
					.attr('disabled', true);
			}
			else if (influenceValue.length > 0 && this.getValue() === null){
				this.setValue(influenceValue[0]);
			}

			var groups = this.$input.find('optgroup');
			if(groups.length)
			{
				// Group support
				var current_group = groups
					.hide()
					.filter('[data-id="' + influenceValue + '"]')
					.show();

				if( ! current_group.find('option:selected').length)
				{
					current_group
						.find('option:first-child')
						.prop('selected', true);
				}
			}
			else if($.isArray(influenceValue))
			{
				// Standard list options
				var options = this.$input.find('option')
					.hide();
				$.each(influenceValue, function(_, value){
					options
						.filter('[value='+value+']')
						.show();
				});

				if(this.$input.find('option:selected').css('display') === 'none'){
					$('option', this.$input).each(function (i, item) {
						var $this = $(item);
						if ($this.css('display') !== 'none') {
							$this.prop('selected', true);
							$this.closest('form').find('button')
								.removeClass('is-disabled')
								.removeAttr('disabled');
							return false;
						}
					}.bind(this));
				}
			}
		}
	};

	/**
	 * $cof.Field type: dropdown
	 */
	$cof.Field['dropdown'] = {
		init:function() {
			// Elements
			this.$container = this.$row.find('.cof-dropdown');
			this.$selected = this.$container.find(' > a');
			this.$list = this.$container.find(' > .cof-dropdown-list');
			// Handlers
			this._events = {
				/**
				 * Change value
				 * @param {object} e jQueryEventObject
				 */
				changeValue: function(e) {
					var $target = $(e.currentTarget);
					this.$list.find('[data-value]').removeClass('selected');
					$target.addClass('selected');
					this.$selected.text($target.text());
					this.trigger('change', [this.getValue()]);
				}.bind(this),
				/**
				 * Refresh data for state_nodata
				 */
				refreshData: function() {
					this.$container
						.closest('.type_dropdown')
						.find('.cof-dropdown-refresh')
						.trigger('click');
				}.bind(this)
			};
			// Watch events
			this.$list.on('click', '[data-value]', this._events.changeValue);
			this._firstSelected();
		},
		/**
		 * Selecting the first default option
		 */
		_firstSelected: function() {
			if ( ! this.getValue() && this.$list.find('a').length) {
				var $first = this.$list.find('a:first');
				this.setValue($first.data('value'));
				this.$selected.text($first.text());
			}
		},
		/**
		 * Get value
		 * @return {string}
		 */
		getValue: function() {
			return this.$list.find('[data-value].selected').data('value') || '';
		},
		/**
		 * Set value
		 * @param {string} value
		 * @param {boolean} quite
		 */
		setValue: function(value, quite) {
			if ( ! this.$list.find('a').length) {
				this.$container
					.addClass('state_nodata')
					.click(this._events.refreshData);
				return;
			}
			if ( ! value) {
				this._firstSelected();
				return;
			}
			this.$list
				.find('[data-value]')
				.removeClass('selected')
				.filter('[data-value="'+value+'"]')
				.addClass('selected');
			this.$selected.text(this.$list.find('[data-value].selected').text());
			if (quite)
				this.trigger('change', [this.getValue()]);
		}
	};

	/**
	 * $cof.Field type: padding
	 */
	$cof.Field['padding'] = {
		init: function(){
			var defaultValues = this.$input.val().split(' ');
			this.$padding = this.$row.find('.cof-padding');
			this.$toggler = this.$row.find('.cof-padding-toggler');
			this.$short = this.$row.find('input[type="text"]');
			this.$short.eq(0).val(defaultValues[0]);
			this.$short.eq(1).val(defaultValues[1] || defaultValues[0]);
			this.$short.eq(2).val(defaultValues[2] || defaultValues[0]);
			this.$short.eq(3).val(defaultValues[3] || defaultValues[1] || defaultValues[0]);

			this.$padding.toggleClass('active_toggler', this._equalCheck());

			// Bad behavior
			// this.$short.on('blur', function () {
			//  this.$padding.toggleClass('active_toggler', this._equalCheck());
			// }.bind(this));

			this.$short.on('change keyup input paste', function (e) {
				if (this.$padding.hasClass('active_toggler')) {
					for (var i = 0; i < 4; i++) {
						this.$short.eq(i).val($(e.currentTarget).val());
					}
				}
				var tmp_val = this._unitsCheck(this.$short.eq(0).val() || '0px') + ' ' + this._unitsCheck(this.$short.eq(1).val() || '0px') + ' ' + this._unitsCheck(this.$short.eq(2).val() || '0px') + ' ' + this._unitsCheck(this.$short.eq(3).val() || '0px');
				this.$input.val(tmp_val.trim());
				this.trigger('change', [this.$input.val()]);
			}.bind(this));

			this.$toggler.on('click', function () {
				// if (!this.$padding.hasClass('active_toggler') && !this._equalCheck()) {
				//  if (!confirm('Make equal paddings?')) return;
				// }
				var value = this.$short.eq(0).val();
				for (var i = 1; i < 4; i++) {
					this.$short.eq(i).val(value);
				}
				this.$input.val(this._unitsCheck(value));
				this.$padding.toggleClass('active_toggler');
				this.trigger('change', [value]);
			}.bind(this));
		},
		_unitsCheck: function (val) {
			if (!val || val === '' || val === 0)
				return '0px';
			return (val.match(/px|em|%/gi)) ? val : val + 'px';
		},
		_equalCheck: function () {
			return ([this.$short.eq(0).val(), this.$short.eq(1).val(), this.$short.eq(2).val(), this.$short.eq(3).val()].every(function(val, i, arr) { return val === arr[0] }));
		},
		setValue: function (value) {
			var values = value.split(' ');
			this.$input.val(value);
			this.$short.eq(0).val(values[0]);
			this.$short.eq(1).val(values[1] || values[0]);
			this.$short.eq(2).val(values[2] || values[0]);
			this.$short.eq(3).val(values[3] || values[1] || values[0]);

			this.$padding.toggleClass('active_toggler', this._equalCheck());
		}
	};

	/**
	 * $cof.Field type: select2
	 */
	$cof.Field['select2'] = $cof.Field['driver'] = {
		init: function(){
			var dropdownCssClass = (this.$row.hasClass('style_inline')) ? 'cof-select2-dropdown cof-select2-dropdown_inline' : 'cof-select2-dropdown';
			var dropdownParent = (this.$row.hasClass('parent_popup')) ? this.$row.closest('.b-popup-box') : this.$row.find('.cof-field-hider');

			if (this.$input.hasClass('i-tokenize')){
				this.select2 = this.$input.select2({
					tags: true,
					placeholder: this.$input.data('placeholder'),
					tokenSeparators: [',', ' '],
					dropdownParent: dropdownParent,
					dropdownCssClass: dropdownCssClass
				});
			} else {
				this.select2 = this.$input.select2({
					placeholder: this.$input.data('placeholder'),
					dropdownCssClass: dropdownCssClass
				});
			}
			this.previous = this.getValue();
			$('select').on('select2:selecting', function(){
				this.previous = this.getValue();
			}.bind(this));
			this.select2.on('select2:select', function(){
				this.trigger('change', [this.getValue(), this.previous]);
			}.bind(this));
			this.select2.on('select2:unselect', function(){
				this.trigger('change', [this.getValue(), this.previous]);
			}.bind(this));
			window.select2 = this;
		},
		getValue: function(){
			return this.select2 ? this.select2.val() : null;
		},
		setValue: function(value, quite){
			if (this.select2)
				this.select2.val(value).trigger('change');
			if (!quite)
				this.trigger('change', [value]);
		}
	};

	$cof.Field['font'] = {
		init: function(){
			var dropdownCssClass = (this.$row.hasClass('style_inline')) ? 'cof-select2-dropdown cof-select2-dropdown_inline' : 'cof-select2-dropdown';

			this.select2 = this.$input.select2({
				dropdownCssClass: dropdownCssClass,
				templateResult: function (item) {
					return $('<span style="font-family: ' + item.text + ';">' + item.text + '</span>');
				}
				// sorter: function (data) {
				//  return data.sort(); // TODO Sorter (hide labels)
				// },
			});

			this.previous = this.getValue();
			$('select').on('select2:selecting', function(){
				this.previous = this.getValue();
			}.bind(this));
			this.select2.on('select2:open', function(){
				$(".select2-search__field").attr("placeholder", "Browse All Google Fonts")
					.on('change keyup input paste', this._search)
					.on('change keyup input paste', $.debounce(250, this._addFonts.bind(this)));
				$('.select2-dropdown').addClass('cof-font-results');
				$(".select2-results__options").on('scroll', $.debounce(1000, this._addFonts.bind(this)));
			}.bind(this));
			this.select2.on('select2:open', $.debounce(250, this._addFonts.bind(this)));
			this.select2.on('select2:close', function(){
				$(".select2-search__field").removeAttr("placeholder")
					.off('change keyup input paste', this._search)
					.on('change keyup input paste', this._addFonts);
				$('.select2-dropdown').removeClass('cof-font-results').removeClass('cof-font-results_search');
				$(".select2-results__options").off('scroll', this._addFonts);
				$(".cof-fonts").remove();
				this._loadedFonts = [];
			}.bind(this));
			this.select2.on('select2:close', this._addFonts);

			this.select2.on('select2:select', function(){
				this.trigger('change', [this.getValue(), this.previous]);
			}.bind(this));
		},
		_loadedFonts: [],
		_addFonts: function () {
			var fonts = '',
				areaHeight = $(".select2-results__options").height() + 40;

			$('.select2-results__option[role="treeitem"]').each(function (i, v) {
				var el = $(v);
				var top = el.position().top;
				var height = el.height(); // TODO remove
				var font = el.find('span').text().replace(/ /g, '+');

				if (top + height > 0 && top <= areaHeight) {
					if (this._loadedFonts.indexOf(font) === -1) {
						fonts += font + '|';
						this._loadedFonts.push(font);
					}
				}
			}.bind(this));
			fonts = fonts.slice(0, -1);
			if (fonts) $('head').append('<link class="cof-fonts" rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=' + fonts + '">');
		},
		_search: function () {
			$('.select2-dropdown').toggleClass('cof-font-results_search', ($(".select2-search__field").val() !== ''));
		},
		getValue: function(){
			return this.select2 ? this.select2.val() : null;
		},
		setValue: function(value, quite){
			if (this.select2)
				this.select2.val(value).trigger('change');
			if (!quite)
				this.trigger('change', [value]);
		}
	};

	/**
	 * $cof.Field type: checkbox
	 */
	$cof.Field['checkbox'] = {
		init: function(){
			this.parentInit();
			this.$checkbox = this.$row.find('input[type="checkbox"]');
		},
		getValue: function(){
			return this.$checkbox.is(':checked') ? 1 : 0;
		},
		setValue: function(value, quite){
			this.$checkbox.prop('checked', +value);
			if (!quite)
				this.trigger('change', [value]);
		}
	};

	/**
	 * $cof.Field type: checkboxes
	 */
	$cof.Field['checkboxes'] = {
		init: function(){
			this.$checkboxes = this.$row.find('input[type="checkbox"]');
			this.$checkboxes.on('change', function(){
				var values = this.getValue();
				this._activateCheckboxButtons(values);
				this.trigger('change', [values]);
			}.bind(this));
		},
		getValue: function() {
			var value = [];
			$.each(this.$checkboxes.filter(':checked'), function (_, $el) {
				value.push($($el).val());
			}.bind(this));
			return value;
		},
		setValue: function(value, quite) {
			this._activateCheckboxButtons(value);
			if (!quite)
				this.trigger('change', [value]);
		},
		_activateCheckboxButtons: function (value) {
			$.each(this.$checkboxes, function (_, $el) {
				$el = $($el);
				$el.prop('checked', $.inArray($el.val(), value) !== -1);
			}.bind(this));
		}
	};

	/**
	 * $cof.Field type: badges
	 */
	$cof.Field['badges'] = $cof.Field['checkboxes'];

	/**
	 * $cof.Field type: varlist
	 */
	$cof.Field['varlist'] = {
		init: function(){
			this.$list = this.$row.find('.cof-varlist-list');
			this.$items = this.$list.find('input');
			this.optionTemplate = this.$row
				.find('template[data-id="empty_item"]')
				.html();
			this.$row
				.on('click', '.g-action.action_delete', function(e){
					if (this.$list.children().length <= 1) return;
					$(e.currentTarget).parent().remove();
					var value = this.getValue();
					this.trigger('change', [value]);
				}.bind(this))
				.on('click', '.cof-add', this._addItem.bind(this));
		},
		_addItem: function () {
			this.$items.off('change', this._changeItem.bind(this));
			this.$list
				.append(this.optionTemplate);
			this.$items = this.$list.find('input');
			this.$items.on('change', this._changeItem.bind(this));
		},
		_changeItem: function () {
			var value = this.getValue();
			this.trigger('change', [value]);
		},
		render: function (value) {
			this.$items.off('change', this._changeItem.bind(this));
			this.$list.html('');
			$.each(value, function (_, values) {
				var $el = $(this.optionTemplate).clone(),
					name = values[0], value = values[1];
				$el.find('input[name="option"]').val(name);
				$el.find('input[name="value"]').val(value);
				this.$list.append($el);
			}.bind(this));
			this.$items = this.$list.find('input');
			this.$items.on('change', this._changeItem.bind(this));
			if (this.$list.children().length === 0) {
				this._addItem();
			}
		},
		getValue: function() {
			var value = [];
			$.each(this.$list.find('.cof-varlist-item'), function (_, $el) {
				$el = $($el);
				var n = $el.find('input[name="option"]').val(),
				v = $el.find('input[name="value"]').val();

				if($el.find('select[name="value"]').length) {
					v = $el.find('select[name="value"]').val();
				}

				if (n !== '') value.push([n, v]); //  && v !== ''
			}.bind(this));
			return value;
		},
		setValue: function(value, quite) {
			this.render(value);
			if (!quite)
				this.trigger('change', [value]);
		}
	};

	/**
	 * $cof.Field type: stringslist
	 */
	$cof.Field['stringslist'] = {
		labels: [],
		init: function(){
			this.optionTemplate = this.$row.find('template[data-id="emptystringslistitem"]').html();
			this.$title = this.$row.find('.cof-form-row-title');
			this.$list = this.$row.find('.cof-stringslist-list');
			this.$items = this.$list.find('input');

			this.$title.on('click', function () {
				this.$row.toggleClass('is-active');
			}.bind(this));
			if (this.$row.find('.cof-stringslist').hasClass('is_expanded') || this.$title.length === 0)
			{
				this.$row.addClass('is-active');
			}
			this.labels = this.$row.find('.cof-stringslist')[0].onclick() || {};
			this.$items.on('change', this._changeItem.bind(this));
		},
		_changeItem: function () {
			var value = this.getValue();
			this.trigger('change', [value]);
		},
		render: function (value) {
			if (value === undefined)
				value = this.getValue();

			this.$items.off('change', this._changeItem.bind(this));
			this.$list.html('');
			$.each(this.labels, function (key, field_name) {
				var $el = $(this.optionTemplate).clone();
				$el.data('key', key);
				$el.find('td.for_name').text(field_name || key);
				$el.find('input[name="value"]').val(value[key] || key);
				this.$list.append($el);
			}.bind(this));
			this.$items = this.$list.find('input');
			this.$items.on('change', this._changeItem.bind(this));
		},
		setLabels: function(labels, render){
			this.labels = labels;
			if (render)
				this.render();
		},
		getValue: function() {
			var value = {};
			$.each(this.$list.find('.cof-stringslist-item'), function (_, $el) {
				$el = $($el);
				var n = $el.data('key'),
					v = $el.find('.for_field input').val();
				if (v !== '')
					value[n] = v;
			}.bind(this));
			return value;
		},
		setValue: function(value, quite) {
			this.render(value);
			if (!quite)
				this.trigger('change', [value]);
		},
		showError: function(message, key){
			var field;
			$.each(this.$list.find('.cof-stringslist-item'), function (_, $el) {
				$el = $($el);
				field = $el.attr('data-key');
				if (message[field] && message[field] !== undefined) {
					$el.find('td').addClass('field-error');
					$el.find('.for_error').html(message[field]);
				}
			}.bind(this));
		},
		clearError : function(){
			$.each(this.$list.find('.cof-stringslist-item'), function (_, $el) {
				$el = $($el);
				$el.find('td').removeClass('field-error');
				$el.find('.for_error').html('');
			}.bind(this));
		}
	};
	/**
	 * $cof.Field type: switch
	 */
	$cof.Field['switcher'] = {
		init: function(){
			this.parentInit();
			this.$checkbox = this.$row.find('input[type="checkbox"]');
			this._confirm = {
				off: this.$input.data('offconfirm'),
				on: this.$input.data('onconfirm')
			};
			if (this._confirm.off || this._confirm.on) {
				this.$input.off('change');
				this.$input.on('change', function(){
					var value = this.getValue(),
						confirmChange;
					if (value === false && this._confirm.off) {
						confirmChange = confirm(this._confirm.off);
						if (confirmChange) {
							this.trigger('change', [this.getValue()]);
						} else {
							this.setValue(!value, true)
						}
					} else if (value === true && this._confirm.on) {
						confirmChange = confirm(this._confirm.on);
						if (confirmChange) {
							this.trigger('change', [this.getValue()]);
						} else {
							this.setValue(!value, true)
						}
					} else {
						this.trigger('change', [this.getValue()]);
					}
				}.bind(this));
			}
		},
		getValue: function(){
			return this.$checkbox.is(':checked');
		},
		setValue: function(value, quite){
			this.$checkbox.prop('checked', ! window.empty(value));
			if (!quite)
				this.trigger('change', [value]);
		}
	};

	/**
	 * $cof.Field type: submit
	 */
	$cof.Field['submit'] = {
		init: function(){
			this.$row.find('.g-btn').on('click', function(){
				this.$row.closest('.i-form').trigger('submit');
			}.bind(this));
		},
		getValue: null,
		setValue: null
	};

	/**
	 * $cof.Field type: image
	 */
	$cof.Field['image'] = {
		init: function(options){
			this.parentInit(options);
			// Cached URLs for certain values (images IDs and sizes)
			this.$btnSet = this.$row.find('.cof-button.type_set');
			this.$btnRemove = this.$row.find('.cof-button.type_remove');
			this.$btnChange = this.$row.find('.cof-button.type_change');
			this.$previewContainer = this.$row.find('.cof-upload-container');
			this.$previewImg = this.$previewContainer.find('img');
			this.$input = this.$row.find('.cof-upload-input');
			this.$popup = this.$row.find('.b-popup');
			this.$btnSet.add(this.$btnChange).on('click', this.openMediaUploader.bind(this));
			this.$btnRemove.on('click', function(){
				this.setValue('');
			}.bind(this));
		},

		setValue: function(value, quiet){
			if (value === ''){
				// Removed value
				this.$previewContainer.hide();
				this.$btnSet.show();
				this.$previewImg.attr('src', '');
				this.$input.val('');
				this.parentSetValue(this.getValue(), quiet);
			} else {
				this.$previewContainer.show();
				this.$btnSet.hide();
				if (value[0] === '/' && value !== $cf.emptyImage)
					value = '<domain>' + value;

				var uploadCallback = function(){
					this.$input.val(value); //fallback;
					this.parentSetValue(this.getValue(), quiet);
				}.bind(this);
				var uploadFallback = setTimeout(uploadCallback, 1000);

				var regexp_dimensions = /\?\d+x\d+$/;
				if (!regexp_dimensions.test(value)){ // sizes does not set, get it from image
					this.$previewImg.on('load', function(){
						clearTimeout(uploadFallback);
						value = value + '?' + parseInt(this.$previewImg[0].naturalWidth) + 'x' + parseInt(this.$previewImg[0].naturalHeight);
						this.$input.val(value);
						this.$previewImg.off('load');
						this.parentSetValue(this.getValue(), quiet);
					}.bind(this));
				} else {
					clearTimeout(uploadFallback);
					uploadCallback();
				}
				this.$previewImg.attr('src', value.replace('<domain>', location.protocol + '//' + location.hostname));
			}

			if (this.popup !== undefined){
				this.popup.hide();
			}
		},

		openMediaUploader: function(){
			if (this.popup === undefined){
				this.popup = new CFPopup(this.$popup);
			}
			window._tmpImageUploader = this; // global var for elFinder
			this.popup.show();
		}
	};

	/**
	 * cof Field: Color
	 */
	$cof.Field['color'] = {
		init: function(){
			this.$color = this.$row.find('.cof-color');
			this.$preview = this.$row.find('.cof-color-preview');
			this.$clear = this.$row.find('.cof-color-clear');
			// Set white text color for dark backgrounds
			if (this.$input.val() !== 'inherit' && this.$input.val() !== 'transparent') {
				this.invertInputColors($.colpick.hexToRgba(this.$input.val()));
			}
			this.$input.colpick({
				layout: 'hex',
				color: (this.$input.val() || ''),
				submit: false,
				onChange: function(hsb, hex, rgb, el, bySetColor){
					this.$preview.css('background', hex);
					this.invertInputColors(rgb);
					this.$input.toggleClass('with_alpha', hex.substr(0, 5) === 'rgba(');
					if (!bySetColor){
						this.$input.val(hex);
						this.trigger('change', [this.$input.val()]);
					}
				}.bind(this),
				onHide: function(){
					this.$color.removeClass('active');
					this.trigger('change', [this.$input.val()]);
				}.bind(this),
				onShow: function(){
					this.$color.addClass('active');
				}.bind(this)
			});
			this.$input.on('keyup', function(){
				var value = this.$input.val() || '', m;
				if (value === '') {
					this.$preview.removeAttr('style');
					return;
				}
				if (value === 'inherit' || value === 'transparent') {
					this.$preview.css('background', value);
					this.$input.removeClass('white');
					return;
				}
				if ((value.length === 3 || value.length === 4) && (m = /^#?([0-9a-fA-F]{3})$/.exec(value))){
					value = '#' + m[1][0] + m[1][0] + m[1][1] + m[1][1] + m[1][2] + m[1][2];
				}
				if ((value.length === 6) && (m = /^([0-9a-fA-F]{6})$/.exec(value))){
					value = '#' + m[1];
				}
				this.$input.colpickSetColor(value);
			}.bind(this));
			this.$input.on('change', function(){
				this.setValue(this.$input.val());
			}.bind(this));
			this.$clear.on('click', function(){
				this.setValue('');
			}.bind(this));
		},

		setValue: function(value, quiet){
			var r, g, b, a, m, hexR, hexG, hexB, hashString;
			value = value.trim();
			this.convertRgbToHex = function(color){
				if (m = /^([^0-9]{1,3})*(\d{1,3})[^,]*,([^0-9]{1,3})*(\d{1,3})[^,]*,([^0-9]{1,3})*(\d{1,3})[\s\S]*$/.exec(color)) {
					hexR = m[2] <= 255 ? ("0" + parseInt(m[2], 10).toString(16)).slice(-2) : 'ff';
					hexG = m[4] <= 255 ? ("0" + parseInt(m[4], 10).toString(16)).slice(-2) : 'ff';
					hexB = m[6] <= 255 ? ("0" + parseInt(m[6], 10).toString(16)).slice(-2) : 'ff';
					color = '#' + hexR + hexG + hexB;
					return color;
				}
			};
			// Catch RGB and RGBa
			if ( (m = /^[^,]*,[^,]*,[\s\S]*$/.exec(value)) ) {
				if (m = /^[^,]*(,)[^,]*(,)[^,]*(,)[^.]*([.0-9])[\s\S]*$/.exec(value)) {
					// Catch RGBa only
					if (m[4] === '.' || m[4] === '1' || m[4] === '0') {
						if (m = /^([^0-9]{1,3})*(\d{1,3})[^,]*,([^0-9]{1,3})*(\d{1,3})[^,]*,([^0-9]{1,3})*(\d{1,3})[^,]*,[^.]*.([^0-9]{1,2})*(\d{1,2})[\s\S]*$/.exec(value)) {
							r = m[2] <= 255 ? m[2] : 255;
							g = m[4] <= 255 ? m[4] : 255;
							b = m[6] <= 255 ? m[6] : 255;
							a = m[8];
							value = 'rgba(' + r + ',' + g + ',' + b + ',0.' + a + ')';
						}
					} else {
						value = this.convertRgbToHex(value);
					}
				} else {
					value = this.convertRgbToHex(value);
				}
			} else {
				// Check Hex Colors
				if (m = /^#?[\s\S]*?([a-fA-F0-9]{1,6})[\s\S]*$/.exec(value)) {
					if (value === 'inherit' || value === 'transparent') {
						// value = value;
					} else if (m[1].length === 3) {
						value = '#' + m[1][0] + m[1][0] + m[1][1] + m[1][1] + m[1][2] + m[1][2];
					} else if (m[1].length <= 6) {
						hashString = m[1].split('');
						while (hashString.length < 6) {
							hashString.unshift('0');
						}
						value = '#' + hashString.join('');
					}
				}
			}
			if (value === '') {
				this.$preview.removeAttr('style');
				this.$input.removeClass('with_alpha');
			} else {
				if (value === 'inherit' || value === 'transparent') {
					this.$input.removeClass('white');
					this.$preview.css('background', value);
				} else {
					this.$input.colpickSetColor(value);
				}
			}
			this.parentSetValue(value, quiet);
		},

		getValue: function(name){
			return this.parentGetValue(name);
		},

		// Make color of text depending on lightness of color
		invertInputColors: function(rgba){
			if ( ! rgba && (typeof rgba !== 'object')) return;
			var r = rgba.r ? rgba.r : 0,
				g = rgba.g ? rgba.g : 0,
				b = rgba.b ? rgba.b : 0,
				a = (rgba.a === 0 || rgba.a) ? rgba.a : 1,
				light;
			// Determine lightness of color
			light = r * 0.213 + g * 0.715 + b * 0.072;
			// Increase lightness regarding color opacity
			if (a < 1) {
				light = light + (1 - a) * (1 - light/255) * 235;
			}
			if (light < 178) {
				this.$input.addClass('white');
			} else {
				this.$input.removeClass('white');
			}
		},
	};

	/**
	 * cof Field: colors
	 */
	$cof.Field['colors'] = {
		init: function() {
			// Variables
			this.list = [];
			// Elements
			this.$container = this.$row.find('.cof-colors');
			this.$list = this.$container.find('.cof-colors-list');
			this.$template = this.$container.find('.cof-colors-template');
			// Handlers
			this._events = {
				/**
				 * Add color
				 * @param {object} e jQueryEventObject
				 */
				add: function(e) {
					this.addColor(this.list.length, '#ffffff');
					this._events.change();
				}.bind(this),
				/**
				 * Color change
				 */
				change: function() {
					var $colors = this.$list.find('.type_color');
					$colors
						.find('.action_remove')
						.toggleClass('is-hidden', $colors.length < 3);
					this.trigger('change', [this.getValue()]);
				}.bind(this),
				/**
				 * Remove color
				 * @param {object} e jQueryEventObject
				 */
				remove: function(e) {
					if(this.$list.find('>*').length < 3) return;
					var $item = $(e.target).closest('.type_color');
					delete this.list[$item.data('id')];
					$item.remove();
					this._events.change();
				}.bind(this),
			};
			// Watch events
			this.$container.on('click', '.action_add', this._events.add);
		},
		/**
		 * Add color
		 * @param {number} id
		 * @param {string} value
		 */
		addColor: function(id, value) {
			var $tpl = this.$template.clone();
			$tpl
				.data('id', id)
				.on('click', '.action_remove', this._events.remove)
				.cfMod('type', 'color');
			$tpl
				.removeClass('cof-colors-template is-hidden')
				.find('input')
				.val(value);
			$tpl
				.find('.cof-color-preview')
				.css('backgroundColor', value);
			var field = new $cof.Field($tpl.get(0));
			field.on('change', $.throttle(100, true, this._events.change));
			this.list[id] = field;
			this.$list.append($tpl);
		},
		/**
		 * Get value
		 * @return {array}
		 */
		getValue: function() {
			var value = [];
			this.list.map(function(item) {
				value.push(item.getValue());
			});
			return value;
		},
		/**
		 * Set value
		 * @param {array} value
		 * @param {boolean} quite
		 */
		setValue: function(value, quite) {
			this.$list.html('');
			this.list = [];
			$.each(value, this.addColor.bind(this));
			var $colors = this.$list.find('.type_color');
			$colors
				.find('.action_remove')
				.toggleClass('is-hidden', $colors.length < 3);
			if ( ! quite)
				this.trigger('change', [value]);
		},
	};

	/**
	 * cof Field: Editor
	 */
	$cof.Field['editor'] = {
		init: function(){
			this.driver = this.$row.find('.cof-form-row-control-ace').cfMod('driver');
			this._events = {};
			this._events.editorChange = function(){
				var value = this.editor.getSession().getValue();
				this.parentSetValue(value);
			}.bind(this);

			this.on('afterShow', function () {
				if (this.editor) this.editor.resize();
			}.bind(this));

			this.$editor = this.$row.find('.cof-form-row-control-ace').text(this.$input.val());
			// Loading ACE dynamically
			if (window.ace === undefined){
				var data = this.$row.find('.cof-form-row-control-param')[0].onclick() || {},
					script = document.createElement('script');
				script.onload = this._init.bind(this);
				script.type = 'text/javascript';
				script.src = data.ace_path;
				document.getElementsByTagName('head')[0].appendChild(script);
				return;
			}
			this._init();
		},

		_init: function(){
			this.$input.hide();
			this.editor = window.ace.edit(this.$editor[0]);
			this.editor.setTheme("ace/theme/twilight"); // dawn
			this.editor.$blockScrolling = Infinity; // FIX editor message
			this.editor.getSession().setUseWrapMode(true);
			var tmpDriver = this.driver === 'js' ? 'javascript' : this.driver;
			this.editor.getSession().setMode("ace/mode/" + tmpDriver);
			this.editor.setShowFoldWidgets(false);
			this.editor.setFontSize(13);
			this.editor.getSession().setUseWorker(false);
			this.editor.getSession().setValue(this.$input.val());
			this.editor.getSession().on('change', this._events.editorChange);

			// Resize handler
			this.$body = $(document.body);
			this.$window = $(window);
			this.$control = this.$row.find('.cof-form-row-control');
			this.controlHeight = this.$row.find('.cof-form-row-control').height();
			this.$resize = this.$row.find('.cof-form-row-resize').insertAfter(this.$control);
			this.$resizeKnob = this.$row.find('.cof-form-row-resize-knob');
			var startPageY, startHeight, draggedValue;
			$.extend(this._events, {
				dragstart: function(e){
					e.stopPropagation();
					this.$resize.addClass('dragged');
					startPageY = e.pageY;
					startHeight = this.$control.height();
					this.$body.on('mousemove', this._events.dragmove);
					this.$window.on('mouseup', this._events.dragstop);
					this._events.dragmove(e);
				}.bind(this),
				dragmove: function(e){
					e.stopPropagation();
					draggedValue = Math.max(startPageY - startHeight + this.controlHeight, Math.round(e.pageY));
					this.$resizeKnob.css('top', draggedValue - startPageY);
				}.bind(this),
				dragstop: function(e){
					e.stopPropagation();
					this.$body.off('mousemove', this._events.dragmove);
					this.$window.off('mouseup', this._events.dragstop);
					this.$control.height(startHeight + draggedValue - startPageY);
					this.$resizeKnob.css('top', 0);
					this.editor.resize();
					this.$resize.removeClass('dragged');
				}.bind(this)
			});
			this.$resizeKnob.on('mousedown', this._events.dragstart);
		},

		setValue: function(value, quiet){
			if (value instanceof Array) value = value.join("\n");
			if (this.editor !== undefined){
				this.editor.getSession().off('change', this._events.editorChange);
				this.editor.setValue(value);
				this.editor.getSession().on('change', this._events.editorChange);
			} else {
				this.parentSetValue(value, quiet);
			}
		},

		getValue: function(){
			if (this.editor !== undefined){
				return this.editor.getValue();
			} else {
				return this.parentGetValue();
			}
		}
	};

	/**
	 * cof Field: Slider
	 */
	$cof.Field['slider'] = {
		init: function(){
			this.$slider = this.$row.find('.cof-slider');
			// Params
			this.min = parseFloat(this.$slider.data('min'));
			this.max = parseFloat(this.$slider.data('max'));
			this.step = parseFloat(this.$slider.data('step')) || 1;
			this.prefix = this.$slider.data('prefix') || '';
			this.postfix = this.$slider.data('postfix') || '';
			this.$textfield = this.$row.find('input[type="text"]');
			this.$buttonL = this.$row.find('.cof-slider-button_l');
			this.$buttonH = this.$row.find('.cof-slider-button_h');
			this.$box = this.$row.find('.cof-slider-box');
			this.$range = this.$row.find('.cof-slider-range');
			this.$body = $(document.body);
			this.$window = $(window);
			this.aliases = this.$slider[0].onclick() || {};
			// Needed box dimensions
			this.sz = {};
			var draggedValue;
			this._events = {
				dragstart: function(e){
					e.stopPropagation();
					this.sz = {left: this.$box.offset().left, width: this.$box.width()};
					this.$body.on('mousemove', this._events.dragmove);
					this.$window.on('mouseup', this._events.dragstop);
					this._events.dragmove(e);
				}.bind(this),
				dragmove: function(e){
					e.stopPropagation();
					var x = Math.max(0, Math.min(1, (Number(this.sz) === 0) ? 0 : ((e.pageX - this.sz.left) / this.sz.width))),
						value = parseFloat(this.min + x * (this.max - this.min));
					value = Math.round(value / this.step) * this.step;
					//this.setValue(draggedValue, true);
					draggedValue = value;
					if (draggedValue !== undefined) this.setValue(parseFloat(draggedValue.toFixed(2)));
				}.bind(this),
				dragstop: function(e){
					e.stopPropagation();
					this.$body.off('mousemove', this._events.dragmove);
					this.$window.off('mouseup', this._events.dragstop);
					this.setValue(parseFloat(draggedValue.toFixed(2)));
				}.bind(this)
			};
			this.$textfield.on('focus', function(){
				this.$textfield.val(parseFloat(this.getValue(true)));
			}.bind(this));
			this.$textfield.on('blur', function(){
				var value = parseFloat(parseFloat(this.$textfield.val().replace(/[^0-9.]/g, '') || 0).toFixed(2));
				value = Math.min(this.max, value);
				value = Math.max(this.min, value);
				this.setValue(value);
			}.bind(this));
			this.$box.on('mousedown', this._events.dragstart);
			this.$buttonL.on('click', function(){
				var value = Math.max(this.min, parseFloat(this.getValue()) - this.step);
				value = parseFloat(value.toFixed(2));
				this.setValue(value);
			}.bind(this));
			this.$buttonH.on('click', function(){
				var value = Math.min(this.max, parseFloat(this.getValue()) + this.step);
				value = parseFloat(value.toFixed(2));
				this.setValue(value);
			}.bind(this));
			this.renderValue(this.getValue(true)); // initial render
		},

		renderValue: function(value){
			if (value === undefined) return;
			value = (typeof value === 'string') ? Number(value.replace(/[^0-9.]/g, '')) : value;

			var x = Math.max(0, Math.min(1, (value - this.min) / (this.max - this.min)));
			this.$range.css('left', x * 100 + '%');

			value = parseFloat(value.toFixed(2));
			this.$buttonL.toggleClass('is-disabled', value === this.min);
			this.$buttonH.toggleClass('is-disabled', value === this.max);

			this.$textfield.val(this.addPrePostFix(value));
		},

		setValue: function(value, quiet){
			this.parentSetValue(value, quiet);
			this.renderValue(value);
		},

		getValue: function(asNumber){
			asNumber = asNumber || false;
			var value = this.parentGetValue()
				.replace(new RegExp('^' + this.prefix), '')
				.replace(new RegExp(this.postfix + '$'), '');
			if (!asNumber && Object.values(this.aliases).indexOf(value) === -1){
				value = this.addPrePostFix(value);
			}
			return value;
		},

		addPrePostFix: function(value){
			value = this.prefix + value + this.postfix;
			if (this.aliases[value]){
				value = this.aliases[value];
			}

			return value;
		}
	};

	/**
	 * cof Field: DateTime
	 */
	$cof.Field['datetime'] = {
		init: function () {
			this.datepicker = new CDatepicker(this.$row.find('.b-datepicker')[0]);

			this.datepicker.bind('change', function () {
				this.setValue(this.datepicker.value);
			}.bind(this));
		},

		renderValue: function (value) {
			if (value === undefined|| value === null) return;
			if (this.datepicker.mode === 'datetime')
			{
				// Rounding to minutes precision
				value = value.substr(0, 16) + ':00';
			}
			this.datepicker.setValue(value, true);
		},

		setValue: function(value, quiet){
			if ( ! value) value = new Date().toISOString().replace('T', ' ').substr(0, 16) + ':00';
			this.parentSetValue(value, quiet);
			if (!quiet)
				this.trigger('change', [value]);
			this.renderValue(value);
		},

		getValue: function () {
			return this.datepicker.value;
		}
	};

	/**
	 * cof Field: Time
	 */
	$cof.Field['time'] = {
		init: function(){
			this.$days = this.$row.find('.cof-time-days');
			this.$hours = this.$row.find('.cof-time-hours');
			this.$minutes = this.$row.find('.cof-time-minutes');
			this.$textfields = this.$row.find('input[type="text"]');

			this.$textfields.on('change keyup input paste', function () {
				var value = this.$days.val() * 86400 + this.$hours.val() * 3600 + this.$minutes.val() * 60;
				this.setValue(value);
			}.bind(this));
		},

		renderValue: function (value) {
			if (value === undefined || (value !== value)) return;

			var days = Math.floor(value / 86400);
			value -= days * 86400;

			var hours = Math.floor(value / 3600) % 24;
			value -= hours * 3600;

			var minutes = Math.floor(value / 60) % 60;

			this.$days.val(days);
			this.$hours.val(hours);
			this.$minutes.val(minutes);
		},

		setValue: function(value, quiet){
			this.parentSetValue(value, quiet);
			if (!quiet)
				this.trigger('change', [value]);
			this.renderValue(value);
		},

		getValue: function(){
			return this.$days.val() * 86400 + this.$hours.val() *  3600 + this.$minutes.val() * 60;
		}
	};

	/**
	 * cof Field: HTML
	 */
	$cof.Field['html'] = {
		init: function(){
			// If Froala assets aren't loaded yet, preloading them first
			if (window.cfFroalaLoaded !== true){
				if (window.FroalaPromice === undefined){
					window.FroalaPromice = loadMultipleExternalResources($('.b-builder-froala-files')[0].onclick() || [])
						.then(function(){
							delete window.FroalaPromice;
							window.cfFroalaLoaded = true;
							this.$row.data('tmp_value', null);
						}.bind(this));
				}
				window.FroalaPromice.then(this._init.bind(this));
			} else {
				this._init.bind(this)();
			}
		},

		_init: function(){
			var $froala = this.$row.find('.cof-froala');
			this.$builder = $('.b-builder');
			this.$titlebar = $('.b-titlebar');
			this.$convContainer = $('.conv-container');
			this.editor = this._createEditor(
				$froala,
				$froala.data('froala-options'),
				$froala.data('froala-plugins')
			);

			var triggerChange = function(){
				this.trigger('change', [this.getValue()]);
			}.bind(this);

			// Fix for https://github.com/froala/wysiwyg-editor/issues/31
			// Froala triggers key event by timeout, which may cause call for the old Froala instance on a new place
			//this.editor.on('froalaEditor.contentChanged', triggerChange);
			//this.editor.on('froalaEditor.blur', triggerChange);
			this.editor.on('froalaEditor.keyup', triggerChange);

			// Watch changes in the editor
			this.editor
				.find('.fr-toolbar > button')
				.on('click', triggerChange);

			this.$row.data('conv_field', this);

			this.editor.on('froalaEditor.quickInsert.commands.after', function (e, editor, cmd) {
				this._setValue(editor.html.get());
			}.bind(this));

			if (this.$row.hasClass('enable_editor_shortcode')) {
				this.editor.on('froalaEditor.html.set', function (e, editor, cmd) {
					this._setValue(editor.html.get());
				}.bind(this));
			}
		},

		_createEditor: function($el, options, plugins){
			var editor;
			options = $.extend({
				enter: $.FroalaEditor.ENTER_BR,
				toolbarSticky: false,
				toolbarButtons: ['bold', 'italic', 'strikeThrough', 'formatOL', 'formatUL', 'link', 'html'],
				toolbarButtonsMD: ['bold', 'italic', 'strikeThrough', 'formatOL', 'formatUL', 'link', 'html'],
				toolbarButtonsSM: ['bold', 'italic', 'strikeThrough', 'formatOL', 'formatUL', 'link', 'html'],
				toolbarButtonsXS: ['bold', 'italic', 'strikeThrough', 'formatOL', 'formatUL', 'link', 'html'],
				pluginsEnabled: ['wordPaste', 'link', 'lists'],
				htmlAllowedTags: ['strong', 'em', 'br', 'b', 'i', 'a', 's', 'ul', 'ol', 'li','span'],
				shortcutsEnabled: ['bold', 'italic'],
				shortcutsHint: false
			}, options || {});

			// Enable plugins
			if ( !! plugins && plugins instanceof Array) {
				if (plugins.indexOf('codeWiew') !== -1) {
					options.pluginsEnabled.push('codeView');
				}
			}

			editor = $el.data('froala.editor');
			if (!editor) {
				$.FroalaEditor.DefineIcon('link', {NAME: 'link'});
				$.FroalaEditor.RegisterCommand('link', {
					title: 'Add / Remove Link',
					focus: true,
					undo: true,
					refreshAfterCallback: true,
					callback: function () {
						if (editor.froalaEditor('link.get') === null) {
							var linkUrl = prompt('Enter the url to insert', 'https://google.com/');
							if (linkUrl) {
								editor.froalaEditor('link.insert', linkUrl, undefined, {target: '_blank'});
							}
						}
						else {
							editor.froalaEditor('link.remove');
						}
					},
					refresh: function(a){
						var b = this.link.get();
						a.toggleClass("fr-active", b !== null);
					}
				});

				if (this.$row.hasClass('enable_editor_shortcode')) {
					$.FroalaEditor.RegisterCommand('shortcode', {
						title: 'Show Shortcodes List',
						icon: '<span style="text-align: center;">{}</span>',
						undo: false,
						focus: false,
						plugin: 'conv_shortcode',
						callback: function () {
							this.conv_shortcode.showShortCodesToolbar(this);
						}
					});
					options.toolbarButtons.push('shortcode');
					options.toolbarButtonsMD.push('shortcode');
					options.toolbarButtonsSM.push('shortcode');
					options.toolbarButtonsXS.push('shortcode');
					options.pluginsEnabled.push('conv_shortcode');
					options.convShortcodeConfig = {
						at_start: "{",
						at_end: "}",
						dataSelector: '.g-shortcodes-item',
						shortcodes: $cf.builder.shortcodes,
					};
					options.htmlAllowedEmptyTags = ['span'];
				}

				editor = $el.froalaEditor(options)
					.on('froalaEditor.drop', function (e, editor, dropEvent) {
						// Focus at the current posisiton.
						editor.markers.insertAtPoint(dropEvent.originalEvent);
						var $marker = editor.$el.find('.fr-marker');
						$marker.replaceWith($.FroalaEditor.MARKERS);
						editor.selection.restore();
						// Insert HTML.
						//editor.html.insert('Element dropped here.');
						dropEvent.preventDefault();
						dropEvent.stopPropagation();
						return false;
					});
			}
			return editor;
		},

		setValue: function(value, quiet){
			if (window.cfFroalaLoaded){
				this._setValue(value, quiet);
			} else if (window.FroalaPromice) {
				this.$row.data('tmp_value', value);
				window.FroalaPromice.then(this._setValue.bind(this, value, quiet));
			}
		},

		_setValue: function(value, quiet){
			if (this.editor.froalaEditor('html.get') !== value) //Fix for froala editor delayed html.set bug
				this.editor.froalaEditor('html.set', value);
			if (!quiet)
				this.trigger('change', [value]);
		},

		getValue: function(){
			var value;
			if (this.editor === undefined){
				value = this.$row.data('tmp_value');
			}else{
				value = this.editor.froalaEditor('html.get');
			}

			return value;
		}
	};

	/**
	 * cof Field: Inline
	 */
	$cof.Field['inline'] = {
		init: function(){
			// If Froala assets aren't loaded yet, preloading them first
			if (window.cfFroalaLoaded !== true){
				if (window.FroalaPromice === undefined){
					window.FroalaPromice = loadMultipleExternalResources($('.cof-builder')[0].onclick() || [])
						.then(function(){
							delete window.FroalaPromice;
							window.cfFroalaLoaded = true;
						});
				}
				window.FroalaPromice.then(this._init.bind(this));
			} else {
				this._init.bind(this)();
			}
		},

		_init: function(){
			this.$builder = $('.cof-builder');
			this.$titlebar = $('.b-titlebar');
			this.$convContainer = $('.conv-container');
			this.$toolbars = this.$builder.find('> .cof-toolbar');
			this.$toolbar = this.$toolbars.filter('[data-field="' + this.name + '"]');
			this.editor = this._createEditor(this.$row);
			this.editor.data('old', this.editor.froalaEditor('html.get'));
			this.editor.on('froalaEditor.keyup', function () {
				if (this.editor.data('old') !== this.editor.froalaEditor('html.get')) {
					this.editor.data('old', this.editor.froalaEditor('html.get'));
					this.trigger('change', [this.getValue()]);
				}
			}.bind(this));
			this.editor.on('froalaEditor.contentChanged', function(){
				this.trigger('change', [this.getValue()]);
			}.bind(this));
			this.$convContainer.on('click', function(e){
				var target = $(e.target);
				if (
					this.$toolbar.hasClass('active')
					&& !this.$toolbar.has(target).length
					&& !this.editor.has(target).length
					&& !this.editor.is(target)
				){
					this.$toolbar.removeClass('active');
				}
			}.bind(this));
			this.$row.data('conv_field', this);
		},

		_createEditor: function($el, options){
			var editor;
			options = options || {
				toolbarInline: true,
				enter: $.FroalaEditor.ENTER_BR,
				//imageMove: true,
				imageResize: 0,
				/*pastePlain: true,*/
				pluginsEnabled: ['wordPaste', 'link'],
				htmlAllowedTags: ['strong', 'em', 'br', 'b', 'i', 'a'],
				shortcutsEnabled: ['bold', 'italic', 'undo', 'redo'],
				shortcutsHint: false,
				toolbarVisibleWithoutSelection: true, // FIX for callback
				imageEditButtons: []
			};

			var tag = $el.data('tagName') || $el.prop("tagName");

			switch (tag) {
				case 'IMG':
					editor = $el;
					this.$btnSet = $('<div class="cof-button type_set"><span>Set Image</span></div>');
					this.$btnChange = $('<div class="cof-button type_change"><span>Change</span></div>');
					this.$btnRemove = $('<div class="cof-button type_remove"><span>Remove</span></div>');
					this.$popup = $('<div class="b-popup">' +
						'<div class="b-popup-overlay pos_fixed active" style="display: none;"></div>' +
						'<div class="b-popup-wrap pos_fixed" style="display: none;">' +
						'<div class="b-popup-box size_xl animation_fadeIn active paddings_none">' +
						'<div class="b-popup-box-h">' +
						'<div class="b-popup-box-header">Choose the file</div>' +
						'<div class="b-popup-box-content ">' +
						'<iframe src="about:blank" data-src="/elfinder/index" id="elFinderFrame" height="600">elFinder loading...</iframe>' +
						'</div>' +
						'<div class="b-popup-box-closer action_hidepopup"></div>' +
						'</div>' +
						'</div>' +
						'</div>' +
						'</div>');

					$el.append(this.$popup);
					$el.append(
						$('<div class="cof-buttons"></div>')
							.append(this.$btnSet)
							.append(this.$btnChange)
							.append(this.$btnRemove)
					);

					this.$btnSet.add(this.$btnChange).on('click', this.openMediaUploader.bind(this));
					editor.on('dblclick', this.openMediaUploader.bind(this));
					this.$btnRemove.on('click', function(){
						this.setValue('');
					}.bind(this));
					this.imgValue = this.sanitizeImgValue(editor.find('.conv-image-h').css('background-image'));
					break;
				case 'INPUT':
					editor = $el;
					$el.val($el.attr('placeholder'));
					$el.attr('placeholder', '');
					$el.attr('autocomplete', 'off');
					$el.attr('type', 'text'); // FIX for FF email

					//TODO: get all placeholder styles and append it to import
					var color = $el.css('color');
					var newColor = colorcolor(color).replace(/[\d.]+(\))$/, "0.5$1");
					$el.data('old-color', color)
						.css('color', newColor);

					editor.data('old', editor.val());
					editor.on('change keyup input paste', $.debounce(250, function(){
						if (editor.data('old') !== editor.val()){
							editor.data('old', editor.val());
							this.setValue(editor.val());
						}
					}.bind(this)));

					break;
				default:
					editor = $el.data('froala.editor');
					if (!editor)
						editor = $el.froalaEditor(options)
							.on('froalaEditor.drop', function(e, editor, dropEvent){
								// Focus at the current posisiton.
								editor.markers.insertAtPoint(dropEvent.originalEvent);
								var $marker = editor.$el.find('.fr-marker');
								$marker.replaceWith($.FroalaEditor.MARKERS);
								editor.selection.restore();
								// Insert HTML.
								//editor.html.insert('Element dropped here.');
								dropEvent.preventDefault();
								dropEvent.stopPropagation();
								return false;
							});
			}

			editor.attr('tabindex', '-1');
			editor
				.on('focusin', function(e){
					if (this.$toolbar.length === 0)
						return false;
					e.preventDefault();

					var toolbarPosition = this._toolbarPosition(editor);
					this.$toolbars.not(this.$toolbar)
						.removeClass('active');
					this.$toolbar
						.css(toolbarPosition)
						.addClass('active');
				}.bind(this))
				.on('froalaEditor.buttons.refresh', function(e, editor){
					this.$toolbar.trigger('editor_callback', [this, editor]);
					editor.selection.save();
				}.bind(this))
				.on('froalaEditor.contentChanged', function(){
					var toolbarPosition = this._toolbarPosition(editor);
					this.$toolbar
						.css(toolbarPosition);
				}.bind(this))
				.on('click', function(){
					var toolbarPosition = this._toolbarPosition(editor);
					this.$toolbar
						.css(toolbarPosition)
						.addClass('active');
				}.bind(this));

			this.$toolbar
				.on('focusin', function(){
					this.$toolbar
						.addClass('active');
				}.bind(this));

			this.$toolbar.data('editor', editor);

			return editor;
		},

		/**
		 * Get toolbar position
		 * @returns {Object}
		 * @private
		 */
		_toolbarPosition: function(editor){
			if (this.$toolbar.length === 0)
				return {};

			var diff = 15;
			var tabsHeight = ((this.$titlebar.hasClass('is-fixed')) ? this.$titlebar.outerHeight() - 80 : 50);
			var position = editor.offset();
			var toolbarBlock = {width: this.$toolbar.outerWidth(), height: this.$toolbar.outerHeight()};
			var wrapPosition = this.$convContainer.offset();
			var toolbarPosition = {
				top: position.top - wrapPosition.top - this.$toolbar.outerHeight() + tabsHeight - diff,
				left: position.left - wrapPosition.left + parseInt(this.$convContainer.css("margin-left")) + editor.outerWidth() / 2 - this.$toolbar.outerWidth() / 2,
				right: 'auto'
			};

			if (toolbarPosition.left + toolbarBlock.width > this.$convContainer.outerWidth()){
				toolbarPosition['right'] = 0;
				toolbarPosition['left'] = 'auto';
			} else if (toolbarPosition.left < 0){
				toolbarPosition['right'] = 'auto';
				toolbarPosition['left'] = 0;
			}

			if (toolbarPosition.top < tabsHeight){
				toolbarPosition['top'] = position.top - wrapPosition.top + editor.outerHeight() + tabsHeight + diff;
			}

			/*var is_mobile = this.$convContainer.cfMod('conv_state') === 'mobile';
			 if (is_mobile){
			 toolbarPosition['left'] = 0;
			 toolbarPosition['right'] = 0;
			 }*/

			return toolbarPosition;
		},

		openMediaUploader: function(){
			if (this.popup === undefined){
				this.popup = new CFPopup(this.$popup);
			}
			window._tmpImageUploader = this; // global var for elFinder
			this.popup.show();
		},

		setValue: function(value, quiet){
			var tag = this.$row.data('tagName') || this.$row.prop("tagName");
			switch (tag) {
				case 'INPUT':
					if (this.editor.val() !== value)
						this.editor
							.val(value);
					if (!quiet)
						this.trigger('change', [value]);
					break;
				case 'IMG':
					var $img = this.editor.find('.conv-image-h');
					if (value !== ''){
						// Need to get dimensions
						var sizeRegexp = /\?(\d+)x(\d+)$/;
						if (!sizeRegexp.test(value)){
							var img = new Image();
							img.onload = function(){
								if (sizeRegexp.test(value)) return;
								value = value + '?' + (img.naturalWidth || 1) + 'x' + (img.naturalHeight || 1);
								$img.css('background-image', 'url("' + value + '")');
								this.trigger('change', [this.imgValue = ((value[0] === '/') ? '<domain>' : '') + value]);
							}.bind(this);
							img.src = value;
						}
						this.trigger('change', [this.imgValue = ((value[0] === '/') ? '<domain>' : '') + value]);
					}else{
						$img.css('background-image', '');
						this.trigger('change', [this.imgValue = '']);
					}
					// Visual style is rendered on change event

					if (this.popup !== undefined) this.popup.hide();
					break;
				default:
					this.editor.froalaEditor('html.set', value);
					if (!quiet)
						this.trigger('change', [value]);
			}
		},

		sanitizeImgValue: function(value){
			if ( ! value || ! (typeof value === "string")) return '';
			value = value
				.replace('url(','').replace(')','')
				.replace(/"/gi, '').replace(/'/gi, '')
				.replace(/^.*\/\/[^\/]+/, '<domain>');
			// Appending size
			if (this.editor && !/\?\d+x\d+$/.test(value)){
				var $img = this.editor.find('.conv-image-h');
				value += '?' + parseInt($img.width()) + 'x' + parseInt($img.height());
			}
			return value;
		},

		getValue: function(){
			var tag = this.$row.data('tagName') || this.$row.prop("tagName");
			switch (tag) {
				case 'INPUT':
					return this.editor.val();
				case 'IMG':
					return this.imgValue;
				default:
					return this.editor.froalaEditor('html.get');
			}
		}
	};

	/**
	 * cof Field: Text
	 */
	$cof.Field['text'] = {
		init: function(){
			this.parentInit();
			this.$input.on('keyup', function(){
				this.trigger('change', [this.getValue()]);
			}.bind(this));
			this.$input.on('keydown', function(e){
				if (e.which === 13){
					var $relevantForm = this.$input.closest('.i-form');
					if ($relevantForm.length){
						// Submitting form on enter key
						e.preventDefault();
						e.stopPropagation();
						$relevantForm.submit();
					}
				}
			}.bind(this));

			this.suggestions = this.$input.data('suggestions') || [];
			if (this.suggestions !== undefined)
			{
				this.$input.autocomplete({
					lookup: this.suggestions,
					minChars:1,
					onSelect: function(){
						this.$input.trigger('change');
					}.bind(this)
				});
				this.$input.on('click', function(){
					this.$input.autocomplete('setOptions', {minChars: 0});
					this.$input.focus();
				}.bind(this));
			}
		}
	};

	/**
	 * cof Field EmailSet
	 */
	$cof.Field['emailset'] = {
		init: function() {

			this.$container = this.$row.find('.cof-emailset:first');
			this.$resendLink = this.$container.find('.action_resend_link');
			this.fieldset = new $cof.Fieldset(this.$container);
			this.siteEmails = this.$container[0].onclick();
			this.$resendDesc = this.fieldset.fields.email.$row.find('.cof-form-row-desc');
			this.fieldset.on('change:email', function(value){
				// When just switched to "(New email)" focusing to the relevant input field
				if (value === '') this.fieldset.fields.new_email.$input.focus();
				// When changing email, updating the value
				else this.trigger('change', [value]);

				// Show/Hide resend link desc
				if ((this.siteEmails[value] && this.siteEmails[value] === '0') || (value.length && ! this.siteEmails[value]))
					this.$resendDesc.show();
				else if ((this.siteEmails[value] && this.siteEmails[value] === '1') || ! value.length)
					this.$resendDesc.hide();
			}.bind(this));

			this.$container.on('submit', function(){
				this.sendLink();
			}.bind(this));

			this.fieldset.on('change:new_email', function(value){
				if (this.$container.cfMod('step') === 'verify' && value !== this.newEmail){
					// Email has changed, resetting the state to request code once again
					this.$container.cfMod('step', 'request');
				}
			}.bind(this));

			this.$resendLink.on('click', function(){
				this.sendLink(true);
			}.bind(this))
		},
		/**
		 * Request email address verification
		 */
		sendLink: function(resend){
			this.fieldset.clearErrors();
			(resend) ? this.$resendLink.addClass('loading') : this.$container.addClass('loading');
			var newEmail = (resend) ? this.fieldset.getValue('email') : this.fieldset.getValue('new_email');
			$.post('/api/site/request_email_verification/' + this.$container.data('site_id'), {
				email: newEmail,
				_nonce: this.$container.data('nonce'),
				action: (resend) ? 'resend' : 'request',
			}, function(r){
				(resend) ? this.$resendLink.removeClass('loading') : this.$container.removeClass('loading');
				if (!r.success) return this.fieldset.showErrors({new_email: r.errors.email});
				// Adding the new option to select
				if (!resend) {
					$('<option value="' + newEmail + '" selected>' + newEmail + '</option>')
						.insertBefore(this.fieldset.fields.email.$input.find('option:last-child'));
					this.fieldset.setValue('email', newEmail);
					this.trigger('change', [this.getValue()]);
					// Making a clean-up to be able to add new email later
					this.$container.cfMod('step', 'request');
					this.fieldset.setValue('new_email', '');
				}
			}.bind(this), 'json');
		},

		getValue: function(){
			return this.fieldset.getValue('email');
		},

		setValue: function(value){
			this.fieldset.setValue('email', value);
		}
	};

	/**
	 * cof Field: wrapper
	 */
	$cof.Field['wrapper_start'] = {
		init: function(){
			this._events = {
				toggle: function () {
					//this.$cont.slideToggle();
					this.$row.toggleClass('is-active');
				}.bind(this),
				show: function () {
					this.$row.addClass('is-active');
				}.bind(this),
				hide: function () {
					this.$row.removeClass('is-active');
				}.bind(this),
				close: function () {
					this.$row.removeClass('is-active');
					$cf.builder._hidePanel();
				}.bind(this)
			};
			this.$title = this.$row
				.find('.cof-form-wrapper-title')
				.setTextPatternVars(); // Here we init the template with empty settings

			this.$title.on('refreshCounter', function(_event, params){
				this.$title.setTextPatternVars(params);
			}.bind(this));
			this.$icon = this.$row.find('.cof-form-wrapper-icon');
			this.$close = this.$row.find('.cof-form-wrapper-icon.type_close');
			// this.$cont = this.$row.find('.cof-form-wrapper-cont');
			this.$title.on('click', this._events.show);
			this.$icon.on('click', this._events.hide);
			this.$close.on('click', this._events.close);
		}
	};

	// Need for old editor
	$cof.Field['froala'] = {
		init: function(){
			this.$buttons = this.$row.find('.cof-froala-button');
			this.$row.parent().on('editor_callback', function(e, element, editor){
				this.$buttons.filter('[data-button="bold"]')
					.toggleClass("active", editor.format.is("strong"));
				this.$buttons.filter('[data-button="italic"]')
					.toggleClass("active", editor.format.is("em"));
			}.bind(this));

			// Using mousedown instead of click to preserve selection in the editor during the event
			this.$buttons.on('mousedown', function(e){
				//var $button = $(e.currentTarget);
				var command = $(e.currentTarget).data('button');
				var editor = this.$row.parent().data('editor');
				switch (command) {
					case 'link.toggle':
						if (editor.froalaEditor('link.get') === null){
							var linkUrl = prompt('Enter the url to insert', 'https://google.com/');
							if (linkUrl){
								editor.froalaEditor('link.insert', linkUrl, undefined, {target: '_blank'});
							}
						}
						else {
							editor.froalaEditor('link.remove');
						}
						this.trigger('change', []);
						break;
					default:
						if (editor.froalaEditor('selection.restore'))
							editor.froalaEditor('commands.' + command);
				}
			}.bind(this));
		},
		setValue: function(){
		},
		getValue: function(){
		}
	};

	/**
	 * cof Field: domains
	 */
	$cof.Field['domains'] = {
		init: function() {
			this.$container = this.$row.find('.cof-domains');
			this.$list = this.$container.find('.cof-domains-list');
			this.template = this.$container.find('> template').html();

			this.$extraTitle = this.$list.find('> .cof-domains-row-title');

			this._events = {
				inputChanged: function(){
					this.trigger('change', [this.getValue()]);
				}.bind(this),
				addInput: this.addInput.bind(this),
				removeInput: function(e){
					var $target = $(e.currentTarget).closest('.cof-domains-row'),
						$input = $target.find('input[type="text"]'),
						index = this.$inputs.index($input);
					this.removeInput(index);
				}.bind(this),
			};

			this.$container.find('.type_add').on('click', this._events.addInput);
			this.$list.on('click', '.type_remove', this._events.removeInput);

			this.$inputs = this.$container.find('.cof-domains-list input[type="text"]')
				.on('change, keyup', this._events.inputChanged);

			this.on('change', function() {
				this.$extraTitle.toggleClass('is-hidden', this.$inputs.length < 2);
			}.bind(this));
		},

		/**
		 * Get values
		 */
		getValue: function() {
			var value = [];
			this.$inputs.each(function(_, input){
				value.push(input.value);
			}.bind(this));

			return value;
		},

		setValue: function(value){
			value = (value instanceof Array) ? value : [value];
			if (value.length == 0) value = [''];
			while (value.length > this.$inputs.length) this.addInput();
			while (this.$inputs.length > value.length) this.removeInput(1);
			$.each(value, function(index, inputValue){
				this.$inputs[index].value = inputValue;
			}.bind(this));
		},

		/**
		 * Add field to list
		 */
		addInput: function() {
			var $field = $(this.template),
				$input = $field.find('input[type="text"]').on('change, keyup', this._events.inputChanged);
			this.$inputs = this.$inputs.add($input);
			this.$list.append($field);
			this.trigger('change', [this.getValue()]);
		},
		/**
		 * Remove field from list
		 * @param index Number
		 */
		removeInput: function(index) {
			// Cannot remove first element or elements that don't exist
			if (index == 0 || this.$inputs[index] === undefined) return;
			var $row = this.$inputs.eq(index).closest('.cof-domains-row');
			this.$inputs = this.$inputs.not(this.$inputs[index]);
			$row.remove();
			this.trigger('change', [this.getValue()]);
		},
		/**
		 * Show error
		 */
		showError: function(errors) {
			$.each(errors, function(index, error){
				this.$inputs.eq(index).closest('.cof-domains-row')
					.addClass('check_wrong')
					.find(' > .cof-domains-row-state')
					.html(error);
			}.bind(this));
		},
		/**
		 * Clear errors
		 */
		clearError: function() {
			this.$container.find('.cof-domains-row.check_wrong')
				.removeClass('check_wrong')
				.find('.cof-domains-row-state')
				.html('');
		}
	};

	/**
	 * Class mutator, adding fieldset behaviour to a class
	 * @type {{}}
	 */
	$cof.mixins.Fieldset = $.extend({}, $cof.mixins.Events, {
		/**
		 * Attach fields to Field set, set callbacks, show_if, etc...
		 * @param fields string or jquery selector
		 * @param noFieldsInit
		 * @param fallbackValues
		 */
		attachFields: function(fields, noFieldsInit, fallbackValues){
			// Dependencies rules and the list of dependent fields for all the affecting fields
			if (!this.showIf) this.showIf = {};
			if (!this.influence) this.influence = {};
			if (!this.affects) this.affects = {};
			if (!this.$fields) this.$fields = $();
			if (!this.fields) this.fields = {};
			if (!this._events) this._events = {};
			if (!this._events.changeField) this._events.changeField = {};

			var $fields = $(fields);
			this.$fields = this.$fields.add($fields);

			$fields.each(function(index, row){
				var $row = $(row),
					type = $row.cfMod('type'),
					name = $row.data('name');

				this.fields[name] = new $cof.Field($row, noFieldsInit);
				var $showIf = $row.find((type === 'wrapper_start') ? '> .cof-form-wrapper-cont > .cof-form-wrapper-showif' : '> .cof-form-row-showif');

				if ($showIf.length){
					this.showIf[name] = ($showIf[0].onclick() || {});
					this.getDependencies(this.showIf[name]).forEach(function(dep){
						// Also can depend on dot-separated path
						dep = dep.split('.')[0];
						//if (this.affects[this.showIf[name][0]] === undefined) this.affects[dep] = [];
						if (this.affects[dep] === undefined) this.affects[dep] = [];
						this.affects[dep].push(name);
					}.bind(this));
				}

				var $influence = $row.find('> .cof-form-row-influence');
				if ($influence.length){
					this.influence[name] = ($influence[0].onclick() || {});
					this.getDependencies(this.influence[name]).forEach(function(dep){
						//if (this.affects[this.showIf[name][0]] === undefined) this.affects[dep] = [];
						if (this.affects[dep] === undefined) this.affects[dep] = [];
						this.affects[dep].push(name);
					}.bind(this));
				}

				// Attaching already bound changes events to newly added fields
				if (this._singleChangeEvents && this._singleChangeEvents[name]){
					this._singleChangeEvents[name].forEach(function(fn){
						this.fields[name].on('change', fn);
					}.bind(this));
				}
				// Attaching global changes event to newly added fields
				if (this.changeWasBound){
					this.fields[name].on('change', function(val){
						this.trigger('change', [name, val]);
					}.bind(this));
				}
			}.bind(this));
			// Fallback values that will be used for missing fields if used by show_if statements
			if (this.fallbackValues === undefined) this.fallbackValues = {};
			if (fallbackValues) $.extend(this.fallbackValues, fallbackValues);
			$.each(this.affects, function(name, affectedList){
				this._events.changeField[name] = function(){
					for (var index = 0; index < affectedList.length; index++){
						var affectedName = affectedList[index];
						if (this.showIf[affectedName] === undefined || this.checkRules(this.showIf[affectedName], this.getValue.bind(this))){
							this.fields[affectedName].trigger('beforeShow');
							this.fields[affectedName].$row.show();
							this.fields[affectedName].trigger('afterShow');
						} else {
							this.fields[affectedName].trigger('beforeHide');
							this.fields[affectedName].$row.hide();
							this.fields[affectedName].trigger('afterHide');
						}
						if (this.influence[affectedName] !== undefined){
							this.fields[affectedName].affect(this.getValue(this.influence[affectedName]));
						}
					}
				}.bind(this, affectedList);
				if (this.fields[name] === undefined && this.fallbackValues[name] === undefined){
					console.error('Field ' + name + ' not found');
				} else if (name[0] === '_'){
					// Custom events for private\virtual fields (_state)
					this.on('change' + name, this._events.changeField[name]);
				} else if (this.fields[name] !== undefined){
					this.fields[name].on('change', this._events.changeField[name]);
				}
				this._events.changeField[name]();
			}.bind(this));
			// Passing visibility-related events to visible fields
			['beforeShow', 'afterShow', 'beforeHide', 'afterHide'].forEach(function(event){
				this.on(event, function(){
					$.map(this.fields, function(field){
						if (field.$row.css('display') !== 'none') field.trigger(event);
					});
				}.bind(this));
			}.bind(this));
			return this;
		},
		/**
		 * Detach fields from Field set
		 * @param fields string | jquery selector
		 */
		detachFields: function(fields){
			var $fields = $(fields);

			$fields.each(function(index, row){
				var $row = $(row),
					name = $row.data('name'),
					field = this.fields[name];

				if (this.showIf[name]){
					delete this.showIf[name];
				}

				Object.keys(this.affects).map(function(key){
					// Try to find deleted key in other affects
					var fieldIndex = this.affects[key].indexOf(name);
					if (fieldIndex !== -1){
						this.affects[key].splice(fieldIndex, 1);
					}
				}.bind(this));

				// clear affects and fallback if removing last field
				delete this.affects[name];
				delete this.fallbackValues[name];

				// Remove events from fields
				for (var fieldProp in field){
					// TODO Move event handlers removal to deinit of each separate field
					if (field.hasOwnProperty(fieldProp) && fieldProp[0] === '$' && field[fieldProp] instanceof jQuery){
						field[fieldProp].off();
					}
				}

				field.deinit();
				field.off('change', this._events.changeField[name]);
				field.$row.removeData('cof_field');

				delete this.fields[name];
			}.bind(this));

			this.$fields = this.$fields.not($fields);
			return this;
		},
		/**
		 * Get a particular field value
		 * @param name string
		 * @returns {*}
		 */
		getValue: function(name){
			if (this.fields === undefined || this.fields[name] === undefined || ! (this.fields[name].getValue instanceof Function)){
				return (this.fallbackValues || {})[name] || null;
			}
			return this.fields[name].getValue();
		},
		setValue: function(name, value, quiet){
			if (this.fields[name] !== undefined && this.fields[name].setValue instanceof Function){
				this.fields[name].setValue(value, quiet);
			}
		},
		getValues: function(){
			var values = {};
			for (var name in this.fields){
				if (!this.fields.hasOwnProperty(name) || !(this.fields[name].getValue instanceof Function)) continue;
				if (this.fields[name].inited !== false){
					values[name] = this.fields[name].getValue();
				}
			}
			return values;
		},
		setValues: function(values, quiet){
			$.each(values, function(name, value){
				if (this.fields[name] !== undefined && this.fields[name].setValue instanceof Function){
					this.fields[name].setValue(value, quiet);
					// As events are suppressed, triggering events required to update fields visibility only
					if (quiet && this._events.changeField[name]) this._events.changeField[name]();
				}
			}.bind(this));

			return this;
		},
		/**
		 *
		 * @param showIf
		 * @returns {Array}
		 */
		getDependencies: function(showIf){
			var deps = [];
			if (showIf[0] instanceof Array){
				// Complex statement with and / or request
				for (var i = 0; i < showIf.length; i += 2) deps = deps.concat(this.getDependencies(showIf[i]));
			}
			else {
				// Simple statement
				deps.push(showIf[0]);
			}
			return deps;
		},

		fieldIsVisible: function(field){
			if (this.showIf[field] === undefined)
				return true;

			return this.checkRules(this.showIf[field], this.getValue.bind(this));
		},

		/**
		 * Check showIf Rules
		 *
		 * @param showIf
		 * @param getValue function
		 * @returns {boolean}
		 */
		checkRules: function(showIf, getValue){
			var result = true;
			if (!$.isArray(showIf) || showIf.length < 3){
				return result;
			} else if ($.inArray(showIf[1].toLowerCase(), ['and', 'or']) !== -1){
				// Complex or / and statement
				result = this.checkRules(showIf[0], getValue);
				var index = 2;
				while (showIf[index] !== undefined) {
					showIf[index - 1] = showIf[index - 1].toLowerCase();
					if (showIf[index - 1] === 'and'){
						result = (result && this.checkRules(showIf[index], getValue));
					} else if (showIf[index - 1] === 'or'){
						result = (result || this.checkRules(showIf[index], getValue));
					}
					index = index + 2;
				}
			} else {
				// Also can use dot-separated paths
				var fieldPath = showIf[0].split('.'),
					fieldName = fieldPath.shift(),
					value = getValue(fieldName);
				if (fieldPath.length) value = arrayPath(value, fieldPath, undefined);
				if (value === undefined) return true;
				if (showIf[1] === '='){
					result = ( value == showIf[2] );
				} else if (showIf[1] === '!=' || showIf[1] === '<>'){
					result = ( value != showIf[2] );
				} else if (showIf[1] === 'in'){
					result = ( !showIf[2].indexOf || showIf[2].indexOf(value) !== -1);
				} else if (showIf[1] === 'not in'){
					result = ( !showIf[2].indexOf || showIf[2].indexOf(value) === -1);
				} else if (showIf[1] === 'has'){
					result = ( !value.indexOf || value.indexOf(showIf[2]) !== -1);
				} else if (showIf[1] === '<='){
					result = ( value <= showIf[2] );
				} else if (showIf[1] === '<'){
					result = ( value < showIf[2] );
				} else if (showIf[1] === '>'){
					result = ( value > showIf[2] );
				} else if (showIf[1] === '>='){
					result = ( value >= showIf[2] );
				} else {
					result = true;
				}
			}
			return result;
		},
		parentOn: $cof.mixins.Events.on,
		on: function(handle, fn){
			if (handle === 'change' && !this.changeWasBound){
				// Dev note: quite a heavy thing. Try to avoid it when possible ...
				// TODO: fields after attachFields not handle (trigger) events
				// TODO: example this.attachFields => 3 fields attached => this.on => 3 events add to Fieldset => again this.attachFields => no new events to Fieldset
				$.each(this.fields, function(name, field){
					field.on('change', function(val){
						Array.prototype.unshift.call(arguments, name); // add name as first argument
						this.trigger('change', arguments);
					}.bind(this));
				}.bind(this));
				this.changeWasBound = true;
			}
			if (handle.substr(0, 7) === 'change:'){
				// Binding a particular field
				var fieldName = handle.substr(7);
				if (this.fields[fieldName] === undefined) return;
				this.fields[fieldName].on('change', fn);
				// Storing single-bound functions to bind them to newly added fields as well
				this._singleChangeEvents = this._singleChangeEvents || {};
				this._singleChangeEvents[fieldName] = this._singleChangeEvents[fieldName] || [];
				this._singleChangeEvents[fieldName].push(fn);
			} else {
				this.parentOn(handle, fn);
			}

		},
		clearErrors: function(){
			$.each(this.fields, function(fieldId, field){
				if (field.clearError instanceof Function) field.clearError();
			}.bind(this));
		},
		showErrors: function(errors, clearFirst){
			if (clearFirst) this.clearErrors();
			for (var key in errors){
				if (!errors.hasOwnProperty(key)) continue;
				var message = errors[key],
					field = key.split('.', 2)[0];
				if (this.fields[field] !== undefined){
					this.fields[field].showError(message, key.substr(key.indexOf('.') + 1));
				} else {
					console.error(errors[key]);
				}
			}
		},
		triggerFieldsIn: function($container, eventType, params){
			$($container).find('.cof-form-row, .conv-form-row').each(function(_, row){
				var $row = $(row),
					field = $row.data('cof_field') || (this.fields && this.fields[$row.data('name')]);
				if (field && field.trigger instanceof Function) field.trigger(eventType, params);
			});
		}
	});

	/**
	 * $cof.Fieldset class
	 * Boundable events: change, change:field
	 * @param container
	 * @param noFieldsInit bool Don't init fields on load. Instead the field will be inited on beforeShow event
	 * @param values
	 * @constructor
	 */
	$cof.Fieldset = function(container, noFieldsInit, values){
		values = values ? values : {};
		var $container = $(container),
			$fallbackValues = $container.children('.cof-form-values'),
			fallbackValues = ($fallbackValues.length ? ($fallbackValues[0].onclick() || {}) : {});
		this.attachFields(
			$container
				.find('> .cof-form-row,'
					+' > .cof-form-wrapper,'
					+' > .cof-form-wrapper > .cof-form-wrapper-cont > .cof-form-row'
				),
				//.not('.blocked'), // do not init blocked controls (free plan)
			noFieldsInit,
			$.extend(fallbackValues, values)
		);
	};

	$.extend($cof.Fieldset.prototype, $cof.mixins.Fieldset);
}(jQuery);
