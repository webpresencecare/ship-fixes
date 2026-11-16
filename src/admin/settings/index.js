/**
 * The settings screens: a DataForm with a Save button, reading and saving through the plugin's REST routes; the
 * result is announced to screen readers. One switch per fix (the list comes from the server).
 *
 * - Network screen: the fixes for every site, and whether site administrators may decide for their own site.
 * - Site screen: the fixes for this site.
 */
import apiFetch from '@wordpress/api-fetch';
import { speak } from '@wordpress/a11y';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import './settings.scss';

const {
	scope = 'site',
	networkWide = false,
	fixes = [],
} = window.shipFixesSettings || {};
const NETWORK = 'network' === scope;
const PATH = NETWORK
	? '/ship-fixes/v1/network-settings'
	: '/ship-fixes/v1/settings';

const FIELDS = [
	...fixes.map( ( { id, label, description } ) => ( {
		id,
		type: 'boolean',
		Edit: 'toggle',
		label,
		description,
	} ) ),
	{
		id: 'site_overrides',
		type: 'boolean',
		Edit: 'toggle',
		label: __(
			'Site administrators can decide for their site',
			'ship-fixes'
		),
		description: __(
			'On: each site’s Settings has this plugin’s screen, and what a site chooses there wins. Off: the network’s settings apply to every site.',
			'ship-fixes'
		),
	},
];

const fieldIds = fixes.map( ( { id } ) => id );
if ( NETWORK ) {
	fieldIds.push( 'site_overrides' );
}
const FORM = { layout: { type: 'regular' }, fields: fieldIds };

function introText() {
	if ( NETWORK ) {
		return __(
			'Each fix is on or off for every site of the network.',
			'ship-fixes'
		);
	}
	return networkWide
		? __(
				'Each fix is on or off for this site. Until this site saves its own choice, the network’s applies.',
				'ship-fixes'
			)
		: __( 'Each fix is on or off for this site.', 'ship-fixes' );
}

function App() {
	const [ saved, setSaved ] = useState( null );
	const [ data, setData ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const notify = ( status, message ) => {
		setNotice( { status, message } );
		speak( message, 'error' === status ? 'assertive' : 'polite' );
	};

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( values ) => {
				setSaved( values );
				setData( values );
			} )
			.catch( ( error ) => notify( 'error', error.message ) );
	}, [] );

	if ( ! data ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const changed = fieldIds.some( ( key ) => data[ key ] !== saved[ key ] );

	const save = async () => {
		setBusy( true );
		try {
			const values = await apiFetch( {
				path: PATH,
				method: 'POST',
				data: Object.fromEntries(
					fieldIds
						.filter( ( key ) => data[ key ] !== saved[ key ] )
						.map( ( key ) => [ key, data[ key ] ] )
				),
			} );
			setSaved( values );
			setData( values );
			notify( 'success', __( 'Settings saved.', 'ship-fixes' ) );
		} catch ( error ) {
			notify( 'error', error.message );
		}
		setBusy( false );
	};

	return (
		<div className="ship-fixes-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<Card>
				<CardBody>
					<p className="ship-fixes-settings-intro">{ introText() }</p>
					<DataForm
						data={ data }
						fields={ FIELDS }
						form={ FORM }
						onChange={ ( edits ) =>
							setData( ( current ) => ( {
								...current,
								...edits,
							} ) )
						}
					/>
					<div className="ship-fixes-settings-actions">
						<Button
							__next40pxDefaultSize
							variant="primary"
							isBusy={ busy }
							disabled={ ! changed || busy }
							accessibleWhenDisabled
							onClick={ save }
						>
							{ __( 'Save changes', 'ship-fixes' ) }
						</Button>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}

const root = document.getElementById( 'ship-fixes-settings' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
