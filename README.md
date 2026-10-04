# Product Description Blocks for WooCommerce

Write product descriptions and short descriptions with blocks, on the classic WooCommerce product screen.

Activate the plugin and the two description boxes become block editors. Everything else stays the classic screen: Product data, the publish box, the gallery, categories and your extensions' meta boxes.

## How it works

Each editor keeps its form field (`content`, `excerpt`) in sync as block markup. The product form posts those fields with everything else, so price, Featured, catalog visibility, the gallery and variations save the way they always have.

- A description written in the classic editor is shown as blocks, and is only saved as blocks once you edit it.
- The short description's blocks are rendered wherever WooCommerce or the theme shows it. On a block theme, the product's own page keeps the formatting that the Post Excerpt block would otherwise flatten.
- Product screens that already use the block editor, and users who turned off the visual editor, are left alone.

There is no build step. The editor is one script that uses the `wp.*` packages WordPress ships: a `BlockEditorProvider` over a block list, with a fixed toolbar and the inserter as a popover.

## Filters

| Filter | What it does |
| --- | --- |
| `pdblocks_allowed_block_types` | Blocks offered in an editor. Receives the list and the editor (`description` or `short_description`). Return `true` to offer every registered block. |
| `pdblocks_format_post_excerpt_block` | Return `false` to let the Post Excerpt block show the short description as plain text. |

## Requirements

WordPress 7.0 or later, WooCommerce 11.1 or later, PHP 7.4 or later. Tested on WordPress 7.1.2 with WooCommerce 11.1.2.

## Install

Build the plugin as shown below, zip `build/product-description-blocks`, and upload it under Plugins > Add New > Upload Plugin. Or copy that folder into `wp-content/plugins`.

## Develop and test

Build the files that ship, without tests or repo files:

```bash
rsync -a --delete --exclude-from=.distignore ./ build/product-description-blocks/
```

Start a disposable store with [WordPress Playground](https://wordpress.github.io/wordpress-playground/). It installs WooCommerce and Plugin Check, mounts the build and logs you in:

```bash
npx @wp-playground/cli@3.1.56 server --port=9402 --workers=1 --login --blueprint=./tests/blueprint.json --mount=./build/product-description-blocks:/wordpress/wp-content/plugins/product-description-blocks --mount=./tests:/wordpress/wp-content/pdblocks-tests
```

Then, in the browser:

- `http://127.0.0.1:9402/wp-admin/admin-post.php?action=pdblocks_seed` adds two sample products.
- `http://127.0.0.1:9402/wp-admin/admin-post.php?action=pdblocks_run` runs the checks in `tests/run.php`.
- Tools > Plugin Check runs the WordPress.org checks.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
