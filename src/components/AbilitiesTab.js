/**
 * Ability switches, one card per group.
 */
import { Notice, SearchControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import AbilityGroupCard from './AbilityGroupCard';

/**
 * Groups rows by their `group` key, keeping the server's order.
 *
 * @param {Array} rows Ability rows.
 * @return {Array<[string, Array]>} Group name and its rows.
 */
function groupRows( rows ) {
	const groups = new Map();

	rows.forEach( ( row ) => {
		if ( ! groups.has( row.group ) ) {
			groups.set( row.group, [] );
		}
		groups.get( row.group ).push( row );
	} );

	return [ ...groups.entries() ];
}

export default function AbilitiesTab( {
	rows,
	onChange,
	isThirdParty = false,
	defaultServer = true,
} ) {
	const [ search, setSearch ] = useState( '' );

	const filtered = useMemo( () => {
		const needle = search.trim().toLowerCase();

		if ( ! needle ) {
			return rows;
		}

		return rows.filter(
			( row ) =>
				row.label.toLowerCase().includes( needle ) ||
				row.name.toLowerCase().includes( needle ) ||
				row.group.toLowerCase().includes( needle )
		);
	}, [ rows, search ] );

	if ( rows.length === 0 ) {
		return (
			<p>
				{ __(
					'No ability is available on this site.',
					'content-manager-for-ai-agents'
				) }
			</p>
		);
	}

	return (
		<div className="cm4ai-abilities">
			{ isThirdParty ? (
				<div className="cm4ai-intro">
					<p>
						{ __(
							'These abilities come from another plugin. Unchecking one hides it from MCP requests: an agent no longer lists it, and can no longer run it. The rest of the site is untouched, and the plugin providing it keeps using it as usual.',
							'content-manager-for-ai-agents'
						) }
					</p>
					<p>
						{ __(
							'Some of them were never meant for an agent by the plugin that registered them. Checking such a one hands it to the MCP server as a tool. It grants no right: the ability decides for itself who may call it, exactly as it does elsewhere. What changes is that an agent can now reach it.',
							'content-manager-for-ai-agents'
						) }
					</p>
					{ ! defaultServer && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This site switches off the default MCP server. Exposing an ability here will have no effect until a server declares it.',
								'content-manager-for-ai-agents'
							) }
						</Notice>
					) }
				</div>
			) : (
				<p className="cm4ai-intro">
					{ __(
						'Pick the abilities exposed to MCP clients. An unchecked ability is not registered at all. Destructive abilities are off until you enable them.',
						'content-manager-for-ai-agents'
					) }
				</p>
			) }

			<Notice
				status="warning"
				isDismissible={ false }
				className="cm4ai-caution"
			>
				{ __(
					'Use write abilities with caution. An agent can make mistakes, and with them it can change or delete what is on the site. Review what it does, especially on a live site.',
					'content-manager-for-ai-agents'
				) }
			</Notice>

			<SearchControl
				className="cm4ai-search"
				value={ search }
				onChange={ setSearch }
				label={ __(
					'Filter abilities',
					'content-manager-for-ai-agents'
				) }
				placeholder={ __(
					'Filter abilities',
					'content-manager-for-ai-agents'
				) }
				__nextHasNoMarginBottom
			/>

			{ filtered.length === 0 && (
				<p>
					{ __(
						'No ability matches.',
						'content-manager-for-ai-agents'
					) }
				</p>
			) }

			{ groupRows( filtered ).map( ( [ group, groupRowsList ] ) => (
				<AbilityGroupCard
					key={ group }
					title={ group }
					rows={ groupRowsList }
					onChange={ onChange }
					isThirdParty={ isThirdParty }
				/>
			) ) }
		</div>
	);
}
