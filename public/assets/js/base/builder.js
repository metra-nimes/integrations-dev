!function($){
	if (window.$cf === undefined) window.$cf = {};
	$cf.Builder = {
		fps: 100,
		_disableHighlight: false,
		animationDuration: 300,

		/**
		 * Fixed screen height for mobile version.
		 * @type number
		 */
		_mobileScreenHeight: 568,

		/**
		 * The default settings for Spin To Win
		 * @var {object}
		 */
		_spintoWinOptions: {
			slices: [],
			selector: 'text',
			center: {},
			inner: {},
			text: {},
			marker: {},
		},

		/**
		 * Window creation flag
		 * @type boolean
		 */
		_isCreateScreen: false,

		/**
		 * Limit on the number of screens
		 * @type number
		 */
		_SCREEN_MAX_LIMIT: 50,

		_screenTemplate: $('template[data-id="empty-screen"]').html(),

		init: function(container, options){
			// Options
			this.options = $.extend({}, options);

			this._events = {
				hashchange: function () {
					if (document.location.hash && (this.hash = document.location.hash.substring(1)) && this.hashStates.indexOf(this.hash) > -1){
						setTimeout(this.openFieldset.bind(this, this.hash), 50);
					}
				}.bind(this),
				resize: this._onResize.bind(this),
				scroll: this._onScroll.bind(this),
				clickElm: this._onElmClick.bind(this),
				moveElm: $.throttle(1000 / this.fps, this._onElmMove.bind(this)),
				unhoverElm: $.throttle(1000 / this.fps, this._onElmUnhover.bind(this)),
				sortElm: this._onSortElm.bind(this),
				hidePanel: this._hidePanel.bind(this),
				scrollPanel: function (e) {
					this.$panelTitle.toggleClass('with_border', e.currentTarget.scrollTop !== 0);
				}.bind(this),
				openFieldset: function(e){
					var $btn = $(e.currentTarget),
						type = $btn.cfMod('type');
					this.openFieldset(type);
				}.bind(this),
				duplicateElm: function(e){
					var $controls = $(e.currentTarget).closest('.show_controls'),
						id = ($controls.cfMod('conv_id')) ? $controls.cfMod('conv_id') : $controls.parent().cfMod('conv_id');
						this.duplicateElm(id);
				}.bind(this),
				removeElm: function(e){
					e.preventDefault();
					var $el = $(e.currentTarget),
						id = $el.closest('.show_controls').cfMod('conv_id') || $el.closest('.show_controls').parent().cfMod('conv_id');
					if (window.confirm('Are you really want delete ' + id + ' element?')){
						this.removeElm(id);
						this._disableHighlight = false;
						this.$widget
							.on('click', this._events.clickElm)
							.on('mousemove', this._events.moveElm)
							.on('mouseleave', this._events.unhoverElm);
						this.hideHighlight();
						// Check the size of the widget for mob. phones
						this._checkWidgetHeight();
					}
				}.bind(this),
				keydown: function(e){
					var reportStr =
						( e.ctrlKey ? "CTRL+" : "" ) +
						( e.shiftKey ? "SHIFT+" : "" ) +
						( e.altKey ? "ALT+" : "" ) +
						( e.metaKey ? "META+" : "" ) +
						e.keyCode;

					switch (reportStr) {
						case 'CTRL+83': // CTRL + S
						case 'META+83': // META + S
							e.preventDefault();
							if (this.$saveBtn.hasClass('state_active')) this.handleAction('save', this.$saveBtn);
							break;
						case 'CTRL+90': // CTRL + Z
						/*case 'CTRL+SHIFT+90': // CTRL + SHIFT + Z
							e.preventDefault();
							// https://github.com/convertful/app/blob/f01dedbd2579da6d79c69fdbcaa011a476f11524/www/assets/js/base/builder.js#L85
							break;*/
					}
				}.bind(this),
				tabClick: function(e){
					var newTab = $(e.currentTarget).data('id');
					// TODO Simplify this and remove constants
					if (newTab === 'design'){
						// Showing previously opened fieldset
						if (this.activeDesignFieldsetID !== undefined){
							return this.openFieldset(this.activeDesignFieldsetID);
						}
						this.openTab('design');
						// Cleaning up the browser url state
						history.replaceState(null, '', window.location.pathname);
					}
					else if (newTab === 'rules'){
						this.openFieldset('rules');
					}
					else if (newTab === 'success'){
						this.openFieldset('success');
					}
				}.bind(this)
			};
			// Commonly used DOM elements
			this.$body = $cf.$body;
			this.$container = $(container);

			// Toolbar
			this.$toolbar = this.$container.find('.b-builder-toolbar');
			this.$stateSwitcher = this.$toolbar.find('.b-stateswitcher');
			this.$saveBtn = this.$toolbar.find('.b-builder-toolbar-control.action_save');

			// Tabs
			this.$tabsButtons = this.$toolbar.find('.b-builder-toolbar-tabs-item').on('click', this._events.tabClick);
			this.activeTab = this.$tabsButtons.filter('.is-active').data('id');
			this.$tabsContents = this.$container.find('.b-builder-tab');

			// Panel
			this.$panel = this.$container.find('.b-builder-panel');
			this.$panelH = this.$panel.find('.b-builder-panel-h');
			this.$panel.find('.b-builder-panel-closer')
				.on('click', this._events.hidePanel);
			this.$panelTitle = this.$panel.find('.b-builder-panel-title');

			// Value and unsaved changes
			this.value = this.$container.find('.b-builder-current-values:first')[0].onclick();

			this.$fieldsetsElm = {};
			this.fieldsets = {};

			// Extrernal elements
			this.$externalElm = {};

			// Fieldsets defaults
			this.defaults = this.$container.find('.b-builder-defaults')[0].onclick();

			// Currently edited element (id and jQuery obj with DOM element)
			this.updatedElm = this.$updatedElm = null;

			// Shortcodes array
			this.shortcodes = this.defaults['default_shortcodes'];

			this.fieldsNames = [];
			this.$container.find('.b-builder-fieldset').each(function(_, elm){
				var $elm = $(elm),
					id = $elm.cfMod('for');
				this.$fieldsetsElm[id] = $elm;
			}.bind(this));
			this.$fieldsetsButtons = this.$panel
				.find('.b-builder-panel-controls.for_fieldset')
				.find('.b-builder-panel-control')
				.on('click', function(e){
					var $el = $(e.currentTarget);
					this.updatedElm = this.$updatedElm = null;
					this.hideHighlight();
					if ($el.hasClass('is-active')) this._events.hidePanel(e);
					else this._events.openFieldset(e);
				}.bind(this));

			//Preview
			this.$preview = this.$container.find('.b-builder-preview');
			this.$preview
				.on('transitionend', function() {
					this._disableHighlight = false;
				}.bind(this));

			this.$container
				.on((window.PointerEvent) ? 'pointerdown': 'mousedown', function () {
					this.$widget.off('mousemove', this._events.moveElm);
				}.bind(this))
				.on((window.PointerEvent) ? 'pointerup': 'mouseup', function () {
					this.$widget.on('mousemove', this._events.moveElm);
				}.bind(this));
			this.$widget
				.on('mouseenter', '.b-builder-highlight-helpers', function () {
					this._disableHighlight = true;
					this.$widget
						.off('click', this._events.clickElm)
						.off('mousemove', this._events.moveElm)
						.off('mouseleave', this._events.unhoverElm);
				}.bind(this))
				.on('mouseleave', '.b-builder-highlight-helpers', function () {
					this._disableHighlight = false;
					this.$widget
						.on('click', this._events.clickElm)
						.on('mousemove', this._events.moveElm)
						.on('mouseleave', this._events.unhoverElm);
				}.bind(this))
				.on('mouseleave', '.show_controls', function (e) {
					this._events.unhoverElm(e);
				}.bind(this))
				.on('click', '.b-builder-highlight-helpers-title', function (e) {
					this._events.clickElm(e);
				}.bind(this))
				.on('click', '.b-builder-highlight-helpers-button.type_duplicate', this._events.duplicateElm)
				.on('click', '.b-builder-highlight-helpers-button.type_remove', this._events.removeElm);

			//Hightlights
			this.$highlight = this.$preview.find('.b-builder-highlight');

			$cf.$window.on('hashchange', this._events.hashchange);
			$cf.$window.on('resize', this._events.resize);
			$cf.$window.on('scroll', this._events.scroll);
			$cf.$window.bind('keydown', this._events.keydown);

			// Related CSS
			this.$css = {};
			this.$container.find('.b-builder-css').each(function(_, style){
				var $style = $(style);
				this.$css[$style.data('id')] = $style.appendTo($cf.$head);
			}.bind(this));

			// Updating css paths dependencies
			this._compileCustomCSS(this.getValue('custom_css', ''), true);

			// State controls
			this.$stateControls = this.$panel
				.find('.b-builder-panel-controls.for_state')
				.find('.b-builder-panel-control')
				.on('click', function(e){
					var oldState = this.$preview.cfMod('state'),
						newState = $(e.currentTarget).cfMod('type');
					// Support chat
					if (newState === 'support_chat') {
						if ( !! window.gist)
						{
							window.gist.chat('openConversationList');
						}
						return;
					}
					if (oldState !== newState){
						this.setState(newState);
						this.showHighlight(this.$updatedElm);
					}
					this.$panel.find('.b-builder-fieldset.is-active').removeClass('is-active');
				}.bind(this));

			// Screens controls
			this.$screensContainer = this.$container.find('.b-builder-tab-screens');
			this.$screensContainerTrack = this.$screensContainer.find('.b-builder-tab-screens-track:first');
			this.$screensContainerTrack
				.find('.b-builder-tab-screen-actions')
				.on({
					mouseover: function(e) {
						var $target = $(e.target);
						$target
							.find('.b-builder-tab-screen-actions-menu')
							.css({
								left: $target.offset().left || 0
							});
					},
					mouseenter: function(e) {
						$(e.target)
							.parents('.b-builder-tab-screen')
							.addClass('is-hover');
					},
					mouseleave: function(e) {
						$(e.target)
							.parents('.b-builder-tab-screen')
							.removeClass('is-hover');
					}
				});

			// Screens sortable and auto scroll
			this._initScreenSortable();

			// Rename screen popup
			this.popupRenameScreen = new CFPopup('.b-popup.for_rename-screen');
			this.popupRenameScreen.input = new $cof.Field(this.popupRenameScreen.$box.find('[data-name=screen_title]'));
			this.popupRenameScreen.$box
				.on('keyup', 'input', function(e) {
					if(e.keyCode === 13) {
						this.popupRenameScreen.input
							.trigger('change:submit');
						this.popupRenameScreen.hide();
					}
				}.bind(this))
				.on('click', '.g-btn', function() {
					this.popupRenameScreen.input
						.trigger('change:submit');
					this.popupRenameScreen.hide();
				}.bind(this));

			// Auth popup
			this.popupAuth = new CFPopup('.b-popup.for_widget-auth');
			this.popupAuth.$box.on('click', '.g-btn', function(e) {
				e.preventDefault();
				this._authUser($(e.target));
			}.bind(this));

			// Always starting with the first screen
			this.setScreen(this.$screensContainerTrack.children(':first').cfMod('id'));

			// Adding new screens
			this.$container
				.find('.b-builder-tab-newscreen')
				.on({
					mouseover: function(e) {
						$(e.currentTarget).addClass('is-hover');
					},
					mouseleave: function(e) {
						$(e.currentTarget).removeClass('is-hover');
					}
				})
				.find('.b-builder-tab-newscreen-list')
				.on('click', '.b-builder-tab-newscreen-item', function(e) {
					var $addBtn = $(e.currentTarget),
						screenType = $addBtn.cfMod('type'),
						screenTitle = $addBtn.html();
					if ($addBtn.hasClass('is-locked')) return;
					$addBtn
						.parents('.b-builder-tab-newscreen')
						.removeClass('is-hover');
					var screenId = this.addScreen(screenType, screenType === 'blank' ? 'Screen' : screenTitle);
					this.setScreen(screenId);
				}.bind(this));

			// Init Floating Action Button
			this.fab.init.call(this);

			// Hash navigation
			this.hashStates = ['widget', 'layout', 'elements', 'rules', 'custom_css', 'import'];

			// Init gamification elements
			this.gamificationElms = ['scratch_card', 'spintowin'];
			this.gamificationElms.forEach(function(element_type){
				this.$preview.find('.conv-'+element_type).each(function(key, elm){
					this._initGamificationElm(elm)
				}.bind(this));
			}.bind(this));

			this._initBackup();

			this._initSortable();
			this._previewResize();
			this.$container.removeClass('noinit');
			$cf.$window.trigger('scroll');
			$cf.$window.trigger('hashchange');

			this.trigger('afterInit');
		},

		_initSortable: function(){
			this.$columns = $('.conv-col-h');
			this.columnsSortables = dragula(this.$columns.toArray(), {
				mirrorContainer: (this.$widget.cfMod('conv_type') === 'welcome') ? document.querySelector('.conv-widget-h') : document.querySelector('.conv-widget')
			});
			this.columnsSortables
				.on('drag', function (el, source) {
					if (el.classList.contains('conv-video') && el.getElementsByTagName('iframe').length) {
						this._elementIframeSrc = el.getElementsByTagName('iframe')[0].src;
						el.getElementsByTagName('iframe')[0].src = "about:blank";
					}
					this._columnsSortablesOldIndex = Array.prototype.indexOf.call(source.children, el);
					this.hideHighlight();
					this._disableHighlight = true;
					this.$preview.addClass('is-elementdrag');
					this.$widget
						.off('click', this._events.clickElm)
						.off('mousemove', this._events.moveElm)
						.off('mouseleave', this._events.unhoverElm);
				}.bind(this))
				.on('dragend', function (el) {
					if (el.classList.contains('conv-video') && el.getElementsByTagName('iframe').length && this._elementIframeSrc) {
						el.getElementsByTagName('iframe')[0].src = this._elementIframeSrc;
					}
					this.$preview.removeClass('is-elementdrag');
					this._disableHighlight = false;
					this.$widget
						.on('click', this._events.clickElm)
						.on('mousemove', this._events.moveElm)
						.on('mouseleave', this._events.unhoverElm);
				}.bind(this))
				.on('drop', this._events.sortElm);
		},

		_destroySortable: function(){
			this.columnsSortables.destroy();
		},

		/**
		 * Check the size of the widget for mob. phones
		 */
		_checkWidgetHeight: function() {
			var pid = setTimeout(function() {
				var widgetHeigth = this.$preview.find('.conv-widget-hhh').outerHeight() || this.$preview.find('.conv-widget-h').outerHeight();
				if(this.$preview.cfMod('state') === 'mobile' &&  widgetHeigth > this._mobileScreenHeight)
				{
					this.$preview.addClass('height_overflow');
				} else {
					this.$preview.removeClass('height_overflow');
				}
				clearTimeout(pid);
			}.bind(this), 1);
		},

		/**
		 * Call parent class method
		 * @param method string
		 * @param args array
		 */
		parent: function(method, args){
			$cf.Builder[method].apply(this, args);
		},

		_previewResize: function () {
			var values = this.getValue(), minWidth;
			switch(values.type) {
				case 'welcome':
					minWidth = 940;
					break;
				case 'inline':
				case 'bar':
					minWidth = values.options.max_width || 900;
					break;
				default:
					minWidth = this.$widget.width() || 900;
			}

			this.$preview
				.find('.b-builder-preview-h')
				.css({minWidth: minWidth});
		},

		/**
		 * Browser window resize handling
		 * @private
		 */
		_onResize: function(){
			this.hideHighlight();
			$.debounce(500, true, this.showHighlight(true));
		},

		/**
		 * Browser window scroll handling
		 * @private
		 */
		_onScroll: function(){
			this.hideHighlight();
			$.debounce(500, true, this.showHighlight(true));
		},

		_getNearestElm: function(elm){
			var found;

			while (!(found = this._isHoverableElement(elm)) && this.$container[0] !== elm) {
				if (!elm.parentNode) return null;
				elm = elm.parentNode;
			}
			return (found) ? elm : null;
		},
		_onElmClick: function(e){
			e.stopPropagation();
			e.preventDefault();

			var elm = this._getNearestElm(e.target);
			if (this.$updatedElm instanceof jQuery && elm === this.$updatedElm[0]) return;

			var $elm = $(elm),
				id = $elm.cfMod('conv_id') || 'widget';

			this.hideHighlight();
			this.openFieldset(id, $elm);
		},
		_onElmMove: function(e){
			if (!this.columnsSortables.dragging) e.stopPropagation();
			var elm = this._getNearestElm(e.target);
			this.hideHighlight();

			if (this._isHoverableElement(elm)) this.showHighlight($(elm));
		},
		_onElmUnhover: function(e){
			e.stopPropagation();
			this.hideHighlight();

			if (this.$updatedElm) this.showHighlight(this.$updatedElm);
		},
		_onSortElm: function(el, target, source){
			var item = $(el).cfMod('id');  // dragged HTMLElement
			var from = (source) ? $(source).parent().cfMod('id') : null;
			var to = $(target).parent().cfMod('id');
			var newIndex = $(el).index();

			var layout = this.getValue('layout');
			var value = arrayPath(layout, to, []);

			if (from){
				var oldValue = arrayPath(layout, from, []);
				oldValue.splice(this._columnsSortablesOldIndex, 1);
				if (oldValue.length === 0)
					oldValue = undefined;
			}

			value.splice(newIndex, 0, item);
			arraySetPath(layout, to, value);
			if (from)
				arraySetPath(layout, from, oldValue);

			this._disableHighlight = false;
			this.showHighlight($(el));
			this.setValue('layout', layout);

		},
		_isHoverableElement: function(elm){
			if (!elm || !elm.className) return false;
			return /\s*(conv_id_(?!(form_))[a-z0-9_]+|conv-widget)(\s|$)/.test(elm.className);
		},
		_onChange: function(fieldsetType, key, value, fieldsetObj){
			var path = key,
				needRender = false,
				newValue = value,
				isHTML =function (str) {
					var doc = new DOMParser().parseFromString(str, "text/html");
					return [].slice.call(doc.body.childNodes).some(function (node) { return node.nodeType === 1 });
				},
				clearHtml = function(s) {
					var div = document.createElement('div');
					div.innerHTML = s;
					var scripts = div.getElementsByTagName('script');
					var i = scripts.length;
					while (i--) {
						scripts[i].parentNode.removeChild(scripts[i]);
					}
					var styles = div.getElementsByTagName('style');
					var j = styles.length;
					while (j--) {
						styles[j].parentNode.removeChild(styles[j]);
					}
					return div.innerHTML;
				};
			switch (fieldsetType) {
				case 'success':
				case 'layout':
				case 'rules':
					break;
				case 'custom_css':
					value = this._compileCustomCSS(value, true);
					this.$css.custom.html(value);
					this.$panel.find('.type_custom_css').toggleClass('show_indicator', !! value);
					break;
				case 'widget':
					path = 'options.' + key;
					needRender = true;
					break;
				case 'html':
					newValue = isHTML(newValue) ? clearHtml(newValue) : '';
					path = 'data.' + this.updatedElm + '.' + key;
					needRender = (this.updatedElm.indexOf(fieldsetType) !== -1);
					break;
				case 'spintowin':
					this._applySpintoWinChange.apply(this, arguments);
					path = 'data.' + this.updatedElm + '.' + key;
					needRender = (this.updatedElm.indexOf(fieldsetType) !== -1);
					break;
				case 'scratch_card':
					if(this.$externalElm.hasOwnProperty(this.updatedElm)) {
						var convScratchCard = this.$externalElm[this.updatedElm][0].convScratchCard;
						if(key === 'scratch_layer_color') {
							convScratchCard.style(value);
						}
						else if (key === 'scratch_layer_text') {
							convScratchCard.backgroundText(value);
						}
						else if (key === 'prizes' && value !== undefined) {
							var currentPrize = convScratchCard._focusPrize || 0;
							convScratchCard.texts(value[currentPrize].text, value[currentPrize].coupon);
						}
						convScratchCard.resize();
					}
					path = 'data.' + this.updatedElm + '.' + key;
					needRender = (this.updatedElm.indexOf(fieldsetType) !== -1);
					break;
				default:
					path = 'data.' + this.updatedElm + '.' + key;
					needRender = (this.updatedElm.indexOf(fieldsetType) !== -1);
			}
			var oldValue = this.getValue(path),
				valueChanged = (typeof oldValue === 'object') ? ! $cf.helpers.equals(oldValue, newValue) : (oldValue !== newValue);

			// If css vars affected, updating css
			if (this._cssVars && this._cssVars.indexOf(path) !== -1){
				this.$css.custom.html(this._compileCustomCSS(this.getValue('custom_css', '')));
			}

			// When we add a NEW "send_data" automation we fill it with data about the fields of the widget
			if (key === 'submit_actions' || key === 'click_actions'){
				$.each(newValue, function(index, valueItem){
					var type = valueItem.name.replace(/[0-9]+$/, '');
					if (type !== 'send_data')
						return;

					if (oldValue && oldValue[index] !== undefined)
						return;

					if (value[index]['vars'].length === 0) {
						$.each(this.getValue('data.form.fields', []), function(_, field){
							var name = field.name || field.type;
							value[index]['vars'].push([name, '{' + name + '}']);
						});
					}
					value[index]['type'] = 'post';

					fieldsetObj.setValue(key, value)
				}.bind(this));
				if (typeof oldValue === 'object') {
					valueChanged = (JSON.stringify(oldValue) !== JSON.stringify(newValue));
				}
			}

			if (valueChanged){
				if (needRender)
					this._renderElmChange(this.updatedElm, key, newValue);
				this.setValue(path, value);
				// Check for email field
				if(key === 'fields'){
					var hasEmail = false,
						$warningEmail = $('.i-email-warning', this.$panelH);
					for(var k in value){
						if(value && value.hasOwnProperty(k) && typeof value[k].name === 'string' && value[k].name.substr(0, 5) === 'email' || value[k].type === 'email'){
							hasEmail = true;
						}
					}
					if( ! hasEmail){
						$warningEmail.removeClass('is-hidden');
					} else {
						$warningEmail.addClass('is-hidden');
					}
				}

				// update shortcodes list
				if (['form', 'scratch_card', 'spintowin'].indexOf(fieldsetType) !== -1)
				{
					if (typeof oldValue === 'object' && typeof newValue === 'object') {
						var oldValues = Object.keys(oldValue).map(function(key) {
								return oldValue[key].name || oldValue[key].type;
							}),
							newValues = Object.keys(newValue).map(function(key) {
								return newValue[key].name || newValue[key].type;
							});
						oldValues.forEach(function (item) {
							if (newValues.indexOf(item) < 0) {
								var index = this.shortcodes.indexOf(item);
								if (index > -1)
									delete this.shortcodes[index];
							}
						}.bind(this));
						this.shortcodes = $.extend(this.shortcodes, newValues);
					} else if (key === 'prize_field' || key === 'coupon_field'){
						var index = this.shortcodes.indexOf(oldValue);
						if (index > -1)
							this.shortcodes[index] = newValue;
					}

					this.shortcodes = $.unique(this.shortcodes);
				}
			}
		},

		/**
		 * Compile custom css value
		 * @param value string
		 * @param updateVars boolean
		 * @return string
		 * @private
		 */
		_compileCustomCSS: function(value, updateVars){
			var reserved = ['media', 'widget_id'];
			if (value instanceof Array) value = value.join("\n");
			// Storing last css value to update dependent paths, when it's updated
			if (updateVars) this._cssVars = [];
			var regExp = /@(([a-z_\d]+\.)?([a-z\d_]+))/g;
			value = value.replace(regExp, function(all, m1, m2){
				var path = [(m2 === undefined) ? 'options' : 'data'].concat(m1.split('.'));
				if (updateVars){
					for (var i = 1; i < path.length + 1; i++) this._cssVars.push(path.slice(0, i).join('.'));
				}
				return (reserved.indexOf(m1) !== -1) ? all : this.getValue(path, '');
			}.bind(this));
			return value;
		},

		_loadFont: function (value, oldValue, callback) {
			if (this.loadedFonts === undefined) this.loadedFonts = [];
			var isGoogle = (value !== "" && value.indexOf(',') === -1);
			if (isGoogle){
				var fontUrl = 'https://fonts.googleapis.com/css?family=' + encodeURIComponent(value);
				if (oldValue && this.loadedFonts[oldValue] !== undefined)
					this.loadedFonts[oldValue]['counter']--;
				if (this.loadedFonts[value] === undefined){
					loadExternalResource(fontUrl, 'font')
						.then(function(){
							this.loadedFonts[value] = {
								el: $('link[href="' + fontUrl + '"]').eq(-1),
								counter: 1
							};
							if (callback instanceof Function) callback();
							if (oldValue && this.loadedFonts[oldValue] !== undefined && this.loadedFonts[oldValue]['counter'] < 1){
								this.loadedFonts[oldValue]['el'].remove();
								delete this.loadedFonts[oldValue];
							}
						}.bind(this));
				} else {
					this.loadedFonts[value]['counter']++;
					if (callback instanceof Function) callback();
					if (oldValue && this.loadedFonts[oldValue] !== undefined && this.loadedFonts[oldValue]['counter'] < 1){
						this.loadedFonts[oldValue]['el'].remove();
						delete this.loadedFonts[oldValue];
					}
				}
			} else {
				if (callback instanceof Function) callback();
			}
		},

		/**
		 * Render element
		 * @param id string Element's ID
		 * @param values object Element's fields values
		 * @param children array Element's children
		 * @param $replaceElm jQuery Element that will be replaced by the rendered element
		 * @param onSuccess function Function to execute on finish
		 * @param onFailure function Function to execute on failure
		 * @private
		 */
		_renderElm: function(id, values, children, $replaceElm, onSuccess, onFailure){
			$replaceElm.addClass('conv-preloader');
			// Storing latest render requests to keep the updates synchronous
			if (this._renderRequests === undefined) this._renderRequests = {};
			if (this._renderRequests[id]) this._renderRequests[id].abort();
			this._renderRequests[id] = $.ajax({
				method: 'POST',
				url: '/api/widget/render_element',
				data: {
					id: id,
					values: values || {},
					children: children || [],
					widget_id: this.$container.data('id')
				},
				timeout: 15000,
				success: function(r){
					if (!r.success){
						if (typeof r.errors.auth !== 'undefined') {
							this.authSession = [id, values, children, $replaceElm, onSuccess, onFailure];
							this.popupAuth.show();
						}
						if (onFailure instanceof Function) onFailure();
						return;
					}
					if (r.data.html !== '' ){
						var $newElm = $(r.data.html);
						$replaceElm.replaceWith($newElm);
						// Appending new styles
					} else {
						$replaceElm.remove();
					}

					this._setElmsCSS(r.data.css);

					// Reinit sortable
					// TODO What's this ?
					this._destroySortable();
					this._initSortable();

					if (onSuccess instanceof Function) onSuccess(children);
					delete this._renderRequests[id];

					// Check the size of the widget for mob. phones
					this._checkWidgetHeight();
				}.bind(this),
				error: function(){
					this.failRequestAlert.show();
					$replaceElm.remove();
					if (onFailure instanceof Function) onFailure();
				}.bind(this),
			});
		},

		/**
		 * Render some in-element change locally without connecting to backend
		 * @param id string Element's ID
		 * @param key string Changed field
		 * @param value mixed New value
		 * @private
		 */
		_renderElmChange: function(id, key, value){
			var type = this.getElmType(id),
				fieldset = this.fieldsets[type];
			if (!fieldset.fields[key]) return;
			var field = fieldset.fields[key][0] || fieldset.fields[key];
			var renderExistingElm = function(){
				var elmValues = fieldset.getValues();
				var $preloader = $('<div class="g-preloader"></div>').appendTo(this.$updatedElm);
				return this._renderElm(id, elmValues, [], this.$updatedElm, function(){
					this.$updatedElm = $('.conv_id_' + id);
					this.showHighlight(this.$updatedElm);
				}.bind(this), function(){
					$preloader.remove();
				}.bind(this));
			}.bind(this);

			// FAB update element
			if (key.indexOf('fab_') === 0) {
				this.$updatedElm = $('.conv-fab', this.$wContainer);
			}

			// Get builder preview rules, defined in config
			var builderPreviews = field.getBuilderPreview();

			$.each(builderPreviews, function(_, builderPreview){
				// Full backend-driven re-render
				if (builderPreview === true) return renderExistingElm();

				if (builderPreview === false || typeof builderPreview !== 'object') return;

				if (builderPreview.if){
					var shouldRender = this.fieldsets[type].checkRules(builderPreview.if, function(key){
						return key === '_state' ? this.$preview.cfMod('state') : this.fieldsets[type].getValue(key);
					}.bind(this));
					if ( ! shouldRender) return;
				}

				// Getting the element that should be updated
				var $elm = this.$updatedElm;
				if (builderPreview.elm){
					$elm = this.$updatedElm.find(builderPreview.elm);
					if ( ! $elm.length && this.updatedElm === 'widget'){
						// The updated element is outside the widget
						$elm = this.$preview.find(builderPreview.elm).first();
					}
				}
				if ( ! $elm.length) return;

				var newCSS = {},
					fieldVal = field.getValue(false);

				// Update animation type and previewing it
				if (builderPreview.mod === 'animation' || builderPreview.mod === 'mobileAnimation'){
					this.$preview.find('.conv-container-h').css({overflow: 'hidden'});
					$elm.cfMod('conv_' + builderPreview.mod, value);
					this.$widget.hide().removeClass('conv_active');
					this.hideHighlight();
					this._disableHighlight = true;
					setTimeout(function(){
						this.$widget.show();
						setTimeout(function () {
							this.$widget.addClass('conv_active');
						}.bind(this), 1); // Edge fix
						setTimeout(function(){
							this._disableHighlight = false;
							this.$preview.find('.conv-container-h').css({overflow: ''});
							this.showHighlight(true);
						}.bind(this), this.animationDuration);
					}.bind(this), 50);
				}
				// Custom logic for html element
				else if (builderPreview.mod === 'smart_html'){
					try {
						var $value = $('<div class="smarthml-wrapper">' + value + '</div>');
						if ($value.find('form').length){
							if (this._smarthtmlRequest) this._smarthtmlRequest.abort();
							this._smarthtmlRequest = $.ajax({
								method: 'POST',
								url: '/api/widget/parse_html_form',
								data: {
									html: value
								},
								success: function(r){
									if (!r.success){
										//if (onFailure instanceof Function) onFailure();
										if (r.errors && r.errors.html) $elm.html(r.errors.html);
										return;
									}
									var $popup = $('.b-popup.for_htmlformimport'),
										$itemsContainer = $popup.find('.g-table > tbody'),
										$itemTemplate = $itemsContainer.children('tr:first');

									$popup.find('.for_field select').each(function(){
										$(this).append($("<option/>", {
											value: 'hidden',
											text: 'Hidden Data',
										}));
									});

									$itemsContainer.empty();

									$.each(r.data.inputs, function (i, item) {
										var $item = $itemTemplate.clone();
										$item.find('.for_name').html(item.name);
										$item.find('.for_label').html(item.field_settings.name);
										var type = item.field_type.replace(/[0-9]+$/, '');
										type = (type === 'name') ? 'first_name' : type;
										$item.find('.for_field').find('option[value="' + type + '"]').attr('selected', true);
										$item.appendTo($itemsContainer);
									}.bind(this));

									$popup.find('.i-newsuccessurl strong').html(r.data.url);

									if (this.htmlFormImportPopup === undefined) this.htmlFormImportPopup = new CFPopup($popup);
									this.htmlFormImportPopup.show();
									this.htmlFormImportPopup.$box.find('.g-btn').off('click').one('click', function(){
										var prevElmBtn = {};
										this.getLayoutElms(this.activeScreen).forEach(function(elm){
											if (this.getElmType(elm) === 'btn' || this.getElmType(elm) === 'form')
											{
												if (this.getElmType(elm) === 'btn' && prevElmBtn !== 'undefined')
													prevElmBtn = $cf.builder.value.data[elm];
												this.removeElm(elm);
											}
										}.bind(this));

										var postVars = [],
											fields = {};

										$.each(r.data.inputs, function (i, item) {
											var key = $itemsContainer.children().eq(i).find('select').val(),
												keyIndex = 1;
											item.field_settings.type = key;
											postVars.push([item.name, (item.field_settings.type !== 'hidden') ? '{'+item.field_settings.type+'}' : item.field_settings.value]);

											while (key in fields) {
												if (key === 'first_name'){
													item.field_settings.type = 'text';
												}
												keyIndex++;
												key = key.replace(/[0-9]+$/, '') + keyIndex;
											}
											item.field_settings.name = item.field_settings.type;
											fields[key] = item.field_settings;

											if ($itemsContainer.children().eq(i).find('select').val() === 'agreement' && ! fields[key].text){
												fields[key].text = this.defaults.form.fields.agreement.text;
											}

											fields[key].source = 'html_import';
										}.bind(this));

										var parentId = this.getParent(id),
											indexId = this.getValue(['layout', parentId]).indexOf(id);

										var mergedBtn = Object.assign(
											prevElmBtn,
											{
												action: 'submit',
												text: r.data.submit,
												submit_actions: [{
													name: 'send_data',
													type: 'post',
													url: r.data.url,
													vars: postVars
												}],
											}
										);

										this.createElm('form', parentId, indexId, {
											layout: "ver",
											fields: Object.values(fields).filter(function (item) {
												item.name = (item.name === 'text') ? 'full_name' : item.name;
												return item.type !== 'hidden';
											})
										}, function(){
											this.htmlFormImportPopup.hide();
											this.removeElm(id);
											this._disableHighlight = false;

											this.createElm('btn', parentId, indexId + 1, mergedBtn);
											this.openFieldset('form');
										}.bind(this));
									}.bind(this));

								}.bind(this),
								error: function(){
									$elm.html(value);
								}.bind(this)
							});
						}else{
							$elm.html(value);
						}
					}
					catch (e) {
						console.error(e);
					}
				}
				// Update textarea line-height
				else if (builderPreview.mod === 'textarea-line-height'){
					var elmFontSize = parseInt($elm.css('font-size'));
					$elm.css({paddingTop: (parseInt(fieldVal) - elmFontSize) / 2, paddingBottom: (parseInt(fieldVal) - elmFontSize) / 2});
				}
				else if (builderPreview.mod == 'css') {
					if (parseInt(value) && ! value.toString().match(/(rem|em|px)$/)) {
						value += 'px';
					}
					$elm.css(builderPreview.css, value);
				}

				// Update css modificator
				else if (builderPreview.mod !== undefined){
					$elm.cfMod('conv_' + builderPreview.mod, value);
				}
				// Update background image
				else if (builderPreview.css === 'background-image'){
					fieldVal = fieldVal.replace('<domain>', location.protocol + '//' + location.hostname);
					fieldVal = fieldVal.replace(/'/g, '%27');
					$elm.css(builderPreview.css, (fieldVal.length === 0) ? '' : 'url("' + fieldVal + ')');
				}
				// Update top/bottom paddings
				else if (builderPreview.css === 'padding_y'){
					fieldVal = /%$/.test(fieldVal) ? parseInt(fieldVal) / 100 : fieldVal;
					$elm.css({paddingTop: fieldVal, paddingBottom: fieldVal});
				}
				// Update left/right paddings
				else if (builderPreview.css === 'padding_x'){
					fieldVal = /%$/.test(fieldVal) ? parseInt(fieldVal) / 100 : fieldVal;
					$elm.css({paddingLeft: fieldVal, paddingRight: fieldVal});
				}
				else if (builderPreview.css === 'bold_toggle'){
					$elm.css({'font-weight': fieldVal ? 'bold': 'normal'});
				}
				// Update element width
				else if (builderPreview.css === 'width'){
					//$elm.css({width: (value === 0) ? 'auto' : value + field.postfix || '%'});
					if (field.aliases && field.aliases[fieldVal])
					{
						fieldVal = field.aliases[fieldVal];
					}
					$elm.css({width: fieldVal});
					if ($elm.hasClass('conv-image')){
						// TODO More general approach required
						var maxWidth = this.fieldsets[type].fields.image.getValue(false).replace(/.*?(\d+)x\d+$/g, "\$1") + 'px';
						$elm.find('.conv-image-h').css({maxWidth: (value === 0) ? maxWidth : ''});
						if (value === 0 && this.fieldsets[type].fields.retina.getValue(false)){
							return renderExistingElm();
						}
					}
				}
				// Update border-radius
				else if (builderPreview.css === 'border-radius'){
					$elm.css({borderRadius: (value === 50) ? '50%' : value + field.postfix || 'px'});
				}
				// Update font
				else if (builderPreview.css === 'font-family'){
					this._loadFont(value, field.previous, function(){
						newCSS[builderPreview.css] = value;
						$elm.css(newCSS);
					}.bind(this));
				}
				// Bold font
				else if (builderPreview.css === 'font-weight'){
					$elm.css({fontWeight: (value) ? 600 : 400});
				}
				else if (builderPreview.css === 'border'){
					$elm.css({borderWidth: value + (field.postfix || 'px'), borderStyle:'solid'})
				}
				// Update some custom css value
				else if (builderPreview.css !== undefined){
					fieldVal = /%$/.test(fieldVal) ? parseInt(fieldVal) / 100 : fieldVal;
					$elm.css(builderPreview.css, fieldVal);
					if (id === 'widget' && key === 'max_width') this._previewResize();
				}
				// Update inner html
				else if (builderPreview.attr === 'html'){
					//value = (value.length === 0 && this.getElmType(key) === 'text') ? 'Enter text here' : value; //TODO: get text.std
					$elm.html(value);
					$elm.find('.conv_shortcode_value').each(function(){
						$(this).attr('contenteditable', false)
							.css({background: 'none', padding: 'none', borderRadius: 'unset', cursor: 'unset',});
					});
				}
				// Update placeholder
				else if (builderPreview.attr === 'placeholder'){
					$elm.attr('placeholder', value);
				}
				// Toggle specific class
				else if (builderPreview.attr === 'toggle' && builderPreview['class'] !== undefined){
					if (key === 'is_content_locker' && value === true) value = false;
					else if (key === 'is_content_locker' && fieldset.fields['closable'].getValue()) value = true;
					$elm.toggleClass(builderPreview['class'], !!value);
				}
				// Update custom class name
				else if (builderPreview.attr === 'class'){
					var oldValue = $elm.data('className');
					if (oldValue){
						oldValue.split(' ').forEach(function(className){
							if (className.length) {
								var classes = $elm[0].className.split(' ');
								delete classes[classes.lastIndexOf(className)];
								$elm[0].className = classes.join(' ');
							}
						});
					}
					if (value) $elm[0].className += ' ' + value;
					$elm.data('className', value);
				}
				// Check pattern
				else if (builderPreview.pattern) {
					field.$row.toggleClass('check_wrong', !new RegExp(builderPreview.pattern).test(fieldVal));
				}
				// Show / hide element
				else if (builderPreview.attr === 'hidden'){
					$elm.toggleClass('conv_hidden', !value);
				}

				var updateHightlight = this.showHighlight.bind(this, true);

				$elm.one("transitionend", function(){
					$.debounce(500, true, updateHightlight)();
				});

				$.debounce(500, true, updateHightlight)();

			}.bind(this));
		},

		/**
		 * Set new state
		 * @param state string 'desktop' / 'mobile'
		 */
		setState: function(state){
			var prefix,
				data,
				size;
			if (!state) return;
			// Changing state shown in controls
			this.$stateControls.removeClass('is-active').filter('.type_' + state).addClass('is-active');
			// Changing state in
			this.$preview.cfMod('state', state);
			// Check the size of the widget for mob. phones
			this._checkWidgetHeight();

			$.each(this.fieldsets, function(key, fieldset){
				fieldset.fallbackValues['_state'] = state;
				// Fix font change without render element
				fieldset.trigger('change_state');
			}.bind(this));

			data = this.getValue('data');
			// TODO remove switches (use builder_preview.elm)
			$.each(data, function (key, values) {
				if (values['size'] || values['fields_size']) {
					prefix = (values['fields_size']) ? 'fields_' : '';
					var $elm = this.$preview.find('.conv_id_' + key),
						type = this.getElmType(key);

					switch (type) {
						case 'text':
							$elm = $elm.find('.conv-text-h');
							break;
						case 'share':
							$elm = $elm.find('.conv-share-h');
							break;
						case 'countdown':
							$elm = $elm.find('.conv-countdown-h');
							break;
						case 'form':
							$elm = $elm.find('.conv-form-field input, .conv-form-field select, .conv-form-field textarea, .conv-form-field.conv_type_radio .conv-form-field-input label, .conv-form-field.conv_type_agreement .conv-form-field-input label');
							break;
						case 'socsign':
							$elm = $elm.find('.conv-socsign-text');
							break;
						case 'btn':
							$elm = $elm.find('.conv-btn-text');
							break;
						case 'spintowin':
							$elm = $elm.find('.conv-spintowin-h');
							this.$externalElm[type].spinToWin('_setLayout', state);
							break;
					}
					size = (state === 'desktop') ? values[prefix+'size'] : values[prefix + 'mobile_size'];
					if (typeof size !== 'string' || !size.match(/(em|px)$/)) size += 'px';
					$elm.css('font-size', size);
				}
				if (values['height'] || values['fields_height']) {
					prefix = (values['fields_height']) ? 'fields_' : '';
					$elm = this.$preview.find('.conv_id_' + key);

					size = ((state === 'desktop') ? values[prefix+'height'] : values[prefix + 'mobile_height']) || '39px';
					if (typeof size !== 'string' || !size.match(/(em|px)$/)) size += 'px';

					switch (this.getElmType(key)) {
						case 'form':
							$elm = $elm.find('.conv-form-field-input');
							$elm.css('line-height', size);
							if (prefix === 'fields_') {
								$elm.find('input, select').css('height', size);
							}
							break;
						case 'socsign':
							$elm = $elm.find('.conv-socsign-link');
							$elm.css('font-size', size);
							$elm.css('height', size);
							break;
						case 'btn':
							$elm.css('line-height', size);
					}
				}
			}.bind(this));

			if (state === 'mobile') {
				var $widget = this.$preview.find('.conv-widget'),
					$widgetH = this.$preview.find('.conv-container-h');
				if ($widget.height() < $widgetH.height())
					$widget.css('overflow', 'visible');
				else
					$widget.css('overflow', 'hidden');
			}

			this._hidePanel();
		},

		/**
		 * Show highlight area.
		 * @param $elm jQuery collection with an element OR true for refresh current element
		 * @param title Element title to show above the area
		 */
		showHighlight: function($elm, title){
			if (this._disableHighlight) return;
			if (this.columnsSortables.dragging) return;
			if ($elm === true)
				$elm = this.$highlight.data('elm');

			if (!($elm instanceof jQuery) || !$elm.is(":visible")) return;
			if ($elm[0].className.indexOf('conv-') === -1) return;
			this.$highlight.data('elm', $elm);

			title = title || $elm.cfMod('for') || /\s*conv-([a-z]+)\s*/.exec($elm[0].className)[1];
			this.$highlight.toggleClass('type_element', !title.match(/(widget|col|row)/));
			switch (title) {
				case 'text':
					//$elm = $elm.find('.conv-text-h'); // old behavior?
					break;
				case 'form':
					title = 'Fields';
					break;
				case 'countdown':
					title = 'Timer';
					break;
				case 'col':
					title = 'Column ' + this.getColLabel($elm.cfMod('conv_sz'));
					break;
				case 'btn':
					title = 'Button';
					break;
			}
			var previewOffset = this.$preview.offset();
			var offset = $elm.offset();
			var offsetWidget = this.$widget.offset();
			var offsetContainer = this.$wContainer.offset();
			var width = $elm.outerWidth();
			var height = (title === 'widget' && ($elm.cfMod('conv_type') === 'welcome' || $elm.cfMod('conv_type') === 'scrollbox')) ? $elm.find('.conv-widget-h').outerHeight() : $elm.outerHeight();
			var newPosition = {
				top: offset.top - previewOffset.top + this.$preview.scrollTop()/*- this.$body.scrollTop()*/,
				left: offset.left - previewOffset.left + this.$preview.scrollLeft(),
				width: width,
				height: height
			};

			// Set title if it exist
			if (title) {
				var $title = this.$highlight.find('.b-builder-highlight-helpers-title'),
					$helpers = this.$highlight.find('.b-builder-highlight-helpers');
				$title.text(title);
				// Hide duplicate for element
				this.$highlight.find('.type_duplicate')
					.toggleClass('is-hidden', ['spintowin', 'scratch'].indexOf(title) !== -1);
			}

			this.$highlight.css(newPosition).show();

			if (title){
				var ver = 'top', hor = 'left';
				// we do it after appending to DOM because we need $title.height
				if (offset.top - previewOffset.top < parseInt($title.height())){
					if (title !== 'widget' && $elm.cfMod('conv_type') !== 'welcome') ver = 'bottom'
				}
				if ($elm.width() < $helpers.width()){
					hor = 'right'
				}

				if (this.$preview.height() <= this.$highlight.height()){
					$helpers.cfMod('place', 'inside');
				} else if (this.$wContainer.cfMod('conv_state') === 'desktop' && (offset.top - offsetContainer.top  < parseInt($title.height()))) {
					$helpers.cfMod('place', 'inside');
				} else if (this.$wContainer.cfMod('conv_state') === 'mobile' && (offset.top - offsetWidget.top  < parseInt($title.height()))) {
					$helpers.cfMod('place', 'inside');
				} else {
					$helpers.cfMod('place', null);
				}

				$helpers.cfMod('pos', ver + '_' + hor);
				this.$widget.find('.b-builder-highlight-helpers').remove();
				$elm.prepend($helpers.clone());
				$elm.addClass('show_controls');
			}
		},

		/**
		 * Hide highlight area.
		 */
		hideHighlight: function($elm){
			if (this._disableHighlight) return;
			var $highlightElms;
			if ($elm instanceof jQuery && $elm.data('highlight')){
				$highlightElms = $elm.data('highlight');
			} else {
				$highlightElms = this.$preview
					.find('.b-builder-highlight');
			}
			this.$widget.find('.b-builder-highlight-helpers').remove();
			$('.show_controls').removeClass('show_controls');
			$highlightElms.hide();
		},

		/**
		 * Make sure that some element is visible in the preview area
		 * @param $elm jQuery
		 * @param instant int If set will define if the element should be viewed instantly or with animation
		 */
		viewElement: function($elm, instant){
			var elmPosition = $elm.offset();
			var previewPosition = this.$preview.offset();
			var currentScroll = {left: this.$preview.scrollLeft(), top: this.$body.scrollTop()};
			var previewWidth = parseInt(this.$preview.width());
			var previewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || this.$preview.height()) - previewPosition.top;
			var animationSpeed = instant ? 0 : 500; //TODO: no hardcode
			var coords = {
				scrollLeft: elmPosition.left + currentScroll.left - previewPosition.left - (previewWidth - $elm.width()) / 2,
				scrollTop: elmPosition.top - previewPosition.top - (previewHeight - $elm.height()) / 2
			};
			this.$preview.animate({scrollLeft: coords.scrollLeft}, {queue: false, duration: animationSpeed});
			this.$body.animate({scrollTop: coords.scrollTop}, {queue: false, duration: animationSpeed});
		},

		/**
		 *
		 * @param tabId
		 */
		openTab: function(tabId){
			if (tabId === this.activeTab) return;
			this.$tabsContents.hide().filter('[data-id="' + tabId + '"]').show();
			this.$tabsButtons.removeClass('is-active').filter('[data-id="' + tabId + '"]').addClass('is-active');
			this.activeTab = tabId;
		},

		/**
		 * Open specified fieldset inside a panel
		 * @param id string Fieldset ID
		 */
		openFieldset: function(id){
			var type = this.getElmType(id);
			if (this.$fieldsetsElm[type] === undefined) return;

			// Initialize fieldset if doesn't exist
			this._initFieldset(type);

			// Fieldset values
			var values = $.extend({}, arrayPath(this.defaults, type, {}));

			// Widget/element settings
			var allElms = arrayFlatten(this.getValue('layout', {}));
			if (id === 'widget' || allElms.indexOf(id) !== -1){
				// Getting either 'options', or 'data.<id>' value
				values = $.extend(values, this.getValue((id === 'widget') ? 'options' : ['data', id], {}));
				// Connecting fieldset to a specific preview area that should be updated
				if (this.$updatedElm instanceof jQuery) this.$updatedElm.removeClass('active_fieldset');
				this.updatedElm = id;
				this.$updatedElm = (id === 'widget') ? this.$widget : this.$widget.find('.conv_id_' + id);
				this.$updatedElm.addClass('active_fieldset');

				// Storing current custom class to element to properly update it later when needed
				if (values['class_name'] !== undefined) this.$updatedElm.data('className', values['class_name']);

				// TODO Move to fieldset open finish
				setTimeout(function(){
					this.showHighlight(this.$updatedElm);
				}.bind(this), 500);
			}
			// Custom CSS panel
			else if (id === 'custom_css'){
				values = {
					custom_css: this.getValue('custom_css', '')
				};
			}
			// Rules fieldset (separate tab)
			else if (id === 'rules'){
				values = {
					show_when: this.getValue('show_when', {}),
					show_if: this.getValue('show_if', {}),
					dont_show_if: this.getValue('dont_show_if', {}),
					onshow_js_enabled: +this.getValue('onshow_js_enabled', 0),
					onshow_js: this.getValue('onshow_js', ''),
				};

				var fabEnabled = this.getValue('options.fab_enabled', false);
				if (this.fieldsets.rules.fields.show_when){
					var $FABrule = this.fieldsets.rules.fields.show_when.$row.find('.cof-ruleset-row[data-name="fab"]');
					if ($FABrule.length){
						$FABrule.toggleClass('is-hidden', ! fabEnabled);
					}
					values['show_when']['fab'] = {'active': $FABrule.length > 0 && fabEnabled};
				}

				if (allElms.indexOf('countdown') !== -1)
				{
					// Add default widget id to behavior countdown value
					var $widget_selects = this.fieldsets.rules.fields.show_if.$row.find('select[data-name="widget"]');
					$widget_selects.each(function(_, select){
						var $select = $(select);
						if (! $select.find('option[value="'+this.widgetId+'"]').length)
						{
							$select.append('<option value="'+this.widgetId+'">'+ $('.b-builder-toolbar-title-text').text() + '</option>')
						}
					}.bind(this));
				}
			}
			// Integrations fieldset (separate tab)
			else if (id === 'integrations'){
				values = {
					integrations: this.getValue('integrations', {})
				};
			}
			// Import fieldset
			else if (id === 'import'){
				values = {widget_config: JSON.stringify(this.getValue())};
			}
			// Spintowin or Scratch Card
			else if (['spintowin', 'scratch_card'].indexOf(type)) {
				this.updatedElm = id;
			}

			var $fieldset = this.$fieldsetsElm[type];
			this.fieldsets[type].setValues(values, true);

			var tabId = $fieldset.closest('.b-builder-tab').data('id');

			this.fieldsets[type].trigger('beforeShow');

			this.openTab(tabId);
			if (tabId === 'design'){
				// Set panels sizes
				var width = $fieldset.data('width');
				var panelData = $fieldset.data();
				panelData.id = type;
				this._showPanel(width, panelData, function(){
					if (this.$updatedElm instanceof jQuery){
						this.viewElement(this.$updatedElm);
						this.showHighlight(this.$updatedElm);
					}
					this.$panelTitle.removeClass('with_border');
					$fieldset.scrollTop(0);
					$fieldset.on('scroll', this._events.scrollPanel);
					this.fieldsets[type].trigger('afterShow');
				}.bind(this));
				this.activeDesignFieldsetID = id;
			}
			else {
				this.fieldsets[type].trigger('afterShow');
			}

			$.each(this.$fieldsetsElm, function(key){
				this.$fieldsetsElm[key].removeClass('is-active');
			}.bind(this));
			setTimeout(function(){
				//this.fieldsets[type].trigger('beforeShow');
				if (this.fieldsets[type].fallbackValues['_state'] !== this.$preview.cfMod('state')) {
					this.fieldsets[type].fallbackValues['_state'] = this.$preview.cfMod('state');
					this.fieldsets[type].trigger('change_state');
				}
				$fieldset.addClass('is-active');
				this.fieldsets[type].trigger('afterShow');
				this.$fieldsetsButtons.filter('.is-active').removeClass('is-active');
				this.$fieldsetsButtons.filter('.type_' + type).addClass('is-active');
			}.bind(this), 0);

			if (this.hashStates.indexOf(type) !== -1)
				history.replaceState(null, '', '#' + id);
			else
				history.replaceState(null, '', window.location.pathname);

			// replace {shortcodes} for btn and text elements
			if (['btn', 'text'].indexOf(type) !== -1) {
				Object.keys(this.fieldsets[type].fields).forEach(function(key){
					var value,fieldSet = this.fieldsets[type].fields[key];
					if (!fieldSet.$row.hasClass('enable_editor_shortcode'))
						return;
					value = fieldSet.getValue();
					if (typeof value !== 'undefined' && value !== null)
						fieldSet.setValue(
							this._replaceShortcodes(value)
						);

					// Update shortcodes list in froala editor
					if (fieldSet.editor) {
						var editor = fieldSet.editor.data('froala.editor'),
							options = editor.opts;
						options.convShortcodeConfig.shortcodes = this.shortcodes;
						$.extend(editor.opts, options);
					}
				}.bind(this));
			}
		},

		/**
		 * Initialize fieldset if it doesn't exist
		 * @param type string
		 */
		_initFieldset: function(type){
			if (this.fieldsets[type] !== undefined) return;
			if (this.$fieldsetsElm[type] === undefined) return console.error('Fieldset '+type+' not found');
			var fieldset = this.fieldsets[type] = new $cof.Fieldset(this.$fieldsetsElm[type].find('> .b-builder-fieldset-h'));
			fieldset.on('change', function(key, value){
				if (type === 'custom_css'){
					$.debounce(1000, this._onChange.bind(this, type, key, value, fieldset))();
					return;
				} else if (type === 'html') {
					$.debounce(200, this._onChange.bind(this, type, key, value, fieldset))();
					return;
				}
				return this._onChange(type, key, value, fieldset);
			}.bind(this));

			if (type === 'layout'){
				var layoutField = fieldset.fields.layout;
				layoutField.on('colsResize', function(){}.bind(this));
			}

			else if (type === 'scratch_card') {
				var layer_elms = ['scratch_layer_color', 'scratch_layer_text'];
				layer_elms.forEach(function(layer){
					fieldset.fields[layer].$row.on('mouseenter mouseleave', function() {
						if (this.$externalElm.hasOwnProperty(this.updatedElm)) {
							this.$externalElm[this.updatedElm]
								.find('.conv-scratch')
								.toggleClass('conv_active');
						}
					}.bind(this))
				}.bind(this));
				fieldset.fields.prizes.on('chance.focus', function(value) {
					$.debounce(200, true, function() {
						var prizes = this.fieldsets[type].getValue('prizes') || [];
						if (prizes.hasOwnProperty(value)) {
							this.$externalElm[this.updatedElm][0].convScratchCard
								.texts(prizes[value].text, prizes[value].coupon)
								._focusPrize = value;
						}
					}.bind(this))();
				}.bind(this));
			}

			else if (type === 'spintowin') {
				fieldset.fields.prizes.on('chance.focus', function(value) {
					$.debounce(200, true, function() {
						this.$externalElm[this.updatedElm].spinToWin('selectedSpin', value);
					}.bind(this))();
				}.bind(this));
			}
		},

		_showPanel: function(width, options, callback){
			var newWidth = this.$container.width() - width - 60;

			this.$panelH
				.css('min-width', width);
			this.$panel
				.addClass('is-active');
			this.$preview
				.css('margin-left', width + 60);

			if (this.$preview.width() !== newWidth) {
				this.$preview.find('.conv-container-h').css({overflow: 'hidden'});
				this.hideHighlight();
				this._disableHighlight = true;
				setTimeout(function(){
					this.$preview.find('.conv-container-h').css({overflow: ''});
					this._disableHighlight = false;
					callback();
				}.bind(this), 450);
			} else {
				callback();
			}

			this.$panelTitle.text(options.title);
		},

		_hidePanel: function(){
			if (!this.$panel.hasClass('is-active')) return;
			this.$panel.find('.b-builder-fieldset.is-active').off('scroll', this._events.scrollPanel);
			this.$panel
				.removeClass('is-active');
			this.$preview
				.css('margin-left', 60);

			if (this.$updatedElm instanceof jQuery) this.$updatedElm.removeClass('active_fieldset');
			this.updatedElm = this.$updatedElm = null;
			this.$fieldsetsButtons.filter('.is-active').removeClass('is-active');
			history.replaceState(null, '', window.location.pathname);
			this.hideHighlight();
			this._disableHighlight = true;
			delete this.activeDesignFieldsetID;
		},

		/**
		 * Set single value by path
		 * @param path string
		 * @param value mixed
		 */
		setValue: function(path, value){
			if (this._transactionChanges !== undefined) return this._transactionChanges.push([path, value]);
			arraySetPath(this.value, path, value);
			this.trigger('change', [[[path, value]]]);
		},

		/**
		 * Set multiple values at once
		 * @param values array [ [path1, value1], [path2, value2], ... ]
		 */
		setValues: function(values){
			if (this._transactionChanges !== undefined){
				this._transactionChanges = this._transactionChanges.concat(values);
				return;
			}
			values = Array.isArray(values) ? values : [];
			for (var i = 0; i < values.length; i++){
				arraySetPath(this.value, values[i][0], values[i][1]);
			}
			this.trigger('change', [values]);
		},

		getValue: function(path, def){
			var result = (path === undefined) ? this.value : arrayPath(this.value, path, def);
			if (Array.isArray(result)){
				// Cloning array, so the origin won't be affected by changes of returned result
				return result.slice(0);
			}
			else if (typeof result === 'object'){
				// Cloning object, so the origin won't be affected by changes of returned result
				return $.extend({}, result);
			}
			return result;
		},

		/**
		 * Start new transaction, all setValue calls during transaction will be executed together during the commit
		 */
		startTransaction: function(){
			if (this._transactionChanges !== undefined) this.commitTransaction();
			this._transactionChanges = [];
		},

		/**
		 * Apply all value changes that were set during the transaction
		 */
		commitTransaction: function(){
			if (this._transactionChanges === undefined) return;
			var transactionChanges = this._transactionChanges;
			delete this._transactionChanges;
			this.setValues(transactionChanges);
		},

		/**
		 * Discard all value changes that were set during the transaction
		 */
		rollbackTransaction: function(){
			delete this._transactionChanges;
		},

		/**
		 * Create new element
		 * @param type string Element type
		 * @param parent string Parent element's ID or the current screen
		 * @param index number Position index. If not defined will be placed as a parent's last child
		 * @param values object Element fields' values
		 * @param callback function Function that will be executed once the element is added
		 */
		createElm: function(type, parent, index, values, callback){
			// By default working within the current screen
			parent = parent || this.activeScreen;
			var siblings = this.getValue('layout.'+parent, []);
			siblings = Object.values(siblings);
			// Cannot have more than 12 columns in a row
			if (type === 'col' && siblings.length >= 12) return;
			index = (index === undefined) ? siblings.length : index;
			values = values || {};
			var id = this.getNewId(type);
			// Preventing race condition on double click
			if (this._renderRequests && this._renderRequests[id]) return;
			// New row should always be created with a single column inside
			var children = (type === 'row') ? [{id: this.getNewId('col'), values: {sz: 12}}] : [],
				$elmPlace = $('<div class="conv-' + type + '">' + ((type === 'col') ? '' : '<div class="g-preloader"></div>') + '</div>');
			if (index === 0){
				// Inserting as a last child of parent element
				var $parent = this.$preview.find((parent === 'container') ? '.conv-widget-h' : ('.conv_id_' + parent));
				if (this.getElmType(parent) === 'col') $parent = $parent.find('.conv-col-h');
				$elmPlace.prependTo($parent);
			}
			else {
				// Inserting after previous sibling
				var $prevSibling = this.$preview.find('.conv_id_' + siblings[index - 1]);
				$elmPlace.insertAfter($prevSibling);
			}
			if (type === 'col'){
				var newColSizes = this._suggestNewColSizes(parent, id);
				values.sz = newColSizes[id];
				$.each(newColSizes, function(elmId, sz){
					this.renderColSize(elmId, sz, true);
				}.bind(this));
			}
			this._renderElm(id, values, children, $elmPlace, function(){
				// Adding new element to structure
				this.startTransaction();
				this.setValue(['data', id], values);
				var layout = this.getValue('layout', {});
				if (children.length){
					layout[id] = [];
					for (var i = 0; i < children.length; i++){
						layout[id].push(children[i].id);
						this.setValue(['data', children[i].id], children[i].values);
					}
				}
				if (type === 'col'){
					// Updating siblings sizes
					$.each(newColSizes, function(elmId, sz){
						this.setValue(['data', elmId, 'sz'], sz);
					}.bind(this));
				} else if (type === 'btn' || type === 'socsign' || type === 'scratch_card') {
					// Add default actions for buttons
					var defaults = this.defaults['btn'].submit_actions;
					if (defaults.default_automations !== undefined) {
						Object.keys(defaults.default_automations).forEach(function(item){
							values.submit_actions.push({name: item})
						})
					}
				}

				siblings.splice(index, 0, id);
				layout[parent] = siblings;
				this.setValue('layout', layout);
				this.commitTransaction();

				if (this.gamificationElms.indexOf(type) !== -1) {
					this._initGamificationElm(id);
				}

				if (callback instanceof Function)
					callback(id);
			}.bind(this));
		},

		/**
		 * Move element to new position
		 * @param id string
		 * @param newParent
		 * @param newIndex
		 */
		moveElm: function(id, newParent, newIndex){
			var layout = this.getValue('layout', {}),
				oldParent = this.getParent(id),
				oldIndex = layout[newParent].indexOf(id);
			if (layout[newParent] === undefined) layout[newParent] = [];
			layout[oldParent].splice(oldIndex, 1);
			layout[newParent].splice(newIndex, 0, id);
			// Moving dom element
			var $elm = this.$widget.find('.conv_id_' + id);
			if (layout[newParent].length === 1){
				// Moving as the only child
				var newParentType = this.getElmType(newParent);
				if (newParentType === 'container'){
					$elm.insertAfter(this.$widget.find('.conv-widget-bgimage'));
				}
				else if (newParentType === 'row'){
					$elm.insertAfter(this.$widget.find('.conv_id_' + newParent + ' > .conv-row-bgimage:first'));
				}
				else if (newParentType === 'col'){
					$elm.insertAfter(this.$widget.find('.conv_id_' + newParent + ' > .conv-col-h:first'));
				}
			}
			else {
				// Placing before/after a sibling
				var siblingId = layout[newParent][newIndex ? (newIndex - 1) : (newIndex + 1)];
				$elm[newIndex ? 'insertAfter' : 'insertBefore'](this.$widget.find('.conv_id_' + siblingId));
			}
			this.setValue('layout', layout);
		},

		/**
		 * Suggest new columns sizes for new column creation
		 * @param parent string
		 * @param newColId string New column id that doesn't exist in the value yet
		 * @return object
		 * @private
		 */
		_suggestNewColSizes: function(parent, newColId){
			var colsIds = this.getValue(['layout', parent], []),
				expectedNewColSz = Math.floor(1 / (colsIds.length + 1) * 12),
				actualNewColSz = expectedNewColSz,
				result = {},
				tmpColSzs = [];
			// 1. make array of cols
			colsIds.forEach(function(colId){
				var curSz = parseInt(this.getValue(['data', colId, 'sz']));
				tmpColSzs.push({
					k: colId,
					v: curSz
				});
				result[colId] = curSz;
			}.bind(this));
			// 2 sort by desc
			tmpColSzs.sort(function(a,b){
				if(a.v > b.v){ return -1}
				if(a.v < b.v){ return 1}
				return 0;
			});
			// 3 resize columns by array
			while (expectedNewColSz !== 0) {
				$.each(tmpColSzs, function (i) {
					if (expectedNewColSz === 0) return;
					if (result[tmpColSzs[i].k] !== 1) {
						result[tmpColSzs[i].k] -= 1;
						expectedNewColSz -= 1;
					}
				}.bind(this));
			}
			result[newColId] = actualNewColSz;
			return result;
		},

		/**
		 * Apply change
		 *
		 * @param {string} type
		 * @param {string} key
		 * @param {mixed} value
		 */
		_applySpintoWinChange: function(type, key, value) {
			var id = this.updatedElm;
			if (value === undefined || ! this.$externalElm.hasOwnProperty(id)) return;
			var $row = this.$externalElm[id],
				options = $.extend({}, $row.data('spinToWinData').o),
				str_options = JSON.stringify(options),
				data = $row.data('data');
			// CF data
			data[key] = value;
			$row.data('data', $.extend(true, {}, data));
			// Apply changes
			if (JSON.stringify(data) !== str_options) {
				$.throttle(100, false, $row.spinToWin());
				this._onResize();
			}
		},

		/**
		 * Duplicate element
		 * @param id string Element ID
		 * @param callback function Function to execute when duplicate is complete
		 */
		duplicateElm: function(id, callback){
			var type = this.getElmType(id),
				occupiedIds = this.getLayoutElms();
			// Cannot duplicate columns and elements that doesn't exist
			if (type === 'col' || occupiedIds.indexOf(id) === -1) return;
			// Generating data for the request
			var data = {oldId: id},
				// Queue of blocks to handle
				queue = [data];
			while (queue.length){
				var elm = queue.shift();
				elm.id = this.getNewId(this.getElmType(elm.oldId), occupiedIds);
				elm.values = this.getValue(['data', elm.oldId], {});
				var childrenIds = this.getValue(['layout', elm.oldId], []);
				if (childrenIds.length){
					elm.children = [];
					for (var i = 0; i < childrenIds.length; i++){
						var oldId = childrenIds[i];
						// Only one form per layout is allowed at the moment
						if (['form', 'socsign', 'spintowin', 'scratch_card'].indexOf(this.getElmType(oldId)) !== -1) continue;
						elm.children.push({oldId: oldId});
						queue.push(elm.children[elm.children.length - 1]);
					}
				}
				delete elm.oldId;
			}
			// Preventing race condition on double click
			if (this._renderRequests && this._renderRequests[data.id]) return;
			// Required for proper rendering
			data.widget_id = this.$container.data('id');
			// Processing the request
			var $newPlace = $('<div class="conv-' + type + '"></div>').insertAfter(this.$preview.find('.conv_id_' + id));
			this._renderElm(data.id, data.values, data.children, $newPlace, function(){
				// Appending the new data to the current value
				var queue = [data],
					layout = this.getValue('layout', {}),
					index;
				// Placing the processed element to the layout
				for (var parent in layout){
					if (layout.hasOwnProperty(parent) && (index = layout[parent].indexOf(id)) !== -1){
						layout[parent].splice(index + 1, 0, data.id);
						break;
					}
				}
				this.startTransaction();
				while (queue.length) {
					var elm = queue.shift();
					this.setValue(['data', elm.id], elm.values);
					if (elm.children){
						var childrenIds = [];
						for (var i = 0; i < elm.children.length; i++){
							var childId = elm.children[i].id;
							childrenIds.push(childId);
							queue.push(elm.children[i]);
						}
						layout[elm.id] = childrenIds;
					}
				}
				this.setValue('layout', layout);
				this.commitTransaction();

				if (callback instanceof Function) callback();
			}.bind(this), function(){
				$newPlace.remove();
			}.bind(this));
		},

		/**
		 * Remove element
		 * @param id string Element ID
		 */
		removeElm: function(id){
			var ids = [id],
				layout = this.getValue('layout', {}),
				type = this.getElmType(id),
				parent = this.getParent(id);
			if (!parent) return;
			this.startTransaction();
			// Cannot remove the last column
			if (type === 'col'){
				if (this.getValue(['layout', parent], []).length <= 1) return;
				// Increasing previous column by the removed size
				var prevElmId = layout[parent][layout[parent].indexOf(id) - 1] || layout[parent][layout[parent].indexOf(id) + 1],
					prevElmSize = parseInt(this.getValue(['data', prevElmId, 'sz'], 0));
				prevElmSize += parseInt(this.getValue(['data', id, 'sz'], 0));
				this.setValue(['data', prevElmId, 'sz'], prevElmSize);
				this.renderColSize(prevElmId, prevElmSize);
			}
			for (var i = 0; i < ids.length; i++){
				var curId = ids[i];
				if (layout[curId]){
					ids = ids.concat(layout[curId]);
					delete layout[curId];
				}
				if (this.$css[curId]){
					this.$css[curId].remove();
					delete this.$css[curId];
				}
				if (curId === this.updatedElm){
					this._hidePanel();
				}
				this.setValue(['data', curId], undefined);
			}
			// Finding the parent and removing the element itself from the layout
			layout[parent].splice(layout[parent].indexOf(id), 1);
			this.setValue('layout', layout);
			this.commitTransaction();

			// Elements fieldset update
			if (['form', 'socsign', 'countdown', 'follow', 'share', 'spintowin', 'scratch_card'].indexOf(type) !== -1) {
				if (this.fieldsets.elements) this.fieldsets.elements.trigger('beforeShow');

				/*if (this.fieldsets.rules && this.fieldsets.rules.fields && this.fieldsets.rules.fields.success_post_vars) {
					var allElms = $cf.builder.getLayoutElms();
					this.fieldsets.rules.fields.success_post_vars.$row
						.toggleClass('is-hidden', allElms.indexOf('form') === -1 && allElms.indexOf('socsign') === -1);
				}*/
			}

			this.$widget.find('.conv_id_' + id).remove();
			this.hideHighlight();
		},

		/**
		 * Get elements' parent ID
		 * @param id string
		 * @return string|null
		 */
		getParent: function(id){
			var layout = this.getValue('layout', {});
			for (var parent in layout){
				if (layout.hasOwnProperty(parent) && layout[parent].indexOf(id) !== -1) return parent;
			}
			return null;
		},

		/**
		 * Create new element ID that is not occupied yet
		 * @param type string Element type
		 * @param occupiedIds array If not defined, will check for existing builder elements
		 * @return string New builder ID
		 */
		getNewId: function(type, occupiedIds){
			// Getting all elements that are present in the layout
			if (occupiedIds === undefined)
				occupiedIds = this.getLayoutElms();
			var index = '',
				newId = type + index;
			while (occupiedIds.indexOf(newId) !== -1) {
				index = index ? (index + 1) : 2;
				newId = type + index;
			}
			occupiedIds.push(newId);
			return newId;
		},

		getElmType: function(id){
			return id.match(/([a-z_]+)(\d?)/)[1];
		},

		/**
		 * Update elements styles based on /api/widget/element_render response
		 * @param styles object { elmID: css }
		 * @private
		 */
		_setElmsCSS: function(styles){
			if (styles === undefined) return;
			$.each(styles, function(elmId, css){
				if (elmId === 'base' || elmId === 'custom') return;
				if (this.$css[elmId]) this.$css[elmId].remove();
				this.$css[elmId] = $('<style type="text/css" class="b-builder-css type_internal" data-id="' + elmId + '">' + css + '</style>').appendTo($cf.$head);
			}.bind(this));
		},

		/**
		 * Get column label
		 * @param sz string|number Column size
		 */
		getColLabel: function(sz){
			return (typeof sz === 'string' && sz.indexOf('%') !== -1) ? sz : (Math.round((sz / 12) * 100) + '%')
		},

		/**
		 * Set DOM element column size
		 * @param id string
		 * @param size int
		 */
		renderColSize: function(id, size){
			var className = '.conv_id_'+id;
			this.$preview.find(className).cfMod('conv_sz', size);
		},

		/**
		 * Get elements that exist in the layout (analogy of WidgetRenderer::get_layout_elms)
		 * @param container
		 * @return array
		 */
		getLayoutElms: function(container){
			container = container || 'container';
			var result = [],
				queue = [container];
			do {
				var curContainer = queue.shift(),
					curContainerElms = this.value.layout[curContainer] || [];
				for (var i = 0; i < curContainerElms.length; i++){
					var elmId = curContainerElms[i];
					result.push(elmId);
					if (this.value.layout[elmId]) queue.push(elmId);
				}
			} while (queue.length);

			return result;
		},

		/**
		 * Add new screen
		 * @param type string
		 * @param title string
		 */
		addScreen: function(type, title){
			if(this._isCreateScreen || this.value.layout.container.length >= this._SCREEN_MAX_LIMIT)
				return;

			//var isDuplicate = (typeof cloneScreenId !== 'undefined');
			this._isCreateScreen = true;

			// Get the name of the screen being copied
			var appendAction,
				$appendParent,
				$parentScreen = this.$screensContainerTrack.find('.b-builder-tab-screen.is-active').eq(0),
				parentScreenId = $parentScreen.cfMod('id'),
				screenId = this.getNewId('screen'),
				occupiedIds = this.getLayoutElms(),
				data;

			// Generate title
			title = $.trim(title);
			var nextScreenNumber = this._getNextScreenNumber(type);
			if(nextScreenNumber !== 1) {
				title += ' ' + nextScreenNumber;
			}

			// Default data
			data = {
				id: screenId,
				values: {type: type, title: title},
				children: []
			};

			switch (type) {
				case 'intro':
					appendAction = 'insertBefore';
					$appendParent = this.$screensContainerTrack.find('.b-builder-tab-screen:first');

					// Cloning the active screen
					data.oldId = this.$screensContainerTrack.children('.is-active').cfMod('id') || 'screen';
					var queue = [data],
						submitButtonIndex = null,
						closeButtonExist = false,
						colForSubmitBtn;
					while (queue.length){
						var elm = queue.shift();
						var elmType = this.getElmType(elm.oldId || elm.type);
						elm.id = this.getNewId(elmType, occupiedIds);
						if (!elm.values)
							elm.values = this.getValue(['data', elm.oldId], {}, 'offer');

						if (elmType === 'btn'){
							elm.values.submit_actions = [];
							elm.values.click_actions = {};
							if (elm.values.type !== 'close'){
								elm.values.type = 'custom';
								elm.values.click_actions = {
									go_to_screen: {
										screen_id: parentScreenId
									}
								};
							}
						}

						var childrenIds = this.getValue(['layout', elm.oldId || elm.id], [], 'offer');
						if (childrenIds.length){
							elm.children = [];
							for (var i = 0; i < childrenIds.length; i++){
								var oldId = childrenIds[i];
								switch (this.getElmType(oldId))
								{
									// Specifically for this case, we remove the form because there will be buttons
									case 'form':
									case 'spintowin':
									case 'scratch_card':
										break;
									case 'btn':
										if (!closeButtonExist && this.getValue('data.' + oldId + '.type', null, 'offer') === 'close'){
											elm.children.push({oldId: oldId});
											queue.push(elm.children[elm.children.length - 1]);
											closeButtonExist = true;
										} else if (submitButtonIndex === null){
											elm.children.push({oldId: oldId});
											submitButtonIndex = elm.children.length -1;
											colForSubmitBtn = elm.children;
											queue.push(elm.children[submitButtonIndex]);
										}
										break;
									default:
										elm.children.push({oldId: oldId});
										queue.push(elm.children[elm.children.length - 1]);
								}

							}
						}
						delete elm.oldId;
						// Create buttons
						if (submitButtonIndex === null && elmType === 'col'){
							if (! elm.children)
								elm.children = [];
							colForSubmitBtn = elm.children;
						}
						if (queue.length === 0 && colForSubmitBtn){
							// Copy object
							if (submitButtonIndex === null){
								colForSubmitBtn.push({
									id: this.getNewId('btn', occupiedIds),
									values: {
										height: "40px",
										textcolor: "#FFF",
										bgcolor: "#53b753",
										text: "Yes, I Want This!",
										type: "custom",
										border_width: "0",
										size: "19px",
										mobile_size: "15px",
										padding: "0 18px",
										click_actions: {
											go_to_screen: {
												screen_id: parentScreenId,
											}
										},
										submit_actions: {}
									}
								});
								submitButtonIndex = colForSubmitBtn.length - 1;
							}
							if (! closeButtonExist){
								colForSubmitBtn.splice(submitButtonIndex + 1 , 0, {
									id: this.getNewId('btn', occupiedIds),
									values: {
										height: "15px",
										textcolor: "",
										bgcolor: "",
										text: "No Thank You",
										type: "close",
										border_width: "0",
										size: "15px",
										mobile_size: "15px",
										padding: "0 18px",
										click_actions: {},
										submit_actions: {}
									}
								})
							}
						}
					}

					break;
				case 'success':
					appendAction = 'insertAfter';
					$appendParent = this.$screensContainerTrack.find('.b-builder-tab-screen:last');

					data.children.push({
						id: this.getNewId('row', occupiedIds),
						values: {
							class_name: 'conv_'+type,
						},
						children: [{
							id: this.getNewId('col', occupiedIds),
							values: {
								sz: 12
							},
							children: [{
								id: this.getNewId('text', occupiedIds),
								values: {
									mobile_size: '15px',
									text: title,
									class_name: 'conv_align_center',
									color: ''
								}
							}]
						}]
					});

					break;
				default:
					appendAction = 'insertAfter';
					$appendParent = $parentScreen;

					data.children.push({
						id: this.getNewId('row', occupiedIds),
						values: {
							class_name: 'conv_'+type,
						},
						children: [{
							id: this.getNewId('col', occupiedIds),
							values: {
								sz: 12
							},
							children: [{
								id: this.getNewId('text', occupiedIds),
								values: {
									mobile_size: '15px',
									text: title,
									class_name: 'conv_align_center',
									color: ''
								}
							}]
						}]
					});
			}

			this._appendScreen(appendAction, $appendParent, data);
		},

		/**
		 * Duplicate screen
		 * @param parentScreenId string
		 */
		duplicateScreen: function(parentScreenId) {
			if(this._isCreateScreen || this.value.layout.container.length >= this._SCREEN_MAX_LIMIT)
				return;

			var type = this._getScreenType(parentScreenId),
				title = this._getNextScreenCopyName(this.value.data[parentScreenId].title || '');

			this._isCreateScreen = true;

			// Get the name of the screen being copied
			var $parentScreen = this.$screensContainerTrack.find('.b-builder-tab-screen.id_'+parentScreenId),
				screenId = this.getNewId('screen'),
				occupiedIds = this.getLayoutElms(),
				data;

			// Default data
			data = {
				id: screenId,
				values: {type: type, title: title},
				children: []
			};

			data.oldId = parentScreenId;
			var queue = [data],
				existingAllFields = this._getWidgetAllFields() || [];
			while (queue.length){
				var elm = queue.shift();
				var elmType = this.getElmType(elm.oldId || elm.type);
				elm.id = this.getNewId(elmType, occupiedIds);
				if (!elm.values){
					elm.values = $.extend(true, {}, this.getValue(['data', elm.oldId], {}, 'offer'));/*this.getValue(['data', elm.oldId], {}, 'offer')*/
				}
				// Generate unique field name
				if(elmType === 'form' && $.isEmptyObject(elm.values.fields) === false){
					$.each(elm.values.fields, function(i, field) {
						if(field.name !== undefined){
							var newName,
								index = '';
							while (existingAllFields.indexOf(newName = field.type + index) !== -1){
								index = (index === '') ? 2 : (index + 1);
							}
							existingAllFields.push(newName);
							elm.values.fields[i].name = newName;
							var newLabel = $cf.builder.defaults.form.fields[field.type].label || field.type;
							elm.values.fields[i].label = newLabel + (index === '' ? '' : ' '+index);
						}
					});
					// sync label
				}
				var childrenIds = this.getValue(['layout', elm.oldId || elm.id], [], 'offer');
				if (childrenIds.length){
					elm.children = [];
					for (var i = 0; i < childrenIds.length; i++){
						var oldId = childrenIds[i];
						elm.children.push({oldId: oldId});
						queue.push(elm.children[elm.children.length - 1]);

					}
				}
				delete elm.oldId;
			}
			this._appendScreen('insertAfter', $parentScreen, data);
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
		},

		_appendScreen: function(appendAction, $appendParent, data, backup){
			var screenId = data.id,
				type = data.values.type,
				title = data.values.title,
				$newTab = $(this._screenTemplate),
				layout = this.getValue('layout', {}),
				screenIndex = layout.container.indexOf($appendParent.cfMod('id')) + (appendAction === 'insertAfter'),
				$screenPreview = $('<div class="conv-screen conv_id_' + screenId + '"><div class="g-preloader"></div></div>');

			// Screen controls
			$newTab
				.find('> .b-builder-tab-screen-title')
				.on('click', this._screenSwitch.bind(this))
				.parent()
				.find('.b-builder-tab-screen-actions-menu')
				.on('click', '.b-builder-tab-screen-actions-btn', this._initScreenActions.bind(this))
				.parent()
				// b-builder-tab-screen-actions
				.on({
					mouseover: function(e) {
						var $target = $(e.target);
						$target
							.find('.b-builder-tab-screen-actions-menu')
							.css({
								left: $target.offset().left || 0
							});
					},
					mouseenter: function(e) {
						$(e.target)
							.parents('.b-builder-tab-screen')
							.addClass('is-hover');
					},
					mouseleave: function(e) {
						$(e.target)
							.parents('.b-builder-tab-screen')
							.removeClass('is-hover');
					}
				});

			$newTab
				.cfMod('id', screenId)
				.cfMod('type', type)
				.removeClass('is-active')
				.find('> span')
				.html(title)
				.attr('title', title);

			$newTab[appendAction]($appendParent);
			$screenPreview.insertAfter(this.$widget.find('.conv-screen:last'));
			this.setScreen(screenId);
			this._renderElm(data.id, data.values, data.children, $screenPreview, function(){
				// Appending the new data to the current value
				var queue = [data],
					layout = this.getValue('layout', {});
				// Placing the processed element to the layout
				// Do not do it if restore from backup
				if (!backup)
					layout.container.splice(screenIndex, 0, screenId);
				this.startTransaction();
				while (queue.length) {
					var elm = queue.shift();
					if (elm.id.indexOf('form'))
						elm.values = this.generateFieldNames(elm.id, elm.values);
					this.setValue(['data', elm.id], elm.values);
					if (elm.children){
						var childrenIds = [];
						for (var i = 0; i < elm.children.length; i++){
							var childId = elm.children[i].id;
							childrenIds.push(childId);
							queue.push(elm.children[i]);
						}
						layout[elm.id] = childrenIds;
					}
					if (['spintowin', 'scratch_card'].indexOf(this.getElmType(elm.id)) !== -1) {
						setTimeout(this._initGamificationElm.bind(this, elm.id), 1);
					}
				}
				this.setValue('layout', layout);
				this.commitTransaction();

				// Setting the screen once again to properly render the opened panel
				if (!backup) {
					this.activeScreen = null;
					this.setScreen(screenId);
				} else
					this.setScreen(this.$screensContainerTrack.children(':first').cfMod('id'));
				this._isCreateScreen = false;
			}.bind(this), function(){
				$screenPreview.remove();
				this._isCreateScreen = false;
			}.bind(this));

			// Update screens list in automation
			$('.b-builder-fieldset .cof-automations-item[data-type="go_to_screen"] select[name="screen_id"]').each(function(){
				$(this).append($("<option/>", {
					value: data.id,
					text: data.values.title
				}));
			});

			// Change screens
			this.$screensContainerTrack
				.trigger('change');
		},

		generateFieldNames: function(elmId, values) {
			var fields = arrayPath(values, 'fields', {});
			var data = this.getValue('data', {});
			var existingFields = [];
			var typesCount;

			$.each(data, function(key, value){
				if (value.fields !== undefined && key !== elmId)
					Array.prototype.push.apply(existingFields, arrayPath(value, 'fields', {}));
			}.bind(this));

			$.each(fields, function(key, value){
				if (value.name !== undefined) {
					typesCount = existingFields.filter(function(item){
						return item.type === value.type;
					});

					if (typesCount.length) {
						fields[key].name = value.type + typesCount.length;
					}
				}
			}.bind(this));

			values.fields = fields;
			return values;
		},

		/**
		 * Rename screen
		 * @param screenId string
		 */
		renameScreen: function(screenId) {
			var oldTitle = this.value.data[screenId].title || '';
			this.popupRenameScreen.input
				.off('change:submit')
				.on('change:submit', function() {
					var newTitle = $.trim(this.popupRenameScreen.input.getValue());
					if(newTitle === oldTitle || ! newTitle) return;
					this.setValue(['data', screenId, 'title'], newTitle);
					$('.b-builder-fieldset select[name="screen_id"] > option[value="'+screenId+'"]')
						.text(newTitle);
					this.$screensContainerTrack
						.find('> .id_'+screenId+' > .b-builder-tab-screen-title')
						.attr('title', newTitle)
						.text(newTitle);
				}.bind(this))
				.setValue(oldTitle);
			this.popupRenameScreen.show();
			this.popupRenameScreen.input.$input.focus();
			this.popupRenameScreen.input.$input.select();
		},

		/**
		 * Remove screen
		 * @param screenId string
		 */
		removeScreen: function(screenId){
			// If the screen doesn't exist
			if (this.value.data[screenId] === null || this.$screensContainerTrack.find('.b-builder-tab-screen').length <= 1) return;
			var $currentScreen = this.$screensContainerTrack.find('> .id_' + screenId),
				$prevScreen = $currentScreen.prev(),
				$nextScreen = $currentScreen.next();

			$currentScreen.remove();
			if (screenId === this.activeScreen){
				if (! $prevScreen.length)
					this.setScreen($nextScreen.cfMod('id') || 'screen');
				else
					this.setScreen($prevScreen.cfMod('id') || 'screen');
			}

			// Change screens
			this.$screensContainerTrack
				.trigger('change');

			// TODO Copy to trash, so the screen could be restored
			this.removeElm(screenId);
			$('.b-builder-fieldset .cof-automations-item[data-type="go_to_screen"] select[name="screen_id"]').each(function(){
				$('option[value="'+screenId+'"]',this).remove();
			});

			// Prevent the removal of the last screen
			if(this.value.layout.container.length <= 1){
				this.$screensContainerTrack
					.find('> .b-builder-tab-screen:first-child .type_remove')
					.addClass('is-hidden')
					.prev()
					.addClass('last-child');
			}
		},

		/**
		 * Selected screen
		 * @param id string
		 */
		setScreen: function(id){
			if (id === undefined) return;

			// Changing state shown in controls
			// Shoul be before active screen check because it is
			this.$screensContainerTrack
				.find('> .b-builder-tab-screen').removeClass('is-active')
				.filter('.id_' + id).addClass('is-active');

			this.$preview
				.cfMod('screen', id)
				.trigger('change.screen', [ id]);
			this.$widget
				.find('.conv-screen').removeClass('conv_active')
				.filter('.conv_id_'+id).addClass('conv_active');


			if (id === this.activeScreen) return;

			if (this.$updatedElm){
				// If the current panel cannot be used for other screen, hiding it
				this.$panel.find('.b-builder-fieldset.is-active').removeClass('is-active');
				this.hideHighlight(this.$updatedElm);
				this._hidePanel();
			}


			this.activeScreen = id;

			// Available scrolling for screens
			if(this.$screensContainer.closest('.b-builder-tab').is('.type_screens-slide')) {
				var $activeScreen = this.$screensContainerTrack
					.find('> .id_'+id+'.is-active');
				if($activeScreen.length) {
					var correctOffset = 0;
					if($activeScreen.index() >= 1) {
						correctOffset = $activeScreen.outerWidth() / 2;
					}
					this.$screensContainer
						.find('> .b-builder-tab-screens-h')
						.addClass('type_scrolling')
						.clearQueue()
						.animate({scrollLeft: $activeScreen.position().left - correctOffset}, 500, function() {
							this.$screensContainer
								.find('> .b-builder-tab-screens-h')
								.removeClass('type_scrolling');
						}.bind(this));
				}
			}

			// Ability to remove any screen
			if(this.value.layout.container.length > 1){
				this.$screensContainerTrack
					.find('.b-builder-tab-screen-actions-btn')
					.removeClass('last-child is-hidden');
			}

			// Updating the currently opened fieldset (this must be done after we changed this.activeScreen)
			if (this.activeDesignFieldsetID && this.value.data[id]) this.openFieldset(this.activeDesignFieldsetID);

			// Resize for external elements
			$.each(this.$externalElm, function(elmId, el) {
				if (this.getElmType(elmId) === 'scratch_card' && $(el).is(':visible')) {
					el[0].convScratchCard.resize();
				}
			}.bind(this));
		},

		/**
		 * Next screen number
		 * @param type string
		 * @return number
		 * @private
		 */
		_getNextScreenNumber: function(type){
			var screenNumbers = [],
				nextNumber = 1;
			$.each(this.value.data, function(_, data) {
				if(typeof data.type !== undefined && data.type === type) {
					var screenNumber = data.title.match(/([A-z]+)\s(\d+)/m);
					if (screenNumber && screenNumber[2]){
						screenNumbers.push(parseInt(screenNumber[2]));
					}else{
						screenNumbers.push(0);
					}
					nextNumber++;
				}
			});
			if(screenNumbers.indexOf(nextNumber) !== -1) {
				return  Math.max.apply(null, screenNumbers) + 1;
			}

			return nextNumber !== 1 ? nextNumber : '';
		},

		/**
		 * Generating the name of the copied screen
		 * @param title string
		 * @return string
		 */
		_getNextScreenCopyName: function(title) {
			var copyPattern = /\(copy\s?([0-9]*)\)$/m,
				title = $.trim(title.replace(copyPattern, '')),
				number = 0;

			// get max number from screens with same titles
			$.map(this.value.data, function(obj, elmId) {
				if(this.getElmType(elmId) === 'screen') {
					var subTitle = obj.title.replace(copyPattern, '');
					if($.trim(subTitle || '') === title)
					{
						var numMatches = obj.title.match(copyPattern);
						if (numMatches !== null)
							number = Math.max(number, parseInt((numMatches || {})[1] || 1));
					}
				}
			}.bind(this));

			number = number + 1;
			if (number === 0)
				return title;
			else if (number > 1)
				title += ' (copy ' +parseInt(number) +')';
			else
				title += ' (copy)';

			return title ;
		},

		/**
		 * Get temporary screen name by its ID
		 * @param id
		 * @return string
		 */
		_getScreenType: function(id){
			if (!this.value.data[id]) return null;
			return this.value.data[id].type;
		},

		/**
		 * @param type string
		 * @return string
		 * @private
		 */
		_getScreenIdByType: function(type){
			for (var i = 0; i < this.value.layout.container.length; i++){
				var screenId = this.value.layout.container[i];
				if (this.value.data[screenId].type === type) return screenId;
			}
			return null;
		},

		/**
		 * Init screen actions
		 * @private
		 */
		_initScreenActions: function(e) {
			var id = $(e.currentTarget).closest('.b-builder-tab-screen').cfMod('id'),
				className = e.target.className;

			if(className.indexOf('type_rename') !== -1) {
				return this.renameScreen(id);
			}
			if(className.indexOf('type_duplicate') !== -1) {
				return this.duplicateScreen(id);
			}
			if(className.indexOf('type_remove') !== -1) {
				if(confirm('Are you sure want to delete the screen with all the contents?')) {
					return this.removeScreen(id);
				}
			}
		},

		/**
		 * Screens sortable and auto scroll
		 * @private
		 */
		_initScreenSortable: function(drag_enable = true) {
			// Screens actions
			this.$screensContainerTrack
				.find('.b-builder-tab-screen > .b-builder-tab-screen-title')
				.on('click', this._screenSwitch.bind(this))
				.parent()
				.find('.b-builder-tab-screen-actions-menu')
				.on('click', '.b-builder-tab-screen-actions-btn', this._initScreenActions.bind(this));

			// Screens slide controls
			this.$screensContainerTrack
				.on('change', function() {
					var trackWidth = this.$screensContainerTrack.outerWidth(),
						screensWidth = this.$screensContainer.outerWidth();
					if(screensWidth <= trackWidth) {
						this.$tabsContents.addClass('type_screens-slide');
					} else {
						this.$tabsContents.removeClass('type_screens-slide');
					}
					// Control of displaying the menu item
					var $newScreen = this.$screensContainer.find('.b-builder-tab-newscreen'),
						$newScreenList = $newScreen.find('> .b-builder-tab-newscreen-list');
					if(($newScreenList.outerWidth() + $newScreen.offset().left) >= screensWidth) {
						$newScreenList.addClass('type_left-list');
					} else {
						$newScreenList.removeClass('type_left-list');
					}
				}.bind(this)).trigger('change');

			this.$screensContainer
				.on('click', '> .b-builder-tab-arrows', function(e) {
					var className = e.target.className;
					var screenWidth = this.$screensContainerTrack
						.find('.b-builder-tab-screen:first-child')
						.outerWidth() || 240;
					// Prev button
					if(className.indexOf('arrow-prev') !== -1) {
						this.$screensContainer
							.find('.b-builder-tab-screens-h')
							// TODO: It is necessary to calculate the sections
							.animate({scrollLeft: '-='+screenWidth * 3}, 350);
					}
					// Next button
					if(className.indexOf('arrow-next') !== -1) {
						this.$screensContainer
							.find('.b-builder-tab-screens-h')
							// TODO: It is necessary to calculate the sections
							.animate({scrollLeft: '+='+screenWidth * 3}, 350);
					}
				}.bind(this));

			// Watch scroll
			this.$screensContainer
				.find('.b-builder-tab-screens-h')
				.scroll(function(e) {
					var $target = $(e.target),
						$arrows = this.$screensContainer.find('.b-builder-tab-arrows'),
						$fab = $('.b-builder-tab-fab'),
						position = $target.scrollLeft() || 0;

					this.$screensContainerTrack
						.find('> .b-builder-tab-screen')
						.each(function(_, element) {
							var $this = $(element);
							if($this.offset().left < 0) {
								$this.addClass('type_hide-actions');
							} else {
								$this.removeClass('type_hide-actions');
							}
						});

					if(this.$tabsContents.is('.type_screens-slide')) {
						// Scroll start position
						if( ! position) {
							$arrows.filter('.type_arrow-prev').hide();
							$fab.show();
						} else {
							$arrows.filter('.type_arrow-prev').show();
							$fab.hide();
						}
						// Scroll end position
						if(position === (e.target.scrollWidth - e.target.offsetWidth)) {
							$arrows.filter('.type_arrow-next').hide();
						} else {
							$arrows.filter('.type_arrow-next').show();
						}
					} else {
						$arrows.hide();
					}
				}.bind(this));

			if (drag_enable) {
				// https://github.com/bevacqua/dragula
				this.screensSortable = dragula([this.$screensContainerTrack.get(0)], {
					mirrorContainer: this.$screensContainerTrack.get(0),
					direction: 'horizontal',
					invalid: function (el, handle) {
						return el.classList.contains('b-builder-tab-screen-actions');
					},
					isContainer: function (el) {
						return el.classList.contains('b-builder-tab-screens-track');
					}
				});
				this.screensSortable
					.on('drop', function() {
						// Sort screens for layout.container
						$('> .b-builder-tab-screen:not(.gu-mirror)', this.$screensContainerTrack).each(function(i, el) {
							this.value.layout.container[i] = $(el).cfMod('id');
						}.bind(this));
						this.setValue('layout', this.value.layout);
					}.bind(this));

				var drake = this.screensSortable;
				// https://github.com/hollowdoor/dom_autoscroller/
				var scroll = autoScroll([ $('.b-builder-tab-screens-h', this.$container).get(0) ], {
					margin: 25,
					maxSpeed: 10,
					//scrollWhenOutside: true,
					autoScroll: function() {
						return this.down && drake.dragging;
					}
				});
			}
		},

		/**
		 * Switch screen
		 * @private
		 */
		_screenSwitch: function(e) {
			var id = $(e.currentTarget).closest('.b-builder-tab-screen').cfMod('id'),
				sameActiveScreen = (id === this.$preview.cfMod('screen'));
			if (sameActiveScreen)
				return;
			this.setScreen(id);
		},

		_initGamificationElm: function(elmId) {
			var $elm, _init, type;
			if (typeof elmId === 'string')
				$elm = this.$widget.find('.conv_id_'+elmId);
			else if (elmId instanceof jQuery)
				$elm = elmId;
			else
				$elm = $(elmId);

			elmId = $elm.cfMod('conv_id');
			type = this.getElmType(elmId);

			this._initFieldset(type);

			switch (type) {
				case 'scratch_card':

					_init = function(){
						// Set and show coupon and code on element
						var $scratch_card = $elm.scratch_card(),
							prize = (this.value.data[elmId].prizes || this.defaults[type].prizes)[0];
						$scratch_card[0].convScratchCard.texts(prize.text, prize.coupon);
						$scratch_card.find('.conv-scratch').addClass('conv_active');
						this.$externalElm[elmId] = $scratch_card;
					}.bind(this);

					break;
				case 'spintowin':

					_init = function() {
						var $row = this.$externalElm[elmId] = $elm.find('.conv-spintowin-container');
						if ( !! $row.data('data'))
							return;
						// TODO: Delete after debugging (window.s2w)
						window.s2w = $row
							.data('data', $.extend(true, {}, this.value.data[elmId]))
							.spinToWin();
					}.bind(this);

					break;
			}

			if (_init !== undefined) {

				if (this.$widget.hasClass('conv_active') || this.$widget.hasClass('conv_scrollable') || this.$widget.cfMod('type') == 'inline')
					_init.call(this);
				else
					this.$widget.find('.conv-widget-h')
						.one('webkitTransitionEnd mozTransitionEnd otransitionend transitionend', _init);

				['prize_field', 'coupon_field'].forEach(function(field){
					var field_value = this.fieldsets[type].getValue(field),
						$shortCodes = $('.g-shortcodes-list');
					if ($shortCodes.children().length)
						$shortCodes.append('<div class="g-shortcodes-item" data-value="{'+field_value+'}">{'+field_value+'}</div>')
				}.bind(this));
			}
		},

		/**
		 * Replace shortcodes inside froala builder
		 * @param value
		 * @param toText
		 * @return value
		 * @private
		 */
		_replaceShortcodes: function(value, toText) {
			if (toText) {
				var div = document.createElement('div');
				div.innerHTML = value;
				$(div).find('[data-shortcode-insert],[data-shortcode-temp],[data-shortcode-value]').each(function(_, el){
					$(el).replaceWith($(el).text())
				});
				return $(div).html();
			} else {
				if (!value.match(/data-shortcode-value/gm))
					value = value.replace(/{([a-z0-9_,-]+)}/gi, '<span class="conv_shortcode_value fr-deletable" data-shortcode-value>$&</span>');
				return value;
			}
		},

		/**
		 * init backup
		 * @private
		 */
		_initBackup: function(){
			var widgetId = this.$container.data('id');
			this.cur_backup = localStorage.getItem('cur_backup'+widgetId);
			this.last_backup = localStorage.getItem('last_backup'+widgetId);
			this.failRequestAlert = new CFAlert('.g-alert.type_note.for_fail_request');

			if (this.cur_backup !== null) {
				localStorage.setItem('last_backup'+widgetId, this.cur_backup);
				localStorage.removeItem('cur_backup'+widgetId);
				this.last_backup = this.cur_backup;
				this.cur_backup = null;
			}
			if (this.last_backup !== null) {
				this.backupAlert = new CFAlert('.g-alert.type_note.for_backup');
				this.backupAlert.show();
				$('.g-alert.type_note.for_backup .g-btn').on('click', function(e){
					var $target = $(e.target);
					if($target.hasClass('loading')) return;

					if ($target.hasClass('action_delete')) {
						this._deleteBackup();
					} else {
						var values = this.getValue(),
							backup = JSON.parse(this.last_backup),
							rules_fields = Object.keys(this.fieldsets['rules'].fields);

						$target.addClass('loading');
						Object.keys(backup).forEach(function(item){
							// If exist layout not render again elements change in data
							if (typeof backup.layout !== 'undefined' && item === 'data')
								return;

							if (rules_fields.indexOf(item) !== -1){
								this.setValue(item, backup[item]);
								return ;
							}

							if (item === 'layout') {
								this._renderBackupLayout(values, backup);
							} else  {
								switch (item) {
									case 'options':
										this._initFieldset('widget');
										this.updatedElm = 'widget';
										this.$updatedElm = $('.conv_id_' + item);
										Object.keys(backup[item]).forEach(function(key){
											this.fieldsets['widget'].setValue(key, backup[item][key])
										}.bind(this));
										break;
									case 'data':
										Object.keys(backup[item]).forEach(function(id){
											var type = this.getElmType(id);
											this._initFieldset(type);
											this.updatedElm = id;
											this.$updatedElm = $('.conv_id_' + id);
											Object.keys(backup[item][id]).forEach(function(key){
												this.fieldsets[type].setValue(key, backup[item][id][key])
											}.bind(this));
										}.bind(this));
										break;
									case 'custom_css':
										this._initFieldset(item);
										this._onChange(item, item, backup[item]);
										break;
									default:
								}
							}
						}.bind(this), function(){
							$target.removeClass('loading');
						});

						this._hidePanel();
						this.backupAlert.hide();
					}
				}.bind(this));
			}
		},

		/**
		 * set Backup
		 * @param changes
		 * @private
		 */
		_setBackup: function(changes){
			localStorage.setItem('cur_backup'+this.widgetId, JSON.stringify(changes));
		},

		/**
		 * delete backup
		 * @param types
		 * @private
		 */
		_deleteBackup: function(types){
			var widgetId = this.$container.data('id');
			types = (types && Array.isArray(types)) ? types : ['last_backup', 'cur_backup'];
			types.forEach(function(type){
				localStorage.removeItem(type+widgetId);
			});
			if (typeof this.backupAlert !== 'undefined')
				this.backupAlert.hide();
		},

		/**
		 * render backup layout
		 * @param values
		 * @param backup
		 * @private
		 */
		_renderBackupLayout(values, backup) {
			var children, screen_data, type, $parentScreen,
				appendAction = 'insertAfter',
				layout = backup.layout,
				getRenderElmObj = function(id) {
					var data = {
						id: id,
						values: $.extend(true, arrayPath(values.data, [id], {}),arrayPath(backup.data, [id], {})),
					};
					if (typeof backup.layout[id] !== 'undefined') {
						children = backup.layout[id].map(function(item){
							return getRenderElmObj(item);
						}.bind(this));
						data.children = children;
					}

					return data;
				}.bind(this);

			layout['container'].forEach(function(id){
				if (backup.layout['container'].indexOf(id) === -1)
					this.removeScreen(id);
				else {
					screen_data = getRenderElmObj(id);
					$screenElm  = this.$widget.find('.conv_id_' + id);
					if ($screenElm.length) {
						// re-render existing screen
						this._renderElm(screen_data.id, screen_data.values, screen_data.children, $screenElm, function(data){
							// check gamification elms on screen
							while (data.length) {
								var elm = data.shift();
								if (elm.children){
									for (var i = 0; i < elm.children.length; i++){
										data.push(elm.children[i]);
									}
								}
								// init gamification elms on screen
								if (['spintowin', 'scratch_card'].indexOf(this.getElmType(elm.id)) !== -1) {
									setTimeout(this._initGamificationElm.bind(this, elm.id), 1);
								}
							}

							this.activeScreen = null;
							this.setScreen('screen');
						}.bind(this));
					} else {
						// append new screen
						type = screen_data.values.type;
						if (type === 'intro') {
							appendAction = 'insertBefore';
							$parentScreen = this.$screensContainerTrack.find('.b-builder-tab-screen:first');
						} else {
							$parentScreen = this.$screensContainerTrack.find('.id_'+prevScreen);
							appendAction = 'insertAfter';
						}

						this._appendScreen(appendAction, $parentScreen, screen_data, true);
					}
					prevScreen = id;
				}
			}.bind(this));

			this.setValue('layout', layout);
			this.setValue('data', $.extend(true, {}, values.data, backup.data));
			this.setScreen(this.$screensContainerTrack.children(':first').cfMod('id'));
		},

		/**
		 * Auth user if session expired
		 * @param $target
		 * @private
		 */
		_authUser($target) {
			if ($target.hasClass('loading'))
				return;

			var data = {},
				$form = $target.closest('.for_sign_in');
			$target.addClass('loading');
			$form
				.find('[name]')
				.each(function () {
					data[this.name] = this.value;
				});

			$.post('/api/auth/login', data, function(res) {
				$target.removeClass('loading');
				$form.find('.g-form-row-state').hide();
				if(!res.success) {
					Object.keys(res.errors).forEach(function(field){
						$form.find('.for_'+field+' .g-form-row-state')
							.show()
							.text(res.errors[field]);
					});
					return;
				}
				this.popupAuth.hide();
				if (Array.isArray(this.authSession)) {
					// try Render elm again
					this._renderElm.apply(this, this.authSession);
				}
				else {
					// try save changes again
					this.handleAction(this.authSession)
				}
			}.bind(this), 'json');
		},

		/**
		 * FAB - Floating Action Button
		 * @type {Object}
		 * @private
		 */
		fab: {
			/**
			 * FAB tab container
			 * @type {jQueryObject}
			 */
			$tabContainer: $('.b-builder-tab-fab-h', this.$screensContainer),

			/**
			 * FAB tab template
			 * @type {string}
			 */
			tabTemplate: $('template[data-id="tab-fab-template"]', this.$container).html(),

			/**
			 * FAB template
			 * @type {string}
			 */
			fabTemplate: $('template[data-id="fab-template"]', this.$container).html(),

			/**
			 * Add FAB Button
			 * @type {jQueryObject}
			 */
			$addFabBtn: $('.b-builder-tab-newscreen-fab-item', this.$container),

			/**
			 * FAB Wrapper
			 * @type {jQueryObject}
			 */
			$wrapper: $('.cof-form-wrapper.style_fab', this.$panelH),

			/**
			 * Event handlers
			 * @type {Object}
			 */
			_events: {
				/**
				 * Change screens
				 * @param {EventObject} e
				 * @param {string} screenType
				 * @return void
				 */
				changeScreen: function(e, screenType) {
					if (screenType !== 'fab')
						this.fab._hide.call(this);
				},
				/**
				 * @param {jQueryEvent} e
				 * @return void
				 */
				scrollPanel: function (e) {
					this.fab.$wrapper
						.find('.cof-form-wrapper-title')
						.toggleClass('with_border', e.currentTarget.scrollTop !== 0);
				},
			},

			/**
			 * FAB Initialization
			 */
			init: function() {

				if (this.$container.find('.b-builder-tab-newscreen-fab-item').hasClass('is-locked'))
					return;

				// Disable link
				this.fab.$wrapper.find('[data-name="fab_text"] .cof-froala').data('froala-options', {
					toolbarButtons: ['bold', 'italic', 'strikeThrough', 'formatOL', 'formatUL']
				});

				this.$container
					.find('.b-builder-tab-newscreen')
					.on('click', '.b-builder-tab-newscreen-fab-item', this.fab._add.bind(this));
				this.$preview
					.on('change.screen', this.fab._events.changeScreen.bind(this));
				this.fab.$wrapper
					.find('.cof-form-wrapper-cont')
					.on('scroll', this.fab._events.scrollPanel.bind(this));

				this._initFieldset('widget');
				this._initFieldset('rules');
				this.fab._assignTabEvents.call(this);

				var pid = setTimeout(function() {
					this.$container
						.find('.i-rule-fab-link')
						.on('click', this.fab._show.bind(this));
					clearTimeout(pid);
				}.bind(this), 1);
			},

			/**
			 * Assign events to control the FAB
			 * @return void
			 */
			_assignTabEvents: function() {
				// Tab events
				this.fab.$tabContainer
					.one('click', '.type_remove', this.fab._remove.bind(this))
					.on('click', '.b-builder-tab-fab-title', this.fab._show.bind(this))
					.find('.b-builder-tab-fab-actions')
					.on({
						mouseover: function(e) {
							var $target = $(e.target);
							$target
								.find('.b-builder-tab-fab-actions-menu')
								.css({
									left: $target.offset().left || 0
								});
						},
						mouseenter: function(e) {
							$(e.target)
								.parents('.b-builder-tab-fab')
								.addClass('is-hover');
						},
						mouseleave: function(e) {
							$(e.target)
								.parents('.b-builder-tab-fab')
								.removeClass('is-hover');
						}
					});
			},

			/**
			 * Add FAB Tab
			 * @param {EventObject} e
			 * @return void
			 * @private
			 */
			_add: function(e) {
				var $target = $(e.currentTarget);
				$target.addClass('is-hidden');
				this.setValue('options.fab_enabled', true);

				this.fab.$tabContainer
					.html(this.fab.tabTemplate)
					.addClass('is-active');

				this.$container
					.addClass('conv_fab');

				this.fab._assignTabEvents.call(this);
			},

			/**
			 * Deletion of all FAB elements
			 * @param {EventObject} e
			 * @return void
			 * @private
			 */
			_remove: function(e) {
				var $addFabBtn = this.$container.find('.b-builder-tab-newscreen-fab-item'),
					$firstScreen = this.$screensContainerTrack.find('.b-builder-tab-screen:first > .b-builder-tab-screen-title');

				$addFabBtn.removeClass('is-hidden');
				this.fab.$tabContainer
					.removeClass('is-active')
					.html('');
				this._hidePanel();

				if ($firstScreen.length)
					$firstScreen.trigger('click');

				this.$container
					.removeClass('conv_fab');

				this.setValue('options.fab_enabled', false);

				// Reset FAB to default settings
				$.each(arrayPath(this.defaults, ['widget'], []), function(param, value) {
					if (param && param.indexOf('fab_') !== -1) {
						this.updatedElm = 'widget';
						this.fieldsets.widget.setValue(param, value);
					}
				}.bind(this));
			},

			/**
			 * Show FAB settings
			 * @return void
			 * @private
			 */
			_show: function() {
				this.$screensContainerTrack
					.find('.is-active')
					.removeClass('is-active');
				this.fab.$tabContainer
					.find('.b-builder-tab-fab')
					.addClass('is-active');
				this.$panel
					.find('.type_wrapper_start')
					.addClass('is-active');

				if (this.$preview.cfMod('screen') !== 'fab')
					this.setScreen('fab');

				this.openFieldset('widget');
				this.hideHighlight();

				if ( ! this.$wContainer.find('.conv-fab').length)
					this.$wContainer
						.prepend(this.fab.fabTemplate);

				if ( ! this.$wContainer.hasClass('conv_fab'))
					this.$wContainer
						.addClass('conv_fab');

				if (this.fab.$wrapper.length)
					this.fab.$wrapper.find('.cof-form-wrapper-title').trigger('click');
			},

			/**
			 * Hide FAB settings
			 * @return void
			 * @private
			 */
			_hide: function() {
				if (this.fab.$wrapper.length)
					this.fab.$wrapper.find('.cof-form-wrapper-title').removeClass('is-active');
				this.fab.$tabContainer
					.find('.is-active')
					.removeClass('is-active');
				this.$panel
					.find('.type_wrapper_start')
					.removeClass(' is-active');
				this.$wContainer
					.removeClass('conv_fab')
			},
		}
	};


	/**
	 * $cof.Field type: export
	 * @requires $cf.builder
	 */
	$cof.Field['export'] = {
		init: function(){
			this.initialValue = JSON.parse(this.$input.val());
			this.$importButton = this.$row.find('.action_import');

			this.on('beforeShow', function(){
				if ($cof.widget !== undefined)
				{
					var values = $cof.widget.getValues();
					$.each(values, function(key, value){
						switch (key)
						{
							case 'integrations':
								break;
							default:
								this.initialValue[key] = value;
						}
					}.bind(this));
					this.$input.val( JSON.stringify(this.initialValue) );
				}
			}.bind(this));


			this.$importButton.click(function(){
				//TODO: refactor after add layout to /api/widget/update/
				var widget_id = ($cof.widget && $cof.widget.widgetId) || $cf.builder.widgetId;
				$.ajax({
					url: '/api/widget/import/'+widget_id,
					type:"POST",
					data: this.$input.val(),
					contentType:"application/json; charset=utf-8",
					dataType:"json",
					success: function(response){
						if ( ! response.success) return console.error(response);
						window.location.reload(true);
					}
				});
			}.bind(this));
		},
		getValue: null,
		setValue: function(value){
			this.$input.val(value);
		}
	};

	/**
	 * $cof.Field type: elements
	 */
	$cof.Field['elements'] = {
		init: function(){
			this.$widget = $('.conv-widget');
			this.$list = this.$row.find('.cof-elements');
			this.sortableElements = dragula({
				containers: [this.$list[0]],
				mirrorContainer: (this.$widget.cfMod('conv_type') === 'welcome') ? document.querySelector('.conv-widget-h') : document.querySelector('.conv-widget'),
				invalid: function (el, handle) {
					return !(handle.classList.contains('cof-elements-item-h') || handle.classList.contains('cof-elements-item'));
				},
				moves: function (el/*, source, handle, sibling*/) {
					return !el.classList.contains('cof-disabled');
				},
				copy: function (el, source) {
					return source === this.$list[0];
				}.bind(this),
				accepts: function (el, target) {
					return target !== this.$list[0];
				}.bind(this)
			});
			this.sortableElements
				.on('drag', function () {
					var $columns = $('.conv-col-h').toArray(),
						lists = [this.$list[0]];
					for (var i = 0; i < $columns.length; i += 1) {
						lists.push($columns[i]);
					}
					this.sortableElements.containers = lists;
					$cf.builder.hideHighlight();
					$cf.builder._disableHighlight = true;
					$cf.builder.$preview.addClass('is-elementdrag');
				}.bind(this))
				.on('dragend', function () {
					$cf.builder.$preview.removeClass('is-elementdrag');
				}.bind(this))
				.on('cancel', function () {
					$cf.builder._disableHighlight = false;
				}.bind(this))
				.on('drop', function (el, target) {
					if (!target) {
						$cf.builder._disableHighlight = false;
						return;
					}
					var $el = $(el),
						index = $(el).index(),
						type = $el.data('type'),
						parentId = $(target).closest('.conv-col').cfMod('conv_id'),
						values = $el.find('.cof-elements-item-param')[0].onclick() || {};
					$el.remove();
					$cf.builder.createElm(type, parentId, index, values, function(id){
						$cf.builder._disableHighlight = false;
						$cf.builder.openFieldset(id);
					}.bind(this));
				}.bind(this));

			this.on('beforeShow', function(){
				var allScreenElms = $cf.builder.getLayoutElms($cf.builder.activeScreen),
					state = $cf.builder.$wContainer.cfMod('conv_state');

					// Search for substrings in array elements
					Array.prototype.inArray = function (name) {
						return this.join().indexOf(name) != -1;
					};

				var canAddElms = {
					// The following elements can be added only on desktop
					video: state === 'desktop',
					image: state === 'desktop',
					spacer: state === 'desktop',
					// One-per widget screen
					share: ! allScreenElms.inArray('share'),
					countdown: ! allScreenElms.inArray('countdown'),
					follow: ! allScreenElms.inArray('follow'),
					form: ! allScreenElms.inArray('form'),
					socsign: ! allScreenElms.inArray('socsign'),
					spintowin: ! allScreenElms.inArray('spintowin'),
					scratch_card: ! allScreenElms.inArray('scratch_card')
				};
				$.each(canAddElms, function(elmType, canAddElm){
					this.$list.find('[data-type="' + elmType + '"]').toggleClass('is-hidden', ! canAddElm);
				}.bind(this));
			}.bind(this));
		},
		setValue: null,
		getValue: null
	};

	/**
	 * $cof.Field type: layout
	 * @requires $cf.builder
	 */
	$cof.Field['layout'] = {

		init: function(){

			this.rowTemplate = this.$row.find('template[data-id="emptyrow"]').html();
			this.colTemplate = this.$row.find('template[data-id="emptycol"]').html();

			this.$rowsList = this.$row.find('.cof-layout-rows');

			this.sortableRows = dragula([this.$rowsList[0]], {
				direction: 'vertical',
				invalid: function (el, handle) {
					return (handle.classList.contains('cof-layout-control') || el.classList.contains('cof-layout-row-cols'));
				}
			});
			this.sortableCols = {};

			this._events = {
				render: this.render.bind(this)
			};

			this.$row
				.on((window.PointerEvent) ? 'pointerdown': 'mousedown', '.cof-layout-row-col-resizer', this._startResizeCol.bind(this))
				.on((window.PointerEvent) ? 'pointerup pointerleave' : 'mouseup mouseleave', '.cof-layout-row-col-resizer', this._stopResizeCol.bind(this))
				.on('mouseover', '.cof-layout-row, .cof-layout-row-col, .cof-layout-row-col-resizer', function(e){
					e.stopPropagation();
					var $el = $(e.currentTarget);
					if ($el.hasClass('cof-layout-row-col-resizer')) $el = $el.closest('.cof-layout-row');
					var id = $el.attr('data-id') || 'widget';
					$cf.builder.showHighlight((id === 'widget') ? $cf.builder.$widget : $cf.builder.$preview.find('.conv_id_' + id));
				}.bind(this))
				.on('mouseout', '.cof-layout-row, .cof-layout-row-col', function(e){
					var id = $(e.currentTarget).attr('data-id') || 'widget';
					$cf.builder.hideHighlight((id === 'widget') ? $cf.builder.$widget : $cf.builder.$preview.find('.conv_id_' + id));
				}.bind(this))
				.on('click', '.cof-add[data-type="row"]', function(){
					$cf.builder.createElm('row', $cf.builder.activeScreen, undefined, undefined, this._events.render);
				}.bind(this))
				.on('click', '.cof-layout-control.type_remove[data-type="row"]', function(e){
					var id = $(e.currentTarget).closest('.cof-layout-row').attr('data-id');
					if (window.confirm('Are you really want to delete the row with all its elements?')){
						$cf.builder.removeElm(id);
						this.render();
					}
				}.bind(this))
				.on('click', '.cof-layout-control.type_duplicate', function(e){
					var id = $(e.currentTarget).closest('.cof-layout-row').attr('data-id');
					$cf.builder.duplicateElm(id, function(){
						this.render();
					}.bind(this));
				}.bind(this))
				.on('click', '.cof-layout-control.type_add[data-type="col"]', function(e){
					var parentId = $(e.currentTarget).closest('.cof-layout-row').attr('data-id');
					$cf.builder.createElm('col', parentId, undefined, undefined, function(){
						this.render();
					}.bind(this));
				}.bind(this))
				.on('click', '.cof-layout-control.type_remove[data-type="col"]', function(e){
					var $el = $(e.currentTarget),
						id = $el.closest('.cof-layout-row-col').attr('data-id');
					if (window.confirm('Are you really want to delete the column with all its elements?')){
						$cf.builder.removeElm(id);
						this.render();
					}
				}.bind(this))
				.on('click', '.cof-layout-control.type_settings', function(e){
					var $el = $(e.currentTarget),
						id = $el.closest('.cof-layout-row-col').data('id') || $el.closest('.cof-layout-row').data('id') || 'widget';
					$cf.builder.openFieldset(id);
				}.bind(this));

			//this.render();
			this.on('beforeShow', this._events.render);
		},

		/**
		 * Gracefully render the element based on the current value
		 */
		render: function(){
			var layout = $cf.builder.getValue('layout', {}),
				data = $cf.builder.getValue('data', {}),
				activeScreenId = $cf.builder.activeScreen,
				allScreenElms = $cf.builder.getLayoutElms(activeScreenId),
				queue = [[layout[activeScreenId], this.$rowsList]],
				updateRowsSortables = false,
				updateColsSortables = [];
			while (queue.length){
				var tuple = queue.shift(),
					elms = tuple[0],
					$parent = tuple[1],
					$prevElm = null;
				for (var i = 0; i < elms.length; i++){
					var elmId = elms[i],
						elmType = $cf.builder.getElmType(elmId);
					// Just in case: skipping wrong types
					if (elmType !== 'row' && elmType !== 'col') continue;
					var $elm = this.$rowsList.find('[data-id="' + elmId + '"]');
					if ( ! $elm.length){
						$elm = $(this[elmType + 'Template']).attr('data-id', elmId);
						if (elmType === 'row'){
							var $rowCols = $elm.find('.cof-layout-row-cols');
							$rowCols.attr('data-parent', elmId);
							updateColsSortables.push(elmId);
							updateRowsSortables = true;
						}
						else if (elmType === 'col'){
							var parent = $cf.builder.getParent(elmId);
							if (updateColsSortables.indexOf(parent) === -1) updateColsSortables.push(parent);
						}
					}
					// Checking that element is on its place, and if not, placing it
					if ( ! $prevElm){
						// Must be a first element of its parent
						if ( ! $parent.children(':first').is($elm)) $elm.prependTo($parent);
					}
					else {
						// Must be after its previous sibling
						if ( ! $prevElm.next().is($elm)) $elm.insertAfter($prevElm);
					}
					if (elmType === 'col'){
						var renderedSize = $elm.attr('data-size'),
							actualSize = $cf.builder.getColLabel(arrayPath(data, [elmId, 'sz'], 6));
						if (renderedSize !== actualSize){
							$elm.attr('data-size', actualSize);
						}
					}
					// Handling children elements
					if (layout[elmId] && layout[elmId].length){
						// Process row element as well
						queue.push([layout[elmId], $elm.find('[data-parent="' + elmId + '"]')]);
					}
					$prevElm = $elm;
				}
				// Gracefully removing all excess elements
				var $curElm = ($prevElm === null) ? $parent.children(':first') : $prevElm.next();
				while ($curElm.length){
					var curElmId = $curElm.attr('data-id'),
						curElmType = $cf.builder.getElmType(curElmId),
						$_curElm = $curElm;
					$curElm = $curElm.next();
					if (allScreenElms.indexOf(curElmId) === -1){
						if (curElmType === 'row'){
							this._destroySortableCols(curElmId);
							updateRowsSortables = true;
						}
						else if (elmType === 'col'){
							var curParent = $cf.builder.getParent(curElmId);
							if (updateColsSortables.indexOf(curParent) === -1) updateColsSortables.push(curParent);
						}
						$_curElm.remove();
					}
				}
			}
			// Updating sortables
			if (updateRowsSortables) this._updateSortableRows();
			updateColsSortables.forEach(function(rowId){
				this._updateSortableCols(rowId);
			}.bind(this));
			// Updating row titles
			this._updateRowTitles();
		},

		_updateSortableRows: function(){
			var el = this.$rowsList[0];
			if (this.sortableRows){
				this.sortableRows.destroy();
				this.sortableRows = null;
			}
			if (el.childElementCount > 1) {
				this.sortableRows = dragula([el], {
					direction: 'vertical',
					invalid: function (el, handle) {
						return (handle.classList.contains('cof-layout-control') || el.classList.contains('cof-layout-row-cols'));
					}
				});
				this.sortableRows.on('drop', function(el, parentEl){
					var id = el.getAttribute('data-id'),
						newIndex = Array.prototype.indexOf.call(parentEl.children, el);
					$cf.builder.moveElm(id, $cf.builder.activeScreen, newIndex);
					this.render();
				}.bind(this));
			}
			this._updateRowTitles();
		},

		_updateRowTitles: function(){
			this.$rowsList.find('.cof-layout-row > .cof-layout-title').each(function(index, row){
				row.innerHTML = 'Row ' + (index + 1);
			}.bind(this));
		},

		_destroySortableCols: function(rowId){
			if (this.sortableCols[rowId]){
				this.sortableCols[rowId].destroy();
				delete this.sortableCols[rowId];
			}
		},

		_updateSortableCols: function(rowId, $colsParent){
			this._destroySortableCols(rowId);
			if ($colsParent === undefined){
				$colsParent = this.$rowsList.find('.cof-layout-row-cols[data-parent="' + rowId + '"]');
			}
			var el = $colsParent[0];
			if (el && el.childElementCount > 1){
				this.sortableCols[rowId] = dragula([el], {
					direction: 'horizontal',
					invalid: function(el, handle){
						return (handle.classList.contains('cof-layout-control') || handle.classList.contains('cof-layout-row-col-resizer'));
					}
				});
				this.sortableCols[rowId]
					.on('drag', function(){
						this.$rowsList.addClass('is-coldrag');
					}.bind(this))
					.on('dragend', function(){
						this.$rowsList.removeClass('is-coldrag');
					}.bind(this))
					.on('drop', function(el, parentEl){
						var id = el.getAttribute('data-id'),
							newParent = parentEl.getAttribute('data-parent'),
							newIndex = Array.prototype.indexOf.call(parentEl.children, el);
						$cf.builder.moveElm(id, newParent, newIndex);
						this.render();
					}.bind(this));
			}
		},

		_getColSize: function(label){
			if (typeof label === 'string' && label.indexOf('%') !== -1) return Math.round(parseInt(label) / 100 * 12);
			return parseInt(label);
		},

		_resizeCol: function (e) {
			var pos = e.clientX,
				step = this.$row.width() / 12, // ~ col width +/-
				$col, $prevCol, id, prevColId, colWidth, prevColWidth;

			if (Math.abs(pos - this._startResizeX) < step) return;

			$col = this.$resizeEl.closest('.cof-layout-row-col');
			id = $col.attr('data-id');
			$prevCol = $col.prev();
			prevColId = $prevCol.attr('data-id');

			$cf.builder.hideHighlight($('.conv_id_' + id));

			colWidth = this._getColSize($col.attr('data-size'));
			prevColWidth = this._getColSize($prevCol.attr('data-size'));
			if (pos > this._startResizeX) {
				if (colWidth === 1) return;
				colWidth -= 1;
				prevColWidth += 1;
				this._startResizeX += step;
			} else {
				if (prevColWidth === 1) return;
				colWidth += 1;
				prevColWidth -= 1;
				this._startResizeX -= step;
			}
			$prevCol.attr('data-size', $cf.builder.getColLabel(prevColWidth));
			$col.attr('data-size', $cf.builder.getColLabel(colWidth));
			$cf.builder.setValues([
				[['data', prevColId, 'sz'], prevColWidth],
				[['data', id, 'sz'], colWidth]
			]);
			$cf.builder.renderColSize(prevColId, prevColWidth);
			$cf.builder.renderColSize(id, colWidth);
		},

		_startResizeCol: function (e) {
			this.$resizeEl = $(e.currentTarget);
			this.$resizeEl.addClass('is-drag');
			this._startResizeX = e.clientX;
			this.$resizeEl.on('mousemove', this._resizeCol.bind(this));
		},

		_stopResizeCol: function (e) {
			this.$resizeEl = $(e.currentTarget);
			this.$resizeEl.removeClass('is-drag');
			this.$resizeEl.off('mousemove');
		},
		// Layout cannot have own value
		getValue: null,
		setValue: null
	};
}(jQuery);
