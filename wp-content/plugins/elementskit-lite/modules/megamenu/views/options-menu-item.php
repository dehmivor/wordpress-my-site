<div class="attr-modal attr-fade" id="attr_menu_control_panel_modal" tabindex="-1" role="dialog">
	<div class="attr-modal-dialog attr-modal-dialog-centered" role="document">
		<div class="attr-modal-content ekit_menu_modal_content">
			<div class="attr-modal-header">
				<ul class="tb-nav tb-nav-tabs ekit_menu_control_nav" role="tablist">
					<li role="presentation" id="attr_content_nav" class="attr-active"><a class="attr-nav-link" href="#attr_content_tab" aria-controls="attr_content_tab"
							role="tab" data-attr-toggle="tab"><?php esc_html_e( 'Content', 'elementskit-lite' ); ?></a></li>
					<li role="presentation" id="attr_icon_nav"><a class="attr-nav-link ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal" href="#attr_icon_tab" aria-controls="attr_icon_tab" role="tab"
							data-attr-toggle="tab"><?php esc_html_e( 'Icon', 'elementskit-lite' ); ?></a></li>
					<li role="presentation" id="attr_badge_nav"><a class="attr-nav-link ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal" href="#attr_badge_tab" aria-controls="attr_badge_tab"
							role="tab" data-attr-toggle="tab"><?php esc_html_e( 'Badge', 'elementskit-lite' ); ?></a></li>
					<li role="presentation" id="attr_setting_nav"><a class="attr-nav-link" href="#attr_vertical_menu_setting_tab" aria-controls="attr_vertical_menu_setting_tab"
							role="tab" data-attr-toggle="tab"><?php esc_html_e( 'Setting', 'elementskit-lite' ); ?></a></li>
				</ul>
				<button class="btn-modal-close" type="button" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'elementskit-lite' ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
					</svg>
				</button>
			</div>
			<div class="attr-modal-body ekit-wid-con">
				<div class="attr-tab-content">
					<div role="tabpanel" class="attr-tab-pane attr-active" id="attr_content_tab">
						<?php if ( defined( 'ELEMENTOR_VERSION' ) ) : ?>
						<div class="ekit-mm-toggle-card">
							<div class="ekit-mm-toggle-text">
								<strong><?php esc_html_e( 'Megamenu Enabled', 'elementskit-lite' ); ?></strong>
								<span class="txt-desc"><?php esc_html_e( 'Show megamenu dropdown for this item', 'elementskit-lite' ); ?></span>
							</div>
							<div class="switch-wrapper">
								<input type="checkbox" value="1" id="elementskit-menu-item-enable" />
								<label for="elementskit-menu-item-enable"><span></span></label>
							</div>
						</div>

						<div id="elementskit-menu-builder-warper">
							<button disabled type="button" id="elementskit-menu-builder-trigger"
								class="elementskit-menu-elementor-button" data-attr-toggle="modal"
								data-target="#elementskit-menu-builder-modal">
								<svg class="elementor-icon" xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 30 30"><rect width="30" height="30" fill="#fff" rx="8"/><path fill="#ed01ee" d="M15 7a8 8 0 1 0 0 16 8 8 0 0 0 0-16m-2.4 12H11v-8h1.6zm6.4 0h-4.8v-1.6H19zm0-3.2h-4.8v-1.6H19zm0-3.2h-4.8V11H19z"/></svg>
								<span class="ekit-mm-builder-label"><?php esc_html_e( 'Edit Mega-Menu Content', 'elementskit-lite' ); ?></span>
								<svg class="ekit-mm-builder-arrow" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
									<path d="M3.75 9h10.5M9.75 4.5L14.25 9l-4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
								</svg>
							</button>

							<div id="mobile_submenu_content_type" class="ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal-container">
								<?php $ekit_mm_mobile_note = __( 'Builder content will render the megamenu layout on mobile devices.', 'elementskit-lite' ); ?>
								<div class="ekit-mm-group-head">
									<strong class="ekit-mm-group-title"><?php esc_html_e( 'Mobile Submenu Display', 'elementskit-lite' ); ?></strong>
									<span class="ekit-mm-tip" tabindex="0" role="img" aria-label="<?php echo esc_attr( $ekit_mm_mobile_note ); ?>">
										<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
											<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
											<path d="M8 10.75v-3.5M8 5.05h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
										</svg>
										<span class="ekit-mm-tip-text" aria-hidden="true"><?php echo esc_html( $ekit_mm_mobile_note ); ?></span>
									</span>
								</div>
								<div class="ekit-mm-choice-grid">
									<input class="ekit-mm-choice-input" type="radio" id="ekit_mm_content_type_builder" name="content_type" checked value="builder_content">
									<label class="ekit-mm-choice" for="ekit_mm_content_type_builder">
										<span class="ekit-mm-choice-icon">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
												<path d="M12.0718 18.3334H10.8337V17.0952C10.8337 16.7023 10.9898 16.3255 11.2676 16.0476L15.3233 11.9921C15.7572 11.5582 16.4607 11.5582 16.8945 11.9921L17.175 12.2726C17.6088 12.7065 17.6088 13.41 17.175 13.844L13.1192 17.8995C12.8415 18.1773 12.4647 18.3334 12.0718 18.3334Z" stroke="#13151D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M15.8337 8.33335V7.50002C15.8337 4.75016 15.8337 3.37523 14.9795 2.52096C14.1251 1.66669 12.7502 1.66669 10.0004 1.66669H8.33371C5.58384 1.66669 4.20891 1.66669 3.35463 2.52096C2.50037 3.37523 2.50037 4.75016 2.50037 7.50002V13.3334C2.50037 15.2824 2.50037 16.2569 2.94742 16.9684C3.18054 17.3394 3.49426 17.6532 3.86527 17.8863C4.57676 18.3334 5.55129 18.3334 7.50037 18.3334" stroke="#13151D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M9.16711 5H12.5004" stroke="#13151D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M5.83374 8.33331H12.5004" stroke="#13151D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M5.83374 11.6667H10.8337" stroke="#13151D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						 					</svg>
										</span>
										<span class="ekit-mm-choice-mark"></span>
										<span class="ekit-mm-choice-title"><?php esc_html_e( 'Builder Content', 'elementskit-lite' ); ?></span>
										<span class="ekit-mm-choice-desc"><?php esc_html_e( 'Use the Elementor builder layout', 'elementskit-lite' ); ?></span>
									</label>
									<input class="ekit-mm-choice-input" type="radio" id="ekit_mm_content_type_list" name="content_type" value="submenu_list">
									<label class="ekit-mm-choice" for="ekit_mm_content_type_list">
										<span class="ekit-mm-choice-icon">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
												<path d="M8.33008 4.16666H17.4968" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
												<path d="M2.49677 4.16666H4.5801" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
												<path d="M8.33008 10H17.4968" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
												<path d="M2.49677 10H4.5801" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
												<path d="M8.33008 15.8333H17.4968" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
												<path d="M2.49677 15.8333H4.5801" stroke="#13151D" stroke-width="1.5" stroke-linecap="round"/>
											</svg>
										</span>
										<span class="ekit-mm-choice-mark"></span>
										<span class="ekit-mm-choice-title"><?php esc_html_e( 'WP Submenu List', 'elementskit-lite' ); ?></span>
										<span class="ekit-mm-choice-desc"><?php esc_html_e( 'Use default WordPress menu items', 'elementskit-lite' ); ?></span>
									</label>
								</div>
							</div>
						</div>
						<?php else : ?>
						<p class="ekit-mm-note ekit-mm-note-warning no-elementor-notice">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
								<path d="M8 4.75v4M8 11.2h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
							</svg>
							<span><?php esc_html_e( 'This plugin requires Elementor page builder to edt megamenu items content', 'elementskit-lite' ); ?></span>
						</p>
						<?php endif; ?>
					</div>
					<div role="tabpanel" class="attr-tab-pane" id="attr_icon_tab">
						<p class="ekit-mm-note ekit-mm-locked-note">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
								<path d="M8 4.75v4M8 11.2h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
							</svg>
							<span><?php esc_html_e( 'Turn on Megamenu in the Content tab to use these options.', 'elementskit-lite' ); ?></span>
						</p>
						<div class="ekit-mm-panel ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal-container">
							<div class="ekit-mm-row">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Icon color', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<input type="text" value="#bada55" class="elementskit-menu-wpcolor-picker"
										id="elementskit-menu-icon-color-field" />
								</div>
							</div>
							<div class="ekit-mm-row">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Select icon', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<select id="elementskit-menu-icon-field" class="elementskit-menu-icon-picker">
										<option value=""><?php esc_html_e( 'No icon', 'elementskit-lite' ); ?></option>
										<?php
										foreach ( self::get_icons() as $icon_class ) {
											echo "<option value='" . esc_attr( $icon_class ) . "'>'" . esc_html( $icon_class ) . "'</option>";
										}
										?>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div role="tabpanel" class="attr-tab-pane" id="attr_badge_tab">
						<p class="ekit-mm-note ekit-mm-locked-note">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
								<path d="M8 4.75v4M8 11.2h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
							</svg>
							<span><?php esc_html_e( 'Turn on Megamenu in the Content tab to use these options.', 'elementskit-lite' ); ?></span>
						</p>
						<div class="ekit-mm-panel ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal ekit-<?php echo esc_attr(ElementsKit_Lite::package_type()); ?>-labal-container">
							<div class="ekit-mm-row">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Badge Text', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<input type="text" class="ekit-mm-input"
										placeholder="<?php esc_attr_e( 'e.g. New', 'elementskit-lite' ); ?>"
										id="elementskit-menu-badge-text-field" />
								</div>
							</div>

							<div class="ekit-df-row-wrap">
								<div class="ekit-mm-row ekit-df-row-stack">
									<span class="ekit-mm-row-label"><?php esc_html_e( 'Text color', 'elementskit-lite' ); ?></span>
									<div class="ekit-mm-row-control">
										<input type="text" class="elementskit-menu-wpcolor-picker" value="#ffffff"
											id="elementskit-menu-badge-color-field" />
									</div>
								</div>

								<div class="ekit-mm-row ekit-df-row-stack">
									<span class="ekit-mm-row-label"><?php esc_html_e( 'Background', 'elementskit-lite' ); ?></span>
									<div class="ekit-mm-row-control">
										<input type="text" class="elementskit-menu-wpcolor-picker" value="#bada55"
											id="elementskit-menu-badge-background-field" />
									</div>
								</div>
							</div>
						</div>
					</div>
					<div role="tabpanel" class="attr-tab-pane" id="attr_vertical_menu_setting_tab">
						<p class="ekit-mm-note ekit-mm-locked-note">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
								<path d="M8 4.75v4M8 11.2h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
							</svg>
							<span><?php esc_html_e( 'Turn on Megamenu in the Content tab to use these options.', 'elementskit-lite' ); ?></span>
						</p>
						<div class="ekit-mm-panel xs_menu_settings_panel">
							<div class="ekit-mm-row ekit-mm-row-stack" id="xs_megamenu_width_type">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Menu Width', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control ekit_width_lists">
									<div class="ekit-mm-segment">
										<input type="radio" name="width_type" id="width_type_default" value="default_width" checked>
										<label for="width_type_default"><?php esc_html_e( 'Default', 'elementskit-lite' ); ?></label>
										<input type="radio" id="width_type_full" name="width_type" value="full_width">
										<label for="width_type_full"><?php esc_html_e( 'Full Width', 'elementskit-lite' ); ?></label>
										<input type="radio" id="width_type_custom" name="width_type" value="custom_width">
										<label for="width_type_custom"><?php esc_html_e( 'Custom', 'elementskit-lite' ); ?></label>
									</div>
								</div>
							</div>
							<div class="ekit-mm-row menu-width-container">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Custom width', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<input type="text" class="ekit-mm-input" placeholder="<?php esc_attr_e( '750px', 'elementskit-lite' ); ?>" id="elementskit-menu-vertical-menu-width-field" />
								</div>
							</div>
							<div class="ekit-mm-row ekit-mm-row-stack" id="vertical_megamenu_position_type">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Menu Position', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<div class="ekit-mm-segment ekit-mm-segment-sm">
										<input type="radio" id="position_type_top" name="position_type" value="top_position">
										<label for="position_type_top"><?php esc_html_e( 'Default', 'elementskit-lite' ); ?></label>
										<input type="radio" name="position_type" id="position_type_relative" checked value="relative_position">
										<label for="position_type_relative"><?php esc_html_e( 'Relative', 'elementskit-lite' ); ?></label>
									</div>
								</div>
							</div>
							<div class="ekit-mm-row" id="enable_ajax_load">
								<span class="ekit-mm-row-label"><?php esc_html_e( 'Ajax load', 'elementskit-lite' ); ?></span>
								<div class="ekit-mm-row-control">
									<div class="ekit-mm-segment ekit-mm-segment-sm">
										<input type="radio" id="ajax_load_yes" name="megamenu_ajax_load" value="yes">
										<label for="ajax_load_yes"><?php esc_html_e( 'Yes', 'elementskit-lite' ); ?></label>
										<input type="radio" id="ajax_load_no" name="megamenu_ajax_load" checked value="no">
										<label for="ajax_load_no"><?php esc_html_e( 'No', 'elementskit-lite' ); ?></label>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="attr-modal-footer">
				<input type="hidden" id="elementskit-menu-modal-menu-id">
				<input type="hidden" id="elementskit-menu-modal-menu-has-child">
				<p class="ekit-mm-note ekit-mm-note-warning ekit-mm-save-error" hidden>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"></circle>
						<path d="M8 4.75v4M8 11.2h.008" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
					</svg>
					<span><?php esc_html_e( 'These settings could not be saved. Please reload the page and try again.', 'elementskit-lite' ); ?></span>
				</p>
				<div class="clearfix ekit-modal-controls">
					<div class="right-content">
						<span class='spinner'></span>
						<a class="ekit-mm-btn ekit-mm-btn-ghost" href="https://help.wpmet.com/docs/mega-menu-module/" target="_blank"><?php esc_html_e( 'Tutorial', 'elementskit-lite' ); ?></a>

						<?php echo wp_kses( get_submit_button( esc_html__( 'Save Changes', 'elementskit-lite' ), 'elementskit-menu-item-save ekit-mm-btn ekit-mm-btn-primary', '', false ), ['input'=>['class'=>[], 'type'=>[], 'value'=>[]]] ); ?>
					</div>
				</div>
			</div>
			<span id="elementskit-menu-modal-spinner" class='spinner'></span>
		</div>
	</div>
</div>

<div class="attr-modal attr-fade" id="elementskit-menu-builder-modal" tabindex="-1" role="dialog"
	aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
	<div class="attr-modal-dialog attr-modal-dialog-centered" role="document">
		<div class="attr-modal-content">
			<div class="attr-modal-body">
				<button class="ekit_close" type="button" data-dismiss="modal"><svg width="20" height="20"
						viewBox="0 0 20 20" xmlns="https://www.w3.org/2000/svg">
						<line fill="none" stroke="#fff" stroke-width="1.4" x1="1" y1="1" x2="19" y2="19"></line>
						<line fill="none" stroke="#fff" stroke-width="1.4" x1="19" y1="1" x2="1" y2="19"></line>
					</svg></button>
				<iframe id="elementskit-menu-builder-iframe" src="" frameborder="0"></iframe>
			</div>
		</div>
	</div>
</div>
