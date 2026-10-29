/**
 * One group of abilities, split into reading and writing.
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Tooltip,
	VisuallyHidden,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Badges an ability can carry, explained once in the legend of its block.
 *
 * Functions rather than strings so that `__()` runs once the translations are
 * loaded.
 */
const BADGES = [
	{
		key: 'destructive',
		className: 'cm4ai-badge cm4ai-badge--danger',
		label: () => __( 'Irreversible', 'content-manager-for-ai-agents' ),
		meaning: () =>
			__(
				'Can overwrite or erase data on the site, with no way to undo it.',
				'content-manager-for-ai-agents'
			),
		applies: ( row ) => row.destructive,
	},
	{
		key: 'hidden',
		className: 'cm4ai-badge',
		label: () => __( 'Hidden by default', 'content-manager-for-ai-agents' ),
		meaning: () =>
			__(
				'Its plugin registers it but does not offer it to agents. Check it to add it to the MCP server, no code needed.',
				'content-manager-for-ai-agents'
			),
		applies: ( row, isThirdParty ) =>
			isThirdParty && 'exposed' === row.option,
	},
];

/**
 * Description of an ability, when it says more than its label.
 *
 * SEOPress's descriptions repeat the label: a "?" showing them would tell
 * nothing.
 *
 * @param {Object} row   Ability row.
 * @param {string} label Label shown next to the box.
 * @return {string} The description, or an empty string.
 */
function usefulDescription( row, label ) {
	const normalize = ( text ) =>
		( text || '' )
			.trim()
			.replace( /[.\s]+$/, '' )
			.toLowerCase();

	return row.description &&
		normalize( row.description ) !== normalize( label )
		? row.description
		: '';
}

/**
 * Checkbox of a single ability, with its details behind a tooltip.
 *
 * The "?" only shows when the description adds something to the label. The
 * details are also tied to the checkbox through `aria-describedby`, so a
 * screen reader announces them with the box itself rather than only from the
 * tooltip, which a touch screen or a screen reader user may never open.
 *
 * @param {Object}   props
 * @param {Object}   props.row      Ability row.
 * @param {Array}    props.badges   Badges to show on this row.
 * @param {Function} props.onChange Receives a name => state map.
 */
function AbilityCheckbox( { row, badges, onChange } ) {
	const label = row.label || row.name;
	const description = usefulDescription( row, label );
	const details = [ row.name, description ].filter( Boolean ).join( ' — ' );
	const detailsId = useInstanceId(
		AbilityCheckbox,
		'cm4ai-ability-details'
	);

	return (
		<li className="cm4ai-ability">
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ label }
				checked={ row.enabled }
				aria-describedby={ detailsId }
				onChange={ ( checked ) =>
					onChange( { [ row.name ]: checked } )
				}
			/>
			<VisuallyHidden id={ detailsId }>{ details }</VisuallyHidden>
			{ description && (
				<Tooltip text={ details }>
					<Button
						className="cm4ai-ability__help"
						size="small"
						aria-label={ sprintf(
							/* translators: %s: label of the ability. */
							__( 'Details about %s', 'content-manager-for-ai-agents' ),
							label
						) }
						aria-describedby={ detailsId }
					>
						?
					</Button>
				</Tooltip>
			) }
			{ badges.map( ( badge ) => (
				<span key={ badge.key } className={ badge.className }>
					{ badge.label() }
				</span>
			) ) }
		</li>
	);
}

/**
 * Meaning of the badges a block shows, written once above its abilities.
 *
 * A badge every ability of the block carries is only shown here, with a
 * sentence saying so: repeated on each row, it would be noise.
 *
 * @param {Object} props
 * @param {Array}  props.badges Badges present in the block.
 * @param {Array}  props.shared Keys of the badges every row carries.
 */
function BadgeLegend( { badges, shared } ) {
	if ( badges.length === 0 ) {
		return null;
	}

	return (
		<dl className="cm4ai-legend">
			{ badges.map( ( badge ) => (
				<div key={ badge.key } className="cm4ai-legend__item">
					<dt>
						<span className={ badge.className }>
							{ badge.label() }
						</span>
					</dt>
					<dd>
						{ badge.meaning() }
						{ shared.includes( badge.key ) &&
							' ' +
								__(
									'Applies to every ability below.',
									'content-manager-for-ai-agents'
								) }
					</dd>
				</div>
			) ) }
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

	const present = BADGES.filter( ( badge ) =>
		rows.some( ( row ) => badge.applies( row, isThirdParty ) )
	);
	const shared = present
		.filter( ( badge ) =>
			rows.every( ( row ) => badge.applies( row, isThirdParty ) )
		)
		.map( ( badge ) => badge.key );
	const rowBadges = ( row ) =>
		present.filter(
			( badge ) =>
				! shared.includes( badge.key ) &&
				badge.applies( row, isThirdParty )
		);

	const toggleAll = ( checked ) =>
		onChange(
			Object.fromEntries( rows.map( ( row ) => [ row.name, checked ] ) )
		);

	return (
		<section className="cm4ai-section">
			<h3 className="cm4ai-section__title">{ title }</h3>
			<p className="cm4ai-section__description">{ description }</p>
			<BadgeLegend badges={ present } shared={ shared } />
			<ul className="cm4ai-grid">
				{ rows.map( ( row ) => (
					<AbilityCheckbox
						key={ row.name }
						row={ row }
						badges={ rowBadges( row ) }
						onChange={ onChange }
					/>
				) ) }
			</ul>
			{ rows.length > 1 && (
				<CheckboxControl
					__nextHasNoMarginBottom
					className="cm4ai-section__toggle"
					label={
						allChecked
							? __( 'Deselect all', 'content-manager-for-ai-agents' )
							: __( 'Select all', 'content-manager-for-ai-agents' )
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
		<Card className="cm4ai-card" size="medium">
			<CardHeader>
				<h2 className="cm4ai-card__title">{ title }</h2>
				<span className="cm4ai-card__count">
					{ sprintf(
						/* translators: 1: number of enabled abilities, 2: number of abilities. */
						__( '%1$d of %2$d enabled', 'content-manager-for-ai-agents' ),
						enabledCount,
						rows.length
					) }
				</span>
			</CardHeader>
			<CardBody>
				<AbilitySection
					title={ __( 'Read-only abilities', 'content-manager-for-ai-agents' ) }
					description={ __(
						'These abilities let an agent read content. They change nothing on the site.',
						'content-manager-for-ai-agents'
					) }
					rows={ readRows }
					onChange={ onChange }
					isThirdParty={ isThirdParty }
				/>
				<AbilitySection
					title={ __( 'Write abilities', 'content-manager-for-ai-agents' ) }
					description={
						isThirdParty
							? __(
									'These abilities do not declare themselves read-only: assume they can change the site.',
									'content-manager-for-ai-agents'
								)
							: __(
									'These abilities let an agent create, update and delete content.',
									'content-manager-for-ai-agents'
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
