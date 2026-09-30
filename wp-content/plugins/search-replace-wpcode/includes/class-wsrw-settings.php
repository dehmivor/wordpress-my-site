<?php
/**
 * Handles all the WPCode settings.
 *
 * @package WPCode
 */

/**
 * Class Wsrw_Settings.
 */
class WSRW_Settings {

	/**
	 * The key used for storing settings in the db.
	 *
	 * @var string
	 */
	private $settings_key = 'WSRW_Settings';

	/**
	 * Options as they are loaded from the db.
	 *
	 * @var array
	 * @see WSRW_Settings::get_options
	 */
	private $options;

	/**
	 * Get an option by name with an optional default value.
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $default The default value (optional).
	 *
	 * @return mixed
	 * @see get_option
	 */
	public function get_option( $option_name, $default = false ) {
		$options = $this->get_options();
		$value   = $default;
		if ( isset( $options[ $option_name ] ) ) {
			$value = $options[ $option_name ];
		}

		return apply_filters( "wsrw_get_option_{$option_name}", $value );
	}

	/**
	 * Get all the options as they are stored in the db.
	 *
	 * @return array
	 */
	public function get_options() {
		if ( ! isset( $this->options ) ) {
			$this->options = get_option( $this->settings_key, array() );
		}

		return $this->options;
	}

	/**
	 * Update an option in the settings object.
	 *
	 * @param string $option The option name.
	 * @param mixed  $value The new value.
	 *
	 * @return void
	 */
	public function update_option( $option, $value ) {
		// Allow false and 0 as valid values, only delete if explicitly null or empty string.
		if ( null === $value || '' === $value ) {
			$this->delete_option( $option );

			return;
		}
		if ( isset( $this->options[ $option ] ) && $this->options[ $option ] === $value ) {
			return;
		}
		$this->options[ $option ] = $value;

		$this->save_options();
	}

	/**
	 * Delete an option by its name.
	 *
	 * @param string $option The option name.
	 *
	 * @return void
	 */
	public function delete_option( $option ) {
		// If there's nothing to delete, do nothing.
		if ( isset( $this->options[ $option ] ) ) {
			unset( $this->options[ $option ] );
			$this->save_options();
		}
	}

	/**
	 * Save the current options object to the db.
	 *
	 * @return void
	 */
	private function save_options() {
		update_option( $this->settings_key, (array) $this->options );
	}

	/**
	 * Use an array to update multiple settings at once.
	 *
	 * @param array $options The new options array.
	 *
	 * @return void
	 */
	public function bulk_update_options( $options ) {
		$this->options = array_merge( $this->get_options(), $options );

		$this->save_options();
	}

	/**
	 * Save a search to history.
	 *
	 * @param array $search_data The search data to save.
	 *
	 * @return bool Whether the search was saved successfully.
	 */
	public function save_search_to_history( $search_data ) {
		// Get the max limit with filter.
		$max_entries = apply_filters( 'wsrw_search_history_limit', 20 );

		// If limit is 0, don't save anything.
		if ( 0 === absint( $max_entries ) ) {
			return false;
		}

		// Get current history from separate option.
		$history = $this->get_search_history();

		// Get the next ID (find highest ID and increment).
		$next_id = 1;
		if ( ! empty( $history ) ) {
			$ids     = array_column( $history, 'id' );
			$max_id  = ! empty( $ids ) ? max( $ids ) : 0;
			$next_id = absint( $max_id ) + 1;
		}

		// Create the search object.
		$search_item = array(
			'id'               => $next_id,
			'search'           => isset( $search_data['search'] ) ? sanitize_text_field( $search_data['search'] ) : '',
			'replace'          => isset( $search_data['replace'] ) ? sanitize_text_field( $search_data['replace'] ) : '',
			'tables'           => isset( $search_data['tables'] ) ? array_map( 'sanitize_text_field', (array) $search_data['tables'] ) : array(),
			'case_insensitive' => isset( $search_data['case_insensitive'] ) ? (bool) $search_data['case_insensitive'] : false,
			'timestamp'        => time(),
			'dry_run'          => isset( $search_data['dry_run'] ) ? (bool) $search_data['dry_run'] : true,
		);

		// Don't save empty searches.
		if ( empty( $search_item['search'] ) ) {
			return false;
		}

		// Check for duplicates: same search, replace, case_insensitive, and tables.
		$history = $this->remove_duplicate_search( $history, $search_item );

		// Add to the beginning of the array (newest first).
		array_unshift( $history, $search_item );

		// Keep only the last X searches (FIFO).
		$history = array_slice( $history, 0, absint( $max_entries ) );

		// Save to separate option (autoload = false for better performance).
		update_option( 'wsrw_search_history', $history, false );

		return true;
	}

	/**
	 * Remove duplicate search from history.
	 *
	 * @param array $history The current history.
	 * @param array $new_search The new search to check against.
	 *
	 * @return array Updated history without the duplicate.
	 */
	private function remove_duplicate_search( $history, $new_search ) {
		$updated_history = array_filter(
			$history,
			function ( $item ) use ( $new_search ) {
				// Check if search, replace, case_insensitive, and tables match.
				$same_search  = isset( $item['search'] ) && $item['search'] === $new_search['search'];
				$same_replace = isset( $item['replace'] ) && $item['replace'] === $new_search['replace'];
				$same_case    = isset( $item['case_insensitive'] ) && $item['case_insensitive'] === $new_search['case_insensitive'];

				// Sort tables for comparison.
				$item_tables = isset( $item['tables'] ) ? $item['tables'] : array();
				$new_tables  = $new_search['tables'];
				$item_tables_sorted = $item_tables;
				$new_tables_sorted = $new_tables;
				sort( $item_tables_sorted );
				sort( $new_tables_sorted );
				$same_tables = $item_tables_sorted === $new_tables_sorted;

				// Keep the item if it's NOT a duplicate.
				return ! ( $same_search && $same_replace && $same_case && $same_tables );
			}
		);

		// Re-index the array.
		return array_values( $updated_history );
	}

	/**
	 * Get all saved searches from history.
	 *
	 * @return array Array of search history items, newest first.
	 */
	public function get_search_history() {
		$history = get_option( 'wsrw_search_history', array() );

		// Ensure it's an array.
		if ( ! is_array( $history ) ) {
			return array();
		}

		return $history;
	}

	/**
	 * Delete a search from history by ID.
	 *
	 * @param int $search_id The ID of the search to delete.
	 *
	 * @return bool Whether the search was deleted successfully.
	 */
	public function delete_search_from_history( $search_id ) {
		$history = $this->get_search_history();

		if ( empty( $history ) ) {
			return false;
		}

		$search_id = absint( $search_id );

		// Filter out the search with the given ID.
		$updated_history = array_filter(
			$history,
			function ( $item ) use ( $search_id ) {
				return isset( $item['id'] ) && absint( $item['id'] ) !== $search_id;
			}
		);

		// Re-index the array.
		$updated_history = array_values( $updated_history );

		// Save the updated history.
		update_option( 'wsrw_search_history', $updated_history, false );

		return true;
	}
}
