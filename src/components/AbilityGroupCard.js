/**
 * One group of abilities, split into reading and writing.
 */
import {
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Tooltip,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Checkbox of a single ability, with its details behind a tooltip.
 *
 * @param {Object}   props
 * @param {Object}   props.row          Ability row.
 * @param {Function} props.onChange     Receives a name => state map.
 * @param {boolean}  props.isThirdParty Whether the row belongs to another plugin.
 */
function AbilityCheckbox( { row, onChange, isThirdParty } ) {
	const details = [ row.name, row.description ]
		.filter( Boolean )
		.join( ' — ' );

	return (
		<li className="wpmcpa-ability">
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ row.label || row.name }
				checked={ row.enabled }
				onChange={ ( checked ) =>
					onChange( { [ row.name ]: checked } )
				}
			/>
			<Tooltip text={ details }>
				<span
					className="wpmcpa-ability__help"
					tabIndex={ 0 }
					role="img"
					aria-label={ details }
				>
					?
				</span>
			</Tooltip>
			{ row.destructive && (
				<span className="wpmcpa-badge wpmcpa-badge--danger">
					{ __( 'Deletes', 'wp-mcp-abilities' ) }
				</span>
			) }
			{ isThirdParty && ! row.joinable && (
				<span
					className="wpmcpa-badge"
					title={ __(
						'Closed to MCP by its own declaration. Check to hand it to an agent.',
						'wp-mcp-abilities'
					) }
				>
					{ __( 'Closed by its plugin', 'wp-mcp-abilities' ) }
				</span>
			) }
		</li>
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
