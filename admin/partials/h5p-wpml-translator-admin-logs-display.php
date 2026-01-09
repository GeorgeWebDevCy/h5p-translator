<?php
/**
 * Provide an admin area view for the live logs.
 */

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
?>

<div class="wrap">
	<h1>H5P Translation Live Logs</h1>

	<div class="card" style="max-width: 100%; margin-top: 20px;">
		<h2>Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'h5p_wpml_translator_logger_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enable Logging</th>
					<td>
						<label>
							<input type="radio" name="<?php echo esc_attr( H5p_Wpml_Translator_Logger::OPTION_ENABLED ); ?>" value="yes" <?php checked( H5p_Wpml_Translator_Logger::is_enabled(), true ); ?>> Yes
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="radio" name="<?php echo esc_attr( H5p_Wpml_Translator_Logger::OPTION_ENABLED ); ?>" value="no" <?php checked( H5p_Wpml_Translator_Logger::is_enabled(), false ); ?>> No
						</label>
						<p class="description">Turn this on to start capturing translation events. Remember to turn it off when done diagnosing to save disk space.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Logging Settings', 'secondary' ); ?>
		</form>
	</div>

	<hr>

	<div id="h5p-translator-logs-viewer">
		<h2>Log Output</h2>
		<p>
			<button type="button" id="h5p-clear-logs" class="button button-link-delete">Clear Logs</button>
			<span id="h5p-log-status" style="margin-left: 10px; font-style: italic; color: #666;">Waiting for logs...</span>
		</p>
		<textarea id="h5p-log-content" class="large-text code" rows="25" readonly style="background: #f0f0f0; white-space: pre; font-size: 13px; font-family: Consolas, Monaco, monospace;"></textarea>
	</div>
</div>

<style>
#h5p-log-content {
	width: 100%;
	max-width: 100%;
}
</style>
