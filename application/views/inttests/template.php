<?php
/**
 * @var array $integrations Connected integrations
 * @var array $drivers Available integration drivers
 * @var Model_User $user Relevant user
 * @var array $form HTML css and js for form
 * @var array $form_data form data
 * @var Assets $assets
 * @var string $after
 */
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Integrations Tests</title>
	<link type="text/css" href="https://app.convertful.com/assets/css/normalize.css" rel="stylesheet"/>
	<link type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:400,300,500,700" rel="stylesheet"/>
	<link type="text/css" href="https://app.convertful.com/assets/css/main.css?v=1.40" rel="stylesheet"/>
	<link type="text/css" href="https://app.convertful.com/assets/css/cof.css?v=1.40" rel="stylesheet"/>
	<link type="text/css" href="/assets/inttests/inttests.css" rel="stylesheet"/>
	<script type="text/javascript" src="https://app.convertful.com/assets/vendor/jquery/jquery-3.1.1.min.js"></script>
	<script type="text/javascript" src="https://app.convertful.com/assets/vendor/select2/js/select2.full.min.js"></script>
	<script type="text/javascript" src="https://app.convertful.com/assets/js/base/main.js?v=1.40"></script>
	<script type="text/javascript" src="https://app.convertful.com/assets/js/base/cof.js?v=1.40"></script>
	<script type="text/javascript" src="http://app.convertful.com/assets/js/user/integrations.js?v=1.40"></script>
	<script type="text/javascript" src="/assets/inttests/inttests.js"></script>
</head>
<body>
<div class="inttests">
	<div class="inttests-sidebar">
		<div class="cof-integrations">
			<div class="cof-integrations-item is-active">
				<h2>Credentials</h2>
				<div class="cof-integrations-item-content">
					<div class="cof-form-row type_select2" data-name="driver" data-id="cof_driver">
						<div class="cof-form-row-title"><span>Email Provider</span></div>
						<div class="cof-form-row-field">
							<div class="cof-form-row-control"><select name="driver" id="cof_driver" autocomplete="off">
									<option value="" selected>Choose integration provider ...</option>
									<?php foreach ($drivers as $driver_id => $driver):?>
										<option value="<?php echo $driver_id?>"><?php echo $driver?></option>
									<?php endforeach;?>
								</select></div><!-- .cof-form-row-control -->
							<div class="cof-form-row-state"></div>
						</div>
					</div><!-- .cof-form-row -->
					<div class="cof-integrations-credentials i-form"></div>
					<textarea class="cof-integrations-credentials-textarea"></textarea>
				</div>
			</div>
		</div>

		<div class="driver-controls">
			<div class="params" style="display: none">
				<div class="driver-controls-request for_meta">
					<h2>Meta</h2>
					<div class="driver-controls-request-h">
						<textarea></textarea>
					</div>
				</div>

				<h2>Parameters</h2>
				<div class="driver-controls-fields for_params">
					<div class="cof-integrations">
						<div class="cof-integrations-item is-active">
							<div class="cof-integrations-item-content">
								<div class="driver-controls-fields-h i-form"></div>
							</div>
						</div>
					</div>
				</div>
				<div class="inttests-form">
					<h2>Widget Form</h2>
					<div class="inttests-form-add" title="Append Field"></div>
					<div class="inttests-form-addlist">
						<div class="inttests-form-addlist-select">
							<select>
								<option value="first_name">First Name</option>
								<option value="last_name">Last Name</option>
								<option value="name">Full Name</option>
								<option value="phone">Phone</option>
								<option value="company">Company</option>
								<option value="site">Site</option>
								<option value="hidden_data">Hidden Data</option>
								<option value="custom_field">Custom Field</option>
							</select>
						</div>
						<div class="inttests-form-addlist-button">Append</div>
					</div>
					<div class="driver-controls-fields for_form">
						<div class="driver-controls-fields-values"></div>

						<form class="conv-form conv_layout_ver conv_labels_none conv_id_form" method="post">
							<div class="conv-form-field for_email">
								<label for="form_email_conv1">Your Email</label>
								<div class="conv-form-field-input">
									<input type="email" name="email" id="form_email_conv1" placeholder="Your Email">
									<div class="conv-form-field-message"></div>
								</div>
								<div class="conv-form-field-remove" title="Remove Field"></div>
							</div>
							<div style="margin: 0 20px;">
								<input type="submit" value="Submit">
							</div>
						</form>

						<div class="driver-controls-fields-buttons">
							<div class="driver-controls-fields-button for_get">Get Person</div>
							<div class="driver-controls-fields-button for_create">Create Person</div>
							<div class="driver-controls-fields-button for_update">Update Person</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="inttests-content">
		<h2>Response</h2>
		<div class="inttests-response"></div>

		<h2>Requests Log</h2>
		<div class="inttests-logs">

		</div>
	</div>
</div>

<template id="log-item">
	<div class="inttests-logs-item">
		<div class="inttests-logs-item-title">
			<input type="text" disabled>
		</div>
		<div class="inttests-logs-item-content"></div>
	</div>
</template>

<?php echo $after; ?>
</body>
</html>