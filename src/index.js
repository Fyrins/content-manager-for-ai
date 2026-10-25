/**
 * Settings screen of WP MCP Abilities.
 */
import { createRoot } from '@wordpress/element';

import App from './components/App';
import './style.scss';

const root = document.getElementById( 'wpmcpa-settings' );

if ( root ) {
	createRoot( root ).render( <App path={ window.wpMcpAbilities?.path } /> );
}
