<?php
namespace LiteSpeed;
defined( 'WPINC' ) || exit;

$api_key_val = Conf::val( Base::O_API_KEY );
if ( ! empty( $_GET[ 'apikey_data' ] ) ) {
	$apikey_data = json_decode( base64_decode( $_GET[ 'apikey_data' ] ), true );
	if ( ! empty( $apikey_data[ 'domain_key' ] ) && $api_key_val != $apikey_data[ 'domain_key' ] ) {
		$api_key_val = $apikey_data[ 'domain_key' ];
		! defined( 'LITESPEED_NEW_API_KEY' ) && define( 'LITESPEED_NEW_API_KEY', true );
	}
	unset( $_GET[ 'apikey_data' ] );
	?>
	<script>window.history.pushState( 'remove_gen_link', document.title, window.location.href.replace( '&apikey_data=', '&' ) );</script>
	<?php
}

$gen_btn_available = get_option( 'permalink_structure' );

$cloud_summary = Cloud::get_summary();


$this->form_action();
?>

<h3 class="litespeed-title-short">
	<?php echo __( 'General Settings', 'litespeed-cache' ); ?>
	<?php $this->learn_more( 'https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:lscwp:configuration:general', false, 'litespeed-learn-more' ); ?>
</h3>

<table class="wp-list-table striped litespeed-table"><tbody>
	<?php if ( ! $this->_is_multisite ) : ?>
		<?php require LSCWP_DIR . 'tpl/general/settings_inc.auto_upgrade.tpl.php'; ?>
	<?php endif; ?>

	<tr>
		<th>
			<?php $id = Base::O_API_KEY; ?>
			<?php $this->title( $id ); ?>
		</th>
		<td>
			<?php $this->build_input( $id, null, defined( 'LITESPEED_NEW_API_KEY' ) ? $api_key_val : null ); ?>
			<?php if ( defined( 'LITESPEED_NEW_API_KEY' ) ) : ?>
				<span class="litespeed-danger"><?php echo sprintf( __( 'Not saved yet! You need to click %s to save this option.', 'litespeed-cache' ), __( 'Save Changes', 'litespeed-cache' ) ); ?></span>
			<?php endif; ?>
			<div class="litespeed-desc">
				<?php echo __( 'An API key is necessary for security when communicating with our QUIC.cloud servers. Required for online services.', 'litespeed-cache' ); ?>
				<?php if ( $gen_btn_available ) : ?>
					<?php $this->learn_more( Utility::build_url( Router::ACTION_CLOUD, Cloud::TYPE_GEN_KEY ), __( 'Generate Key', 'litespeed-cache' ), '', true ); ?>
				<?php else: ?>
					<?php $this->learn_more( 'javascript:;', __( 'Generate Key', 'litespeed-cache' ), 'disabled', true ); ?>
					<br />
					<span class="litespeed-danger">
						<?php echo __( 'Warning', 'litespeed-cache' ); ?>:
						<?php echo sprintf( __( 'You must set WordPress %1$s to a value other than %2$s before generating an API key.', 'litespeed-cache' ), '<code>' . __( 'Permalink Settings' ) . '</code>', '<code>' . __( 'Plain' ) . '</code>' ); ?>
						<?php echo '<a href="options-permalink.php">' . __( 'Click here to config', 'litespeed-cache' ) . '</a>'; ?>
					</span>
				<?php endif; ?>
				<br /><?php echo sprintf( __( 'If you have previously generated a key as an anonymous user, but now wish to log into the %1$s Dashboard to see usage, status and statistics, please use the %2$s in %3$s to register at QUIC.cloud.', 'litespeed-cache' ),
						'<strong>QUIC.cloud</strong>',
						'<code>' . __( 'Administration Email Address' ) . '</code>',
						'<code>' . __( 'Settings' ) . ' > ' . __( 'General Settings' ) . '</code>'
					); ?>

				<br />
				<div class="litespeed-callout notice notice-success inline">
					<h4><?php echo __( 'Current Cloud Nodes in Service','litespeed-cache' ); ?>
						<a class="litespeed-right" href="<?php echo Utility::build_url( Router::ACTION_CLOUD, Cloud::TYPE_CLEAR_CLOUD ); ?>" data-balloon-pos="up" data-balloon-break aria-label='<?php echo __( 'Click to clear all nodes for further redetection.', 'litespeed-cache' ); ?>' data-litespeed-cfm="<?php echo __( 'Are you sure to clear all cloud nodes?', 'litespeed-cache' ) ; ?>"><i class='litespeed-quic-icon'></i></a>
					</h4>
					<p>
						<?php
						$has_service = false;
						foreach ( Cloud::$SERVICES as $svc ) {
							if ( isset( $cloud_summary[ 'server.' . $svc ] ) ) {
								$has_service = true;
								echo '<p><b>Service:</b> <code>' . $svc . '</code> <b>Node:</b> <code>' . $cloud_summary[ 'server.' . $svc ] . '</code> <b>Connected Date:</b> <code>' . Utility::readable_time( $cloud_summary[ 'server_date.' . $svc ] ) . '</code></p>';
							}
						}
						if ( ! $has_service ) {
							echo __( 'No cloud services currently in use', 'litespeed-cache' );
						}
						?>
					</p>
				</div>

			</div>
		</td>
	</tr>

	<tr>
		<th>
			<?php $id = Base::O_NEWS; ?>
			<?php $this->title( $id ); ?>
		</th>
		<td>
			<?php $this->build_switch( $id ); ?>
			<div class="litespeed-desc">
				<?php echo __( 'Turn this option ON to show latest news automatically, including hotfixes, new releases, available beta versions, and promotions.', 'litespeed-cache' ); ?>
			</div>
		</td>
	</tr>

</tbody></table>

<?php
$this->form_end();

