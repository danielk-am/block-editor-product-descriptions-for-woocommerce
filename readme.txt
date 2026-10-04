=== Product Description Blocks for WooCommerce ===
Tags: woocommerce, block editor, gutenberg, product description, short description
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write product descriptions and short descriptions with blocks, on the classic product screen.

== Description ==

Activate the plugin and the two description boxes on the product screen become block editors. Everything else on the screen stays the classic WooCommerce screen: Product data, the publish box, the gallery, categories and your extensions' meta boxes.

Each editor keeps its form field in sync as block markup. The product form posts those fields with everything else, so price, Featured, catalog visibility, the gallery and variations save the way they always have.

= What you get =

* **A block editor in each box.** Add blocks with the + button or by typing /. The toolbar carries undo, redo and the selected block's tools. The cog opens that block's settings.
* **Media Library and uploads** for image and other media blocks.
* **Content blocks only.** The description offers text, media, tables, buttons, columns, embeds and shortcodes. The short description offers a shorter list. Blocks that need a full post editor, such as Post Title or Query Loop, are left out.
* **Blocks on the storefront.** The description already renders blocks. For the short description, the plugin renders its blocks wherever WooCommerce or the theme shows it.

= Descriptions you already have =

Opening a product does not change it. A description written in the classic editor is shown as blocks, but it is only saved as blocks once you edit it. Until then it is posted back as it was stored.

= Good to know =

* **Block themes and the short description.** Block themes show the short description through the Post Excerpt block, which flattens it to plain text. On the product's own page, the plugin keeps the formatting of a short description written in blocks. Return false from the `pdblocks_format_post_excerpt_block` filter to turn that off.
* **The editor is not the storefront.** Blocks are drawn with WordPress's default block styles, so fonts and colours can differ from your theme.
* **No post editor underneath.** Blocks or plugin panels that depend on the full post editor, such as SEO sidebars, are not available in these editors.
* **Choosing blocks.** Use the `pdblocks_allowed_block_types` filter. It receives the list and which editor it is for: `description` or `short_description`. Return `true` to offer every registered block.
* **Who keeps the classic editors.** Users who turned off the visual editor in their profile, and product screens that already use the block editor, are left alone.
* **Browser backups.** WordPress's "Restore the backup" notice restores into the block editors. Undo brings back what was there before.
* Tested on WordPress 7.1.2 with WooCommerce 11.1.2 and a block theme. Not tested in a browser with a classic theme, on multisite, on older WordPress or WooCommerce versions, or with extensions that add their own scripts to the product screen.

Deactivate the plugin to go back to the classic editors. Descriptions saved as blocks keep their block markup.

== Installation ==

1. Go to Plugins > Add New > Upload Plugin and upload the ZIP, or copy the `product-description-blocks` folder into `wp-content/plugins`.
2. Activate the plugin. WooCommerce needs to be active.
3. Edit any product.

== Changelog ==

= 1.0.0 =
* First release.
