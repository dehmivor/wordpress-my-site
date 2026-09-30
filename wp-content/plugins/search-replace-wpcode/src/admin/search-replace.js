/* global ajaxurl, wsrwjs */
const WSRSearchReplace = window.WSRSearchReplace || (
	function ( document, window, $ ) {
		// jquery-confirm defaults.
		jconfirm.defaults               = {
			closeIcon: true,
			closeIconClass: 'close-icon-svg',
			backgroundDismiss: false,
			escapeKey: true,
			animationBounce: 1,
			useBootstrap: false,
			theme: 'modern',
			boxWidth: '400px',
			type: 'blue',
			animateFromElement: false,
		};
		const app                       = {
			pages: 0,
			dry_run: true,
			init() {
				window.WSRSearchReplace = app;
				app.find_elements();
				app.init_form();
				app.init_upsell();
				app.init_search_history();
				app.init_history_modal();
				app.init_preloaded_search();
			},
			find_elements() {
				app.form                = $( '#wsrw-search-replace-form' );
				app.results             = $( '#wsrw-results' );
				app.progress            = $( '#wsrw-progress-bar-replace .wsrw-progress-bar-inner' );
				app.table               = $( '#wsrw-results-table' );
				app.start_button        = $( '#wsrw-start-replace' );
				app.do_button           = $( '#wsrw-perform-search-replace' );
				app.text_display        = $( '#wsrw-progress-text-replace' );
				app.modal               = $( '#wsrw-search-replace-progress' );
				app.undo_button         = $( '#wsrw-results-undo-button' );
				app.historyOpenBtn      = $( '#wsrw-open-history-modal' );
				app.historyModal        = $( '#wsrw-history-modal' );
				app.historyCloseBtn     = $( '#wsrw-close-history-modal' );
				app.selectedSearch      = $( '#wsrw-selected-search' );
				app.selectedSearchText  = $( '#wsrw-selected-search-text' );
				app.selectedSearchClear = $( '#wsrw-clear-selected-search' );
				app.historyList         = $( '#wsrw-search-history-list' );
				app.historySection      = $( '#wsrw-search-history-section' );
			},
			init_form() {
				app.form.on(
					'submit',
					function ( e ) {
						e.preventDefault();
						WSRSpinner.show_button_spinner( app.start_button );
						app.start_search_replace();
					}
				);
				app.do_button.on(
					'click',
					function ( e ) {
						e.preventDefault();
						// Jconfirm the user before proceeding.
						$.confirm(
							{
								title: wsrwjs.sr_confirm_title,
								content: wsrwjs.sr_confirm_message,
								type: 'blue',
								icon: 'fa fa-exclamation-circle',
								animateFromElement: false,
								buttons: {
									confirm: {
										text: wsrwjs.yes,
										btnClass: 'btn-confirm',
										keys: ['enter'],
									},
									cancel: {
										text: wsrwjs.no,
										btnClass: 'btn-cancel',
										keys: ['esc'],
									},
								},
								onAction: function ( action ) {
									if ( action === 'confirm' ) {
										app.do_button.prop( 'disabled', true );
										app.start_search_replace( false );
									}
								},
							}
						);

					}
				);
			},
			start_search_replace( dry_run = true ) {
				app.dry_run  = dry_run;
				const search = $( '#wsrw-search' ).val();
				const data   = {
					action: 'wsrw_start_search_replace',
					nonce: wsrwjs.nonce,
					search: search,
					replace: $( '#wsrw-replace' ).val(),
				};
				// If search term is empty return and show error.
				if ( search === '' ) {
					WSRSpinner.hide_button_spinner( app.start_button );
					$.alert(
						{
							title: wsrwjs.no_search_term_title,
							content: wsrwjs.no_search_term_message,
							type: 'blue',
							icon: 'fa fa-exclamation-circle',
							animateFromElement: false,
							buttons: {
								confirm: {
									text: wsrwjs.ok,
									btnClass: 'btn-confirm',
									keys: ['enter'],
								},
							},
						}
					);
					return;
				}
				if ( $( '#wsrw-case-insensitive' ).is( ':checked' ) ) {
					data.case_insensitive = 1;
				}
				if ( ! dry_run ) {
					data.dry_run = 0;
				}
				// Let's get the tables value from all the checkboxes with the name of tables[].
				const tables = $( 'input[name="tables[]"]:checked' ).map(
					function () {
						return $( this ).val();
					}
				).get();
				// If no tables selected, return and show error.
			if ( tables.length === 0 ) {
				WSRSpinner.hide_button_spinner( app.start_button );
				$.alert(
					{
						title: wsrwjs.no_table_selected_title,
						content: wsrwjs.no_table_selected_message,
						type: 'blue',
						icon: 'fa fa-exclamation-circle',
						animateFromElement: false,
						buttons: {
							confirm: {
								text: wsrwjs.ok,
								btnClass: 'btn-confirm',
								keys: ['enter'],
							},
						},
					}
				);
				return;
			}

				data['tables[]'] = tables;

				$( document ).trigger( 'wsr_before_start_search_replace', [data] );

				app.do_button.prop( 'disabled', true );
				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: data,
						beforeSend: function () {
							app.form.find( 'input, button' ).prop( 'disabled', true );
						},
						success: function ( response ) {
							// Let's show the modal at this stage.
							app.selectedSearch.hide();
							app.selectedSearchText.text( '' );
							app.undo_button.hide();
							$( 'body' ).addClass( 'wsrw-show-progress-modal wsrw-no-close' );
							app.modal.addClass( 'wsrw-progress-open' ).show();
							app.fit_results();
							WSRSpinner.hide_button_spinner( app.start_button );
							app.form.find( 'input, button' ).prop( 'disabled', false );
							app.pages = response.data.pages;
							// Remove all the rows from the table except the first one.
							app.table.find( 'tr:gt(0)' ).remove();
							app.do_search_replace( response.data );
						},
						error: function ( response ) {
							app.form.find( 'input, button' ).prop( 'disabled', false );
							app.show_results( response );
						}
					}
				);
			},
			do_search_replace() {
				const data = {
					action: 'wsrw_do_search_replace',
					nonce: wsrwjs.nonce,
				};
				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: data,
						success: function ( response ) {
							app.show_results( response );
							// Let's calculate the progress bar width.
							const progress = (
												response.data.page / app.pages
											) * 100;
							app.progress.css( 'width', progress + '%' );
							if ( response.data.page < response.data.pages ) {
								app.do_search_replace();
							} else {
								app.show_finish_message();
							}
						},
						error: function ( response ) {
							app.show_results( response );
						}
					}
				);
			},
			show_results( response ) {
				if ( response.data.updated_data && response.data.updated_data.length > 0 ) {
					// Loop through the updated data and show it.
					$.each(
						response.data.updated_data,
						function ( key, value ) {
							// The value here has the format of an object with the keys: table, column, row, old, new.
							app.table.append(
								$(
									'<tr data-table="' + value.table + '" data-row="' + value.row + '" data-column="' + value.column + '">' +
									'<td><span class="wsrw-check-row-input"><input type="checkbox" data-table="' + value.table + '" data-row="' + value.row + '" data-column="' + value.column + '" class="wsrw-check-row" /></span></td>' +
									'<td>' + value.table + '</td>' +
									'<td>' + value.column + '</td>' +
									'<td><button class="wsrw-row-info wsrw-button wsrw-button-text" type="button">' + value.row + '</button></td>' +
									'<td><pre>' + value.old + '</pre></td>' +
									'<td><pre>' + value.new + '</pre></td>' +
									'</tr>'
								)
							);
						}
					);
				}

				if ( response.data.message ) {
					app.display_text( response.data.message );
				}
			},
			show_finish_message() {
				// If no rows were added to the table, show a message.
				if ( app.table.find( 'tr' ).length === 1 ) {
					app.display_text( wsrwjs.no_results_found );
				} else {
					app.display_text( wsrwjs.finished );
				}
				// Remove the no-close class so the modal can be closed.
				$( 'body' ).removeClass( 'wsrw-no-close' );
				if ( ! app.dry_run ) {
					app.undo_button.show();
				} else {
					app.do_button.prop( 'disabled', false );
				}

				// Trigger event so we can clear data.
				$( document ).trigger( 'wsr_search_replace_finished' );
			},
			display_text( text ) {
				app.text_display.text( text );
			},
			fit_results() {
				// Let's fit the results box to the modal height.
				const modal_height = app.modal.height();
				let other_height   = 0;
				app.modal.children().each(
					function () {
						if ( ! $( this ).is( app.results ) ) {
							const childHeight = $( this ).outerHeight( true );
							other_height     += childHeight;
						}
					}
				);
				app.results.height( modal_height - other_height );
			},
			init_upsell() {
				// If the body class is "wsrw-not-licensed" we show upsell message when .wsrw-check-row is clicked.
				if ( ! $( 'body' ).hasClass( 'wsrw-not-licensed' ) ) {
					return;
				}
				app.table.on(
					'click',
					'.wsrw-check-row-input',
					function ( e ) {
						e.preventDefault();
						app.show_upsell( wsrwjs.check_row_title, wsrwjs.check_row_content, wsrwjs.check_row_url );
					}
				);
				app.table.on(
					'click',
					'.wsrw-row-info',
					function ( e ) {
						e.preventDefault();
						e.stopPropagation();
						app.show_upsell( wsrwjs.row_info_title, wsrwjs.row_info_content, wsrwjs.row_info_url );
					}
				);
			},
			show_upsell( title, content, button_url, button_text ) {
				// If button_text is not set let's default it to wsrwjs.upgrade_to_pro.
				button_text = button_text || wsrwjs.upgrade_to_pro;
				$.alert(
					{
						title: title,
						content: content,
						type: 'blue',
						animateFromElement: false,
						backgroundDismiss: true,
						boxWidth: '550px',
						draggable: false,
						buttons: {
							confirm: {
								text: button_text,
								btnClass: 'wsrw-button wsrw-button-large wsrw-button-orange',
								keys: ['enter'],
								action: function () {
									// Open in new window wsrwjs.check_row_url.
									window.open( button_url, '_blank' );
								},
							},
						},
						onOpenBefore() {
							if ( wsrwjs.upgrade_bonus ) {
								this.$btnc.after( '<div class="wsrw-discount-note">' + wsrwjs.upgrade_bonus + '</div>' );
								this.$body.find( '.jconfirm-content' ).addClass( 'wsrw-lite-upgrade' );
							}
							this.$icon.html( wsrwjs.lock_icon );
						},
						onContentReady() {
							this.$icon.html( wsrwjs.lock_icon );
						}
					}
				);
			},
			init_search_history() {
				// Handle Apply button click.
				app.historyList.on(
					'click',
					'.wsrw-search-history-apply',
					function ( e ) {
						e.preventDefault();
						const $item    = $( this ).closest( '.wsrw-search-history-item' );
						const $content = $item.find( '.wsrw-search-history-item-content' );
						app.populate_search_form( $content );
						const searchTerm  = $content.data( 'search' );
						const replaceTerm = $content.data( 'replace' );
						app.selectedSearchText.text( searchTerm + ( replaceTerm ? ' → ' + replaceTerm : '' ) );
						app.selectedSearch.show();
						app.close_history_modal();
						// Move this item to the top of the list visually.
						$item.fadeOut(
							200,
							function () {
								$item.prependTo( app.historyList ).fadeIn( 200 );
							}
						);
					}
				);

				// Handle Delete button click.
				app.historyList.on(
					'click',
					'.wsrw-search-history-delete',
					function ( e ) {
						e.preventDefault();
						const searchId = $( this ).data( 'search-id' );
						app.delete_search_history( searchId, $( this ) );
					}
				);

				// Clear selected preset.
				app.selectedSearchClear.on(
					'click',
					function ( e ) {
						e.preventDefault();
						app.reset_form();
					}
				);
			},
			init_history_modal() {
				if ( app.historyOpenBtn.length ) {
					app.historyOpenBtn.on(
						'click',
						function ( e ) {
							e.preventDefault();
							app.open_history_modal();
						}
					);
				}
				if ( app.historyCloseBtn.length ) {
					app.historyCloseBtn.on(
						'click',
						function ( e ) {
							e.preventDefault();
							app.close_history_modal();
						}
					);
				}
			},
			open_history_modal() {
				$( 'body' ).addClass( 'wsrw-show-history-modal' );
				app.historyModal.addClass( 'wsrw-history-open' ).show();
			},
			close_history_modal() {
				app.historyModal.removeClass( 'wsrw-history-open' ).hide();
				$( 'body' ).removeClass( 'wsrw-show-history-modal' );
			},
			populate_search_form( $content ) {
				const searchTerm      = $content.data( 'search' );
				const replaceTerm     = $content.data( 'replace' );
				let tables            = $content.data( 'tables' );
				const caseInsensitive = $content.data( 'case-insensitive' );

				// Parse tables if it's a string.
				if ( typeof tables === 'string' ) {
					try {
						tables = JSON.parse( tables );
					} catch ( e ) {
						tables = [];
					}
				}

				// Populate search and replace fields.
				$( '#wsrw-search' ).val( searchTerm );
				$( '#wsrw-replace' ).val( replaceTerm );

				// Set case insensitive checkbox.
				$( '#wsrw-case-insensitive' ).prop( 'checked', caseInsensitive === 1 );

				// Uncheck all table checkboxes first.
				$( 'input[name="tables[]"]' ).prop( 'checked', false ).trigger( 'change' );

				// Check the tables that were selected.
				if ( tables && Array.isArray( tables ) && tables.length > 0 ) {
					tables.forEach(
						function ( table ) {
							// Escape special characters in the selector.
							const escapedTable = table.replace( /[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&' );
							const $checkbox    = $( 'input[name="tables[]"][value="' + escapedTable + '"]' );
							if ( $checkbox.length ) {
								$checkbox.prop( 'checked', true ).trigger( 'change' );
							}
						}
					);
				}

				// Scroll to the form.
				$( 'html, body' ).animate(
					{
						scrollTop: app.form.offset().top - 50
					},
					500
				);
			},
			delete_search_history( searchId, $button ) {
				const $item       = $button.closest( '.wsrw-search-history-item' );
				const isFirstItem = $item.is( ':first-child' );

				const data = {
					action: 'wsrw_delete_search_history',
					nonce: wsrwjs.nonce,
					search_id: searchId,
				};

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: data,
						beforeSend: function () {
							$button.prop( 'disabled', true );
						},
						success: function ( response ) {
							if ( response.success ) {
								// Check if this is the currently preloaded search (first item).
								const shouldResetForm = isFirstItem && app.selectedSearch.is( ':visible' );

								// Remove the history item from the DOM.
								$item.fadeOut(
									300,
									function () {
										$( this ).remove();
										// Update the count in the header.
										const count = app.historyList.find( '.wsrw-search-history-item' ).length;
										if ( count === 0 ) {
											// Hide the entire history section if no items left.
											app.historySection.fadeOut( 300 );
											// Also close the modal if open.
											app.close_history_modal();
											// Reset form since no history remains.
											app.reset_form();
										} else {
											// If we deleted the currently preloaded search, reset the form.
											if ( shouldResetForm ) {
												app.reset_form();
											}
										}
									}
								);
							} else {
								$.alert( response.data.message );
								$button.prop( 'disabled', false );
							}
						},
					}
				);
			},
			reset_form() {
				// Clear fields.
				$( '#wsrw-search' ).val( '' );
				$( '#wsrw-replace' ).val( '' );
				$( '#wsrw-case-insensitive' ).prop( 'checked', false );
				$( 'input[name="tables[]"]' ).prop( 'checked', false ).trigger( 'change' );
				app.selectedSearch.hide();
				app.selectedSearchText.text( '' );
			},
			init_preloaded_search() {
				// Check if form has preloaded data.
				if ( app.form.length && app.form.data( 'has-preload' ) === 1 ) {
					// Use setTimeout to ensure the multi-select interface is ready.
					setTimeout(
						function () {
							// Trigger change event on checked checkboxes to populate the second column.
							$( 'input[name="tables[]"]:checked' ).each(
								function () {
									$( this ).trigger( 'change' );
								}
							);
						},
						100
					);

					// Show the preset indicator.
					const searchTerm  = app.form.data( 'preload-search' );
					const replaceTerm = app.form.data( 'preload-replace' );

					if ( searchTerm ) {
						const displayText = searchTerm + ( replaceTerm ? ' → ' + replaceTerm : '' );
						app.selectedSearchText.text( displayText );
						app.selectedSearch.show();
					}
				}
			}
		};
		return app;
	}( document, window, jQuery )
);

WSRSearchReplace.init();