/**
 * One group of abilities, split into reading and writing.
 */
import {
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Whether a third-party row is closed to MCP until an administrator opens it.
 *
 * @param {Object}  row          Ability row.
 * @param {boolean} isThirdParty Whether the row belongs to another plugin.
 */
const isHiddenByDefault = ( row, isThirdParty ) =>
	isThirdParty && 'exposed' === row.option;

/**
 * Checkbox of a single ability, with its details written underneath.
 *
 * The details used to sit behind a "?" tooltip, which told nothing when the
 * description only repeated the label, as SEOPress's do. They are shown now,
 * the description only when it adds something, and stay tied to the checkbox
 * through `aria-describedby`.
 *
 * @param {Object}   props
 * @param {Object}   props.row          Ability row.
 * @param {Function} props.onChange     Receives a name => state map.
 * @param {boolean}  props.isThirdParty Whether the row belongs to another plugin.
 */
function AbilityCheckbox( { row, onChange, isThirdParty } ) {
	const label = row.label || row.name;
	const normalize = ( text ) =>
		( text || '' )
			.trim()
			.replace( /[.\s]+$/, '' )
			.toLowerCase();
	const description =
		row.description && normalize( row.description ) !== normalize( label )
			? row.description
			: '';
	const detailsId = useInstanceId(
		AbilityCheckbox,
		'wpmcpa-ability-details'
	);

	return (
		<li className="wpmcpa-ability">
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ label }
				checked={ row.enabled }
				aria-describedby={ detailsId }
				onChange={ ( checked ) =>
					onChange( { [ row.name ]: checked } )
				}
			/>
			<div id={ detailsId } className="wpmcpa-ability__details">
				{ ( row.destructive ||
					isHiddenByDefault( row, isThirdParty ) ) && (
					<div className="wpmcpa-ability__badges">
						{ row.destructive && (
							<span className="wpmcpa-badge wpmcpa-badge--danger">
								{ __( 'Irreversible', 'wp-mcp-abilities' ) }
							</span>
						) }
						{ isHiddenByDefault( row, isThirdParty ) && (
							<span className="wpmcpa-badge">
								{ __(
									'Hidden by default',
									'wp-mcp-abilities'
								) }
							</span>
						) }
					</div>
				) }
				{ description && (
					<p className="wpmcpa-ability__description">
						{ description }
					</p>
				) }
				<code className="wpmcpa-ability__name">{ row.name }</code>
			</div>
		</li>
	);
}

/**
 * Meaning of the badges a block shows, written once above its abilities.
 *
 * Only the badges present in the block are explained.
 *
 * @param {Object}  props
 * @param {Array}   props.rows         Ability rows of the block.
 * @param {boolean} props.isThirdParty Whether the rows belong to another plugin.
 */
function BadgeLegend( { rows, isThirdParty } ) {
	const hasDestructive = rows.some( ( row ) => row.destructive );
	const hasHidden = rows.some( ( row ) =>
		isHiddenByDefault( row, isThirdParty )
	);

	if ( ! hasDestructive && ! hasHidden ) {
		return null;
	}

	return (
		<dl className="wpmcpa-legend">
			{ hasDestructive && (
				<div className="wpmcpa-legend__item">
					<dt>
						<span className="wpmcpa-badge wpmcpa-badge--danger">
							{ __( 'Irreversible', 'wp-mcp-abilities' ) }
						</span>
					</dt>
					<dd>
						{ __(
							'Can overwrite or erase data on the site, with no way to undo it.',
							'wp-mcp-abilities'
						) }
					</dd>
				</div>
			) }
			{ hasHidden && (
				<div className="wpmcpa-legend__item">
					<dt>
						<span className="wpmcpa-badge">
							{ __( 'Hidden by default', 'wp-mcp-abilities' ) }
						</span>
					</dt>
					<dd>
						{ __(
							'Its plugin registers it but does not offer it to agents. Check it to add it to the MCP server, no code needed.',
							'wp-mcp-abilities'
						) }
					</dd>
				</div>
			) }
		</dl>
	);
}

/**
 * A reading or writing block, with its select-all box.
 *
 * @param {Object}   props
 * @param {string}   props.title        Block title.
 * @param {string}   props.description  Sentence under the title.
 * @param {Array}    props.rows         Ability rows of the block.
 * @param {Function} props.onChange     Receives a name => state map.
 * @param {boolean}  props.isThirdParty Whether the rows belong to another plugin.
 */
function AbilitySection( {
	title,
	description,
	rows,
	onChange,
	isThirdParty,
} ) {
	if ( rows.length === 0 ) {
		return null;
	}

	const checkedCount = rows.filter( ( row ) => row.enabled ).length;
	const allChecked = checkedCount === rows.length;

	const toggleAll = ( checked ) =>
		onChange(
			Object.fromEntries( rows.map( ( row ) => [ row.name, checked ] ) )
		);

	return (
		<section className="wpmcpa-section">
			<h3 className="wpmcpa-section__title">{ title }</h3>
			<p className="wpmcpa-section__description">{ description }</p>
			<BadgeLegend rows={ rows } isThirdParty={ isThirdParty } />
			<ul className="wpmcpa-grid">
				{ rows.map( ( row ) => (
					<AbilityCheckbox
						key={ row.name }
						row={ row }
						onChange={ onChange }
						isThirdParty={ isThirdParty }
					/>
				) ) }
			</ul>
			{ rows.length > 1 && (
				<CheckboxControl
					__nextHasNoMarginBottom
					className="wpmcpa-section__toggle"
					label={
						allChecked
							? __( 'Deselect all', 'wp-mcp-abilities' )
							: __( 'Select all', 'wp-mcp-abilities' )
					}
					checked={ allChecked }
					indeterminate={ checkedCount > 0 && ! allChecked }
					onChange={ () => toggleAll( ! allChecked ) }
				/>
			) }
		</section>
	);
}

export default function AbilityGroupCard( {
	title,
	rows,
	onChange,
	isThirdParty,
} ) {
	const readRows = rows.filter( ( row ) => row.readonly );
	const writeRows = rows.filter( ( row ) => ! row.readonly );
	const enabledCount = rows.filter( ( row ) => row.enabled ).length;

	return (
		<Card className="wpmcpa-card" size="medium">
			<CardHeader>
				<h2 className="wpmcpa-card__title">{ title }</h2>
				<span className="wpmcpa-card__count">
					{ sprintf(
						/* translators: 1: number of enabled abilities, 2: number of abilities. */
						__( '%1$d of %2$d enabled', 'wp-mcp-abilities' ),
						enabledCount,
						rows.length
					) }
				</span>
			</CardHeader>
			<CardBody>
				<AbilitySection
					title={ __( 'Read-only abilities', 'wp-mcp-abilities' ) }
					description={ __(
						'These abilities let an agent read content. They change nothing on the site.',
						'wp-mcp-abilities'
					) }
					rows={ readRows }
					onChange={ onChange }
					isThirdParty={ isThirdParty }
				/>
				<AbilitySection
					title={ __( 'Write abilities', 'wp-mcp-abilities' ) }
					description={
						isThirdParty
							? __(
									'These abilities do not declare themselves read-only: assume they can change the site.',
									'wp-mcp-abilities'
								)
							: __(
									'These abilities let an agent create, update and delete content.',
									'wp-mcp-abilities'
								)
					}
					rows={ writeRows }
					onChange={ onChange }
					isThirdParty={ isThirdParty }
				/>
			</CardBody>
		</Card>
	);
}
