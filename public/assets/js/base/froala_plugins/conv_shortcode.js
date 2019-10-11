(function ($) {
	// Add an option for your plugin.
	$.FroalaEditor.DEFAULTS = $.extend($.FroalaEditor.DEFAULTS, {
		convShortcodeConfig: {}
	});

	// Define the plugin.
	// The editor parameter is the current instance.
	$.FroalaEditor.PLUGINS.conv_shortcode = function (editor) {

		var shortcodes,
			config = editor.opts.convShortcodeConfig,
			listSelector = '.conv_shortcodes_list';

		var ShortCodes = function(){
			this.$body = $('body');
			this.listActive = false;

			if ( ! $(listSelector).length)
				this.$body.append('<div class="conv_shortcodes_list" data-replace=""></div>');

			this.$list = $(listSelector);
			setTimeout(function(){
				this._highlightShortCodes();
			}.bind(this), 100);

			this._events = {
				listEventListener: function(e){
					var id = this.$list.attr('data-replace');
					if (editor.$el.find('[data-id="'+id+'"],[data-shortcode-insert]').length) {
						var key = e.keyCode,
							$currentItem,
							$listItems = this.$list.find('ul li'),
							$activeItem = $listItems.filter('.is_active');

						// Close list on ESCAPE
						if (key === 27) {
							this._hideList(editor);
							return;
						}

						if (key !== 40 && key !== 38 && !$(e.target).hasClass('conv_shortcode_item')) return;

						$listItems.removeClass('is_active');

						// Dropdown navigation on keydown & mouse event
						if (key === 40){
							$currentItem = (!$activeItem.length || $activeItem.is(':last-child')) ? $listItems.eq(0) : $activeItem.next();
							this._scrollList($currentItem, this.$list);
						}
						else if (key === 38){
							$currentItem = (!$activeItem.length || $activeItem.is(':first-child')) ? $listItems.last() : $activeItem.prev();
							this._scrollList($currentItem, this.$list);
						} else {
							$currentItem = $(e.target);
						}

						$currentItem.addClass('is_active');
					}
				}.bind(this)
			};
			editor.events.on('keypress input', function(e){
				if (e.type === 'keypress') {
					editor.at_start = e.originalEvent.key;
				}
				else if (e.type === 'input') {
					var selection = editor.selection.get(),
						node = selection.anchorNode,
						parent = node.parentNode,
						insertMarker = (parent.hasAttribute('data-shortcode-insert')),
						tempMarker = (parent.hasAttribute('data-shortcode-temp')),
						valueMarker = (parent.hasAttribute('data-shortcode-value'));

					if (!insertMarker) {
						this.removeInsertMarker(editor);
					}
					if (valueMarker) {
						this._moveElmChars(node);
						this._highlightShortCodes();
					}

					if (editor.at_start === config.at_start && !insertMarker && !valueMarker) {
						this.removeInsertMarker(editor);
						editor.cursor.backspace();
						editor.html.insert('<span data-shortcode-insert>'+config.at_start+$.FroalaEditor.MARKERS+'</span>');
						this.showShortCodesList(editor);
					} else if (insertMarker || tempMarker) {
						var [value] = parent.textContent.split(' ');

						if (value.match(/{([a-z0-9_,-]+)}/)) {
							this._toggleMarker(parent, 'data-shortcode-temp');
							this._insertShortCode(editor, null, value);
						} else {
							if (value.match(/\s/))
								this._toggleMarker(parent, 'data-shortcode-insert');
							this._match(editor, parent, value);
						}
					}
				}
			}.bind(this));

			editor.events.on('click', function(e){
				this.removeInsertMarker(editor);
			}.bind(this));

			$(window).on('mouseup touchstart mousewheel DOMMouseScroll touchstart', function (e) {
				if (this.listActive && $(e.target).closest(listSelector).length === 0) {
					this._hideList(editor);
				}
			}.bind(this));

			/**
			 * Click element in shortCodes list
			 */
			this.$body.on('click', '.conv_shortcode_item', function(e){
				var value = $(e.target).text(),
					replaceId = this.$list.attr('data-replace'),
					replaceSel = (replaceId && replaceId.length) ? '[data-id="'+replaceId+'"]' : '[data-shortcode-insert]';
				if (editor.$el.find(replaceSel).length)
					this._insertShortCode(editor, replaceId, value);
			}.bind(this));

			/**
			 * Click shortCode event in editor
			 */
			this.$body.on('click', '.conv_shortcode_value', function(e){
				var id = $(e.target).attr('data-id');

				// generate id for dynamic inserted shortCodes
				if (!id) {
					id =  this._generateId($(e.target).text());
					$(e.target).attr('data-id', id);
				}

				if (editor.$el.find('[data-id="'+id+'"]').length) {
					this.$list.attr('data-replace', id);
					this.showShortCodesList(editor, id);
				}
			}.bind(this));

			/**
			 * prevent default Froala enter event
			 */
			editor.events.on('keydown', function(e){
				if (e.which === 13 && this.listActive) {
					if (this.listActive) {
						e.preventDefault();
						var value = this.$list.find('ul li').filter('.is_active').text(),
							id = this.$list.attr('data-replace');
						this._insertShortCode(editor, id, value);
						return false;
					} else {
						var sel = editor.selection.get();
						if (sel.anchorNode.parentNode.classList.contains('conv_shortcode_value')){
							e.preventDefault();
							return false;
						}
					}
				}
			}.bind(this), true);

			editor.events.on('keyup', function(e){
				if (e.which === 37 || e.which === 39) {
					this._hideList(editor);
				} else if (e.which === 8) {
					if (this.listActive)
						this._hideList(editor);
					else {
						var sel = editor.selection.get();
						if (sel.anchorNode.parentNode.classList.contains('conv_shortcode_value')){
							if (sel.anchorNode.nodeValue.match(/{(.*)}/))
								this._highlightShortCodes();
							else sel.anchorNode.parentNode.remove();
						}
					}
				}
			}.bind(this));
			editor.events.on('paste.afterCleanup', function(clipboard_html){
				var div = document.createElement('div');
				div.innerHTML = clipboard_html;
				$(div).find('[data-shortcode-value]').each(function(_, el){
					$(el).attr('data-id', this._generateId($(el).text()));
				}.bind(this));

				return $(div).html();
			}.bind(this));

			editor.events.on('froalaEditor.destroy', function(e){
				editor.html.set('test');
			});
		};

		ShortCodes.prototype = {
			/**
			 * Remove insert marker from editor
			 * @param editor
			 * @param remove
			 */
			removeInsertMarker: function(editor, remove) {
				var selection = editor.selection.get(),
					node = selection.anchorNode;

				if (node) {
					var parent = node.parentNode,
						insertMarker = (parent.hasAttribute('data-shortcode-insert'));
				}

				if (!insertMarker || remove) {
					editor.$el.find('[data-shortcode-insert]').each(function(_, item){
						this._toggleMarker(item, 'data-shortcode-insert', 'data-shortcode-temp');
					}.bind(this));
				}
			},
			/**
			 * Show shortCodes list
			 * @param editor
			 * @param id
			 * @param type
			 */
			showShortCodesList: function(editor, id , type) {
				var target = (id) ? '[data-id="'+id+'"]' : '[data-shortcode-insert]',
					$target = editor.$el.find(target),
					offset = (type === 'toolbar') ? {} : $target.offset();
				// Fill list actual shortCodes
				this._loadList();

				// Set list offset
				this.$list.css($.extend(offset, {display: 'block'}));

				this.$list.animate({scrollTop: 0}, 'fast');
				this.$list.find('ul li').each(function(i,item){
					if (i === 0 && ! $(item).hasClass('is_active'))
						$(item).addClass('is_active');
					else if (i > 0)
						$(item).removeClass('is_active');
				});

				// mark that the list is open
				this.listActive = true;

				window.addEventListener('keydown', this._events.listEventListener, false);
				window.addEventListener('mouseover', this._events.listEventListener);
			},
			/**
			 * Insert shortCode
			 * @param editor
			 * @param id
			 * @param shortcode
			 * @private
			 */
			_insertShortCode: function(editor, id, shortcode) {
				var template, newId;
				if (!id) {
					// insert new shortcode
					[id, template] = this._getShortCodeTemplate(shortcode);
					editor.$el.find('[data-shortcode-insert]').replaceWith(template);
				} else {
					newId = this._generateId(shortcode);
					// replace existing
					editor.$el.find('[data-id="'+id+'"]')
						.text(shortcode)
						.removeClass('conv_shortcode_error')
						.attr('data-id', newId);
					id = newId;
				}
				this._highlightShortCodes();
				editor.html.set(editor.html.get());
				this._setCaretAt(editor.$el.find('[data-id="'+id+'"]')[0]);
				this._hideList(editor);
			},
			/**
			 * shortCode template
			 * @param shortcode
			 * @returns {string}
			 * @private
			 */
			_getShortCodeTemplate: function(shortcode) {
				var id = this._generateId(shortcode),
					template = '<span class="conv_shortcode_value fr-deletable" data-id="'+id+'" data-shortcode-value contentEditable="true">'+shortcode+'</span>';
				return [id, template];
			},
			/**
			 * Load shortCodes to list
			 * @param values
			 * @private
			 */
			_loadList: function(values) {
				var html =  '<ul>';
				values = values || config.shortcodes;
				values.forEach(function(item, i){
					var is_active = (i === 0) ? 'is_active' : '';
					//remove ip_address  from shortcodes list
					if (item === 'ip_address')
						return;
					html += '<li class="conv_shortcode_item '+is_active+'"><span>{'+item+'}</span></li>';
				});
				html += '</ul>';
				this.$list.html(html);
			},
			/**
			 * Hide shortCodes list
			 * @param editor
			 */
			_hideList: function(editor) {
				this.$list.css('display', 'none');
				this.$list.attr('data-replace', '');
				this.listActive = false;
				this.removeInsertMarker(editor, true);
				window.removeEventListener('keydown', this._events.listEventListener);
				window.removeEventListener('mouseover', this._events.listEventListener);
			},
			/**
			 * Scroll shortCodes list
			 * @param element
			 * @param container
			 */
			_scrollList: function(element, container){
				var elementRect = element[0].getBoundingClientRect(),
					containerRect = container[0].getBoundingClientRect();

				if (elementRect.bottom > containerRect.bottom) {
					container.animate({
						scrollTop: $(container).scrollTop() - $(container).offset().top + $(element).offset().top
					}, 'fast');
				}
				else if (elementRect.top < containerRect.top) {
					container.animate({
						scrollTop: $(container).scrollTop() - $(container).offset().top + $(element).offset().top
					}, 'fast');
				}
			},
			/**
			 * looks for matches with the shortCodes list
			 * @param editor
			 * @param node
			 * @param value
			 * @private
			 */
			_match: function(editor, node, value) {
				if (! /[a-z0-9_]+/.test(value))
					return;

				var matches = config.shortcodes.filter(function(item){
					return item !== 'ip_address' && item.startsWith(value.replace(/\{/, ''));
				});

				if (!matches.length && this.listActive) {
					this._hideList(editor);
				}
				else if (matches.length) {
					this._toggleMarker(node, 'data-shortcode-temp');
					if (! this.listActive)
						this.showShortCodesList(editor);
					this._loadList(matches);
				}
			},
			/**
			 * Generate unique shortCode id
			 * @param text
			 * @returns {string}
			 * @private
			 */
			_generateId: function(text){
				text = text.replace(/{(.*)}/, "$1");
				var $elms = $('.conv_shortcode_value:contains("'+text+'")');
				index = ($elms.length) ? $elms.length+1 : 1;
				return 'shortcode_'+text+index;
			},
			/**
			 * if put the cursor next to the item with attr contentEditable=true,
			 * froala goes to the shortcode editing node,
			 * to avoid this, move the characters to the next / previous node
			 * @param node
			 * @private
			 */
			_moveElmChars: function(node){
				var firstSubstr, value, lastSubstr, textNode,
					parentValue = node.parentNode.innerText;
				[firstSubstr, value, lastSubstr] = parentValue.split(/{(.*)}/);
				if (typeof value !== 'undefined') {
					if (lastSubstr && lastSubstr.length) {
						textNode = document.createTextNode(lastSubstr);
						node.parentNode.parentNode.insertBefore(textNode, node.parentNode.nextSibling);
						node.parentNode.innerText = '{'+value+'}';
						editor.selection.setAfter(textNode);
						editor.selection.restore();
					} else if (firstSubstr && firstSubstr.length){
						textNode = document.createTextNode(firstSubstr);
						node.parentNode.parentNode.insertBefore(textNode, node.parentNode);
						node.parentNode.innerText = '{'+value+'}';
					}
				}
			},
			/**
			 * toggle node attribute
			 * @param node
			 * @param marker
			 * @param newest
			 * @private
			 */
			_toggleMarker: function(node, marker, newest){
				if (newest)
					$(node).removeAttr(marker)
						.attr(newest, '');
				else {
					if (node.hasAttribute(marker))
						$(node).removeAttr(marker)
							.attr((marker === 'data-shortcode-insert') ? 'data-shortcode-temp' : 'data-shortcode-insert', '');
				}
			},
			/**
			 * Highlight shortCodes
			 * @private
			 */
			_highlightShortCodes: function(){
				var regexp = new RegExp(/{([a-z0-9_,-]+)}/gi);
				editor.$el.find('.conv_shortcode_value').each(function(_, el){
					var value = $(el).text();
					if (regexp.test(value)) {
						value.replace(regexp, function(varPattern, varName){
							var defaultValue;
							[varName, defaultValue] = varName.split(',');
							if (config.shortcodes.indexOf(varName) === -1)
								$(el).addClass('conv_shortcode_error');
							else $(el).removeClass('conv_shortcode_error');
						});
					} else $(el).addClass('conv_shortcode_error')
				})
			},
			/**
			 * Set cursor position after node
			 * @param node
			 * @private
			 */
			_setCaretAt: function(node) {
				if (document.createRange) {
					rng = document.createRange();
					rng.setStartAfter(node);
					rng.collapse(true);
					sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(rng);
				}
			},
		};
		
		/**
		 * The start point plugin.
		 * @private
		 */
		function _init () {
			shortcodes = new ShortCodes();
		}
		
		/**
		 * show shortCodes list on toolbar btn click
		 * @param editor
		 */
		function showShortCodesToolbar(editor){
			var $btn = editor.$tb.find('.fr-command[data-cmd="shortcode"]'),
				left = $btn.offset().left + $btn.outerWidth() / 2,
				top = $btn.offset().top + (editor.opts.toolbarBottom ? 10 : $btn.outerHeight() - 10);

			shortcodes.removeInsertMarker(editor);
			editor.html.insert('<span data-shortcode-insert></span>');
			shortcodes.showShortCodesList(editor, null, 'toolbar');
			shortcodes.$list.css({'left':left, 'top': top});
		}
		return {
			_init: _init,
			showShortCodesToolbar: showShortCodesToolbar,
		}
	};
})(jQuery);