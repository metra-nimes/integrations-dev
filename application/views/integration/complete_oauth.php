<?php
/**
 * @var string $driver_name Integrations driver name
 * @var string $field_name Field name
 */
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title></title>
	<script src="https://app.convertful.com/assets/vendor/jquery/jquery-3.1.1.min.js" type="text/javascript"></script>
	<script src="https://app.convertful.com/assets/vendor/jquery/jquery.bbq.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		jQuery(function($){
			var opener = window.opener,
				driverName = <?php echo json_encode($driver_name) ?>,
				fieldName = <?php echo json_encode($field_name) ?>;
			if (typeof opener.cfSetOauthToken === 'undefined') return;
			// Filtering credentials
			var data = {
				driver: driverName,
				credentials: {}
			};
			data.credentials[fieldName] = $.deparam(location.hash.substr(1));
			Object.assign(data.credentials[fieldName], $.deparam(location.search.substr(1)));
			$.post('/api/integration/filter_credentials', data, function(result){
				opener.cfSetOauthToken(result.data[fieldName] || {});
				opener.focus();
				window.close();
			}, 'json');
		});
	</script>
</head>
<body>
</body>
</html>