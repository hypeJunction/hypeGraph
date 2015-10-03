<?php

use hypeJunction\Graph\ErrorResult;
use hypeJunction\Graph\GenericResult;

$result = elgg_extract('result', $vars);

if (!$result instanceof GenericResult) {
	$result = new ErrorResult(elgg_echo('Exception:ApiResultUnknown'), 200);
}

$params = (array) elgg_extract('params', $vars, array());
$export = $result->export($params);
?>
<table class="graph-result">
	<tr>
		<td>Status:</td>
		<td><?php echo $result->getStatusCode() ?></td>
	</tr>

	<?php if (!empty($export->message)) { ?>
		<tr>
			<td>Message:</td>
			<td><?php echo $export->message; ?></td>
		</tr>
	<?php } ?>
	<?php if (!empty($export->result)) { ?>
		<tr>
			<td>Result:</td><td>
				<pre><?php print_r($export->result); ?></pre>
			</td>
		</tr>
	<?php } ?>
	<?php if (!empty($export->log)) { ?>
		<tr>
			<td>Log:</td>
			<td><pre><?php print_r($export->log); ?></pre></td>
		</tr>
	<?php } ?>
	<?php if (!empty($export->debug)) { ?>
		<tr>
			<td>Runtime:</td>
			<td><pre><?php print_r($export->debug); ?></pre></td>
		</tr>
	<?php } ?>
</table>