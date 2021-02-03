<?php
/**
 * payro24 gateway settings.
 *
 * @package RCP_payro24
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function rcp_payro24_settings( $rcp_options ) {

	?>
	<hr>

	<table class="form-table">
		<tr valign="top">
			<th colspan="2">
				<h3><?php _e( 'payro24 gateway settings', 'payro24-for-rcp' ); ?></h3>
			</th>
		</tr>
		<tr valign="top">
			<th>
				<label for="rcp_settings[payro24_api_key]" id="payro24ApiKey"><?php _e( 'API Key', 'payro24-for-rcp' ); ?></label>
			</th>
			<td>
				<input class="regular-text" name="rcp_settings[payro24_api_key]" id="payro24ApiKey" value="<?php echo isset( $rcp_options['payro24_api_key'] ) ? $rcp_options['payro24_api_key'] : ''; ?>">
				<p class="description"><?php _e( 'You can create an API Key by going to your <a href="https://payro24.ir/dashboard/web-services">payro24 account</a>.', 'payro24-for-rcp' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th>
				<label for="rcp_settings[payro24_sandbox]" id="payro24Sandbox"><?php _e( 'Sandbox mode', 'payro24-for-rcp' ); ?></label>
			</th>
			<td>
				<p class="description">
					<select id="rcp_settings[payro24_sandbox]" name="rcp_settings[payro24_sandbox]">
						<option value="yes" <?php selected('yes', isset($rcp_options['payro24_sandbox']) ? $rcp_options['payro24_sandbox'] : '');?>><?php _e('Yes', 'payro24-for-rcp');?></option>
						<option value="no" <?php selected('no', isset($rcp_options['payro24_sandbox']) ? $rcp_options['payro24_sandbox'] : '');?>><?php _e('No', 'payro24-for-rcp');?></option>
					</select>
					<?php _e( 'If you check this option, the gateway will work in Test (Sandbox) mode.', 'payro24-for-rcp' ); ?>
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th>
				<label for="rcp_settings[payro24_symbol]" id="payro24Symbol"><?php _e( 'Show currency?', 'payro24-for-rcp' ); ?></label>
			</th>
			<td>
				<p class="description">
					<select id="rcp_settings[payro24_symbol]" name="rcp_settings[payro24_symbol]">
						<option value="yes" <?php selected('yes', isset($rcp_options['payro24_symbol']) ? $rcp_options['payro24_symbol'] : '');?>><?php _e('Yes', 'payro24-for-rcp');?></option>
						<option value="no" <?php selected('no', isset($rcp_options['payro24_symbol']) ? $rcp_options['payro24_symbol'] : '');?>><?php _e('No', 'payro24-for-rcp');?></option>
					</select>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action('rcp_payments_settings', 'rcp_payro24_settings');
