/**
 * Ship Fixes on the dev network (run with tools/e2e-plugin.sh <repository folder> from the dev project). The plugin
 * must be active: for the whole network, or on the main site only.
 *
 * Network-wide: the network screen (a switch per fix, site administrators may decide), saved and kept after a reload,
 * accessibility (axe, WCAG 2.2 AA); the network turning site choices off removes the site screen. Both ways: the site
 * screen, saved, kept, axe; the Table of Contents block for a visitor and in the editor (valid, listing the post's
 * headings), gone when the fix is off; YouTube links of every form pasted in the editor: YouTube embeds whose
 * players play from youtube-nocookie.com with the video's own title (YouTube's real oEmbed service, asked from the
 * server), for a visitor too, and as WordPress makes them when the fix is off (YouTube blocked in the browsers:
 * nothing is loaded from it); a visitor gets nothing from the REST routes.
 * Every setting is put back and everything made here is deleted at the end.
 */
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';

const SLUG = 'ship-fixes';
const FIX = 'Table of Contents block';
const SITE_OPTION = 'ship_fixes_settings';
const NETWORK_OPTION = 'ship_fixes_network';
const SITE_SCREEN = `/wp-admin/options-general.php?page=${ SLUG }`;
const NETWORK_SCREEN = `/wp-admin/network/settings.php?page=${ SLUG }`;
const STAMP = Date.now();
const WCAG = [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ];

test.describe.configure( { mode: 'serial' } );

let root = '';
let networkWide = false;
const saved = {};
const made = [];

const wp = ( ...args ) =>
	execFileSync(
		'wp',
		[ ...args, '--path=/var/www/html/web', `--url=${ root }/` ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
	).trim();
const tryWp = ( ...args ) => {
	try {
		return wp( ...args );
	} catch {
		return null;
	}
};
const optionCommand = ( name ) =>
	NETWORK_OPTION === name ? [ 'site', 'option' ] : [ 'option' ];
const readOption = ( name ) =>
	tryWp( ...optionCommand( name ), 'get', name, '--format=json' );

test.beforeAll( () => {
	root = test.info().project.use.baseURL;
	networkWide = null !== tryWp( 'plugin', 'is-active', SLUG, '--network' );
	for ( const name of [ SITE_OPTION, NETWORK_OPTION ] ) {
		saved[ name ] = readOption( name );
	}
} );

test.afterAll( () => {
	// Everything this run made carries STAMP in its title, also when a test failed half way.
	const found = wp(
		'post',
		'list',
		'--post_type=post',
		'--post_status=any',
		`--s=${ STAMP }`,
		'--field=ID'
	);
	const ids = found.split( /\s+/ ).filter( Boolean ).map( Number );
	for ( const id of new Set( [ ...made, ...ids ] ) ) {
		wp( 'post', 'delete', String( id ), '--force' );
	}
	for ( const name of [ SITE_OPTION, NETWORK_OPTION ] ) {
		if ( null === saved[ name ] ) {
			tryWp( ...optionCommand( name ), 'delete', name );
		} else {
			wp(
				...optionCommand( name ),
				'update',
				name,
				saved[ name ],
				'--format=json'
			);
		}
	}
} );

async function expectNoAxeViolations( page ) {
	const axe = await new AxeBuilder( { page } )
		.include( '#wpbody-content' )
		.withTags( WCAG )
		.analyze();
	expect( axe.violations ).toEqual( [] );
}

async function save( page ) {
	const button = page.getByRole( 'button', { name: 'Save changes' } );
	await expect( button ).toBeEnabled();
	await button.click();
	await expect(
		page.locator( '.components-notice' ).getByText( 'Settings saved.' )
	).toBeVisible();
	await expect( button ).toBeDisabled();
}

const fixIsOn = () =>
	wp(
		'eval',
		'echo WP_Block_Type_Registry::get_instance()->is_registered( "core/table-of-contents" ) ? "yes" : "no";'
	);

// Turns the fix off, saves, checks it is kept, then turns it on again.
async function offAndOnAgain( page, screen, option ) {
	await page.goto( screen );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Ship Fixes' } )
	).toBeVisible();
	const fix = page.getByRole( 'checkbox', { name: FIX } );
	await expect( fix ).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Save changes' } )
	).toBeDisabled();
	if ( ! ( await fix.isChecked() ) ) {
		await fix.check();
		await save( page );
	}

	await fix.uncheck();
	await save( page );
	await page.reload();
	await expect(
		page.getByRole( 'checkbox', { name: FIX } )
	).not.toBeChecked();
	expect( JSON.parse( readOption( option ) ).table_of_contents ).toBe(
		false
	);
	await expectNoAxeViolations( page );

	await page.getByRole( 'checkbox', { name: FIX } ).check();
	await save( page );
	expect( JSON.parse( readOption( option ) ).table_of_contents ).toBe( true );
}

test( 'network screen: the fixes for every site', async ( { page } ) => {
	test.skip( ! networkWide, 'The plugin is active on one site only.' );
	await offAndOnAgain( page, NETWORK_SCREEN, NETWORK_OPTION );
	const overrides = page.getByRole( 'checkbox', {
		name: 'Site administrators can decide for their site',
	} );
	if ( ! ( await overrides.isChecked() ) ) {
		await overrides.check();
		await save( page );
	}
} );

test( 'site screen: the fixes for this site', async ( { page } ) => {
	await offAndOnAgain( page, SITE_SCREEN, SITE_OPTION );
	await expect(
		page.getByRole( 'checkbox', {
			name: 'Site administrators can decide for their site',
		} )
	).toHaveCount( 0 );
} );

test( 'Table of Contents block: for a visitor, in the editor, and gone when the fix is off', async ( {
	page,
	browser,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	expect( fixIsOn() ).toBe( 'yes' );
	const content =
		'<!-- wp:table-of-contents /-->' +
		`<!-- wp:heading --><h2 class="wp-block-heading" id="first">First ${ STAMP }</h2><!-- /wp:heading -->` +
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading" id="inner">Inner</h3><!-- /wp:heading -->';
	const id = Number(
		wp(
			'post',
			'create',
			`--post_title=E2E contents ${ STAMP }`,
			`--post_content=${ content }`,
			'--post_status=publish',
			'--porcelain'
		)
	);
	made.push( id );
	const link = wp( 'post', 'list', `--post__in=${ id }`, '--field=url' );

	const visitor = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		ignoreHTTPSErrors: true,
	} );
	const front = await visitor.newPage();
	await front.goto( link );
	const nav = front.locator( 'nav.wp-block-table-of-contents' );
	await expect( nav ).toHaveAttribute( 'aria-label', 'Table of Contents' );
	await expect( nav.locator( 'ol > li > a[href="#first"]' ) ).toHaveText(
		`First ${ STAMP }`
	);
	await expect(
		nav.locator( 'ol > li > ol > li > a[href="#inner"]' )
	).toHaveText( 'Inner' );
	const axe = await new AxeBuilder( { page: front } )
		.include( 'nav.wp-block-table-of-contents' )
		.withTags( WCAG )
		.analyze();
	expect( axe.violations ).toEqual( [] );

	// In the editor: a valid block that lists the post's headings, new ones included.
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	await page.waitForFunction(
		() =>
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks().length >
			0
	);
	const block = await page.evaluate( () => {
		const first = window.wp.data
			.select( 'core/block-editor' )
			.getBlocks()[ 0 ];
		return { name: first.name, isValid: first.isValid };
	} );
	expect( block ).toEqual( {
		name: 'core/table-of-contents',
		isValid: true,
	} );
	const list = page
		.frameLocator( 'iframe[name="editor-canvas"]' )
		.locator( 'nav.wp-block-table-of-contents' );
	await expect( list ).toContainText( `First ${ STAMP }` );
	await page.evaluate( () =>
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks(
			window.wp.blocks.createBlock( 'core/heading', {
				content: 'Added in the editor',
			} )
		)
	);
	await expect( list ).toContainText( 'Added in the editor' );
	expect( errors ).toEqual( [] );

	// Off for this site: the block is not registered, and the page shows no list.
	const before = readOption( SITE_OPTION );
	wp(
		'option',
		'update',
		SITE_OPTION,
		'{"table_of_contents":false}',
		'--format=json'
	);
	if ( networkWide ) {
		wp(
			'site',
			'option',
			'patch',
			'update',
			NETWORK_OPTION,
			'site_overrides',
			'true',
			'--format=json'
		);
	}
	expect( fixIsOn() ).toBe( 'no' );
	await front.goto( link );
	await expect( front.locator( 'h2#first' ) ).toBeVisible();
	await expect( nav ).toHaveCount( 0 );
	await visitor.close();
	wp( 'option', 'update', SITE_OPTION, before, '--format=json' );
	expect( fixIsOn() ).toBe( 'yes' );
} );

test( 'YouTube privacy-enhanced mode: every link form plays from youtube-nocookie.com, with its title', async ( {
	page,
	browser,
} ) => {
	// Asks YouTube's real oEmbed service from the server (the dev network must be online). In the browsers, YouTube
	// is blocked: the players are checked, never loaded.
	const links = {
		'youtu.be': [ 'https://youtu.be/eMTEmPNwkGQ', 'eMTEmPNwkGQ' ],
		'youtu.be, si': [
			'https://youtu.be/jzuTOdM4kRI?si=SKFPywCGF82_VjsQ',
			'jzuTOdM4kRI',
		],
		'watch, t': [
			'https://www.youtube.com/watch?v=0zQjLy-f_Dg&t=2117s',
			'0zQjLy-f_Dg?start=2117',
		],
		'youtu.be, feature': [
			'https://youtu.be/HVVpa_-pLkE?feature=shared',
			'HVVpa_-pLkE',
		],
		nocookie: [
			'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ',
			'eMTEmPNwkGQ',
		],
		'youtube.com embed': [
			'https://www.youtube.com/embed/jzuTOdM4kRI?start=60',
			'jzuTOdM4kRI?start=60',
		],
		'm., v not first': [
			'https://m.youtube.com/watch?feature=shared&v=HVVpa_-pLkE',
			'HVVpa_-pLkE',
		],
		shorts: [ 'https://www.youtube.com/shorts/eMTEmPNwkGQ', 'eMTEmPNwkGQ' ],
		live: [ 'https://www.youtube.com/live/0zQjLy-f_Dg', '0zQjLy-f_Dg' ],
	};
	const GENERIC = 'YouTube video player';
	const youtube = ( address ) =>
		/(^|\.)youtube(-nocookie)?\.com$/.test( address.hostname );
	const offline = ( route ) => route.abort();
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.route( youtube, offline );
	const id = Number(
		wp(
			'post',
			'create',
			`--post_title=E2E YouTube ${ STAMP }`,
			'--post_content=<!-- wp:paragraph --><p>Below the videos.</p><!-- /wp:paragraph -->',
			'--post_status=draft',
			'--porcelain'
		)
	);
	made.push( id );

	// In the editor, each link pasted: a YouTube embed whose preview plays from youtube-nocookie.com.
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	await page.waitForFunction(
		() =>
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks().length >
			0
	);
	const urls = Object.values( links ).map( ( [ url ] ) => url );
	await page.evaluate( ( all ) => {
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks(
			window.wp.blocks.rawHandler( {
				HTML: all.map( ( url ) => `<p>${ url }</p>` ).join( '' ),
			} ),
			0
		);
	}, urls );
	await page.waitForFunction(
		( count ) =>
			window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.filter( ( block ) => 'video' === block.attributes.type )
				.length === count,
		urls.length,
		{ timeout: 45000 }
	);
	const blocks = await page.evaluate( () =>
		window.wp.data
			.select( 'core/block-editor' )
			.getBlocks()
			.filter( ( block ) => 'core/embed' === block.name )
			.map( ( block ) => ( {
				url: block.attributes.url,
				variation: window.wp.data
					.select( 'core/blocks' )
					.getActiveBlockVariation( block.name, block.attributes )
					?.name,
				className: block.attributes.className,
			} ) )
	);
	expect( blocks ).toEqual(
		urls.map( ( url ) => ( {
			url,
			variation: 'youtube',
			className: 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
		} ) )
	);
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	for ( const [ index, [ , expected ] ] of Object.values(
		links
	).entries() ) {
		const player = canvas
			.locator( 'figure.wp-block-embed-youtube iframe' )
			.nth( index )
			.contentFrame()
			.locator( 'iframe' );
		await expect( player ).toHaveAttribute(
			'src',
			`https://www.youtube-nocookie.com/embed/${ expected }`
		);
		await expect( player ).not.toHaveAttribute( 'title', GENERIC );
	}
	await page.evaluate( () => {
		window.wp.data
			.dispatch( 'core/editor' )
			.editPost( { status: 'publish' } );
		return window.wp.data.dispatch( 'core/editor' ).savePost();
	} );
	expect( errors ).toEqual( [] );

	// For a visitor: every player on youtube-nocookie.com with the video's own title, nothing on www.youtube.com.
	const visitor = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		ignoreHTTPSErrors: true,
	} );
	const requested = [];
	await visitor.route( youtube, ( route ) => {
		requested.push( route.request().url() );
		return route.abort();
	} );
	const front = await visitor.newPage();
	const link = wp( 'post', 'list', `--post__in=${ id }`, '--field=url' );
	await front.goto( link );
	const players = front.locator( 'figure.wp-block-embed-youtube iframe' );
	await expect( players ).toHaveCount( urls.length );
	for ( const [ index, [ , expected ] ] of Object.values(
		links
	).entries() ) {
		const player = players.nth( index );
		await expect( player ).toHaveAttribute(
			'src',
			`https://www.youtube-nocookie.com/embed/${ expected }`
		);
		const title = await player.getAttribute( 'title' );
		expect( title ).not.toBe( GENERIC );
	}
	expect(
		requested.filter( ( url ) =>
			new URL( url ).hostname.endsWith( 'youtube.com' )
		)
	).toEqual( [] );
	const axe = await new AxeBuilder( { page: front } )
		.include( '.entry-content, .wp-block-post-content' )
		.withTags( WCAG )
		.analyze();
	expect( axe.violations ).toEqual( [] );

	// Off for this site: the players as WordPress makes them, from its cache.
	const before = readOption( SITE_OPTION );
	wp(
		'option',
		'update',
		SITE_OPTION,
		'{"youtube_no_cookie":false}',
		'--format=json'
	);
	if ( networkWide ) {
		wp(
			'site',
			'option',
			'patch',
			'update',
			NETWORK_OPTION,
			'site_overrides',
			'true',
			'--format=json'
		);
	}
	await front.goto( link );
	await expect( players.first() ).toHaveAttribute(
		'src',
		/^https:\/\/www\.youtube\.com\/embed\/eMTEmPNwkGQ/
	);
	await visitor.close();
	wp( 'option', 'update', SITE_OPTION, before, '--format=json' );
} );

test( 'network turns site choices off: no site screen', async ( { page } ) => {
	test.skip( ! networkWide, 'The plugin is active on one site only.' );
	await page.goto( NETWORK_SCREEN );
	await page
		.getByRole( 'checkbox', {
			name: 'Site administrators can decide for their site',
		} )
		.uncheck();
	await save( page );

	await page.goto( '/wp-admin/options-general.php' );
	await expect(
		page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Ship Fixes' } )
	).toHaveCount( 0 );
	const response = await page.goto( SITE_SCREEN );
	expect( [ 403, 404 ] ).toContain( response.status() );
	await expect( page.locator( '#ship-fixes-settings' ) ).toHaveCount( 0 );
} );

test( 'a visitor gets nothing from the REST routes', async ( { browser } ) => {
	const visitor = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		ignoreHTTPSErrors: true,
	} );
	for ( const route of [ 'settings', 'network-settings' ] ) {
		const response = await visitor.request.get(
			`${ root }/wp-json/ship-fixes/v1/${ route }`
		);
		expect( response.status() ).toBe( 401 );
	}
	await visitor.close();
} );
