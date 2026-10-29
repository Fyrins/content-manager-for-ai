/**
 * Loads the settings, renders the tabs and saves the switches.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SnackbarList,
	Spinner,
	VisuallyHidden,
	TabPanel,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import AbilitiesTab from './AbilitiesTab';
import ConnectionTab from './ConnectionTab';

/**
 * Builds the payload the REST route expects from the rows on screen.
 *
 * Each row names the parameter it belongs to in `option`, as decided by the
 * server: `enabled` for the switches, `exposed` for third-party abilities
 * their own plugin keeps closed.
 *
 * @param {Array} abilities  Rows of this plugin.
 * @param {Array} thirdParty Rows of other plugins.
 * @return {{enabled: Object, exposed: Object}} Body of the POST request.
 */
function toPayload( abilities, thirdParty ) {
	const payload = { enabled: {}, exposed: {} };

	[ ...abilities, ...thirdParty ].forEach( ( row ) => {
		payload[ row.option ][ row.name ] = row.enabled;
	} );

	return payload;
}

/**
 * Applies a map of name => state to a list of rows.
 *
 * @param {Array}  rows    Rows to update.
 * @param {Object} changes Ability name => new state.
 * @return {Array} Updated rows.
 */
function applyChanges( rows, changes ) {
	return rows.map( ( row ) =>
		row.name in changes ? { ...row, enabled: changes[ row.name ] } : row
	);
}

export default function App( { path } ) {
	const [ data, setData ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notices, setNotices ] = useState( [] );
	const [ saveError, setSaveError ] = useState( null );
	const [ currentTab, setCurrentTab ] = useState( 'connection' );
	// Held here so it survives a switch of tab: it is shown only once.
	const [ password, setPassword ] = useState( '' );

	useEffect( () => {
		apiFetch( { path } )
			.then( ( response ) => {
				setData( response );
				setSaved( response );
			} )
			.catch( ( e ) =>
				setError(
					e?.message ||
						__( 'Could not load the settings.', 'content-manager-for-ai-agents' )
				)
			);
	}, [ path ] );

	const isDirty = useMemo(
		() =>
			!! data &&
			JSON.stringify( toPayload( data.abilities, data.thirdParty ) ) !==
				JSON.stringify(
					toPayload( saved.abilities, saved.thirdParty )
				),
		[ data, saved ]
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
		return (
			<div role="status">
				<Spinner />
				<VisuallyHidden>
					{ __( 'Loading the settings…', 'content-manager-for-ai-agents' ) }
				</VisuallyHidden>
			</div>
		);
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
		setSaveError( null );

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
						content: __( 'Settings saved.', 'content-manager-for-ai-agents' ),
					},
				] );
			} )
			// A failure stays on screen until the next attempt: a snackbar
			// would vanish before it is read, and looks like a success.
			.catch( ( e ) =>
				setSaveError(
					e?.message ||
						__( 'Could not save the settings.', 'content-manager-for-ai-agents' )
				)
			)
			.finally( () => setIsSaving( false ) );
	};

	const enabledCount = data.abilities.filter( ( row ) => row.enabled ).length;

	const tabs = [
		{
			name: 'connection',
			title: __( 'Connection', 'content-manager-for-ai-agents' ),
		},
		{
			name: 'abilities',
			title: sprintf(
				/* translators: 1: number of enabled abilities, 2: number of abilities. */
				__( 'Abilities (%1$d/%2$d)', 'content-manager-for-ai-agents' ),
				enabledCount,
				data.abilities.length
			),
		},
	];

	if ( data.thirdParty.length > 0 ) {
		tabs.push( {
			name: 'third-party',
			title: __( 'Other plugins', 'content-manager-for-ai-agents' ),
		} );
	}

	return (
		<div className="cm4ai-app">
			<TabPanel
				className="cm4ai-tabs"
				tabs={ tabs }
				onSelect={ setCurrentTab }
			>
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

					return (
						<AbilitiesTab
							rows={ data.abilities }
							onChange={ updateOwn }
						/>
					);
				} }
			</TabPanel>

			{ /* Only the abilities tabs hold switches; the save bar stays out of the way elsewhere unless something is pending. */ }
			{ ( 'connection' !== currentTab || isDirty || saveError ) && (
				<div className="cm4ai-savebar">
					{ saveError && (
						<Notice
							status="error"
							className="cm4ai-savebar__error"
							onRemove={ () => setSaveError( null ) }
						>
							{ saveError }
						</Notice>
					) }
					<Button
						variant="primary"
						onClick={ save }
						isBusy={ isSaving }
						disabled={ isSaving || ! isDirty }
					>
						{ __( 'Save settings', 'content-manager-for-ai-agents' ) }
					</Button>
					{ isDirty && (
						<span className="cm4ai-savebar__hint">
							{ __( 'Unsaved changes', 'content-manager-for-ai-agents' ) }
						</span>
					) }
				</div>
			) }

			<SnackbarList
				className="cm4ai-snackbars"
				notices={ notices }
				onRemove={ ( id ) =>
					setNotices( ( current ) =>
						current.filter( ( notice ) => notice.id !== id )
					)
				}
			/>
		</div>
	);
}
