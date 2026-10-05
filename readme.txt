=== Product Description Blocks for WooCommerce ===
Contributors: danielkam1
Tags: woocommerce, block editor, gutenberg, product description, short description
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write WooCommerce product descriptions and short descriptions with blocks, on the classic product screen.

== Description ==

Product Description Blocks turns the two description boxes on the WooCommerce product screen into block editors. You get headings, lists, images, tables, buttons and columns in your product copy, and the rest of the screen stays where it is.

Nothing else about the product screen changes. Each editor writes to the same form field WooCommerce already saves, so price, Featured, catalog visibility, the gallery and variations are saved by the same Update button as before. The plugin does not touch Product data, the publish box or the meta boxes your extensions add.

= What you can do =

* Add blocks with the + button, or type / in an empty line.
* Use the toolbar for undo, redo and the selected block's tools. The cog opens that block's settings.
* Insert images from the Media Library, or upload new ones.
* Write the short description in blocks too. The plugin renders those blocks on the storefront.

= Descriptions you already have =

Opening a product does not change it. A description written in the classic editor is shown as blocks, and it is only saved as blocks once you edit it. Until then it is posted back as it was stored.

= Blocks on offer =

The description offers Paragraph, Heading, List, Quote, Pullquote, Image, Gallery, Video, Audio, File, Media & Text, Cover, Embed, Table, Buttons, Columns, Group, Details, Separator, Spacer, Code, Preformatted, Verse, Custom HTML and Shortcode.

The short description sits beside the price and the add to cart button, so it offers a shorter list: Paragraph, Heading, List, Quote, Image, Buttons, Separator, Custom HTML and Shortcode.

Blocks that need a full post editor, such as Post Title or Query Loop, are left out.

= Limits worth knowing =

* The editors draw blocks with WordPress's default block styles. Fonts and colours can differ from your storefront, so check the product page after a bigger change.
* Only the blocks that come with WordPress load in these editors. Blocks from other plugins are not offered.
* Panels that belong to the full post editor, such as an SEO plugin's sidebar, are not available here.
* Users who turned off the visual editor in their profile keep the plain editors.
* Tested on WordPress 7.1.2 with WooCommerce 11.1.2 and a block theme. Not tested in a browser with a classic theme, on multisite, or with extensions that add their own scripts to the product screen.

= For developers =

* `pdblocks_allowed_block_types` filters the blocks an editor offers. It receives the list and which editor it is for: `description` or `short_description`.
* `pdblocks_format_post_excerpt_block` controls whether a short description written in blocks keeps its formatting in the Post Excerpt block on the product page. Return `false` to turn that off.

The source and tests are on [GitHub](https://github.com/danielk-am/product-description-blocks-for-woocommerce).

== Installation ==

1. In your WordPress admin, go to Plugins > Add New, search for "Product Description Blocks for WooCommerce" and install it. Or upload the ZIP under Plugins > Add New > Upload Plugin.
2. Activate the plugin. WooCommerce needs to be active.
3. Edit any product. The description and short description boxes are now block editors.

There are no settings. Deactivate the plugin to go back to the classic editors.

== Frequently Asked Questions ==

= Does this switch the product screen to the block editor? =

No. The product screen stays the classic WooCommerce screen. Only the product description and product short description boxes change.

= Will it change my existing product descriptions? =

Not until you edit them. A description written in the classic editor is converted to blocks for editing, the same way the Classic block's "Convert to blocks" does it. If you save the product without touching the description, it is saved as it was.

= Does the short description keep its formatting on the storefront? =

On the product's own page, yes. WooCommerce does not render blocks in the short description by itself, so the plugin does. Block themes show the short description through the Post Excerpt block, which normally flattens it to plain text. On the product page the plugin keeps the formatting of a short description written in blocks. Anywhere a theme shows a trimmed excerpt, such as a product grid, it stays plain text.

= Can I use blocks from other plugins? =

Not yet. These editors load the blocks that come with WordPress. Blocks registered by other plugins are not offered.

= Does the editor show my theme's fonts and colours? =

No. Blocks are drawn with WordPress's default block styles inside the admin. The storefront still uses your theme, so the product page is the place to check the final look.

= What if my product screen already uses the block editor? =

Then this plugin stands aside. It only acts on the classic product screen.

= What happens if I deactivate the plugin? =

The classic editors come back. Descriptions saved as blocks keep their block markup, and WordPress keeps rendering blocks in the description. Short descriptions written in blocks rely on this plugin to render cleanly. Without it, WooCommerce treats the block markup as plain HTML, which may leave empty paragraphs.

== Changelog ==

= 1.0.0 =
* First release.
