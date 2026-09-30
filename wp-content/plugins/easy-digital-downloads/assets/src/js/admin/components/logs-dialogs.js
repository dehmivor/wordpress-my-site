/**
 * Log list table dialogs.
 *
 * Wires up the native <dialog class="edd-modal"> modals used by the admin log
 * list tables (gateway errors and API requests). Delegation mode means one
 * listener covers every row's "view" trigger, including rows rendered later.
 *
 * @package EDD
 */

import { setupEddModal } from '@easy-digital-downloads/modal';

setupEddModal( { trigger: '.edd-logs__view-dialog' } );
