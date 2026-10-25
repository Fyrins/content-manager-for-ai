/**
 * Everything a client needs to connect: server URL, credentials, config.
 */
import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Placeholder shown in the snippets until a password is created on screen.
 */
const PASSWORD_PLACEHOLDER = 'YOUR_APPLICATION_PASSWORD';

/**
 * Base64 of a string that may hold non-ASCII characters, as a login can.
 *
 * @param {string} value Text to encode.
 * @return {string} Base64 of its UTF-8 bytes.
 */
function base64( value ) {
	const bytes = new TextEncoder().encode( value );

	return window.btoa( String.fromCharCode( ...bytes ) );
}

/**
 * A copy button that says when it copied.
 *
 * @param {Object} props
 * @param {string} props.text  Text to copy.
 * @param {string} props.label Accessible name of the button.
 */
function CopyButton( { text, label } ) {
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => {
		setCopied( true );
		// The button keeps its accessible name, so the change of its text is
		// not read out: announce it.
		speak( __( 'Copied!', 'wp-mcp-abilities' ) );
		window.setTimeout( () => setCopied( false ), 2000 );
	} );

	return (
		<Button
			variant="secondary"
			size="compact"
			ref={ ref }
			label={ label }
			showTooltip={ false }
		>
			{ copied
				? __( 'Copied!', 'wp-mcp-abilities' )
				: __( 'Copy', 'wp-mcp-abilities' ) }
		</Button>
	);
}

/**
 * A code block with its copy button.
 *
 * @param {Object} props
 * @param {string} props.code  Code to show and copy.
 * @param {string} props.label Accessible name of the copy button.
 */
function Snippet( { code, label } ) {
	return (
		<div className="wpmcpa-snippet">
			<pre>
				<code>{ code }</code>
			</pre>
			<CopyButton text={ code } label={ label } />
		</div>
	);
}

/**
 * The snippets of every supported client.
 *
 * @param {Object} props
 * @param {Object} props.connection Connection details from the REST route.
 * @param {string} props.password   Application password created on screen, if any.
 */
function ClientSnippets( { connection, password } ) {
	const { endpoint, serverName, userLogin } = connection;
	const hasPassword = !! password;
	const token = hasPassword
		? base64( `${ userLogin }:${ password }` )
		: 'BASE64_OF_USERNAME_COLON_APPLICATION_PASSWORD';
	const header = `Authorization: Basic ${ token }`;

	const httpConfig = JSON.stringify(
		{
			mcpServers: {
				[ serverName ]: {
					type: 'http',
					url: endpoint,
					headers: { Authorization: `Basic ${ token }` },
				},
			},
		},
		null,
		2
	);

	const cursorConfig = JSON.stringify(
		{
			mcpServers: {
				[ serverName ]: {
					url: endpoint,
					headers: { Authorization: `Basic ${ token }` },
				},
			},
		},
		null,
		2
	);

	const desktopConfig = JSON.stringify(
		{
			mcpServers: {
				[ serverName ]: {
					command: 'npx',
					args: [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
					env: {
						WP_API_URL: endpoint,
						WP_API_USERNAME: userLogin,
						WP_API_PASSWORD: password || PASSWORD_PLACEHOLDER,
					},
				},
			},
		},
		null,
		2
	);

	// The login goes between single quotes: one inside it closes the quote,
	// is escaped, and reopens it.
	const quotedLogin = userLogin.replace( /'/g, "'\\''" );
	const tokenCommand = `read -rsp 'Application password: ' WPMCPA_PW && printf '%s' '${ quotedLogin }:'"$WPMCPA_PW" | base64 && unset WPMCPA_PW`;

	const clients = [
		{
			name: 'claude-code',
			title: 'Claude Code',
			render: () => (
				<>
					<p>
						{ __(
							'Run this in a terminal. Add --scope user to reach the site from every project. The command carries the password, only Base64-encoded: it stays in your shell history unless you remove it.',
							'wp-mcp-abilities'
						) }
					</p>
					<Snippet
						code={ `claude mcp add --transport http ${ serverName } ${ endpoint } --header "${ header }"` }
						label={ __(
							'Copy the Claude Code command',
							'wp-mcp-abilities'
						) }
					/>
				</>
			),
		},
		{
			name: 'claude-desktop',
			title: 'Claude Desktop',
			render: () => (
				<>
					<p>
						{ __(
							'Open Settings → Developer → Edit Config, then paste this into the file. Claude Desktop reaches the site through a small proxy run with npx, so Node.js has to be installed.',
							'wp-mcp-abilities'
						) }
					</p>
					<dl className="wpmcpa-paths">
						<dt>macOS</dt>
						<dd>
							<code>
								~/Library/Application
								Support/Claude/claude_desktop_config.json
							</code>
						</dd>
						<dt>Windows</dt>
						<dd>
							<code>
								%APPDATA%\Claude\claude_desktop_config.json
							</code>
						</dd>
					</dl>
					<Snippet
						code={ desktopConfig }
						label={ __(
							'Copy the Claude Desktop configuration',
							'wp-mcp-abilities'
						) }
					/>
				</>
			),
		},
		{
			name: 'cursor',
			title: 'Cursor',
			render: () => (
				<>
					<p>
						{ createInterpolateElement(
							__(
								'Paste this into <code>~/.cursor/mcp.json</code>, or into <code>.cursor/mcp.json</code> at the root of a project.',
								'wp-mcp-abilities'
							),
							{ code: <code /> }
						) }
					</p>
					<Snippet
						code={ cursorConfig }
						label={ __(
							'Copy the Cursor configuration',
							'wp-mcp-abilities'
						) }
					/>
				</>
			),
		},
		{
			name: 'other',
			title: __( 'Other client', 'wp-mcp-abilities' ),
			render: () => (
				<>
					<p>
						{ __(
							'The exact format depends on the client. One speaking HTTP usually takes something like this.',
							'wp-mcp-abilities'
						) }
					</p>
					<Snippet
						code={ httpConfig }
						label={ __(
							'Copy the configuration',
							'wp-mcp-abilities'
						) }
					/>
				</>
			),
		},
	];

	return (
		<>
			<TabPanel className="wpmcpa-clients" tabs={ clients }>
				{ ( client ) => client.render() }
			</TabPanel>
			{ ! hasPassword && (
				<div className="wpmcpa-token-help">
					<p>
						{ __(
							'Already have an application password? This command turns it into the Base64 value the snippets expect, without leaving it in your shell history.',
							'wp-mcp-abilities'
						) }
					</p>
					<Snippet
						code={ tokenCommand }
						label={ __(
							'Copy the Base64 command',
							'wp-mcp-abilities'
						) }
					/>
				</div>
			) }
		</>
	);
}

/**
 * Creates an application password for the current user, on screen.
 *
 * @param {Object}   props
 * @param {Object}   props.connection Connection details from the REST route.
 * @param {string}   props.password   Password created on screen, if any.
 * @param {Function} props.onCreated  Receives the new password.
 */
function PasswordCreator( { connection, password, onCreated } ) {
	const [ name, setName ] = useState(
		__( 'MCP client', 'wp-mcp-abilities' )
	);
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	if ( ! connection.appPasswordsAvailable ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Application passwords are not available for your account. WordPress only offers them on sites served over HTTPS, or on a local environment, and a plugin may also switch them off.',
					'wp-mcp-abilities'
				) }
			</Notice>
		);
	}

	if ( password ) {
		return (
			<Notice status="success" isDismissible={ false }>
				{ __(
					'Application password created and filled into the snippets below. Copy the one you need now: the password will not be shown again once you leave this page.',
					'wp-mcp-abilities'
				) }
			</Notice>
		);
	}

	const create = () => {
		setIsBusy( true );
		setError( null );

		apiFetch( {
			path: '/wp/v2/users/me/application-passwords',
			method: 'POST',
			data: { name },
		} )
			.then( ( response ) => onCreated( response.password ) )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setIsBusy( false ) );
	};

	return (
		<div className="wpmcpa-password">
			<div className="wpmcpa-password__row">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __(
						'Application password name',
						'wp-mcp-abilities'
					) }
					value={ name }
					onChange={ setName }
				/>
				<Button
					variant="primary"
					__next40pxDefaultSize
					onClick={ create }
					isBusy={ isBusy }
					disabled={ isBusy || ! name.trim() }
				>
					{ __(
						'Create an application password',
						'wp-mcp-abilities'
					) }
				</Button>
			</div>
			<p className="description">
				{ /* The login goes in as an element, never into the string the
				interpolation parses: a login holding a tag would break it. */ }
				{ createInterpolateElement(
					__(
						'Created for your account, <login />: the client will act with exactly your rights. You can revoke it at any time from <a>your profile</a>.',
						'wp-mcp-abilities'
					),
					{
						login: <strong>{ connection.userLogin }</strong>,
						// eslint-disable-next-line jsx-a11y/anchor-has-content -- Filled by createInterpolateElement.
						a: <a href={ connection.profileUrl } />,
					}
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</div>
	);
}

export default function ConnectionTab( {
	connection,
	password,
	onPasswordCreated,
} ) {
	return (
		<div className="wpmcpa-connection">
			{ ! connection.mcpAdapter && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The MCP Adapter plugin is not running. The abilities are registered, but no MCP client can reach them until it is installed and active.',
						'wp-mcp-abilities'
					) }{ ' ' }
					<ExternalLink href={ connection.adapterUrl }>
						{ __( 'Download the MCP Adapter', 'wp-mcp-abilities' ) }
					</ExternalLink>
				</Notice>
			) }
			{ ! connection.defaultServer && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This site switches off the default MCP server, so the URL below does not answer. Use the URL of the server your code declares instead.',
						'wp-mcp-abilities'
					) }
				</Notice>
			) }

			<Card className="wpmcpa-card" size="medium">
				<CardHeader>
					<h2 className="wpmcpa-card__title">
						{ __( 'MCP server URL', 'wp-mcp-abilities' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'The address to give your AI client. It acts as a WordPress user, within that user’s capabilities, and only reaches the abilities enabled on this screen.',
							'wp-mcp-abilities'
						) }
					</p>
					<div className="wpmcpa-url">
						<code>{ connection.endpoint }</code>
						<CopyButton
							text={ connection.endpoint }
							label={ __(
								'Copy the server URL',
								'wp-mcp-abilities'
							) }
						/>
					</div>
				</CardBody>
			</Card>

			<Card className="wpmcpa-card" size="medium">
				<CardHeader>
					<h2 className="wpmcpa-card__title">
						<span className="wpmcpa-step">1</span>
						{ __( 'Create the credentials', 'wp-mcp-abilities' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'The client signs in with an application password. Pick the account deliberately: a dedicated user with the lowest role that covers the job is the safest choice.',
							'wp-mcp-abilities'
						) }
					</p>
					<PasswordCreator
						connection={ connection }
						password={ password }
						onCreated={ onPasswordCreated }
					/>
				</CardBody>
			</Card>

			<Card className="wpmcpa-card" size="medium">
				<CardHeader>
					<h2 className="wpmcpa-card__title">
						<span className="wpmcpa-step">2</span>
						{ __( 'Configure your client', 'wp-mcp-abilities' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<ClientSnippets
						connection={ connection }
						password={ password }
					/>
				</CardBody>
			</Card>

			<Card className="wpmcpa-card" size="medium">
				<CardHeader>
					<h2 className="wpmcpa-card__title">
						<span className="wpmcpa-step">3</span>
						{ __( 'Check that it works', 'wp-mcp-abilities' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<ol>
						<li>
							{ __(
								'Restart the client, then ask it which WordPress abilities it can use.',
								'wp-mcp-abilities'
							) }
						</li>
						<li>
							{ createInterpolateElement(
								__(
									'It should call <code>mcp-adapter-discover-abilities</code> and list the abilities checked in the Abilities tab.',
									'wp-mcp-abilities'
								),
								{ code: <code /> }
							) }
						</li>
						<li>
							{ __(
								'A 401 error means the credentials are wrong; a 404 means the MCP Adapter is not active.',
								'wp-mcp-abilities'
							) }
						</li>
					</ol>
				</CardBody>
			</Card>
		</div>
	);
}
