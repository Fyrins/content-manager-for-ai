/**
 * Settings screen of Content Manager for AI Agents.
 */
import { createRoot } from '@wordpress/element';

import App from './components/App';
import './style.scss';

const root = document.getElementById( 'cm4ai-settings' );

if ( root ) {
	createRoot( root ).render( <App path={ window.cm4ai?.path } /> );
}
