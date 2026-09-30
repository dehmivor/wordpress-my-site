<?php
/**
 * Numbered setup steps for one AI client.
 *
 * Included by mcp-client-tabs.php and mcp-oauth-setup.php with in scope:
 * - $steps_blocks    Blocks to render. Each: title, optional desc, then either
 *                    copy (one snippet) or copies (labeled variants, each:
 *                    badge, optional desc, copy).
 * - $steps_id_prefix Unique prefix for the copy-block element ids.
 * - $copy_alias      Connection name, read by mcp-copy-block.php.
 *
 * @package PremiumAddons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<ol class="pa-mcp-client-steps">
	<?php foreach ( $steps_blocks as $steps_index => $steps_block ) : ?>
		<li>
			<p class="pa-mcp-step-title"><strong><?php echo esc_html( $steps_block['title'] ); ?></strong></p>

			<?php if ( ! empty( $steps_block['desc'] ) ) : ?>
				<p class="description pa-mcp-step-detail"><?php echo esc_html( $steps_block['desc'] ); ?></p>
			<?php endif; ?>

			<?php
			$steps_copies = array();

			if ( ! empty( $steps_block['copies'] ) ) {
				$steps_copies = $steps_block['copies'];
			} elseif ( ! empty( $steps_block['copy'] ) ) {
				$steps_copies = array( array( 'copy' => $steps_block['copy'] ) );
			}

			foreach ( $steps_copies as $steps_copy_index => $steps_copy ) :
				?>

				<?php if ( ! empty( $steps_copy['badge'] ) ) : ?>
					<p class="pa-mcp-copy-badge"><?php echo esc_html( $steps_copy['badge'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $steps_copy['desc'] ) ) : ?>
					<p class="description pa-mcp-step-detail"><?php echo esc_html( $steps_copy['desc'] ); ?></p>
				<?php endif; ?>

				<?php
				$copy_id      = $steps_id_prefix . '-' . ( $steps_index + 1 ) . ( 0 === $steps_copy_index ? '' : '-' . ( $steps_copy_index + 1 ) );
				$copy_text    = $steps_copy['copy'];
				$copy_label   = __( 'Copy', 'premium-addons-for-elementor' );
				$copy_primary = false;
				include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-copy-block.php';
			endforeach;
			?>
		</li>
	<?php endforeach; ?>
</ol>
