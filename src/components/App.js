/**
 * Loads the settings, renders the tabs and saves the switches.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SnackbarList, Spinner, TabPanel } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import AbilitiesTab from './AbilitiesTab';
import ConnectionTab from './ConnectionTab';

/**
 * Builds the payload the REST route expects from the rows on screen.
 *
 * A third-party ability its own plugin keeps closed is written to the
 * exposures, every other row to the switches.
 *
 * @param {Array} abilities  Rows of this plugin.
 * @param {Array} thirdParty Rows of other plugins.
 * @return {{enabled: Object, exposed: Object}} Body of the POST request.
 */
function toPayload( abilities, thirdParty ) {
	const enabled = {};
	const exposed = {};

	abilities.forEach( ( row ) => {
		enabled[ row.name ] = row.enabled;
	} );
	thirdParty.forEach( ( row ) => {
		if ( row.joinable ) {
			enabled[ row.name ] = row.enabled;
		} else {
			exposed[ row.name ] = row.enabled;
		}
	} );

	return { enabled, exposed };
}

/**
 * Applies a map of name => state to a list of rows.
 *
 * @param {Array}  rows    Rows to update.
 * @param {Object} changes Ability name => new state.
 * @return {Array} Updated rows.
 */
function applyChanges( rows, changes ) {
	return rows.map( ( row ) => ( row.name in changes ? { ...row, enabled: changes[ row.name ] } : row ) );
}

export default function App( { path } ) {
	const [ data, setData ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notices, setNotices ] = useState( [] );
	const [ currentTab, setCurrentTab ] = useState( 'connection' );
	// Held here so it survives a switch of tab: it is shown only once.
	const [ password, setPassword ] = useState( '' );

	useEffect( () => {
		apiFetch( { path } )
			.then( ( response ) => {
				setData( response );
				setSaved( response );
			} )
			.catch( ( e ) => setError( e.message ) );
	}, [ path ] );

	const isDirty = useMemo(
		() =>
			!! data &&
			JSON.stringify( toPayload( data.abilities, data.thirdParty ) ) !==
				JSON.stringify( toPayload( saved.abilities, saved.thirdParty ) ),
		[ data, saved ],
	);

	// Warns before leaving with unsaved switches, as a classic form would not.
	useEffect( () => {
		if ( ! isDirty ) {
			return;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ isDirty ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! data ) {
		return <Spinner />;
	}

	const updateOwn = ( changes ) =>
		setData( ( current ) => ( {
			...current,
			abilities: applyChanges( current.abilities, changes ),
		} ) );

	const updateThirdParty = ( changes ) =>
		setData( ( current ) => ( {
			...current,
			thirdParty: applyChanges( current.thirdParty, changes ),
		} ) );

	const save = () => {
		setIsSaving( true );

		apiFetch( {
			path,
			method: 'POST',
			data: toPayload( data.abilities, data.thirdParty ),
		} )
			.then( ( response ) => {
				setData( response );
				setSaved( response );
				setNotices( [
					{
						id: Date.now(),
						content: __( 'Settings saved.', 'wp-mcp-abilities' ),
					},
				] );
			} )
			.catch( ( e ) => setNotices( [ { id: Date.now(), content: e.message } ] ) )
			.finally( () => setIsSaving( false ) );
	};

	const enabledCount = data.abilities.filter( ( row ) => row.enabled ).length;

	const tabs = [
		{
			name: 'connection',
			title: __( 'Connection', 'wp-mcp-abilities' ),
		},
		{
			name: 'abilities',
			title: sprintf(
				/* translators: 1: number of enabled abilities, 2: number of abilities. */
				__( 'Abilities (%1$d/%2$d)', 'wp-mcp-abilities' ),
				enabledCount,
				data.abilities.length,
			),
		},
	];

	if ( data.thirdParty.length > 0 ) {
		tabs.push( {
			name: 'third-party',
			title: __( 'Other plugins', 'wp-mcp-abilities' ),
		} );
	}

	return (
		<div className="wpmcpa-app">
			<TabPanel className="wpmcpa-tabs" tabs={ tabs } onSelect={ setCurrentTab }>
				{ ( tab ) => {
					if ( 'connection' === tab.name ) {
						return (
							<ConnectionTab
								connection={ data.connection }
								password={ password }
								onPasswordCreated={ setPassword }
							/>
						);
					}

					if ( 'third-party' === tab.name ) {
						return (
							<AbilitiesTab
								rows={ data.thirdParty }
								onChange={ updateThirdParty }
								isThirdParty
								defaultServer={ data.connection.defaultServer }
							/>
						);
					}

					return <AbilitiesTab rows={ data.abilities } onChange={ updateOwn } />;
				} }
			</TabPanel>

			{ /* Only the abilities tabs hold switches; the save bar stays out of the way elsewhere unless something is pending. */ }
			{ ( 'connection' !== currentTab || isDirty ) && (
				<div className="wpmcpa-savebar">
					<Button variant="primary" onClick={ save } isBusy={ isSaving } disabled={ isSaving || ! isDirty }>
						{ __( 'Save settings', 'wp-mcp-abilities' ) }
					</Button>
					{ isDirty && (
						<span className="wpmcpa-savebar__hint">{ __( 'Unsaved changes', 'wp-mcp-abilities' ) }</span>
					) }
				</div>
			) }

			<SnackbarList
				className="wpmcpa-snackbars"
				notices={ notices }
				onRemove={ ( id ) => setNotices( ( current ) => current.filter( ( notice ) => notice.id !== id ) ) }
			/>
		</div>
	);
}
