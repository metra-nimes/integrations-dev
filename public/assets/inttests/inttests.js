!function(){
	// Tab key support for textareas
	// https://css-tricks.com/snippets/javascript/support-tabs-in-textareas/
	HTMLTextAreaElement.prototype.getCaretPosition = function(){
		return this.selectionStart;
	};
	HTMLTextAreaElement.prototype.setCaretPosition = function(position){
		this.selectionStart = position;
		this.selectionEnd = position;
		this.focus();
	};
	HTMLTextAreaElement.prototype.hasSelection = function(){
		return (this.selectionStart != this.selectionEnd)
	};
	HTMLTextAreaElement.prototype.getSelectedText = function(){
		return this.value.substring(this.selectionStart, this.selectionEnd);
	};
	HTMLTextAreaElement.prototype.setSelection = function(start, end){
		this.selectionStart = start;
		this.selectionEnd = end;
		this.focus();
	};
	var textareas = document.getElementsByTagName('textarea');
	for (var i = 0; i < textareas.length; i++){
		var textarea = textareas[i];
		!function(textarea){
			textarea.onkeydown = function(event){
				var newCaretPosition;
				if (event.keyCode == 9){ //tab was pressed
					newCaretPosition = textarea.getCaretPosition() + "    ".length;
					textarea.value = textarea.value.substring(0, textarea.getCaretPosition()) + "    " + textarea.value.substring(textarea.getCaretPosition(), textarea.value.length);
					textarea.setCaretPosition(newCaretPosition);
					return false;
				}
				if (event.keyCode == 8){ //backspace
					if (textarea.value.substring(textarea.getCaretPosition() - 4, textarea.getCaretPosition()) == "    "){ //it's a tab space
						newCaretPosition = textarea.getCaretPosition() - 3;
						textarea.value = textarea.value.substring(0, textarea.getCaretPosition() - 3) + textarea.value.substring(textarea.getCaretPosition(), textarea.value.length);
						textarea.setCaretPosition(newCaretPosition);
					}
				}
				if (event.keyCode == 37){ //left arrow
					if (textarea.value.substring(textarea.getCaretPosition() - 4, textarea.getCaretPosition()) == "    "){ //it's a tab space
						newCaretPosition = textarea.getCaretPosition() - 3;
						textarea.setCaretPosition(newCaretPosition);
					}
				}
				if (event.keyCode == 39){ //right arrow
					if (textarea.value.substring(textarea.getCaretPosition() + 4, textarea.getCaretPosition()) == "    "){ //it's a tab space
						newCaretPosition = textarea.getCaretPosition() + 3;
						textarea.setCaretPosition(newCaretPosition);
					}
				}
			}
		}(textarea);
	}
}();
jQuery(function($){

	$.ajaxSetup({
		beforeSend: function(jqXHR, settings) {
			$cf.Inttests.clearLog();
			$cf.Inttests.$response
				.html('<pre>Connecting...</pre>')
		},
		success:  function(data, textStatus, jqXHR) {
			var errorText = 'Error ';
			try {

				if (typeof data !== 'object') {
					data = JSON.parse(data);
				}

				$.map(data.log, function(item){
					var $logItem = $cf.Inttests.appendLog(item.request);
					var $cont = $logItem.find('.inttests-logs-item-content');
					$cont.append('<strong>Request Headers</strong><p>' + JSON.stringify(item.request_headers, null, 4) + '</p>');
					$cont.append('<strong>Request Data</strong><p>' + JSON.stringify(item.request_data, null, 4) + '</p>');
					$cont.append('<strong>Response Code</strong><p>' + item.response_code + '</p>');
					$cont.append('<strong>Response Headers</strong><pre>' + JSON.stringify(item.response_headers, null, 4)+ '</pre>');
					$cont.append('<strong>Response Data</strong><pre></pre>').find('pre:last').text(JSON.stringify(item.response_data, null, 4));
					$cont.append('<strong>Response Body</strong><pre></pre>').find('pre:last').text(item.response_body);
				});

				delete data.log;
				if (data.success) {
					if (data.data && data.data.person) {
						$cf.Inttests.$response.html('<b>Person:</b><pre>' + JSON.stringify(data.data.person, null, 4) + '</pre>').highlight();
					} else {
						$cf.Inttests.$response.html('<div class="inttests-response-success">Success!</div>')
							.highlight();
					}
				} else {
					if (data.errors) {
						if (data.errors.error) {
							errorText += data.errors.error;
						} else {
							errorText += data.errors[Object.keys(data.errors)[0]];
						}
					}
					$cf.Inttests.$response
						.html('<div class="inttests-response-error">' + errorText + '</div>');
				}

				if (data.changed)
				{
					if (data.changed.credentials)
					{
						$cf.Inttests.credentials.setValues(data.changed.credentials);
					}
					if (data.changed.meta)
					{
						$cf.Inttests.$metaTextarea.val(JSON.stringify(data.changed.meta, null, 4)).highlight();
					}
				}
			} catch (e) {
				$cf.Inttests.$response.html('<pre></pre>').find('pre:last').text(data);
			}

		},
		error: function(){
			$cf.Inttests.$response
				.html('<div class="inttests-response-error">Internal error</div>');
		}
	});


	if (window.$cf === undefined) window.$cf = {};
	$cf.Inttests = {
		logTmpl: $('#log-item').html(),

		$sidebar: $('.inttests-sidebar'),
		$credentials: $('.cof-integrations-credentials'),
		$credentialsTextarea: $('.cof-integrations-credentials-textarea'),
		$metaTextarea: $('.driver-controls-request.for_meta textarea'),

		$paramsWrapper: $('.params'),

		$automations: $('.driver-controls-fields.for_automations .driver-controls-fields-h'),
		$automationsSelect: $('.driver-controls-fields.for_automations .cof-integrations-item-content'),

		$response: $('.inttests-response'),
		$log: $('.inttests-logs'),
		$formWrapper: $('.driver-controls-fields.for_form'),
		$form: $('.conv_id_form'),
		$toggleFormFieldDialog: $('.inttests-form-add'),
		$formFieldList: $('.inttests-form-addlist-select > select'),
		$appendFormField: $('.inttests-form-addlist-button'),

		defaultFields: ['email', 'first_name', 'last_name', 'name', 'phone', 'company', 'site'],

		driverSelector: null,
		credentials: null,
		automationSelect: null,
		formControls: null,


		init: function(){
			// toggle log items
			this.$log.on('click', '.inttests-logs-item-title', function () {
				$(this).parent().toggleClass('is-active');
			});

			$('.inttests-accordion > h2').on('click', function () {
				$(this).parent().toggleClass('is-active');
			});

			//Driver selector
			this.driverSelector = new $cof.Field('.cof-form-row[data-name="driver"]');
			this.driverSelector.on('change', function(value){
				if (value === ''){
					// TODO: scroll to 0
					this.$credentials.slideUp();
					this.$paramsWrapper.slideUp();
				}
				else {
					// TODO: scroll to meta
					this.$paramsWrapper.slideUp();
					this.renderCredentials(value);
				}
			}.bind(this));

			// Click on "Connect" button
			this.$credentials.on('submit', function(){
				this.submitCredentials();
			}.bind(this));

			// Click refresh button
			this.$paramsWrapper.on('click', '.cof-form-row-control-refresh',function(){
				this.submitCredentials();
			}.bind(this));

			// Buttons
			this.$form.on('submit', function(e){
				e.preventDefault();
				this.signUp();
			}.bind(this));

			this.$toggleFormFieldDialog.on('click', function () {
				this.$toggleFormFieldDialog.toggleClass('is-active');
			}.bind(this));

			this.$appendFormField.on('click', function () {
				var value = this.$formFieldList.val(),
					title,
					$field;
				if (this.$formFieldList.find('option[value=' + value + ']').prop('disabled') !== false) return;
				if (value === 'hidden_data' || value === 'custom_field') {
					title = window.prompt('Enter Field Name', value);
					value = quoteattr(title);
					if (title !== null) title += ' [meta]';
				} else {
					title = this.$formFieldList.find('option[value=' + value + ']').text();
					this.$formFieldList.find('option[value=' + value + ']').prop('disabled', true);
				}
				if (title === null) return;
				$field = '<div class="conv-form-field for_' + value+ '">' +
					'<label for="form_' + value + '_conv1">' + title + '</label>' +
					'<div class="conv-form-field-input">' +
					'<input type="text" name="' + value + '" id="form_' + value + '_conv1" placeholdder="' + title + '">' +
					'<div class="conv-form-field-message"></div>' +
					'</div>' +
					'<div class="conv-form-field-remove" title="Remove Field"></div>' +
					'</div>';
				this.$form.find('.conv-form-field:last').after($field);
				this.$toggleFormFieldDialog.removeClass('is-active');
			}.bind(this));

			this.$formWrapper
				.on('click', '.conv-form-field-remove', function (e) {
					var $el = $(e.currentTarget).closest('.conv-form-field'),
						name = $el.find('input').attr('name');
					$el.remove();
					this.$formFieldList.find('option[value="' + quoteattr(name) + '"]').prop('disabled', false);
				}.bind(this))
				.on('click', '.for_get',  function(e){
					e.preventDefault();
					this.getSubscriber();
				}.bind(this));

			this.$automations
				.on('click', '.g-btn', function(e){
					e.preventDefault();
					var action  = $(e.currentTarget).cfMod('action');

					if (action)
					{
						this.execAutomation(action);
					}
				}.bind(this));
		},

		clearLog: function () {
			this.$log.html('');
		},


		appendLog: function (title) {
			var $item = $(this.logTmpl);
			$item.find('.inttests-logs-item-title').text(title);
			this.$log.append($item);
			return $item;
		},

		renderCredentials: function(driverName, onComplete){
			var url = '/api/inttests/describe_credentials_fields',
				data = {
					driver_name: driverName
				};

			$.get(url, data)
				.done(function(response){
					try {
						if (typeof response !== 'object')
							response = JSON.parse(response);

						if (response.success){
							this.$credentials
								.html(response.data)
								.highlight();

							var that = this;
							this.credentials = new $cof.Fieldset(this.$credentials);
							$.extend(this.credentials.prototype, {
								getValues: function(){
									var values = {};
									$.each(this.fields, function(name, field){
										if (field[0] !== undefined && typeof field[0].getValue === 'function')
										{
											// init fields if they are not has been init yet
											if (field[0].inited !== false)
											{
												values[name] = field[0].getValue();
											}
										}
									}.bind(this));

									var val = JSON.parse(that.$credentialsTextarea.val() || '{}');
									return $.extend(val, values);
								},
								setValues: function(values){
									$.each(values, function(name, value){
										if (this.fields[name] !== undefined){
											this.fields[name].forEach(function(field){
												if (typeof field.setValue === 'function') field.setValue(value)
											});
										}
									}.bind(this));
									that.$credentialsTextarea.val(JSON.stringify(values));
								}
							});
							if (onComplete instanceof Function)
								onComplete();
							this.$credentials.slideDown();
						} else {

							this.$paramsWrapper.slideUp();
							this.$credentials.slideUp();
						}
					} catch (e) {
						// Error parsing response
						console.error(e);
						if (response.errors === undefined)
							this.$response.html('<pre>' +  response + '</pre>');

						this.$paramsWrapper.slideUp();
						this.$credentials.slideUp();
					}
				}.bind(this))
				.fail(function () {
					this.$credentials.slideUp();
				}.bind(this));
		},

		submitCredentials: function(onComplete){
			var url = '/api/inttests/fetch_meta',
				data = {
					driver_name: this.driverSelector.getValue(),
					credentials: this.credentials.getValues(),
					duplicate: $('#credentials_duplicate').is(':checked') ? 1 : 0
				};

			$.get(url, data)
				.done(function(response){
					var meta = '';
					this.$sidebar.find('.cof-form-row').removeClass('check_wrong')
						.find('.cof-form-row-field > .cof-form-row-state').html('');
					try {
						if (typeof response !== 'object') response = JSON.parse(response);
						if (response.success){

							if (response.data.automations)
							{
								this.$automationsSelect
									.find('.cof-form-row[data-name="automation"]')
									.remove();

								this.$automationsSelect.prepend(response.data.automations);
								//Driver selector
								this.automationSelect = new $cof.Field('.cof-form-row[data-name="automation"]');
								this.automationSelect.on('change', function(value){
									this.renderAutomation(value);
								}.bind(this));
							}

							meta = JSON.stringify(response.data.meta, null, 4);
							this.$paramsWrapper.slideDown();

							if (response.data.credentials)
							{
								this.credentials.setValues(response.data.credentials);
							}

							if (response.data.meta)
							{
								this.$metaTextarea.val(JSON.stringify(response.data.meta, null, 4)).highlight();
							}

							if (onComplete instanceof Function)
								onComplete();
						} else {
							// Error in response
							$.each(response.errors, function(key, message){
								this.$sidebar.find('.cof-form-row[data-name="' + key + '"]').addClass('check_wrong')
									.find('.cof-form-row-field > .cof-form-row-state')
									.html(message.replace(/^\d+\: /, ''));
							}.bind(this));

							this.$paramsWrapper.slideUp();
						}
					} catch (e) {
						// Error parsing response
						console.error(e);
						if (response.errors === undefined)
							this.$response.html('<pre>' +  response + '</pre>');

						this.$paramsWrapper.slideUp();
					}
					this.$metaTextarea.val(meta).highlight();
				}.bind(this));
		},

		renderAutomation: function(automationName){
			var url = '/api/inttests/describe_automation',
				data = {
					driver_name: this.driverSelector.getValue(),
					credentials: this.credentials.getValues(),
					meta: this.$metaTextarea.val(),
					automation_name: automationName
				};

			$.post(url, data)
				.done(function(response){
					try {
						if (typeof response !== 'object')
							response = JSON.parse(response);

						if (response.success){
							this.$automations
								.html(response.data)
								.highlight();

							this.automation = new $cof.Fieldset(this.$automations);
						} else {
							this.$response.html('<pre>' +  response + '</pre>');
						}
					} catch (e) {
						// Error parsing response
						console.error(e);
						if (response.errors === undefined)
							this.$response.html('<pre>' +  response + '</pre>');
					}
				}.bind(this))
				.fail(function () {
					this.$response.html('<pre>Something went wrong!</pre>');
				}.bind(this));
		},

		execAutomation: function(automationName){
			var url = '/api/inttests/automation',
				data = {
					driver_name: this.driverSelector.getValue(),
					credentials: this.credentials.getValues(),
					meta: JSON.parse(this.$metaTextarea.val()),
					automation: automationName,
					automation_params: this.automation.getValues(),
					data: this.$form.serializeArray().reduce(function(obj, item) {
						obj[item.name] = item.value;
						if (this.defaultFields.indexOf(item.name) === -1) {
							obj.meta = obj.meta || {};
							obj.meta[item.name] = item.value;
							delete obj[item.name];
						}
						return obj;
					}.bind(this), {})
				};

			this.$sidebar.find('.conv-form-field').removeClass('conv_error')
				.find('.cof-form-row-state').html('');

			return $.post(url, data)
				.then(function(response){
					try {
						if (typeof response !== 'object')
							response = JSON.parse(response);
						if (!response.success) {
							$.each(response.errors, function(key, message){
								if (message)
								{
									this.$sidebar.find('.conv-form-field.for_' + key).addClass('conv_error')
										.find('.conv-form-field-message')
										.html(message.replace(/^\d+: /, ''));
								}
							}.bind(this));
						}
						return (response.data && response.data.person) ? response.data.person : null;
					} catch (e) {
						// Error parsing response
						console.error(e);
						if (response.errors === undefined)
							this.$response.html('<pre>' +  response + '</pre>');
					}
					return null;
				}.bind(this));
		},

		getSubscriber: function(){
			var url = '/api/inttests/get_subscriber',
				meta = {},
				data = {
					driver_name: this.driverSelector.getValue(),
					credentials: this.credentials.getValues(),
					meta: JSON.parse(this.$metaTextarea.val()),
					data: this.$form.serializeArray().reduce(function(obj, item) {
						obj[item.name] = item.value;
						if (this.defaultFields.indexOf(item.name) === -1) {
							obj.meta = obj.meta || {};
							obj.meta[item.name] = item.value;
							delete obj[item.name];
						}
						return obj;
					}.bind(this), {})
				};
			$.extend(data.meta, meta);
			this.$sidebar.find('.conv-form-field').removeClass('conv_error')
				.find('.cof-form-row-state').html('');

			return $.post(url, data)
				.then(function(response){
					try {
						if (typeof response !== 'object')
							response = JSON.parse(response);
						if (!response.success) {
							$.each(response.errors, function(key, message){
								this.$sidebar.find('.conv-form-field.for_' + key).addClass('conv_error')
									.find('.conv-form-field-message')
									.html(message.replace(/^\d+\: /, ''));
							}.bind(this));
						}
						return (response.data && response.data.person) ? response.data.person : null;
					} catch (e) {
						// Error parsing response
						console.error(e);
						if (response.errors === undefined)
							this.$response.html('<pre>' +  response + '</pre>');
					}
					return null;
				}.bind(this));
		}
	};

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

	$.fn.highlight = function(){
		this.addClass('highlight');
		setTimeout(function(){
			this.removeClass('highlight');
		}.bind(this), 500);
	};

	function quoteattr(s, preserveCR) {
		preserveCR = preserveCR ? '&#13;' : '\n';
		return ('' + s) /* Forces the conversion to string. */
			.replace(/&/g, '&amp;') /* This MUST be the 1st replacement. */
			.replace(/'/g, '&apos;') /* The 4 other predefined entities, required. */
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			/*
			You may add other replacements here for HTML only
			(but it's not necessary).
			Or for XML, only if the named entities are defined in its DTD.
			*/
			.replace(/\r\n/g, preserveCR) /* Must be before the next replacement. */
			.replace(/[\r\n]/g, preserveCR);
		;
	}


	$cf.Inttests.init();
});