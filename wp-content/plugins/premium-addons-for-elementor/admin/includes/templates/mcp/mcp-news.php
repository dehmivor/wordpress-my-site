<?php
/**
 * What's New sidebar — recent MCP/AI updates fetched from premiumaddons.com.
 * $news_entries comes from ai-abilities.php, which includes this file into its
 * own scope. Entry strings are remote English copy — escaped, never translated.
 */

use PremiumAddons\Admin\Includes\MCP_News;
use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type_labels = array(
	'new'         => 'New',
	'improvement' => 'Improved',
	'fix'         => 'Fixed',
);

?>

<aside class="pa-mcp-news"<?php echo MCP_News::has_unread() ? ' data-unread="1"' : ''; ?>>

	<h3 class="pa-mcp-news-title"><?php esc_html_e( "What's New", 'premium-addons-for-elementor' ); ?></h3>

	<ul class="pa-mcp-news-list">

		<?php foreach ( $news_entries as $entry ) : ?>

			<li class="pa-mcp-news-item">
				<div class="pa-mcp-news-meta">
					<span class="pa-mcp-news-type is-<?php echo esc_attr( $entry['type'] ); ?>"><?php echo esc_html( isset( $type_labels[ $entry['type'] ] ) ? $type_labels[ $entry['type'] ] : $entry['type'] ); ?></span>
					<time class="pa-mcp-news-date" datetime="<?php echo esc_attr( $entry['date'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry['date'] ) ) ); ?></time>
				</div>

				<a class="pa-mcp-news-link" href="<?php echo esc_url( Helper_Functions::get_campaign_link( $entry['link'], 'wp-dash', 'link', 'mcp-news', $entry['id'] ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( $entry['title'] ); ?>
				</a>

				<p class="pa-mcp-news-desc"><?php echo esc_html( $entry['description'] ); ?></p>
			</li>

		<?php endforeach; ?>

	</ul>

</aside>
