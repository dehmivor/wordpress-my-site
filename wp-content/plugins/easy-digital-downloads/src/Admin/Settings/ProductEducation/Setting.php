<?php
/**
 * Abstract class for handling product education.
 *
 * @package     EDD\Admin\Settings\ProductEducation
 * @copyright   Copyright (c) 2026, Easy Digital Downloads
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Admin\Settings\ProductEducation;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

abstract class Setting {

	/**
	 * The configuration for the plugin.
	 *
	 * @var array
	 * @since 3.7.0
	 */
	protected $config;

	/**
	 * The Extension Manager.
	 *
	 * @var \EDD\Admin\Extensions\Extension_Manager
	 */
	protected $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->manager = new \EDD\Admin\Extensions\Extension_Manager();
	}

	/**
	 * Register the setting hook.
	 *
	 * @param array $settings The array of settings.
	 * @return array
	 */
	abstract public function register_setting( $settings ): array;

	/**
	 * Gets the description for the plugin.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	abstract protected function get_description(): string;

	/**
	 * Whether the plugin is active.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	abstract protected function is_active(): bool;

	/**
	 * Output the settings field (installation helper).
	 *
	 * @param array $args The setting field arguments.
	 * @return void
	 */
	public function settings_field( $args ) {
		$this->manager->enqueue();
		?>
		<div class="edd-extension-manager__body">
			<p class="edd-extension-manager__description">
				<?php echo esc_html( $this->get_description() ); ?>
			</p>

			<div class="edd-extension-manager__group edd-extension-manager__actions">
				<div class="edd-extension-manager__step">
					<?php $this->manager->button( $this->get_button_parameters() ); ?>
				</div>

				<?php if ( ! $this->is_active() ) : ?>
					<div class="edd-extension-manager__step" style="display:none;">
						<?php $this->manager->link( $this->get_link_parameters() ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets the button parameters for the three states (install / activate / configure).
	 *
	 * @since 3.7.0
	 * @return array
	 */
	protected function get_button_parameters() {
		$button = array();

		if ( ! $this->manager->is_plugin_installed( $this->config['plugin'] ) ) {
			$button['plugin'] = $this->config['download_url'];
			$button['action'] = 'install';
			/* translators: the plugin name */
			$button['button_text'] = sprintf( __( 'Install & Activate %s', 'easy-digital-downloads' ), $this->config['name'] );
		} elseif ( ! $this->is_active() ) {
			$button['plugin'] = $this->config['plugin'];
			$button['action'] = 'activate';
			/* translators: the plugin name */
			$button['button_text'] = sprintf( __( 'Activate %s', 'easy-digital-downloads' ), $this->config['name'] );
		} else {
			$button = $this->get_link_parameters();
		}

		return $button;
	}

	/**
	 * Gets the array of parameters for the link to configure ActiveLayer.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	protected function get_link_parameters() {
		return array(
			/* translators: the plugin name */
			'button_text' => sprintf( __( 'Configure %s', 'easy-digital-downloads' ), $this->config['name'] ),
			'href'        => admin_url( $this->config['settings'] ),
		);
	}
}
