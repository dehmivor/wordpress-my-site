/**
 * Upgrade Prompt Component
 *
 * Modal prompt for users who need to upgrade to use checkout templates.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { Modal, Button, Notice } from '@wordpress/components';
import { useRef, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store/constants';

/**
 * UpgradePrompt component
 *
 * Shows a modal prompting users to upgrade their license or resolve license issues.
 * Handles different scenarios: Lite users, expired licenses, inactive licenses.
 * Manages focus for accessibility.
 *
 * @param {Object}   props          Component props.
 * @param {boolean}  props.isOpen   Whether the prompt is open.
 * @param {Function} props.onClose  Callback when prompt is closed.
 * @param {string}   props.reason   Reason for showing prompt ('license' | 'editor').
 * @param {boolean}  props.inline   When true, render as section instead of Modal.
 * @return {JSX.Element|null} The UpgradePrompt component or null if closed.
 */
const UpgradePrompt = ( { isOpen, onClose, reason = 'license', inline = false } ) => {
	const primaryButtonRef = useRef( null );

	// Focus the primary action button when dialog opens.
	useEffect( () => {
		if ( inline ) {
			return;
		}
		if ( isOpen && primaryButtonRef.current ) {
			const timeoutId = setTimeout( () => {
				primaryButtonRef.current?.focus();
			}, 100 );
			return () => clearTimeout( timeoutId );
		}
	}, [ isOpen, inline ] );

	const { upgradeUrl, accountUrl, licenseStatus, isLite } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			upgradeUrl: store.getCheckoutPage()?.upgradeUrl || globalThis.eddCheckoutTemplates?.upgradeUrl,
			accountUrl: globalThis.eddCheckoutTemplates?.accountUrl,
			licenseStatus: store.getLicenseStatus(),
			isLite: store.getIsLite(),
		};
	}, [] );

	if ( ! inline && ! isOpen ) {
		return null;
	}

	/**
	 * Handle license refresh click.
	 * Redirects to the EDD license settings page.
	 */
	const handleRefreshLicense = () => {
		// Redirect to EDD license settings page
		globalThis.location.href = globalThis.eddCheckoutTemplates?.licensesUrl || '/wp-admin/edit.php?post_type=download&page=edd-settings';
	};

	const getTitle = () => {
		// Lite users always see the upgrade prompt, regardless of license status.
		if ( isLite ) {
			return __( 'Upgrade Required', 'easy-digital-downloads' );
		}

		switch ( licenseStatus ) {
			case 'expired':
				return __( 'License Expired', 'easy-digital-downloads' );
			case 'inactive':
			case 'disabled':
				return __( 'License Inactive', 'easy-digital-downloads' );
			case 'invalid':
				return __( 'License Invalid', 'easy-digital-downloads' );
			case 'missing':
				return __( 'License Required', 'easy-digital-downloads' );
			default:
				return __( 'Upgrade Required', 'easy-digital-downloads' );
		}
	};

	const getMessage = () => {
		// Lite users always see the upgrade-to-pro message, regardless of license status.
		if ( isLite ) {
			return __(
				'Checkout templates are a Pro feature. Upgrade to EDD Pro to access beautiful, conversion-optimized checkout templates.',
				'easy-digital-downloads'
			);
		}

		switch ( licenseStatus ) {
			case 'expired':
				return __(
					'Your EDD license has expired. Renew your license to continue importing checkout templates and accessing other Pro features.',
					'easy-digital-downloads'
				);
			case 'inactive':
			case 'disabled':
				return __(
					'Your EDD license is inactive. Please activate your license in the EDD settings to import checkout templates.',
					'easy-digital-downloads'
				);
			case 'invalid':
				return __(
					'Your license key appears to be invalid. Please check your license key in the EDD settings or contact support.',
					'easy-digital-downloads'
				);
			case 'missing':
				return __(
					'No license key found. Please enter your EDD Pro license key in the settings to access checkout templates.',
					'easy-digital-downloads'
				);
			default:
				return __(
					'Checkout templates are a Pro feature. Upgrade to EDD Pro to access beautiful, conversion-optimized checkout templates.',
					'easy-digital-downloads'
				);
		}
	};

	const getActions = () => {
		// Inactive or disabled license - show refresh button (pro only; Lite falls through to Upgrade to Pro).
		if ( ! isLite && ( licenseStatus === 'inactive' || licenseStatus === 'disabled' || licenseStatus === 'invalid' || licenseStatus === 'missing' ) ) {
			return (
				<>
					{ ! inline && (
						<Button variant="secondary" onClick={ onClose }>
							{ __( 'Cancel', 'easy-digital-downloads' ) }
						</Button>
					) }
					<Button
						ref={ primaryButtonRef }
						className="edd-checkout-templates__upgrade-cta"
						variant="primary"
						onClick={ handleRefreshLicense }
					>
						{ __( 'Manage License', 'easy-digital-downloads' ) }
					</Button>
				</>
			);
		}

		// Expired license - show renew button (pro only; Lite falls through to Upgrade to Pro).
		if ( ! isLite && licenseStatus === 'expired' ) {
			return (
				<>
					{ ! inline && (
						<Button variant="secondary" onClick={ onClose }>
							{ __( 'Maybe Later', 'easy-digital-downloads' ) }
						</Button>
					) }
					<Button
						ref={ primaryButtonRef }
						className="edd-checkout-templates__upgrade-cta"
						variant="primary"
						href={ accountUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Renew License', 'easy-digital-downloads' ) }
					</Button>
				</>
			);
		}

		// Default - upgrade prompt for Lite users
		return (
			<>
				{ ! inline && (
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Maybe Later', 'easy-digital-downloads' ) }
					</Button>
				) }
				<Button
					ref={ primaryButtonRef }
					className="edd-checkout-templates__upgrade-cta"
					variant="primary"
					href={ upgradeUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Upgrade to Pro', 'easy-digital-downloads' ) }
				</Button>
			</>
		);
	};

	if ( inline ) {
		return (
			<section
				className="edd-checkout-templates__upgrade-prompt"
				aria-labelledby="edd-cti-upgrade-heading"
				aria-describedby="upgrade-prompt-message"
			>
				<h2
					id="edd-cti-upgrade-heading"
					className="edd-checkout-templates__upgrade-prompt-title edd-checkout-templates__footer-panel-title screen-reader-text"
				>
					{ getTitle() }
				</h2>
				<Notice status="info" isDismissible={ false }>
					<p id="upgrade-prompt-message">{ getMessage() }</p>
					{ getActions() }
				</Notice>
			</section>
		);
	}

	return (
		<Modal
			title={ getTitle() }
			onRequestClose={ onClose }
			className="edd-checkout-templates__upgrade-prompt"
			aria-describedby="upgrade-prompt-message"
		>
			<div className="edd-checkout-templates__upgrade-prompt-content">
				<Notice status="info" isDismissible={ false }>
					<p id="upgrade-prompt-message">{ getMessage() }</p>
				</Notice>
			</div>

			<fieldset
				className="edd-checkout-templates__upgrade-prompt-actions"
			>
				<legend className="screen-reader-text">
					{ __( 'License actions', 'easy-digital-downloads' ) }
				</legend>
				{ getActions() }
			</fieldset>
		</Modal>
	);
};

export default UpgradePrompt;
