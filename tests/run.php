<?php
/**
 * Plugin checks, run inside a WordPress admin request on a disposable site.
 * Plain assertions, so they run without the WordPress test suite. Not shipped in the release ZIP.
 *
 * @package Product_Description_Blocks
 */

defined( 'ABSPATH' ) || exit;

$pdblocks_results = array();

$pdblocks_check = static function ( string $label, bool $passed ) use ( &$pdblocks_results ): void {
	$pdblocks_results[] = array( $label, $passed );
};

$pdblocks_group = static function ( string $name, callable $tests ) use ( $pdblocks_check ): void {
	try {
		$tests( $pdblocks_check );
	} catch ( Throwable $error ) {
		$pdblocks_check( $name . ': ' . get_class( $error ) . ' - ' . $error->getMessage(), false );
	}
};

$pdblocks_output = static function ( callable $callback, WP_Post $post ): string {
	ob_start();
	$callback( $post );
	return ob_get_clean();
};

$pdblocks_product = static function ( string $content, string $excerpt ): WP_Post {
	$product = new WC_Product_Simple();
	$product->set_name( 'PDBlocks test product' );
	$product->save();
	wp_update_post(
		wp_slash(
			array(
				'ID'           => $product->get_id(),
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			)
		)
	);

	return get_post( $product->get_id() );
};

$pdblocks_group(
	'Description editor',
	static function ( callable $check ) use ( $pdblocks_output, $pdblocks_product ): void {
		$raw     = "Tom &amp; Jerry <b>bold</b>\n\n</textarea><script>alert(1)</script>";
		$post    = $pdblocks_product( $raw, '' );
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => 'PDBlocks test page',
				'post_content' => 'Page content',
			)
		);
		$render  = array( 'PDBlocks_Plugin', 'render_editor' );

		try {
			$check( 'The product screen itself stays classic.', false === use_block_editor_for_post_type( 'product' ) );
			$check( 'The fixture holds the raw description.', get_post_field( 'post_content', $post->ID, 'raw' ) === $raw );

			$html = $pdblocks_output( $render, $post );
			$check( 'A mount point for the block editor is output.', false !== strpos( $html, 'class="pdblocks-field__editor"' ) && false !== strpos( $html, 'data-pdblocks-field="content"' ) );
			$check( 'It takes the place WooCommerce styles as the description box.', false !== strpos( $html, 'id="postdivrich"' ) );
			$check( 'The description is posted through the content field.', false !== strpos( $html, '<textarea id="content" name="content"' ) );
			$check( 'The stored description is escaped for the field, not altered.', false !== strpos( $html, '>' . esc_textarea( $raw ) . '</textarea>' ) );
			$check( 'Markup in the description cannot break out of the field.', 1 === substr_count( $html, '</textarea>' ) && false === strpos( $html, '<script>' ) );
			$check( 'WordPress skips its own editor.', false === post_type_supports( 'product', 'editor' ) );

			PDBlocks_Plugin::restore_editor_support();
			$check( 'Editor support is back for the rest of the request.', true === post_type_supports( 'product', 'editor' ) );

			$check( 'Other post types keep their editor.', '' === $pdblocks_output( $render, get_post( $page_id ) ) && true === post_type_supports( 'page', 'editor' ) );
			$check( 'The expanding editor script is skipped for products only.', false === apply_filters( 'wp_editor_expand', true, 'product' ) && true === apply_filters( 'wp_editor_expand', true, 'post' ) );

			add_filter( 'user_can_richedit', '__return_false' );
			$check( 'A user who turned the visual editor off keeps the plain editor.', '' === $pdblocks_output( $render, $post ) && true === post_type_supports( 'product', 'editor' ) );
			remove_filter( 'user_can_richedit', '__return_false' );

			remove_post_type_support( 'product', 'editor' );
			$check( 'Nothing is output when products have no editor.', '' === $pdblocks_output( $render, $post ) );
			add_post_type_support( 'product', 'editor' );
		} finally {
			remove_filter( 'user_can_richedit', '__return_false' );
			PDBlocks_Plugin::restore_editor_support();
			add_post_type_support( 'product', 'editor' );
			wp_delete_post( $post->ID, true );
			wp_delete_post( $page_id, true );
		}
	}
);

$pdblocks_group(
	'Short description editor',
	static function ( callable $check ) use ( $pdblocks_output, $pdblocks_product ): void {
		global $wp_meta_boxes;

		$saved    = $wp_meta_boxes;
		$raw      = 'Short &amp; <em>sweet</em>';
		$post     = $pdblocks_product( '', $raw );
		$ours     = array( 'PDBlocks_Plugin', 'render_short_description_editor' );
		$woo      = 'WC_Meta_Box_Product_Short_Description::output';
		$callback = static function () use ( &$wp_meta_boxes ) {
			return $wp_meta_boxes['product']['normal']['default']['postexcerpt']['callback'];
		};
		$reset    = static function ( $with ) use ( &$wp_meta_boxes ): void {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixture for the meta box swap.
			$wp_meta_boxes['product'] = array(
				'normal' => array(
					'default' => array(
						'postexcerpt' => array(
							'id'       => 'postexcerpt',
							'title'    => 'Product short description',
							'callback' => $with,
							'args'     => null,
						),
					),
				),
			);
		};

		try {
			$reset( $woo );
			PDBlocks_Plugin::swap_short_description_editor( $post );
			$check( 'The short description meta box gets the block editor.', $callback() === $ours );

			$reset( '__return_null' );
			PDBlocks_Plugin::swap_short_description_editor( $post );
			$check( 'A meta box another plugin replaced is left alone.', '__return_null' === $callback() );

			$reset( $woo );
			add_filter( 'use_block_editor_for_post', '__return_true' );
			PDBlocks_Plugin::swap_short_description_editor( $post );
			remove_filter( 'use_block_editor_for_post', '__return_true' );
			$check( 'A product screen already in the block editor is left alone.', $callback() === $woo );

			add_filter( 'user_can_richedit', '__return_false' );
			PDBlocks_Plugin::swap_short_description_editor( $post );
			remove_filter( 'user_can_richedit', '__return_false' );
			$check( 'A user who turned the visual editor off keeps the plain short description.', $callback() === $woo );

			$html = $pdblocks_output( $ours, $post );
			$check( 'A mount point for the short description editor is output.', false !== strpos( $html, 'class="pdblocks-field__editor"' ) && false !== strpos( $html, 'data-pdblocks-field="excerpt"' ) );
			$check( 'The short description is posted through the excerpt field.', false !== strpos( $html, '<textarea id="excerpt" name="excerpt"' ) );
			$check( 'The stored short description is escaped for the field, not altered.', false !== strpos( $html, '>' . esc_textarea( $raw ) . '</textarea>' ) );
		} finally {
			remove_filter( 'use_block_editor_for_post', '__return_true' );
			remove_filter( 'user_can_richedit', '__return_false' );
			$wp_meta_boxes = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset test state.
			wp_delete_post( $post->ID, true );
		}
	}
);

$pdblocks_group(
	'Short description on the storefront',
	static function ( callable $check ) use ( $pdblocks_product ): void {
		$blocks = "<!-- wp:paragraph -->\n<p>Short <strong>blocks</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>One</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->";
		$post   = $pdblocks_product( '', $blocks );

		try {
			$html = apply_filters( 'woocommerce_short_description', $blocks );
			$check( 'Block comments do not reach the page.', false === strpos( $html, '<!-- wp:' ) );
			$check( 'No empty paragraphs are left behind.', 0 === preg_match( '#<p>\s*</p>#', $html ) );
			$check( 'The blocks themselves are shown.', 1 === preg_match( '#<p[^>]*>Short <strong>blocks</strong></p>#', $html ) && 1 === preg_match( '#<li[^>]*>One</li>#', $html ) );
			$check( 'Paragraph handling is back in place afterwards.', 10 === has_filter( 'woocommerce_short_description', 'wpautop' ) && false === has_filter( 'woocommerce_short_description', array( 'PDBlocks_Plugin', 'restore_short_description_autop' ) ) );

			$legacy = apply_filters( 'woocommerce_short_description', "Line one\n\nLine two" );
			$check( 'A short description without blocks is shown as before.', 1 === preg_match( '#<p>Line one</p>\s*<p>Line two</p>#', $legacy ) );

			$excerpt = get_the_excerpt( $post );
			$check( 'Themes that show the excerpt get rendered blocks too.', false === strpos( $excerpt, '<!-- wp:' ) && false !== strpos( $excerpt, 'Short <strong>blocks</strong>' ) );

			$template = static function ( int $post_id ): string {
				return ( new WP_Block(
					array(
						'blockName' => 'core/post-excerpt',
						'attrs'     => array( 'excerptLength' => 100 ),
					),
					array(
						'postId'   => $post_id,
						'postType' => 'product',
					)
				) )->render();
			};

			$check( 'Away from the product page, the Post Excerpt block keeps its plain text.', false === strpos( $template( $post->ID ), '<li' ) && false === strpos( $template( $post->ID ), '<!-- wp:' ) );

			query_posts( // phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts -- Stands in for the product page's main query.
				array(
					'p'         => $post->ID,
					'post_type' => 'product',
				)
			);
			$html = $template( $post->ID );
			$check( 'On the product page, a block theme shows the short description with its formatting.', 1 === preg_match( '#<div class="wp-block-post-excerpt__excerpt">.*<li[^>]*>One</li>.*</div>#s', $html ) && false === strpos( $html, '<!-- wp:' ) );

			add_filter( 'pdblocks_format_post_excerpt_block', '__return_false' );
			$check( 'A filter turns that off.', false === strpos( $template( $post->ID ), '<li' ) );
			remove_filter( 'pdblocks_format_post_excerpt_block', '__return_false' );

			$plain = $pdblocks_product( '', 'Plain <strong>short</strong> description' );
			query_posts( // phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts -- Stands in for the product page's main query.
				array(
					'p'         => $plain->ID,
					'post_type' => 'product',
				)
			);
			$check( 'A short description without blocks is shown as the theme always showed it.', false !== strpos( $template( $plain->ID ), '<p class="wp-block-post-excerpt__excerpt">' ) );
			wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- Pairs with query_posts() above.
			wp_delete_post( $plain->ID, true );

			ob_start();
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The template reads the global post.
			wc_get_template( 'single-product/short-description.php' );
			$html = ob_get_clean();
			$check( 'WooCommerce\'s own short description template shows the blocks.', 1 === preg_match( '#<li[^>]*>One</li>#', $html ) && false === strpos( $html, '<!-- wp:' ) );

			$page_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'page',
						'post_title'   => 'PDBlocks excerpt page',
						'post_excerpt' => $blocks,
					)
				)
			);
			$check( 'Excerpts of other post types are untouched.', false !== strpos( get_the_excerpt( get_post( $page_id ) ), '<!-- wp:paragraph -->' ) );
			wp_delete_post( $page_id, true );
		} finally {
			remove_filter( 'pdblocks_format_post_excerpt_block', '__return_false' );
			wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- Restores the main query.
			wp_delete_post( $post->ID, true );
		}
	}
);

$pdblocks_group(
	'Blocks on offer',
	static function ( callable $check ): void {
		$blocks = PDBlocks_Plugin::get_allowed_block_types();
		$short  = PDBlocks_Plugin::get_allowed_block_types( 'short_description' );
		$check( 'Content blocks are offered for the description.', is_array( $blocks ) && in_array( 'core/paragraph', $blocks, true ) && in_array( 'core/table', $blocks, true ) && in_array( 'core/shortcode', $blocks, true ) );
		$check( 'Blocks that need a full post editor are left out.', ! in_array( 'core/post-title', $blocks, true ) && ! in_array( 'core/query', $blocks, true ) && ! in_array( 'core/freeform', $blocks, true ) );
		$check( 'The short description offers text blocks only.', array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote' ) === $short );

		$only_short = static function ( $block_types, $field ) {
			return 'short_description' === $field ? true : $block_types;
		};
		add_filter( 'pdblocks_allowed_block_types', $only_short, 10, 2 );
		$check( 'A filter can change the blocks for one editor.', true === PDBlocks_Plugin::get_allowed_block_types( 'short_description' ) && is_array( PDBlocks_Plugin::get_allowed_block_types( 'description' ) ) );
		remove_filter( 'pdblocks_allowed_block_types', $only_short, 10 );
	}
);

$pdblocks_group(
	'Google for WooCommerce',
	static function ( callable $check ) use ( $pdblocks_product ): void {
		$blocks   = "<!-- wp:paragraph -->\n<p>Soft &amp; light, cut for <em>every</em> day.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Why you will like it</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Organic cotton</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Pre-washed</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody><tr><td>Small</td><td>48 cm</td></tr><tr><td>Large</td><td>56 cm</td></tr></tbody></table></figure>\n<!-- /wp:table -->";
		$expected = "Soft & light, cut for every day.\nWhy you will like it\n- Organic cotton\n- Pre-washed\nSmall | 48 cm\nLarge | 56 cm";
		$filter   = 'woocommerce_gla_product_attribute_value_description';
		$classic  = '<p>Classic <strong>HTML</strong></p><ul><li>One</li></ul>';

		$check( 'Blocks become plain text, one line per paragraph, heading, list item and table row.', PDBlocks_Plugin::html_to_text( $blocks ) === $expected );
		$check( 'A description with blocks is sent to Google as plain text.', apply_filters( $filter, $blocks, null ) === $expected );
		$check( 'A description without blocks is sent to Google as plain text too.', "Classic HTML\n- One" === apply_filters( $filter, $classic, null ) );
		$check( 'Text that was already plain is only tidied.', "Tom & Jerry\nSecond line" === apply_filters( $filter, "Tom &amp; Jerry\n\n\n  Second   line ", null ) );

		$cut = mb_substr( $blocks, 0, mb_strpos( $blocks, 'Pre-washed' ) + 10 ) . "</li>\n<!-- /wp:li";
		$check( 'Markup cut short by the 5,000 character limit leaves nothing behind.', "Soft & light, cut for every day.\nWhy you will like it\n- Organic cotton\n- Pre-washed" === apply_filters( $filter, $cut, null ) );

		add_filter( 'pdblocks_google_description_as_text', '__return_false' );
		$check( 'A filter turns the conversion off.', apply_filters( $filter, $blocks, null ) === $blocks && apply_filters( $filter, $classic, null ) === $classic );
		remove_filter( 'pdblocks_google_description_as_text', '__return_false' );

		$blocks_only = static function ( $as_text, $description ) {
			return has_blocks( $description );
		};
		add_filter( 'pdblocks_google_description_as_text', $blocks_only, 10, 2 );
		$check( 'A filter can keep the conversion to descriptions with blocks.', apply_filters( $filter, $classic, null ) === $classic && apply_filters( $filter, $blocks, null ) === $expected );
		remove_filter( 'pdblocks_google_description_as_text', $blocks_only, 10 );

		// The real thing, when Google for WooCommerce is in the plugins folder. It does not need to be active.
		$autoload = WP_PLUGIN_DIR . '/google-listings-and-ads/vendor/autoload.php';
		$adapter  = 'Automattic\\WooCommerce\\GoogleListingsAndAds\\Product\\WCProductAdapter';
		if ( ! class_exists( $adapter ) && file_exists( $autoload ) ) {
			require_once $autoload;
		}
		if ( ! class_exists( $adapter ) ) {
			echo "SKIP  Google for WooCommerce is not installed, so its own description pipeline was not run.\n";
			return;
		}

		$post    = $pdblocks_product( $blocks, '' );
		$product = wc_get_product( $post->ID );
		$product->set_regular_price( '20' );
		$product->save();
		$sync    = static function ( WC_Product $product ) use ( $adapter ) {
			return ( new $adapter(
				array(
					'wc_product'    => $product,
					'targetCountry' => 'US',
				)
			) )->getDescription();
		};

		try {
			$check( 'The fixture product holds the block description.', $product->get_description() === $blocks );

			remove_filter( $filter, array( 'PDBlocks_Plugin', 'google_description_as_text' ), 10 );
			$without = $sync( $product );
			add_filter( $filter, array( 'PDBlocks_Plugin', 'google_description_as_text' ), 10, 2 );
			$with = $sync( $product );

			$check( 'Without the plugin, Google for WooCommerce would send the block markup.', false !== strpos( $without, '<!-- wp:paragraph -->' ) );
			$check( 'With it, Google for WooCommerce sends the plain text.', $with === $expected );

			if ( false === strpos( $without, '<!-- wp:paragraph -->' ) || $with !== $expected ) {
				echo 'NOTE  Google for WooCommerce returned: ' . wp_json_encode( array( $without, $with ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text response.
			}

			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $classic,
					)
				)
			);
			$check( 'A classic description goes through Google for WooCommerce as plain text.', "Classic HTML\n- One" === $sync( wc_get_product( $post->ID ) ) );
		} finally {
			add_filter( $filter, array( 'PDBlocks_Plugin', 'google_description_as_text' ), 10, 2 );
			wp_delete_post( $post->ID, true );
		}
	}
);

$pdblocks_failed = 0;
foreach ( $pdblocks_results as list( $pdblocks_label, $pdblocks_passed ) ) {
	$pdblocks_failed += $pdblocks_passed ? 0 : 1;
	echo ( $pdblocks_passed ? 'PASS  ' : 'FAIL  ' ) . $pdblocks_label . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text response.
}
printf( "\n%d passed, %d failed. WordPress %s, WooCommerce %s, PHP %s.\n", count( $pdblocks_results ) - $pdblocks_failed, $pdblocks_failed, esc_html( get_bloginfo( 'version' ) ), esc_html( WC()->version ), esc_html( PHP_VERSION ) );
