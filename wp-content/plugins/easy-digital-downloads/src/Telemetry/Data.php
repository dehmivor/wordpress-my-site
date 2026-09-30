<?php
/**
 * Telemetry Data.
 *
 * Gets the data to send to our telemetry server.
 *
 * @package     EDD\Telemetry
 * @copyright   Copyright (c) 2023, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.1.1
 */

namespace EDD\Telemetry;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Data
 *
 * @since 3.1.1
 */
class Data {
	use Traits\Anonymize;

	/**
	 * Gets all of the site data.
	 *
	 * @return false|array
	 */
	public function get() {
		$data = array(
			'id' => $this->get_id(),
		);

		$classes = $this->get_collectors();

		foreach ( $classes as $key => $class ) {
			$data[ $key ] = $class->get();
		}

		return $data;
	}

	/**
	 * Gets the telemetry collector instances.
	 *
	 * @since 3.6.8
	 *
	 * @return array
	 */
	protected function get_collectors(): array {
		return array(
			'environment'  => new Environment(),
			'integrations' => new Integrations(),
			'licenses'     => new Licenses(),
			'sales'        => new Orders(),
			'refunds'      => new Orders( 'refund' ),
			'settings'     => new Settings(),
			'stats'        => new Stats(),
			'products'     => new Products(),
		);
	}
}
