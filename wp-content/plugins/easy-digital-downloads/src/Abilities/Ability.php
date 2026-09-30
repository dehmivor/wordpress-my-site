<?php
/**
 * Base class for EDD abilities registered with the WordPress Abilities API.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Base class for EDD abilities.
 *
 * Each ability describes one EDD operation: its name, input/output JSON
 * Schema, behavior annotations, and the capability that gates it. Subclasses
 * implement the metadata getters and extend ReadAbility or WriteAbility, which
 * own execute(); registration with WordPress is handled here.
 *
 * @since 3.7.1
 */
abstract class Ability {

	/**
	 * The namespace prefix for all EDD abilities.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const ABILITY_NAMESPACE = 'edd';

	/**
	 * Executes the ability.
	 *
	 * The Abilities API validates input against the input schema before calling
	 * this, but the method is public and reachable without it, so ReadAbility and
	 * WriteAbility run require_input() before handing anything to a subclass
	 * rather than trusting the argument.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The input, or null when the ability accepts no input.
	 * @return array|\WP_Error
	 */
	abstract public function execute( $input = null );

	/**
	 * Gets the fully qualified ability name.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::ABILITY_NAMESPACE . '/' . $this->get_slug();
	}

	/**
	 * Whether the ability should be registered on this site.
	 *
	 * Feature-gated abilities override this so a site only exposes the
	 * features it actually has active.
	 *
	 * @since 3.7.1
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Checks whether the current user may run this ability.
	 *
	 * The Abilities API passes the validated input when an input schema is
	 * registered, so subclasses may override this for input-aware checks.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The validated input, or null when the ability accepts no input.
	 * @return bool
	 */
	public function check_permissions( $input = null ): bool {
		return current_user_can( $this->get_capability() );
	}

	/**
	 * Registers the ability with the WordPress Abilities API.
	 *
	 * Must be called during the `wp_abilities_api_init` action.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function register(): void {
		$args = array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => Loader::CATEGORY,
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'output_schema'       => $this->get_output_schema(),
			'meta'                => array(
				'annotations'  => $this->get_annotations(),
				'show_in_rest' => true,
			),
		);

		$input_schema = $this->get_input_schema();
		if ( ! empty( $input_schema ) ) {
			$args['input_schema'] = $input_schema;
		}

		wp_register_ability( $this->get_name(), $args );
	}

	/**
	 * Normalizes the input, then checks it against the ability's own input schema.
	 *
	 * The required keys come from the schema's own `required` list, so an ability
	 * names them in one place. A missing one returns `edd_ability_missing_input`,
	 * whose message names the field; a value the schema refuses returns
	 * `edd_ability_invalid_input`, carrying the validator's own message.
	 *
	 * The schema check runs because execute() is public and reachable without the
	 * Abilities API, which is the only other thing that validates the input.
	 *
	 * Input which passes is returned sanitized, so a declared type arrives as that
	 * type. The Abilities API validates without sanitizing, and the validator
	 * accepts the string `'false'` for a boolean and `"12"` for an integer, which
	 * every truthiness and identity check downstream would read the wrong way.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The input passed to execute().
	 * @return array|\WP_Error The input as an array, or the error to hand back.
	 */
	protected function require_input( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$schema = $this->get_input_schema();

		foreach ( (array) ( $schema['required'] ?? array() ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				continue;
			}

			return new \WP_Error(
				'edd_ability_missing_input',
				sprintf(
					/* translators: %s: the name of the required input field which was not provided. */
					__( 'The %s value is required.', 'easy-digital-downloads' ),
					$key
				),
				array( 'status' => 400 )
			);
		}

		if ( empty( $schema ) ) {
			return $input;
		}

		$validated = rest_validate_value_from_schema( $input, $schema, 'input' );
		$sanitized = is_wp_error( $validated ) ? $validated : rest_sanitize_value_from_schema( $input, $schema, 'input' );

		if ( is_wp_error( $sanitized ) ) {
			return new \WP_Error( 'edd_ability_invalid_input', $sanitized->get_error_message(), array( 'status' => 400 ) );
		}

		return is_array( $sanitized ) ? $sanitized : $input;
	}

	/**
	 * Gets the ability slug (the part after the `edd/` namespace).
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_slug(): string;

	/**
	 * Gets the human-readable label for the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_label(): string;

	/**
	 * Gets the description of the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_description(): string;

	/**
	 * Gets the capability required to run the ability.
	 *
	 * Name the capability which gates the equivalent admin screen. The base class
	 * checks only this, so a capability an unprivileged role holds opens the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_capability(): string;

	/**
	 * Gets the output JSON Schema for the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	abstract protected function get_output_schema(): array;

	/**
	 * Gets the behavior annotations for the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	abstract protected function get_annotations(): array;

	/**
	 * Gets the input JSON Schema for the ability.
	 *
	 * The default describes an ability which accepts no input. It is an empty
	 * object rather than nothing at all because many MCP clients send `{}`
	 * instead of omitting input, and those calls have to stay valid. Return an
	 * empty array to register no input schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => array(),
		);
	}
}
