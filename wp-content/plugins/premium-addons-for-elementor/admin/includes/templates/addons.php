<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Includes\Helper_Functions;

$prefix = Helper_Functions::get_prefix();

$features = $elements['cat-13']['elements'];

?>

<div class="pa-section-content">
	<div class="row">
		<div class="col-full">
			<form action="" method="POST" id="pa-features" name="pa-features" class="pa-settings-form">
			<div id="pa-features-settings" class="pa-settings-tab">

				<?php // AI Abilities lives in its own dashboard tab (templates/ai-abilities.php), which owns its switcher. ?>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php echo esc_html( $features[14]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[14]['desc'] ); ?></p>
						</div>
						<?php

						$status         = ( isset( $features[14]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'disabled' : checked( 1, $enabled_elements['premium-mscroll'], false );
						$class          = ( isset( $features[14]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'pro-' : '';
						$switcher_class = $class . 'slider round pa-control';

						?>
						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-mscroll" pa-element="feature" name="premium-mscroll" <?php echo esc_attr( $status ); ?>>
									<span class="<?php echo esc_attr( $switcher_class ); ?>"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[14]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php printf( '%1$s %2$s', esc_html( $prefix ), esc_html( $features[0]['title'] ) ); ?></h4>
							<p><?php echo esc_html( $features[0]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-templates" pa-element="feature" name="premium-templates" <?php echo checked( 1, $enabled_elements['premium-templates'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[0]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[2]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[2]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="pa-display-conditions" pa-element="feature" name="pa-display-conditions" <?php echo checked( 1, $enabled_elements['pa-display-conditions'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[2]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[1]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[1]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-equal-height" pa-element="feature" name="premium-equal-height" <?php echo checked( 1, $enabled_elements['premium-equal-height'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[1]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4 class = "pa-inline-flex"><?php echo esc_html( $features[3]['title'] ); ?>
							<button type="button" class="pa-btn-clear-cursor pa-inline-flex" title="<?php esc_html_e( 'Clear Site Cursor Settings', 'premium-addons-for-elementor' ); ?>">
								<i class="dashicons dashicons-image-rotate"></i>
							</button>
						</h4>
							<p><?php echo esc_html( $features[3]['desc'] ); ?></p>
						</div>
						<?php

						$status         = ( isset( $features[3]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'disabled' : checked( 1, $enabled_elements['premium-global-cursor'], false );
						$class          = ( isset( $features[3]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'pro-' : '';
						$switcher_class = $class . 'slider round pa-control';

						?>
						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-global-cursor" pa-element="feature" name="premium-global-cursor" <?php echo esc_attr( $status ); ?>>
									<span class="<?php echo esc_attr( $switcher_class ); ?>"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[3]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php echo esc_html( $features[4]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[4]['desc'] ); ?></p>
						</div>
						<?php

						$status         = ( isset( $features[4]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'disabled' : checked( 1, $enabled_elements['premium-global-badge'], false );
						$class          = ( isset( $features[4]['is_pro'] ) && ! Helper_Functions::check_papro_version() ) ? 'pro-' : '';
						$switcher_class = $class . 'slider round pa-control';

						?>
						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-global-badge" pa-element="feature" name="premium-global-badge" <?php echo esc_attr( $status ); ?>>
									<span class="<?php echo esc_attr( $switcher_class ); ?>"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[4]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php echo esc_html( $features[5]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[5]['desc'] ); ?></p>
						</div>
												<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-shape-divider" pa-element="feature" name="premium-shape-divider" <?php echo checked( 1, $enabled_elements['premium-shape-divider'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[5]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php echo esc_html( $features[10]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[10]['desc'] ); ?></p>
						</div>
												<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-global-tooltips" pa-element="feature" name="premium-global-tooltips" <?php echo checked( 1, $enabled_elements['premium-global-tooltips'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[10]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[6]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[6]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-floating-effects" pa-element="feature" name="premium-floating-effects" <?php echo checked( 1, $enabled_elements['premium-floating-effects'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[6]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[7]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[7]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-cross-domain" pa-element="feature" name="premium-cross-domain" <?php echo checked( 1, $enabled_elements['premium-cross-domain'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[7]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
							<h4><?php echo esc_html( $features[8]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[8]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-duplicator" pa-element="feature" name="premium-duplicator" <?php echo checked( 1, $enabled_elements['premium-duplicator'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[11]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[11]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-wrapper-link" pa-element="feature" name="premium-wrapper-link" <?php echo checked( 1, $enabled_elements['premium-wrapper-link'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[11]['demo'] ); ?>" target="_blank"></a>
				</div>

				<div class="pa-section-outer-wrap">
					<div class="pa-section-info-wrap">
						<div class="pa-section-info">
						<h4><?php echo esc_html( $features[12]['title'] ); ?></h4>
							<p><?php echo esc_html( $features[12]['desc'] ); ?></p>
						</div>

						<div class="pa-section-info-cta">
							<label class="switch">
								<input type="checkbox" id="premium-glassmorphism" name="premium-glassmorphism" <?php echo checked( 1, $enabled_elements['premium-glassmorphism'], false ); ?>>
									<span class="slider round pa-control"></span>
								</label>
						</div>
					</div>
					<a href="<?php echo esc_url( $features[12]['demo'] ); ?>" target="_blank"></a>
				</div>

			</div>
			</form> <!-- End Form -->
		</div>
	</div>
</div> <!-- End Section Content -->
