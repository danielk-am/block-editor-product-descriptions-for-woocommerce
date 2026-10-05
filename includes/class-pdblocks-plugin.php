<?php
/**
 * Block editors for the product description and short description, on the classic product screen.
 *
 * WordPress and WooCommerce skip their own editors and this plugin puts a block editor in each
 * place. Each editor keeps its form field (`content`, `excerpt`) in sync as block markup, so both
 * are saved by the same form submission as the rest of the product.
 *
 * @package Product_Description_Block_Editor
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Swaps the classic editors for block editors on the product screen.
 */
final class PDBlocks_Plugin {

	/**
	 * Blocks offered for the description. Blocks that need a full post editor, such as Post Title
	 * or Query Loop, are left out. Blocks already in a description are kept either way.
	 */
	const DESCRIPTION_BLOCK_TYPES = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/pullquote',
		'core/image',
		'core/gallery',
		'core/video',
		'core/audio',
		'core/file',
		'core/media-text',
		'core/cover',
		'core/embed',
		'core/table',
		'core/buttons',
		'core/button',
		'core/columns',
		'core/column',
		'core/group',
		'core/details',
		'core/separator',
		'core/spacer',
		'core/code',
		'core/preformatted',
		'core/verse',
		'core/html',
		'core/shortcode',
	);

	/**
	 * Blocks offered for the short description. It sits beside the price and the add to cart
	 * button, so it keeps to text.
	 */
	const SHORT_DESCRIPTION_BLOCK_TYPES = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
	);

	/**
	 * Block editor settings the editors use.
	 */
	const EDITOR_SETTINGS = array(
		'alignWide',
		'allowedMimeTypes',
		'imageSizes',
		'imageDimensions',
		'imageDefaultSize',
		'maxUploadFileSize',
		'disableCustomColors',
		'disableCustomFontSizes',
		'disableCustomGradients',
		'disableCustomSpacingSizes',
		'enableCustomLineHeight',
		'enableCustomSpacing',
		'enableCustomUnits',
		'disableLayoutStyles',
		'__experimentalFeatures',
		'colors',
		'gradients',
		'fontSizes',
		'spacingSizes',
		'isRTL',
		'__unstableIsBlockBasedTheme',
	);

	/**
	 * Whether editor support was removed to skip the classic editor, and is due back.
	 *
	 * @var bool
	 */
	private static $editor_support_removed = false;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );
		add_filter( 'wp_editor_expand', array( __CLASS__, 'skip_editor_expand' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_editor' ) );
		add_action( 'edit_form_after_editor', array( __CLASS__, 'restore_editor_support' ) );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'swap_short_description_editor' ) );
		add_filter( 'woocommerce_short_description', array( __CLASS__, 'render_short_description_blocks' ), 9 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'render_excerpt_blocks' ), 9, 2 );
		add_filter( 'render_block_core/post-excerpt', array( __CLASS__, 'render_post_excerpt_blocks' ), 10, 3 );
		add_filter( 'woocommerce_gla_product_attribute_value_description', array( __CLASS__, 'google_description_as_text' ), 10, 2 );
	}

	/**
	 * The plugin only changes the two description boxes, so orders and checkout are unaffected.
	 */
	public static function declare_compatibility() {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', PDBLOCKS_PLUGIN_FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PDBLOCKS_PLUGIN_FILE, true );
	}

	/**
	 * Whether block editors can be used. A user who turned the visual editor off in their profile
	 * keeps the plain editors.
	 *
	 * @return bool
	 */
	private static function can_use_blocks() {
		return class_exists( 'WooCommerce' ) && user_can_richedit();
	}

	/**
	 * Whether the block editor takes the classic editor's place for a post type.
	 *
	 * @param string $post_type Post type of the classic edit screen.
	 * @return bool
	 */
	private static function replaces_editor( $post_type ) {
		return 'product' === $post_type && post_type_supports( 'product', 'editor' ) && self::can_use_blocks();
	}

	/**
	 * The expanding editor script works on the classic editor's own markup, which is not output.
	 *
	 * @param bool   $expand    Whether to enable the expanding editor.
	 * @param string $post_type Post type being edited.
	 * @return bool
	 */
	public static function skip_editor_expand( $expand, $post_type ) {
		return self::replaces_editor( $post_type ) ? false : $expand;
	}

	/**
	 * Blocks offered in an editor's inserter: a list of block names, or true for every registered block.
	 *
	 * @param string $field Which editor: 'description' or 'short_description'.
	 * @return string[]|true
	 */
	public static function get_allowed_block_types( $field = 'description' ) {
		/**
		 * Filters the blocks offered in the product description editors.
		 *
		 * @since 1.0.0
		 *
		 * @param string[]|true $block_types Block names, or true to offer every registered block.
		 * @param string        $field       Which editor: 'description' or 'short_description'.
		 */
		$block_types = apply_filters(
			'pdblocks_allowed_block_types',
			'short_description' === $field ? self::SHORT_DESCRIPTION_BLOCK_TYPES : self::DESCRIPTION_BLOCK_TYPES,
			$field
		);

		return true === $block_types ? true : array_values( array_filter( (array) $block_types, 'is_string' ) );
	}

	/**
	 * Output a block editor's mount point and the form field it writes to. The field shows as a
	 * plain text area until the editor is running, so the content can always be edited and saved.
	 *
	 * @param string $name    Name of the form field: 'content' or 'excerpt'.
	 * @param string $content Stored content.
	 * @param string $label   Accessible name of the field.
	 */
	private static function render_field( $name, $content, $label ) {
		?>
		<div class="pdblocks-field" data-pdblocks-field="<?php echo esc_attr( $name ); ?>">
			<div class="pdblocks-field__editor"></div>
			<textarea id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" class="pdblocks-field__content" rows="10" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Output the description's block editor where the classic editor would be.
	 *
	 * Only the classic edit form fires this hook, so a product screen that already uses the block
	 * editor is left alone.
	 *
	 * @param WP_Post $post The post being edited.
	 */
	public static function render_editor( $post ) {
		if ( ! $post instanceof WP_Post || ! self::replaces_editor( $post->post_type ) ) {
			return;
		}

		// WordPress checks editor support right after this hook. Without it, the classic editor is skipped.
		remove_post_type_support( 'product', 'editor' );
		self::$editor_support_removed = true;
		?>
		<div id="postdivrich" class="postarea">
			<?php self::render_field( 'content', get_post_field( 'post_content', $post->ID, 'raw' ), __( 'Product description', 'product-description-block-editor-for-woocommerce' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Give products their editor support back once WordPress has skipped the classic editor.
	 */
	public static function restore_editor_support() {
		if ( self::$editor_support_removed ) {
			add_post_type_support( 'product', 'editor' );
			self::$editor_support_removed = false;
		}
	}

	/**
	 * Hand WooCommerce's short description meta box to the block editor, on the classic product screen.
	 *
	 * @param WP_Post $post The product being edited.
	 */
	public static function swap_short_description_editor( $post ) {
		global $wp_meta_boxes;

		if ( ! self::can_use_blocks() || use_block_editor_for_post( $post ) ) {
			return;
		}

		foreach ( (array) ( $wp_meta_boxes['product'] ?? array() ) as $context => $priorities ) {
			foreach ( (array) $priorities as $priority => $meta_boxes ) {
				// Only WooCommerce's own editor is swapped: a box another plugin replaced is left alone.
				if ( 'WC_Meta_Box_Product_Short_Description::output' === ( $meta_boxes['postexcerpt']['callback'] ?? null ) ) {
					$wp_meta_boxes['product'][ $context ][ $priority ]['postexcerpt']['callback'] = array( __CLASS__, 'render_short_description_editor' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Swaps one meta box callback.
				}
			}
		}
	}

	/**
	 * Output the short description's block editor in its meta box.
	 *
	 * @param WP_Post $post The product being edited.
	 */
	public static function render_short_description_editor( $post ) {
		self::render_field( 'excerpt', get_post_field( 'post_excerpt', $post->ID, 'raw' ), __( 'Product short description', 'product-description-block-editor-for-woocommerce' ) );
	}

	/**
	 * Render the blocks in a short description. WordPress does this for post content only, so
	 * without it the block comments would reach wpautop() and leave empty paragraphs behind.
	 *
	 * @param string $short_description Short description being displayed.
	 * @return string
	 */
	public static function render_short_description_blocks( $short_description ) {
		if ( ! is_string( $short_description ) || ! has_blocks( $short_description ) ) {
			return $short_description;
		}

		// Blocks bring their own paragraphs, so wpautop() sits this one out, as it does for post content.
		$priority = has_filter( 'woocommerce_short_description', 'wpautop' );
		if ( false !== $priority ) {
			remove_filter( 'woocommerce_short_description', 'wpautop', $priority );
			add_filter( 'woocommerce_short_description', array( __CLASS__, 'restore_short_description_autop' ), $priority + 1 );
		}

		return do_blocks( $short_description );
	}

	/**
	 * Put wpautop() back for the next short description.
	 *
	 * @param string $short_description Short description being displayed.
	 * @return string
	 */
	public static function restore_short_description_autop( $short_description ) {
		$priority = has_filter( 'woocommerce_short_description', array( __CLASS__, 'restore_short_description_autop' ) );

		add_filter( 'woocommerce_short_description', 'wpautop', $priority - 1 );
		remove_filter( 'woocommerce_short_description', array( __CLASS__, 'restore_short_description_autop' ), $priority );

		return $short_description;
	}

	/**
	 * Render the blocks in a product's excerpt, for themes and blocks that show the short
	 * description through the excerpt.
	 *
	 * @param string       $excerpt The post excerpt.
	 * @param WP_Post|null $post    The post it belongs to.
	 * @return string
	 */
	public static function render_excerpt_blocks( $excerpt, $post = null ) {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type || ! is_string( $excerpt ) || ! has_blocks( $excerpt ) ) {
			return $excerpt;
		}

		return do_blocks( $excerpt );
	}

	/**
	 * Show a short description written in blocks with its formatting on the product's own page,
	 * where a block theme displays it through the Post Excerpt block. That block trims the excerpt
	 * to plain text, which would flatten lists, headings and buttons into one line.
	 *
	 * Short descriptions without blocks, and excerpts shown anywhere else, keep the block's own output.
	 *
	 * @param string   $block_content Rendered Post Excerpt block.
	 * @param array    $block         Parsed block.
	 * @param WP_Block $instance      Block instance.
	 * @return string
	 */
	public static function render_post_excerpt_blocks( $block_content, $block, $instance ) {
		$post_id = (int) ( $instance->context['postId'] ?? 0 );

		if ( ! $post_id || ! is_singular( 'product' ) || get_queried_object_id() !== $post_id ) {
			return $block_content;
		}

		$short_description = get_post_field( 'post_excerpt', $post_id, 'raw' );

		/**
		 * Filters whether a short description written in blocks keeps its formatting in the Post Excerpt block.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $show    Whether to show the formatted short description.
		 * @param int  $post_id Product ID.
		 */
		if ( ! has_blocks( $short_description ) || ! apply_filters( 'pdblocks_format_post_excerpt_block', true, $post_id ) ) {
			return $block_content;
		}

		/** This filter is documented in woocommerce/templates/single-product/short-description.php */
		$short_description = apply_filters( 'woocommerce_short_description', $short_description ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, so the short description renders as WooCommerce renders it.

		$formatted = preg_replace_callback(
			'#<p class="wp-block-post-excerpt__excerpt">.*?</p>#s',
			static function () use ( $short_description ) {
				return '<div class="wp-block-post-excerpt__excerpt">' . $short_description . '</div>';
			},
			$block_content,
			1,
			$count
		);

		return $count ? $formatted : $block_content;
	}

	/**
	 * Send Google for WooCommerce product descriptions as plain text.
	 *
	 * Google defines the description as a string of up to 5,000 characters. Google for WooCommerce
	 * keeps HTML tags and comments when it cleans a description, so the tags, and the block markup
	 * of a description written in blocks, would reach Google as part of it.
	 *
	 * @param string          $description Description prepared by Google for WooCommerce.
	 * @param WC_Product|null $product     The product being synced.
	 * @return string
	 */
	public static function google_description_as_text( $description, $product = null ) {
		if ( ! is_string( $description ) ) {
			return $description;
		}

		/**
		 * Filters whether a description is sent to Google for WooCommerce as plain text.
		 *
		 * @since 1.0.0
		 *
		 * @param bool            $as_text     Whether to send plain text. Default true.
		 * @param string          $description Description prepared by Google for WooCommerce.
		 * @param WC_Product|null $product     The product being synced.
		 */
		if ( ! apply_filters( 'pdblocks_google_description_as_text', true, $description, $product ) ) {
			return $description;
		}

		return self::html_to_text( $description );
	}

	/**
	 * Turn a description's HTML into plain text, one line per paragraph, heading, list item or table row.
	 *
	 * @param string $html HTML, with or without block markup.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		// Comments carry the block markup. A length limit can cut the last comment or tag short.
		$text = preg_replace( '/<!--.*?(?:-->|$)/s', '', (string) $html );
		$text = preg_replace( '/<[^>]*$/', '', $text );

		$text = preg_replace( '/<li\b[^>]*>/i', '- ', $text );
		$text = preg_replace( '/<\/t[dh]>/i', ' | ', $text );
		$text = preg_replace( '/<br\b[^>]*>|<\/(?:p|div|h[1-6]|li|tr|blockquote|figure|figcaption|pre|ul|ol|table|details|summary)>/i', "\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$lines = array();
		foreach ( preg_split( '/\R/u', $text ) as $line ) {
			$line = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $line ), " |" );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Load the block editors on the classic product screen.
	 */
	public static function enqueue_assets() {
		global $post;

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || 'product' !== $screen->post_type || $screen->is_block_editor() || ! $post instanceof WP_Post || ! self::can_use_blocks() ) {
			return;
		}

		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'wp-edit-blocks', 'wp-block-editor', 'wp-components', 'wp-format-library' ) as $style ) {
			wp_enqueue_style( $style );
		}
		wp_enqueue_style(
			'pdblocks-editor',
			plugins_url( 'assets/css/editor.css', PDBLOCKS_PLUGIN_FILE ),
			array( 'wp-block-editor', 'wp-components', 'wp-edit-blocks' ),
			PDBLOCKS_VERSION
		);

		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_script(
			'pdblocks-editor',
			plugins_url( 'assets/js/editor.js', PDBLOCKS_PLUGIN_FILE ),
			array( 'wp-autop', 'wp-block-editor', 'wp-block-library', 'wp-blocks', 'wp-components', 'wp-compose', 'wp-core-data', 'wp-data', 'wp-element', 'wp-format-library', 'wp-hooks', 'wp-i18n', 'wp-keyboard-shortcuts', 'wp-keycodes', 'wp-media-utils' ),
			PDBLOCKS_VERSION,
			true
		);
		wp_set_script_translations( 'pdblocks-editor', 'product-description-block-editor-for-woocommerce' );

		$settings = get_block_editor_settings( array(), new WP_Block_Editor_Context( array( 'post' => $post ) ) );

		wp_localize_script(
			'pdblocks-editor',
			'pdblocksSettings',
			array(
				'editor'    => array_intersect_key( $settings, array_flip( self::EDITOR_SETTINGS ) ),
				'canUpload' => current_user_can( 'upload_files' ),
				'fields'    => array(
					'content' => array(
						'allowedBlockTypes' => self::get_allowed_block_types( 'description' ),
						'placeholder'       => __( 'Describe this product, or type / to choose a block', 'product-description-block-editor-for-woocommerce' ),
					),
					'excerpt' => array(
						'allowedBlockTypes' => self::get_allowed_block_types( 'short_description' ),
						'placeholder'       => __( 'Sum this product up, or type / to choose a block', 'product-description-block-editor-for-woocommerce' ),
					),
				),
			)
		);
	}
}
