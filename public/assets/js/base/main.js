!function($){
	/**
	 * Retrieve/set/erase dom modificator class <mod>_<value> for the CSS Framework
	 * @param {String} mod Modificator namespace
	 * @param {String} [value] Value
	 * @returns {string|jQuery|boolean}
	 */
	$.fn.cfMod = function(mod, value){
		if (this.length === 0) return this;
		// Remove class modificator
		if (value === false){
			return this.each(function(){
				this.className = this.className.replace(new RegExp('(^| )' + mod + '\_[a-zA-Z0-9\_\-]+((?= )|$)', 'g'), '$2');
			});
		}
		var pcre = new RegExp('^.*?' + mod + '\_([a-zA-Z0-9\_\-]+).*?$'),
			arr;
		// Retrieve modificator
		if (value === undefined){
			return (arr = pcre.exec(this.get(0).className)) ? arr[1] : false;
		}
		// Set modificator
		else {
			var regexp = new RegExp('(^| )' + mod + '\_[a-zA-Z0-9\_\-]+( |$)');
			return this.each(function(){
				if (this.className.match(regexp)){
					this.className = this.className.replace(regexp, '$1' + mod + '_' + value + '$2');
				}
				else {
					this.className += ' ' + mod + '_' + value;
				}
			});
		}
	};

	$.fn.slideUpRow = function(callback){
		setTimeout(function(){
			this.children('td').animate({paddingTop: 0, paddingBottom: 0, borderTopWidth: 0, borderBottomWidth: 0}, 500)
				.wrapInner('<div />')
				.children()
				.slideUp(500);
			// Not attaching to slideUp, as we need it to be fired just once
			if (callback instanceof Function) setTimeout(callback, 500)
		}.bind(this), 350);
		return this;
	};
	$.fn.slideDownRow = function(callback){
		var $tds = this.children('td').wrapInner('<div />'),
			$divs = $tds.children().hide(),
			atts = $tds.css(['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']);
		$tds.css({paddingTop: 0, paddingBottom: 0, borderTopWidth: 0, borderBottomWidth: 0});
		this.show();
		$divs.slideDown(500);
		$tds.animate(atts);
		// Not attaching to slideUp, as we need it to be fired just once
		setTimeout(function(){
			$tds.css({paddingTop: '', paddingBottom: '', borderTopWidth: '', borderBottomWidth: ''});
			// Unwrapping
			$divs.each(function(){
				this.parentNode.innerHTML = this.innerHTML;
			});
			if (callback instanceof Function) callback();
		}.bind(this), 500);
		return this;
	};

	$.fn.bindFirst = function(name, fn){
		var elem, handlers, i, _len;
		this.bind(name, fn);
		for (i = 0, _len = this.length; i < _len; i++){
			elem = this[i];
			handlers = jQuery._data(elem).events[name.split('.')[0]];
			handlers.unshift(handlers.pop());
		}
	};

	/**
	 * Show errors from API request in some particular form
	 * @param errors
	 */
	$.fn.showErrors = function(errors){
		// Cleaning previous errors at first
		this.find('.g-form-row.check_wrong .g-form-row-state').html('');
		this.find('.g-form-row.check_wrong').removeClass('check_wrong');
		for (var key in errors){
			if (!errors.hasOwnProperty(key)) continue;
			var $input = this.find('[name="' + key + '"]');
			if ($input.length === 0) continue;
			$input.parents('.g-form-row').addClass('check_wrong').find('.g-form-row-state').html(errors[key]);
		}
	};

	/**
	 * jQuery extend for rendering the string by pattern
	 * @param params array - variables {name: value, ...}
	 * @chainable
	 * @return self
	 */
	$.fn.setTextPatternVars = function(params) {
		var $this = $(this),
			params = params ? params : {},
			str = $this.text(),
			renderText = $this.data('textPattern');

		if(renderText !== undefined)
			str = $this.data('textPattern');
		else
			// Using the text.pattern object
			$this.data('textPattern', str);

		$this.html(str.replace(/{([A-z\-_]+)}/g, function(_, key) {
			if (params[key] !== undefined)
				return '<span class="g-textpattern g-textpattern_'+key+'">'+(params[key] || '')+'</span>';

			return '';
		}));

		return this;
	};
}(jQuery);

// FIX for ajax boolean values
// https://stackoverflow.com/questions/4933631/jquery-ajax-and-sending-boolean-request-arguments
function convertBoolToNum(obj){
	if (typeof obj === 'object')
		jQuery.each(obj, function(i){
			if (!obj.hasOwnProperty(i))
				return;

			if (typeof obj[i] === 'object'){
				convertBoolToNum(obj[i]);
			}
			else if (typeof obj[i] === 'boolean'){
				obj[i] = Number(obj[i]);
			}
		});
}

jQuery.ajax = (function($ajax){
	return function(options){
		convertBoolToNum(options.data);
		return $ajax(options);
	};
})(jQuery.ajax);

function getParameterByName(name, url){
	if (!url){
		url = self.location.href;
	}
	name = name.replace(/[\[\]]/g, "\\$&");
	var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
		results = regex.exec(url);
	if (!results) return null;
	if (!results[2]) return '';
	return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function setParameterByName(key, value, uri){
	var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
	var separator = uri.indexOf('?') !== -1 ? "&" : "?";
	if (!value){
		return uri.replace(re, '$1').replace(/&$/, '');
	}
	if (uri.match(re)){
		return uri.replace(re, '$1' + key + "=" + value + '$2');
	}
	else {
		return uri + separator + key + "=" + value;
	}
}

// Determine whether a variable is empty
function empty(mixed_var){
	return (mixed_var === "" || mixed_var === 0 || mixed_var === "0" || mixed_var === null || mixed_var === false || (typeof mixed_var === 'object' && mixed_var.length === 0));
}


/**
 * Globally available Convertful helpers
 */
!function($){
	if (window.$cf === undefined) window.$cf = {};

	$cf.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
	$cf.isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

	// jQuery objects of commonly used DOM-elements
	$cf.$window = $(window);
	$cf.$document = $(document);
	$cf.$html = $(document.documentElement).toggleClass('no-touch', !$cf.isMobile);
	$cf.$head = $(document.head);
	$cf.$body = $(document.body);
	// Empty image for COF
	// http://stackoverflow.com/questions/5775469/whats-the-valid-way-to-include-an-image-with-no-src
	// Use "//:0" is buggy in FF
	$cf.emptyImage = "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";

	if ($cf.mixins === undefined) $cf.mixins = {};

	// Helpers
	if ($cf.helpers === undefined) $cf.helpers = {};
	$cf.helpers.escapeHtml = function(text){
		if (typeof text != 'string') return '';
		var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
		return text.replace(/[&<>"']/g, function(m){
			return map[m];
		});
	};
	$cf.helpers.stripTags = function(text){
		return text.replace(/(<([^>]+)>)/ig, '');
	};
	$cf.helpers.urlencode = function(str){
		//       discuss at: http://locutus.io/php/urlencode/
		//      original by: Philip Peterson
		//      improved by: Kevin van Zonneveld (http://kvz.io)
		//      improved by: Kevin van Zonneveld (http://kvz.io)
		//      improved by: Brett Zamir (http://brett-zamir.me)
		//      improved by: Lars Fischer
		//         input by: AJ
		//         input by: travc
		//         input by: Brett Zamir (http://brett-zamir.me)
		//         input by: Ratheous
		//      bugfixed by: Kevin van Zonneveld (http://kvz.io)
		//      bugfixed by: Kevin van Zonneveld (http://kvz.io)
		//      bugfixed by: Joris
		// reimplemented by: Brett Zamir (http://brett-zamir.me)
		// reimplemented by: Brett Zamir (http://brett-zamir.me)
		//           note 1: This reflects PHP 5.3/6.0+ behavior
		//           note 1: Please be aware that this function
		//           note 1: expects to encode into UTF-8 encoded strings, as found on
		//           note 1: pages served as UTF-8
		//        example 1: urlencode('Kevin van Zonneveld!')
		//        returns 1: 'Kevin+van+Zonneveld%21'
		//        example 2: urlencode('http://kvz.io/')
		//        returns 2: 'http%3A%2F%2Fkvz.io%2F'
		//        example 3: urlencode('http://www.google.nl/search?q=Locutus&ie=utf-8')
		//        returns 3: 'http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3DLocutus%26ie%3Dutf-8'

		str = (str + '');

		// Tilde should be allowed unescaped in future versions of PHP (as reflected below),
		// but if you want to reflect current
		// PHP behavior, you would need to add ".replace(/~/g, '%7E');" to the following.
		return encodeURIComponent(str)
			.replace(/!/g, '%21')
			.replace(/'/g, '%27')
			.replace(/\(/g, '%28')
			.replace(/\)/g, '%29')
			.replace(/\*/g, '%2A')
			.replace(/%20/g, '+')
	};
	$cf.helpers.urldecode = function(str){
		//       discuss at: http://locutus.io/php/urldecode/
		//      original by: Philip Peterson
		//      improved by: Kevin van Zonneveld (http://kvz.io)
		//      improved by: Kevin van Zonneveld (http://kvz.io)
		//      improved by: Brett Zamir (http://brett-zamir.me)
		//      improved by: Lars Fischer
		//      improved by: Orlando
		//      improved by: Brett Zamir (http://brett-zamir.me)
		//      improved by: Brett Zamir (http://brett-zamir.me)
		//         input by: AJ
		//         input by: travc
		//         input by: Brett Zamir (http://brett-zamir.me)
		//         input by: Ratheous
		//         input by: e-mike
		//         input by: lovio
		//      bugfixed by: Kevin van Zonneveld (http://kvz.io)
		//      bugfixed by: Rob
		// reimplemented by: Brett Zamir (http://brett-zamir.me)
		//           note 1: info on what encoding functions to use from:
		//           note 1: http://xkr.us/articles/javascript/encode-compare/
		//           note 1: Please be aware that this function expects to decode
		//           note 1: from UTF-8 encoded strings, as found on
		//           note 1: pages served as UTF-8
		//        example 1: urldecode('Kevin+van+Zonneveld%21')
		//        returns 1: 'Kevin van Zonneveld!'
		//        example 2: urldecode('http%3A%2F%2Fkvz.io%2F')
		//        returns 2: 'http://kvz.io/'
		//        example 3: urldecode('http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3DLocutus%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a')
		//        returns 3: 'http://www.google.nl/search?q=Locutus&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a'
		//        example 4: urldecode('%E5%A5%BD%3_4')
		//        returns 4: '\u597d%3_4'

		return decodeURIComponent((str + '')
			.replace(/%(?![\da-f]{2})/gi, function(){
				// PHP tolerates poorly formed escape sequences
				return '%25'
			})
			.replace(/\+/g, '%20'))
	};
	$cf.helpers.equals = function(obj1, obj2) {
		function _equals(obj1, obj2) {
			var clone = $.extend(true, {}, obj1),
				cloneStr = JSON.stringify(clone);
			return cloneStr === JSON.stringify($.extend(true, clone, obj2));
		}

		return _equals(obj1, obj2) && _equals(obj2, obj1);
	};
	$cf.helpers.diff = function (obj1, obj2) {
		// Make sure an object to compare is provided
		if (!obj2 || Object.prototype.toString.call(obj2) !== '[object Object]') {
			return obj1;
		}

		var diffs = {};
		var key;

		/**
		 * Check if two arrays are equal
		 * @param  {Array}   arr1 The first array
		 * @param  {Array}   arr2 The second array
		 * @return {Boolean}      If true, both arrays are equal
		 */
		var arraysMatch = function (arr1, arr2) {

			// Check if the arrays are the same length
			if (arr1.length !== arr2.length) return false;

			// Check if all items exist and are in the same order
			for (var i = 0; i < arr1.length; i++) {
				if (arr1[i] !== arr2[i]) return false;
			}

			// Otherwise, return true
			return true;

		};

		/**
		 * Compare two items and push non-matches to object
		 * @param  {*}      item1 The first item
		 * @param  {*}      item2 The second item
		 * @param  {String} key   The key in our object
		 */
		var compare = function (item1, item2, key) {

			// Get the object type
			var type1 = Object.prototype.toString.call(item1);
			var type2 = Object.prototype.toString.call(item2);

			// If type2 is undefined it has been removed
			if (type2 === '[object Undefined]') {
				diffs[key] = null;
				return;
			}

			// If items are different types
			if (type1 !== type2) {
				diffs[key] = item2;
				return;
			}

			// If an object, compare recursively
			if (type1 === '[object Object]') {
				var objDiff = diff(item1, item2);
				if (Object.keys(objDiff).length > 1) {
					diffs[key] = objDiff;
				}
				return;
			}

			// If an array, compare
			if (type1 === '[object Array]') {
				if (!arraysMatch(item1, item2)) {
					diffs[key] = item2;
				}
				return;
			}

			// Else if it's a function, convert to a string and compare
			// Otherwise, just compare
			if (type1 === '[object Function]') {
				if (item1.toString() !== item2.toString()) {
					diffs[key] = item2;
				}
			} else {
				if (item1 !== item2 ) {
					diffs[key] = item2;
				}
			}

		};

		// Loop through the first object
		for (key in obj1) {
			if (obj1.hasOwnProperty(key)) {
				compare(obj1[key], obj2[key], key);
			}
		}

		// Loop through the second object and find missing items
		for (key in obj2) {
			if (obj2.hasOwnProperty(key)) {
				if (!obj1[key] && obj1[key] !== obj2[key] ) {
					diffs[key] = obj2[key];
				}
			}
		}

		return diffs;

	};

	/**
	 * Copying value to clipboard
	 * @param {string} value
	 */
	$cf.copyToClipboard = function (value) {
		var el = document.createElement('textarea');
		el.value = value;
		el.setAttribute('readonly', '');
		el.style.position = 'absolute';
		el.style.left = '-9999px';
		document.body.appendChild(el);
		var selected = document.getSelection().rangeCount > 0 ? document.getSelection().getRangeAt(0) : false;
		el.select();
		document.execCommand('copy');
		document.body.removeChild(el);
		if (selected) {
			document.getSelection().removeAllRanges();
			document.getSelection().addRange(selected);
		}
	};

	$cf.countDropdownPlace = function($dropdown, $button, offset){
		var offset = offset || 0,
			dropdownW = $dropdown.width(),
			dropdownH = $dropdown.height(),
			buttonW = $button.width(),
			buttonOffset = $button[0].getBoundingClientRect(),
			align = $dropdown.data('align') || '',
			valign;
		if (align === ''){
			if (buttonOffset.left + buttonW / 2 + dropdownW / 2 > $cf.$window.width()){
				align = 'right';
			}
			if (buttonOffset.left < (dropdownW / 2 - buttonW / 2)){
				align = 'left';
			}
		}
		if (buttonOffset.top > (dropdownH + offset) && (buttonOffset.bottom + offset + dropdownH) > $cf.$window.height()){
			valign = 'up';
		} else {
			valign = 'down';
		}
		return valign + ((align === 'center') ? '' : align);
	};

	/**
	 * Class mutator, allowing bind, unbind, and trigger class instance events
	 * @type {{}}
	 */
	$cf.mixins.Events = {
		/**
		 * Attach a handler to an event for the class instance
		 * @param {String} eventType A string containing event type, such as 'beforeShow' or 'change'
		 * @param {Function} handler A function to execute each time the event is triggered
		 */
		bind: function(eventType, handler){
			if (this.$$events === undefined) this.$$events = {};
			if (this.$$events[eventType] === undefined) this.$$events[eventType] = [];
			this.$$events[eventType].push(handler);
			return this;
		},
		/**
		 * Remove a previously-attached event handler from the class instance
		 * @param {String} eventType A string containing event type, such as 'beforeShow' or 'change'
		 * @param {Function} [handler] The function that is to be no longer executed.
		 * @chainable
		 */
		unbind: function(eventType, handler){
			if (this.$$events === undefined || this.$$events[eventType] === undefined) return this;
			if (handler !== undefined){
				var handlerPos = $.inArray(handler, this.$$events[eventType]);
				if (handlerPos != -1){
					this.$$events[eventType].splice(handlerPos, 1);
				}
			} else {
				this.$$events[eventType] = [];
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
			if (this.$$events === undefined || this.$$events[eventType] === undefined || this.$$events[eventType].length == 0) return this;
			for (var index = 0; index < this.$$events[eventType].length; index++){
				this.$$events[eventType][index].apply(this, extraParameters);
			}
			return this;
		}
	};
	/**
	 * Load module
	 * @param {string} file
	 * @param {function} callback
	 */
	$cf.require = function(file, callback) {
		if( ! file) return;
		var ext = file.match(/\.([js|css]{2,3})(?:[\?#]|$)/i);
			ext = ext && ext[1];
		if (ext === null) return;
		var $el = $(ext === 'js' ? '<script>' : '<link>'),
			$elms = $($el[0].tagName.toLowerCase());
		if(ext === 'css') {
			if ($elms.filter('[href^="'+file+'"]').length) return;
			$el.attr({type: 'text/css', rel: 'stylesheet', href: file});
			$('head').append($el);
		} else {
			if ($elms.filter('[src^="'+file+'"]').length) return;
			$el.attr({type: 'text/javascript', src: file});
			$('body').append($el);
		}
		$el.one('load', callback);
	};
}(jQuery);

/**
 * Events
 */
!function($){

	var disabledActions = [],
		superProps = {},
		config = {
			trackChargeEnabled: false
		};

	$cf.addPropsToAllTrackedEvents = function(props){
		$.extend(superProps, props);
	};

	$cf.setTrackingConfig = function(cfg){
		$.extend(config, cfg);
	};

	$cf.trackEvent = function(action, props, callback, once){
		// Temporary debug wrapper
		if (disabledActions.indexOf(action) !== -1){
			// Tracking disabled
			return (callback instanceof Function) ? callback() : null;
		}
		// Providing fault tolerance
		var callbackWrapper = function(){
			if (callback instanceof Function) callback();
			callback = undefined;
		};
		if (callback instanceof Function) setTimeout(callbackWrapper, 500);
		$.extend(props, superProps);
		if (action === 'Page View'){
			if (window.ga instanceof Function) ga('send', 'pageview');
			if (window.fbq instanceof Function) fbq('track', 'PageView');
		}
		if (action === 'Payment Complete' && props.$amount){
			// Facebook
			if (window.fbq instanceof Function) fbq('track', 'Purchase', {
				value: props['Original Amount'],
				currency: props['Original Currency'],
				content_ids: props['Plan']
			});
		}
		if (action === 'Sign Up'){
			if (window.fbq instanceof Function) fbq('track', 'CompleteRegistration');
		}
		if (action === 'Checkout Start'){
			if (window.fbq instanceof Function) fbq('track', 'InitiateCheckout');
		}
		if (once) disabledActions.push(action);
	};

	$cf.trackLinks = function($links, action, props){
		$links = ($links instanceof jQuery) ? $links : $($links);
		$links.each(function(_, link){
			var $link = $(link);
			$link.on('click', function(e){
				// Links, opening in new window won't interrupt event tracking
				if ($link.attr('target') === '_blank') return $cf.trackEvent(action, props);
				e.preventDefault();
				$cf.trackEvent(action, props, function(){
					window.location = $link.attr('href');
				});
			});
		});
	};

}(jQuery);

/**
 * Accordion
 */
!function($){
	"use strict";
	var CFAccordion = window.CFAccordion = function(container){
		this.$container = $(container);
		this.$titles = this.$container.find('.b-accordion-title');
		this.$content = this.$container.find('.b-accordion-content');
		this.$container.find('.b-accordion-title').on('click', function(e){
			var $el = $(e.currentTarget),
				$content = $el.next();
			if (!$el.hasClass('is-active')){
				this.$titles.removeClass('is-active');
				this.$content.slideUp();
				$el.addClass('is-active');
				$content.slideDown();
			}
		}.bind(this));
	};
}(jQuery);

/**
 * Alert
 */
!function($){
	"use strict";
	var CFAlert = window.CFAlert = function(container){
		this.$container = $(container);
		this.$container.find('.g-alert-closer').on('click', this.hide.bind(this));
	};
	$.extend(CFAlert.prototype, {
		show: function() {
			this.$container.slideDown();
		},
		hide: function() {
			this.$container.slideUp();
		}
	});
}(jQuery);

/**
 * Modal Popup
 */
!function($){
	"use strict";
	var CFPopup = window.CFPopup = function(container){
		this.$container = $(container);
		this._events = {
			resize: this.resize.bind(this),
			keypress: function(e){
				if (e.keyCode == 27) this.hide();
			}.bind(this)
		};
		this.isFixed = !$cf.isMobile;
		this.$wrap = this.$container.find('.b-popup-wrap:first')
			.cfMod('pos', this.isFixed ? 'fixed' : 'absolute')
			.on('click', function(e){
				if (!$.contains(this.$box[0], e.target) && !$(e.target).hasClass('disable_wrapper_click')) this.hide();
			}.bind(this));
		this.$box = this.$container.find('.b-popup-box:first');
		this.$overlay = this.$container.find('.b-popup-overlay:first')
			.cfMod('pos', this.isFixed ? 'fixed' : 'absolute');
		this.$box.on('click', '.action_hidepopup', this.hide.bind(this));
		this.timer = null;
	};
	$.extend(CFPopup.prototype, $cf.mixins.Events, {
		_hasScrollbar: function(){
			return document.documentElement.scrollHeight > document.documentElement.clientHeight;
		},
		_getScrollbarSize: function(){
			if ($cf.scrollbarSize === undefined){
				var scrollDiv = document.createElement('div');
				scrollDiv.style.cssText = 'width: 99px; height: 99px; overflow: scroll; position: absolute; top: -9999px;';
				document.body.appendChild(scrollDiv);
				$cf.scrollbarSize = scrollDiv.offsetWidth - scrollDiv.clientWidth;
				document.body.removeChild(scrollDiv);
			}
			return $cf.scrollbarSize;
		},
		show: function(){
			clearTimeout(this.timer);
			this.$overlay.appendTo($cf.$body).show();
			this.$wrap.appendTo($cf.$body).show();
			// Load iframe
			this.$wrap.find('iframe[data-src]').each(function(){
				var url = $(this).data('src');
				var owner = $('.cof-container').data('owner') || $('.b-builder').data('owner') || $('.b-main').data('owner');
				if (owner !== 0)
					url = url + '?owner_id=' + owner;
				$(this).attr('src', url);
			});
			this.resize();
			if (this.isFixed){
				$cf.$html.addClass('overlay_fixed');
				// Storing the value for the whole popup visibility session
				this.windowHasScrollbar = this._hasScrollbar();
				if (this.windowHasScrollbar && this._getScrollbarSize()) $cf.$html.css('margin-right', this._getScrollbarSize());
			} else {
				this.$overlay.css({
					height: $cf.$document.height()
				});
				this.$wrap.css('top', $cf.$window.scrollTop());
			}
			$cf.$body.on('keypress', this._events.keypress);
			this.timer = setTimeout(this.afterShow.bind(this), 25);
			this.trigger('show', []);
		},
		afterShow: function(){
			clearTimeout(this.timer);
			this.$overlay.addClass('active');
			this.$box.addClass('active');
			$cf.$window.trigger('resize');
			$cf.$window.on('resize', this._events.resize);
		},
		hide: function(){
			clearTimeout(this.timer);
			$cf.$window.off('resize', this._events.resize);
			$cf.$body.off('keypress', this._events.keypress);
			this.$overlay.removeClass('active');
			this.$box.removeClass('active');
			// Closing it anyway
			this.timer = setTimeout(this.afterHide.bind(this), 500);
			this.trigger('hide', []);
		},
		afterHide: function(){
			clearTimeout(this.timer);
			// TODO: bug with iframe here. By reason of re-appending the iframe content will be reload. For example its effect on the youtube iframe
			// and little fix for elFinder
			this.$wrap.find('iframe[data-src]').attr('src', '');
			this.$overlay.appendTo(this.$container).hide();
			this.$wrap.appendTo(this.$container).hide();
			if (this.isFixed){
				$cf.$html.removeClass('overlay_fixed');
				if (this.windowHasScrollbar) $cf.$html.css('margin-right', '');
			}
		},
		resize: function(){
			var animation = this.$box.cfMod('animation'),
				padding = parseInt(this.$box.css('padding-top')),
				winHeight = $cf.$window.height(),
				popupHeight = this.$box.height();
			if (!this.isFixed) this.$overlay.css('height', $cf.$document.height());
			this.$box.css('top', Math.max(0, (winHeight - popupHeight) / 2 - padding));
		}
	});
}(jQuery);

/**
 * Filters
 */
!function($){
	"use strict";
	var CFFilters = window.CFFilters = function(container, callback){
		this.$container = $(container);
		if (!this.$container.length) return;
		this.$window = $(window);
		this.$triggers = {};
		this.$titles = {};
		this.titles = {};
		this.value = {};
		this.defaults = {};
		this._events = {
			togglePopup: function(e){
				// Don't toggle when clicked on inner items
				if ($(e.target).closest('.b-textfilter-popup-h').length) return;
				var $trigger = $(e.target).closest('.b-textfilter-trigger'),
					type = $trigger.cfMod('type');
				this.togglePopup(type);
			}.bind(this)
		};
		this.options = {};
		this.$container.find('.b-textfilter-trigger').each(function(_, trigger){
			var $trigger = $(trigger),
				name = $trigger.cfMod('type'),
				$popup = $trigger.find('.b-textfilter-popup');
			this.$triggers[name] = $trigger;
			this.titles[name] = typeof trigger.onclick === "function" ? trigger.onclick() || {} : {};
			this.value[name] = $popup.data('value');
			this.defaults[name] = $popup.data('default');
			this.$titles[name] = $trigger.on('click', this._events.togglePopup);
			this.options[name] = {};
			$trigger.find('.b-textfilter-popup-item').each(function(_, item){
				this.options[name][item.getAttribute('data-value')] = item.innerHTML;
			}.bind(this));
		}.bind(this));
		// Change event
		this.$container.find('.b-textfilter-popup-item').on('click', function(e){
			clearTimeout(this.timerId);
			var $item = $(e.target).toggleClass('is-active'),
				$trigger = $item.closest('.b-textfilter-trigger'),
				name = $trigger.cfMod('type');
			this.value[name] = '';
			$trigger.find('.b-textfilter-popup-item').each(function(_, item){
				if (item.className.indexOf(' is-active') === -1) return;
				this.value[name] += (this.value[name].length ? '|' : '') + item.getAttribute('data-value');
			}.bind(this));
			// Changing title
			this.$titles[name].find('span').html(this.getTitle(name));
			// Changing current url
			var query = location.search;
			$.each(this.value, function(k, v){
				if (v === '' || v === null)
					v = this.defaults[k];
				query = setParameterByName(k, $cf.helpers.urlencode(v), query);
			}.bind(this));
			window.history.replaceState(null, null, location.pathname + query);
			// Making the callback
			this.timerId = setTimeout(function() {
				callback(this.value, name);
				}.bind(this), 1000);
		}.bind(this));
		this.activePopup = null;
	};
	CFFilters.prototype = {
		togglePopup: function(name){
			var active = this.activePopup;
			if (name === active || active !== null) this.hidePopup();
			if (name !== active) this.showPopup(name);
		},
		hidePopup: function(){
			this.$triggers[this.activePopup].removeClass('is-active');
			this.activePopup = null;
			this.$window.off('mouseup touchstart mousewheel DOMMouseScroll touchstart', this._events.hidePopup);
		},
		showPopup: function(name){
			this.$triggers[name].addClass('is-active');
			this.activePopup = name;
			this._events.hidePopup = function(e){
				if (this.$triggers[name].has(e.target).length !== 0) return;
				e.stopPropagation();
				e.preventDefault();
				this.hidePopup();
			}.bind(this);
			this.$window.on('mouseup touchstart mousewheel DOMMouseScroll touchstart', this._events.hidePopup);
		},

		/**
		 * Analog for php ->get_title function of filters
		 * @param name string
		 * @return string
		 */
		getTitle: function(name){
			var value = this.value[name] || this.defaults[name],
				title = this.titles[name].default;
			if (value && value !== this.defaults[name]){
				if (this.titles[name].special[value] !== undefined){
					title = this.titles[name].special[value];
				}
				else if (value.indexOf('|') !== -1){
					// Plural form
					if (this.titles[name].plural){
						title = this.titles[name].plural.replace('%d', value.split('|').length);
					}
				}
				else {
					// Singular form
					if (this.titles[name].singular){
						var valueTitle = this.options[name][value] || value;
						title = this.titles[name].singular.replace('%s', valueTitle);
					}
				}
			}
			return title;
		}
	};
}(jQuery);

!function($){
	"use strict";
	var Assignee = window.Assignee = function(container, callback){
		this.$container = $(container);
		if (!this.$container.length) return;
		this.$window = $(window);
		this.container = container;
		this.callback = callback;
		this._events = {
			togglePopup: function(e){
				if ($(e.target).hasClass('b-assignee-popup-item')) return;
				var $popup = $(e.target).parent().find('.b-assignee-popup');
				this.togglePopup($popup);
			}.bind(this),
			popupItemClick: function(e){
				var $popupItem = $(e.target),
					$title = $popupItem.closest('.b-assignee-trigger').find('span'),
					$parent = $popupItem.parent(),
					assignedId = $popupItem.data('value'),
					$activeItem = $parent.find('.is-active');

				if ($activeItem.length > 0) {
					$activeItem.removeClass('is-active');
				}
				$popupItem.addClass('is-active');
				$title.html($popupItem.html());
				callback(assignedId, $parent, this)
			}.bind(this)
		};

		this.$container.find('.b-assignee-trigger').each(function(_, trigger){
			var $trigger = $(trigger);
			$trigger.on('click', this._events.togglePopup);
		}.bind(this));

		this.$container.find('.b-assignee-popup-item').each(function(_, popupItem){
			var $popupItem = $(popupItem);
			$popupItem.on('click', this._events.popupItemClick);
		}.bind(this));
	};
	Assignee.prototype = {
		togglePopup: function(target){
			(target.hasClass('is-active')) ? this.hidePopup(target) : this.showPopup(target);
		},
		hidePopup: function(target){
			target.hide();
			target.removeClass('is-active');
			this.$window.off('mouseup touchstart mousewheel DOMMouseScroll touchstart', this._events.hidePopup);
		},
		showPopup: function(target){
			target.addClass('is-active');
			target.show();
			this._events.hidePopup = function(e){
				if (target.parent().has(e.target).length !== 0) return;
				e.stopPropagation();
				e.preventDefault();
				this.hidePopup(target);
			}.bind(this);
			this.$window.on('mouseup touchstart mousewheel DOMMouseScroll touchstart', this._events.hidePopup);
		},
	}
}(jQuery);

jQuery(function($){
	/**
	 * Title editor
	 * @param container mixed
	 * @constructor
	 */
	$cf.TitleEditor = function(container){

		this.$container = $(container);
		this.$container_class = this.$container.attr('class');
		this.$text = this.$container.find('.'+this.$container_class+'-text');
		this.$form = this.$container.find('.'+this.$container_class+'-input');
		this.$error = this.$container.find('.'+this.$container_class+'-error');
		this.$errorH = this.$error.find('.'+this.$container_class+'-error-h');

		this.fixWidth = function () {
			this.$input.css('width', this.$text.outerWidth());
		};

		this._events = {
			submit: function(e){
				e.stopPropagation();
				e.preventDefault();
				var oldValue = this.$input.data('oldvalue') || '',
					newValue = this.$input.val().trim();
				if (oldValue == newValue) return;
				this.$input
					.data('oldvalue', newValue)
					.off('blur', this._events.submit)
					.trigger('blur');
				this.submit();
			}.bind(this)
		};
		this.$input = this.$form.find('input[type="text"]')
			.on('focus', function () {
				this.$container.addClass('is-active');
				this.fixWidth();
				if ( ! $cf.isSafari) {
					this.$input[0].select();
				}
			}.bind(this))
			.on('blur', function () {
				if ( ! $cf.isSafari && this.$input[0].setSelectionRange) {
					this.$input[0].setSelectionRange(0, 0);
				}
				this.fixWidth();
				this.$container.removeClass('is-active');
			}.bind(this))
			.on('change keyup paste', function(e){
				this.$input
					.off('blur', this._events.submit)
					.one('blur', this._events.submit);
				if (e.type === 'keyup') {
					// Esc key
					if (e.which === 27) {
						this.$input
							.val(this.$input.data('oldvalue'))
							.trigger('blur');
					}
					else return;
				}
				this.$text.html(this.$input.val().replace(/ /g, '&nbsp;'));
				this.fixWidth();
			}.bind(this));
		this.$text.html(this.$input.val().replace(/ /g, '&nbsp;'));
		this.fixWidth();
		this.$form.on('submit', this._events.submit);

		this.showError = function(message){
			// this.$container.addClass('check_wrong');
			this.$errorH.html(message);
		};

		this.$container.addClass('is-editable');
	};
});


jQuery(function($){
	// Fixed titlebar

	var $titlebar = $('.b-titlebar'),
		$titlebarFixed = $titlebar.find('.b-titlebar-fixed');
	if ($titlebarFixed.length){
		var _titlebarOffset = $titlebarFixed.offset().top + ($titlebarFixed.data('offset') ? $titlebarFixed.data('offset') : 0);
		$(window).on('scroll', function(){
			$titlebarFixed.closest('.b-titlebar').toggleClass('is-fixed', (window.pageYOffset > _titlebarOffset));
		});
	}

	// :hover  menu fix for mobile phones
	$('.b-menu-item.has_dropdown, .b-switcher-h').on('click touch', function(e){
		var $this = $(this);
		if (!$this.hasClass('is_active') && $cf.isMobile){
			e.preventDefault();
			$this.addClass('is_active');
			setTimeout(function(){
				$('body').one('click touch', function(e){
					$this.removeClass('is_active');
				});
			}, 10);
		}
	});
});

/**
 * CSS-analog of jQuery slideDown/slideUp/fadeIn/fadeOut functions (for better rendering)
 */
!function($){
	/**
	 * Remove the passed inline CSS attributes.
	 *
	 * Usage: $elm.resetInlineCSS('height', 'width');
	 */
	$.fn.resetInlineCSS = function(){
		for (var index = 0; index < arguments.length; index++){
			this.css(arguments[index], '');
		}
		return this;
	};

	$.fn.clearPreviousTransitions = function(){
		// Stopping previous events, if there were any
		var prevTimers = (this.data('animation-timers') || '').split(',');
		if (prevTimers.length >= 2){
			this.resetInlineCSS('transition', '-webkit-transition');
			prevTimers.map(clearTimeout);
			this.removeData('animation-timers');
		}
		return this;
	};
	/**
	 *
	 * @param {Object} css key-value pairs of animated css
	 * @param {Number} duration in milliseconds
	 * @param {Function} onFinish
	 * @param {String} easing CSS easing name
	 * @param {Number} delay in milliseconds
	 */
	$.fn.performCSSTransition = function(css, duration, onFinish, easing, delay){
		duration = duration || 250;
		delay = delay || 25;
		easing = easing || 'ease-in-out';
		var $this = this,
			transition = [];

		this.clearPreviousTransitions();

		for (var attr in css){
			if (!css.hasOwnProperty(attr)) continue;
			transition.push(attr + ' ' + (duration / 1000) + 's ' + easing);
		}
		transition = transition.join(', ');
		$this.css({
			transition: transition,
			'-webkit-transition': transition
		});

		// Starting the transition with a slight delay for the proper application of CSS transition properties
		var timer1 = setTimeout(function(){
			$this.css(css);
		}, delay);

		var timer2 = setTimeout(function(){
			$this.resetInlineCSS('transition', '-webkit-transition');
			if (typeof onFinish == 'function') onFinish();
		}, duration + delay);

		this.data('animation-timers', timer1 + ',' + timer2);
	};
	// Height animations
	$.fn.slideDownCSS = function(duration, onFinish, easing, delay){
		if (this.length === 0) return;
		var $this = this;
		this.clearPreviousTransitions();
		// Grabbing paddings
		this.resetInlineCSS('padding-top', 'padding-bottom');
		var timer1 = setTimeout(function(){
			var paddingTop = parseInt($this.css('padding-top')),
				paddingBottom = parseInt($this.css('padding-bottom'));
			// Grabbing the "auto" height in px
			$this.css({
				visibility: 'hidden',
				position: 'absolute',
				height: 'auto',
				'padding-top': 0,
				'padding-bottom': 0,
				display: 'block'
			});
			var height = $this.height();
			$this.css({
				overflow: 'hidden',
				height: '0px',
				visibility: '',
				position: '',
				opacity: 0
			});
			$this.performCSSTransition({
				height: height + paddingTop + paddingBottom,
				opacity: 1,
				'padding-top': paddingTop,
				'padding-bottom': paddingBottom
			}, duration, function(){
				$this.resetInlineCSS('overflow').css('height', 'auto');
				if (typeof onFinish == 'function') onFinish();
			}, easing, delay);
		}, 25);
		this.data('animation-timers', timer1 + ',null');
	};
	$.fn.slideUpCSS = function(duration, onFinish, easing, delay){
		if (this.length === 0) return;
		this.clearPreviousTransitions();
		this.css({
			height: this.outerHeight(),
			overflow: 'hidden',
			'padding-top': this.css('padding-top'),
			'padding-bottom': this.css('padding-bottom'),
			opacity: 1
		});
		var $this = this;
		this.performCSSTransition({
			height: 0,
			'padding-top': 0,
			'padding-bottom': 0,
			opacity: 0
		}, duration, function(){
			$this.resetInlineCSS('overflow', 'padding-top', 'padding-bottom', 'opacity').css({
				display: 'none'
			});
			if (typeof onFinish == 'function') onFinish();
		}, easing, delay);
	};
}(jQuery);

/**
 * Get width text from input
 * @return {number}
 */
var getInputValueWidth = (function(){
	function copyNodeStyle(sourceNode, targetNode) {
		var computedStyle = window.getComputedStyle(sourceNode);
		Array.from(computedStyle).forEach(key => targetNode.style.setProperty(key, computedStyle.getPropertyValue(key), computedStyle.getPropertyPriority(key)))
	}
	function getWidth( input ) {
		var value = input.value || input.placeholder || ' ';
		var span = document.createElement('span');
		copyNodeStyle(input, span);
		span.style.width = 'auto';
		span.style.position = 'absolute';
		span.style.left = '-9999px';
		span.style.top = '-9999px';
		span.style.whiteSpace = 'pre';
		span.textContent = value;
		document.body.appendChild(span);
		return span.offsetWidth;
	}
	return function() {
		return getWidth(this);
	}
})();

/**
 * g-form accordion
 */
(function($){
	$('.g-form-field-accordion-title').on('click', function(e){
		$(e.target).parent().toggleClass('is-active');
	});
})(jQuery);

// Helpers for loading resource
function loadExternalResource(url, type){
	return new Promise(function(resolve, reject){
		var tag;
		if (!type){
			var match = url.match(/\.([^.]+)$/);
			if (match)
				type = match[1];
		}
		if (!type)
			type = "js"; // default to js

		switch (type) {
			case 'css':
				tag = document.createElement("link");
				tag.type = 'text/css';
				tag.rel = 'stylesheet';
				tag.href = url;
				break;
			case 'js':
				tag = document.createElement("script");
				tag.type = "text/javascript";
				tag.src = url;
				tag.defer = "defer";
				break;
			case 'font':
				tag = document.createElement("link");
				//tag.type = 'text/css';
				tag.rel = 'stylesheet';
				tag.href = url;
				break;
		}

		if (tag){
			tag.onload = function(){
				resolve(url, tag);
			};
			tag.onerror = function(){
				reject(url);
			};
			document.getElementsByTagName("head")[0].appendChild(tag);
		}
	});
}

function loadMultipleExternalResources(itemsToLoad){
	var promises = itemsToLoad.map(function(url){
		return loadExternalResource(url);
	});
	return Promise.all(promises);
}

// Searches the array for a given value and returns the corresponding key if successful
function arraySearch(needle, haystack, strict){
	strict = !!strict;

	for (var key in haystack){
		if ((strict && haystack[key] === needle) || (!strict && haystack[key] == needle)){
			return key;
		}
	}

	return false;
}

/**
 * Convert a multi-dimensional object|array into a single-dimensional array.
 * @param obj array|object
 * @return array
 */
function arrayFlatten(obj){
	var arr = (obj instanceof Array) ? obj : Object.values(obj);
	return arr.reduce(function(result, value){
		return result.concat((typeof value === 'object') ? arrayFlatten(value) : value);
	}, []);
}

/**
 * Set a value on array by path
 * @param obj Object
 * @param path string|Array
 * @param value mixed|undefined
 */
function arraySetPath(obj, path, value){
	var keys = (path instanceof Array) ? path : path.split('.');
	// Providing the path
	for (var i = 0; i < keys.length - 1; i++){
		var key = keys[i];
		if (typeof obj[key] !== 'object' || obj[key] === null) obj[key] = {};
		obj = obj[key];
	}
	// Setting the value
	if (value === undefined){
		delete obj[keys[keys.length - 1]];
	} else {
		obj[keys[keys.length - 1]] = value;
	}
}

/**
 * Gets a value from an array using a dot separated path.
 * @param obj Object
 * @param path string|Array
 * @param [def] mixed Default value
 * @return mixed
 */
function arrayPath(obj, path, def){
	var keys = (path instanceof Array) ? path : path.split('.'),
		value = obj;
	for (var i = 0; i < keys.length; i++){
		var key = keys[i];
		if (value[key] === undefined) return def;
		value = value[key];
	}
	return value;
}

function arrayPathKeys(obj){
	var keys = [];
	for (var key in obj){
		if (typeof obj[key] === "object"){
			var subkeys = arrayPathKeys(obj[key]);
			keys = keys.concat(subkeys.map(function(subkey){
				return key + "." + subkey;
			}));
		} else {
			keys.push(key);
		}
	}
	return keys;
}

// eslint-disable-line camelcase
//  discuss at: http://locutus.io/php/array_combine/
// original by: Kevin van Zonneveld (http://kvz.io)
// improved by: Brett Zamir (http://brett-zamir.me)
//   example 1: array_combine([0,1,2], ['kevin','van','zonneveld'])
//   returns 1: {0: 'kevin', 1: 'van', 2: 'zonneveld'}
function arrayCombine (keys, values) {
	var newArray = {};
	var i;

	// input sanitation
	// Only accept arrays or array-like objects
	// Require arrays to have a count
	if (typeof keys !== 'object') {
		return false
	}
	if (typeof values !== 'object') {
		return false
	}
	if (typeof keys.length !== 'number') {
		return false
	}
	if (typeof values.length !== 'number') {
		return false
	}
	if (!keys.length) {
		return false
	}

	// number of elements does not match
	if (keys.length !== values.length) {
		return false
	}

	for (i = 0; i < keys.length; i++) {
		newArray[keys[i]] = values[i]
	}

	return newArray
}

/**
 * Convert bytes to readable format.
 * @param {Number} bytes
 * @return {String}
 */
function bytesToSize(bytes) {
	var sizes = ['Bytes', 'Kb', 'Mb', 'Gb', 'Tb'];
	if (bytes == 0) return '0 Byte';
	var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
	return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];
};

// Storage driver: Browser HTML5 Storage
!function(window, undefined){
	if (window.localStorage === undefined) return;
	var localStore = {
		/**
		 * Set value by key
		 * @param string key
		 * @param mixed value
		 * @return boolean
		 */
		set: function(key, value){
			if(value === undefined || typeof value === "function") {
				value += '';
			} else {
				try {
					value = JSON.stringify(value);
				} catch(e) {}
			}
			try {
				localStorage[(value !== null && value !== undefined) ? 'setItem' : 'removeItem'](key, value);
				return true;
			} catch (e) {
				return false;
			}
		},
		/**
		 * Get value by key
		 * @param string key
		 * @param mixed defaultValue
		 * @return mixed
		 */
		get: function(key, defaultValue) {
			var value = localStorage.getItem(key);
			if(value === null || value === undefined) {
				return defaultValue || '';
			}
			try {
				return JSON.parse(value);
			} catch(e) {
				return value;
			}
		}
	};
	try {
		// Checking first if localStorage is available
		localStore.set('', 1);
		localStore.set('', null);
		localStore.set('', {});
		window.localStore = localStore;
	} catch (e) {}
}(window);

jQuery(function($){
	// Setting focus to the target element
	$('.i-setfocus input[type="text"], .i-setfocus input[type="password"], .i-setfocus textarea').focus();
});

;(function($) {
	/**
	 * Lightweight javascript plugin to lazy load responsive images.
	 * TODO: Rewrite as web worker for off-stream background work
	 *
	 * @param {String} selector
	 * @param {Object} options
	 */
	var CFLazyLoad = function(selector, options) {
		var defaults = {
			threshold: 0,
			srcAttrName: 'data-src',
			init: $.noop,
			onLoad: $.noop,
			onError: $.noop,
		};

		this.selector = selector;
		this.options = $.extend(defaults, options);
		this.$placeholders = $(this.selector);

		// Registration of nodes
		this.$placeholders.each(this._register.bind(this));

		['init', 'onLoad', 'onError'].map(function(func) {
			if ( ! this.options[func] instanceof Function)
				this.options[func] = $.noop;
		}.bind(this));

		this.options.init.call(this);
	};
	// Export API
	CFLazyLoad.prototype = {
		/**
		 * @param  {jQueryNode} placeholder
		 */
		_register: function(_, placeholder) {
			if (this._isVisible(placeholder)) {
				this._loadImg(placeholder);
			} else {
				placeholder.scrollHandler = this._getScrollHandler(placeholder);
				$(window).on('scroll', placeholder.scrollHandler);
			}
		},
		/**
		 * @param {jQueryNode} placeholder
		 * @return {Function}
		 */
		_getScrollHandler: function(placeholder) {
			var scrollHandler = function(e) {
				if (this._isVisible(placeholder)) {
					this._loadImg(placeholder);
					$(window).off('scroll', placeholder.scrollHandler);
				}
			};
			return scrollHandler.bind(this);
		},
		/**
		 * @param {jQueryNode} placeholder
		 * @return {Boolean}
		 */
		_isVisible: function(placeholder) {
			var windowInnerHeight = (window.innerHeight || placeholder.clientHeight);
			return (placeholder.getBoundingClientRect().top - windowInnerHeight) <= parseInt(this.options.threshold) && $(placeholder).is(':visible');
		},
		/**
		 * @param  {jQueryNode} placeholder
		 * @return {Void}
		 */
		_loadImg: function(placeholder) {
			var img = new Image,
				src = placeholder.getAttribute(this.options.srcAttrName);
			if ( ! src) return;
			img.src = src;
			img.onload = function() {
				this.options.onLoad.call(this, placeholder, img);
				placeholder.removeAttribute(this.options.srcAttrName);
			}.bind(this);
			img.onerror = this.options.onError.call(this, placeholder, img);
		}
	};
	window.CFLazyLoad = CFLazyLoad;
})(jQuery);
