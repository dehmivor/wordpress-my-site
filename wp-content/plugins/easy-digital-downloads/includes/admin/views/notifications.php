<?php
/**
 * React mount point for the notifications panel.
 *
 * The React app renders into this element and uses the data-count attribute
 * to initialize the notification count.
 *
 * @package   easy-digital-downloads
 * @copyright Copyright (c) 2021, Easy Digital Downloads
 * @license   GPL2+
 * @since     2.11.4
 */
?>
<div id="edd-notifications-root" class="edd-hidden"
	data-count="<?php echo esc_attr( EDD()->notifications->countActiveNotifications() ); ?>">
</div>
