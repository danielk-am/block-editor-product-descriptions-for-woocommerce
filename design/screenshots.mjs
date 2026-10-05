#!/usr/bin/env node
/**
 * Captures the WordPress.org listing screenshots from the Playground test store.
 *
 * Start the store first (see README.md), then run: node design/screenshots.mjs
 * It seeds the sample products, gives the tee a description written in blocks, and writes
 * .wordpress-org/screenshot-1.png to screenshot-5.png.
 *
 * Needs Node 22 or later and Google Chrome. Set CHROME_BIN or PDBLOCKS_STORE to override the defaults.
 */
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const STORE = process.env.PDBLOCKS_STORE || 'http://127.0.0.1:9402';
const CHROME =
	process.env.CHROME_BIN ||
	'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const OUT = join(
	dirname( fileURLToPath( import.meta.url ) ),
	'..',
	'.wordpress-org'
);
const PORT = 9333;
const VIEWPORT = { width: 1280, height: 860 };

const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

const profile = mkdtempSync( join( tmpdir(), 'pdblocks-screenshots-' ) );
const chrome = spawn(
	CHROME,
	[
		'--headless=new',
		`--remote-debugging-port=${ PORT }`,
		`--user-data-dir=${ profile }`,
		'--hide-scrollbars',
		'--no-first-run',
		'--disable-gpu',
		'about:blank',
	],
	{ stdio: 'ignore' }
);

async function connect() {
	for ( let attempt = 0; attempt < 50; attempt++ ) {
		try {
			const targets = await (
				await fetch( `http://127.0.0.1:${ PORT }/json/list` )
			 ).json();
			const page = targets.find( ( target ) => 'page' === target.type );
			if ( page ) {
				return new WebSocket( page.webSocketDebuggerUrl );
			}
		} catch {
			// Chrome is still starting.
		}
		await sleep( 200 );
	}
	throw new Error( 'Chrome did not start.' );
}

const socket = await connect();
await new Promise( ( resolve ) => socket.addEventListener( 'open', resolve ) );

let lastId = 0;
const pending = new Map();
const waiting = new Map();

socket.addEventListener( 'message', ( event ) => {
	const message = JSON.parse( event.data );

	if ( message.id && pending.has( message.id ) ) {
		const { resolve, reject } = pending.get( message.id );
		pending.delete( message.id );
		if ( message.error ) {
			reject( new Error( message.error.message ) );
		} else {
			resolve( message.result );
		}
	} else if ( message.method && waiting.has( message.method ) ) {
		const resolvers = waiting.get( message.method );
		waiting.delete( message.method );
		resolvers.forEach( ( resolve ) => resolve( message.params ) );
	}
} );

const send = ( method, params = {} ) =>
	new Promise( ( resolve, reject ) => {
		const id = ++lastId;
		pending.set( id, { resolve, reject } );
		socket.send( JSON.stringify( { id, method, params } ) );
	} );

const once = ( method ) =>
	new Promise( ( resolve ) => {
		waiting.set( method, [ ...( waiting.get( method ) || [] ), resolve ] );
	} );

async function goTo( path ) {
	const loaded = once( 'Page.loadEventFired' );
	await send( 'Page.navigate', { url: STORE + path } );
	await loaded;
	await sleep( 1800 );
}

// Runs a function in the page. It must be self-contained: only its arguments cross over.
async function inPage( fn, ...args ) {
	const { result, exceptionDetails } = await send( 'Runtime.evaluate', {
		expression: `(${ fn })(...${ JSON.stringify( args ) })`,
		awaitPromise: true,
		returnByValue: true,
	} );

	if ( exceptionDetails ) {
		throw new Error(
			exceptionDetails.exception?.description || exceptionDetails.text
		);
	}

	return result.value;
}

async function capture( number, height ) {
	const params = { format: 'png' };

	if ( height ) {
		params.captureBeyondViewport = true;
		params.clip = { x: 0, y: 0, width: VIEWPORT.width, height, scale: 1 };
	}

	const { data } = await send( 'Page.captureScreenshot', params );
	writeFileSync(
		join( OUT, `screenshot-${ number }.png` ),
		Buffer.from( data, 'base64' )
	);
	console.log( `screenshot-${ number }.png` );
}

try {
	await send( 'Page.enable' );
	await send( 'Runtime.enable' );
	await send( 'Emulation.setDeviceMetricsOverride', {
		...VIEWPORT,
		deviceScaleFactor: 2,
		mobile: false,
	} );

	// The first visit logs in. The second clears any one-off admin redirect.
	await goTo( '/' );
	await goTo( '/wp-admin/' );
	await goTo( '/wp-admin/admin-post.php?action=pdblocks_seed' );

	await goTo( '/wp-admin/edit.php?post_type=product' );
	const productId = await inPage( () => {
		const row = [ ...document.querySelectorAll( '#the-list tr' ) ].find(
			( tr ) => 'pdblocks-tee' === tr.querySelector( '.sku' )?.textContent.trim()
		);
		return row ? Number( row.id.replace( 'post-', '' ) ) : null;
	} );

	if ( ! productId ) {
		throw new Error( 'The sample tee was not found. Is the store seeded?' );
	}

	const edit = `/wp-admin/post.php?post=${ productId }&action=edit`;

	// Write the tee's descriptions with the editor's own blocks, so the markup is exactly what it saves.
	await goTo( edit );
	const productPath = await inPage( async ( id ) => {
		const { createBlock, serialize } = wp.blocks;
		const paragraph = ( content ) => createBlock( 'core/paragraph', { content } );
		const list = ( items ) =>
			createBlock(
				'core/list',
				{},
				items.map( ( content ) => createBlock( 'core/list-item', { content } ) )
			);
		const row = ( tag, cells ) => ( {
			cells: cells.map( ( content ) => ( { content, tag } ) ),
		} );

		const content = serialize( [
			paragraph(
				'A soft everyday tee, cut from organic cotton and made to keep its shape wash after wash.'
			),
			createBlock( 'core/heading', { content: 'Why you will like it', level: 2 } ),
			list( [
				'Midweight 180 gsm cotton',
				'Pre-washed, so it stays true to size',
				'Ribbed collar that holds its shape',
			] ),
			createBlock( 'core/heading', { content: 'Size guide', level: 3 } ),
			createBlock( 'core/table', {
				hasFixedLayout: true,
				head: [ row( 'th', [ 'Size', 'Chest', 'Length' ] ) ],
				body: [
					row( 'td', [ 'Small', '48 cm', '68 cm' ] ),
					row( 'td', [ 'Medium', '52 cm', '71 cm' ] ),
					row( 'td', [ 'Large', '56 cm', '74 cm' ] ),
				],
			} ),
		] );
		const excerpt = serialize( [
			paragraph( 'Our softest tee yet, in organic cotton.' ),
			list( [ 'Midweight and pre-washed', 'Sizes Small to Large' ] ),
		] );

		const product = await wp.apiFetch( {
			path: `/wp/v2/product/${ id }`,
			method: 'POST',
			data: { title: 'Everyday Tee', content, excerpt },
		} );
		await wp.apiFetch( {
			path: '/wc-admin/options',
			method: 'POST',
			data: { woocommerce_coming_soon: 'no' },
		} );

		return new URL( product.link ).pathname;
	}, productId );

	const field = ( name ) => `.pdblocks-field[data-pdblocks-field="${ name }"]`;

	// 1. The description as a block editor, with a block selected.
	await goTo( edit );
	await inPage( ( selector ) => {
		document.querySelector( `${ selector } [data-type="core/heading"]` ).focus();
		window.scrollTo( 0, 0 );
	}, field( 'content' ) );
	await sleep( 600 );
	await capture( 1 );

	// 2. The inserter.
	await inPage( ( selector ) => {
		document.querySelector( `${ selector } .pdblocks__inserter-toggle` ).click();
	}, field( 'content' ) );
	await sleep( 1200 );
	await capture( 2 );

	// 3. A block's settings.
	await goTo( edit );
	await inPage( ( selector ) => {
		document.querySelector( `${ selector } [data-type="core/heading"]` ).focus();
		document.querySelector( `${ selector } .pdblocks__settings-toggle` ).click();
		window.scrollTo( 0, 0 );
	}, field( 'content' ) );
	await sleep( 1000 );
	await capture( 3 );

	// 4. The short description.
	await goTo( edit );
	await inPage( ( selector ) => {
		document.querySelector( `${ selector } [data-type="core/paragraph"]` ).focus();
		document
			.querySelector( '#woocommerce-product-data' )
			.scrollIntoView( { block: 'start', behavior: 'instant' } );
		window.scrollBy( 0, -60 );
	}, field( 'excerpt' ) );
	await sleep( 800 );
	await capture( 4 );

	// 5. The storefront, without the admin bar.
	await goTo( productPath );
	await inPage( () => {
		document.getElementById( 'wpadminbar' )?.remove();
		document.documentElement.style.setProperty( 'margin-top', '0', 'important' );
		window.scrollTo( 0, 0 );
	} );
	await sleep( 600 );
	await capture( 5, 1600 );
} finally {
	socket.close();
	chrome.kill();
	await sleep( 500 );
	rmSync( profile, { recursive: true, force: true } );
}
