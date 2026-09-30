<?php
/**
 * A copyable snippet with its copy button.
 *
 * Included wherever a connection snippet is shown. Expects in scope:
 * - $copy_id      Unique element id for the <pre>, used by the copy button.
 * - $copy_text    Snippet text. Occurrences of MCP_Settings::NAME_TOKEN become
 *                 the highlighted connection-name span.
 * - $copy_alias   Connection name substituted for that token.
 * - $copy_label   Button label.
 * - $copy_primary Whether the button is the panel's primary action.
 *
 * @package PremiumAddons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Admin\Includes\MCP_Settings;

$copy_parts = explode( MCP_Settings::NAME_TOKEN, (string) $copy_text );
// phpcs:disable Squiz.PHP.EmbeddedPhp, Generic.WhiteSpace.ScopeIndent -- inline PHP tags keep literal whitespace out of the rendered <pre>.
?>
<pre class="pa-mcp-connect-prompt" id="<?php echo esc_attr( $copy_id ); ?>"><?php
	echo implode(
		'<span class="pa-mcp-alias">' . esc_html( $copy_alias ) . '</span>',
		array_map( 'esc_html', $copy_parts )
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
?></pre>
<?php // phpcs:enable Squiz.PHP.EmbeddedPhp, Generic.WhiteSpace.ScopeIndent ?>
<button type="button" class="button <?php echo esc_attr( $copy_primary ? 'button-primary ' : '' ); ?>pa-mcp-copy" data-pa-mcp-copy="<?php echo esc_attr( $copy_id ); ?>" data-pa-mcp-copied="<?php esc_attr_e( 'Copied!', 'premium-addons-for-elementor' ); ?>">
	<?php echo esc_html( $copy_label ); ?>
</button>
