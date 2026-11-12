/**
 * WordPress dependencies
 */
import { test, expect, Editor } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { followAdminLink, newAuthedPage } from './helpers/stream-plugin';

test.describe( 'Editor: saving a new post', () => {
	let page, editor, postTitle, postId;

	test.beforeAll( async ( { browser } ) => {
		test.setTimeout( 90_000 );

		page = await newAuthedPage( browser );
		editor = new Editor( { page } );

		postTitle = `Test Post ${ crypto.randomUUID() }`; // Sometimes this runs more than once within a microsecond so it's a UUID.

		// eslint-disable-next-line no-console
		console.log( `New post ${ postTitle }` );

		await page.goto( '/wp-admin/post-new.php' );

		// Wait for Gutenberg to be ready.
		await page.waitForFunction( () => window?.wp?.blocks && window?.wp?.data );

		// Dismiss the welcome dialog if it appears.
		const welcomeClose = page.getByRole( 'dialog', { name: 'Welcome to the editor' } ).getByRole( 'button', { name: 'Close' } );
		if ( await welcomeClose.isVisible().catch( () => false ) ) {
			await welcomeClose.click();
		}

		// Set the title via the data layer, and the body via the editor helper.
		await page.evaluate( ( title ) => {
			window.wp.data.dispatch( 'core/editor' ).editPost( { title } );
		}, postTitle );
		await editor.setContent( '<!-- wp:paragraph --><p>I\'m a test post</p><!-- /wp:paragraph -->' );

		// Publish; the helper handles the pre-publish panel and waits for the published snackbar.
		await editor.publishPost();

		postId = await page.locator( 'input#post_ID' ).inputValue();

		// eslint-disable-next-line no-console
		console.log( `Post ID: ${ postId }` );

		// Go straight to the Stream log so the assertions below can check the published row.
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
	} );

	test.afterAll( async () => {
		// Clean up the published post so it doesn't accumulate in the posts table.
		// WP 7.1 row-actions expose "Edit “Title”" / "Move “Title” to the Trash"
		// (not "“Title” (Edit)"). Use the trash href so we do not need a hover.
		if ( postId ) {
			await page.goto( '/wp-admin/edit.php?post_type=post' );
			const trashLink = page.getByRole( 'link', { name: `Move “${ postTitle }” to the Trash` } );
			if ( await trashLink.count() ) {
				await followAdminLink( page, trashLink );
			}
		}

		await page.context().close();
	} );

	test( 'has published row', async () => {
		await expect( page.getByText( `"${ postTitle }" post published` ) ).toBeVisible();
	} );

	test( 'does not have updated row', async () => {
		await expect( page.getByText( `"${ postTitle }" post updated` ) ).not.toBeVisible();
	} );
} );
