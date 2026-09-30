<?php
/**
 * Class to handle registering and adding service providers for EDD.
 *
 * @package     EDD\EventManagement
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.1.1
 */

namespace EDD\EventManagement;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Registers EDD's service providers and attaches them to the event manager.
 *
 * @since 3.1.1
 */
abstract class Subscribers {

	/**
	 * The pass handler.
	 *
	 * @since 3.1.1
	 * @var \EDD\Admin\PassHandler\Handler
	 */
	protected $pass_handler;

	/**
	 * Constructor.
	 *
	 * @since 3.1.1
	 */
	public function __construct() {
		$this->pass_handler = new \EDD\Admin\PassHandler\Handler();
		$this->add_service_providers();
	}

	/**
	 * Add registered service providers.
	 *
	 * @since 3.1.1
	 * @return void
	 */
	private function add_service_providers() {
		$events = new EventManager();

		if ( ! $events instanceof EventManager ) {
			return;
		}

		$service_providers = array_merge(
			$this->get_service_providers(),
			$this->get_admin_providers(),
			$this->get_replaceable_providers()
		);

		// Attach subscribers.
		foreach ( $service_providers as $service_provider ) {
			try {
				$events->add_subscriber( $service_provider );
			} catch ( \Throwable $e ) {
				// A provider failed to attach; skip it rather than fataling boot.
				edd_debug_log(
					sprintf(
						'EDD: skipped service provider %s during boot: %s',
						is_object( $service_provider ) ? get_class( $service_provider ) : gettype( $service_provider ),
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Gets providers that may be extended/replaced in lite/pro.
	 *
	 * @since 3.1.1
	 * @return array
	 */
	protected function get_replaceable_providers() {
		return array();
	}

	/**
	 * Gets the service providers for EDD.
	 *
	 * @since 3.1.1
	 * @return array
	 */
	abstract protected function get_service_providers();

	/**
	 * Gets the admin service providers for EDD.
	 *
	 * @since 3.1.1
	 * @return array
	 */
	abstract protected function get_admin_providers();
}
