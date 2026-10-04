<?php
/**
 * Synthetic catalogue for manual and browser checks on a disposable site.
 * Not shipped in the release ZIP.
 *
 * @package Product_Description_Blocks
 */

defined( 'ABSPATH' ) || exit;

if ( wc_get_product_id_by_sku( 'pdblocks-tee' ) ) {
	echo "Already seeded.\n";
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';

$pdblocks_attachment = static function ( string $name, array $rgb ): int {
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['path'] ) . $name . '.png';
	$image  = imagecreatetruecolor( 600, 600 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] ) );
	imagepng( $image, $file );

	$id = wp_insert_attachment(
		array(
			'post_title'     => $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		),
		$file
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );

	return $id;
};

$pdblocks_images = array(
	$pdblocks_attachment( 'pdblocks-blue', array( 37, 99, 235 ) ),
	$pdblocks_attachment( 'pdblocks-teal', array( 8, 126, 139 ) ),
	$pdblocks_attachment( 'pdblocks-ink', array( 21, 56, 71 ) ),
);

// Written the way the classic editor stores text: no block markup.
$pdblocks_tee = new WC_Product_Simple();
$pdblocks_tee->set_name( 'Block Tee' );
$pdblocks_tee->set_sku( 'pdblocks-tee' );
$pdblocks_tee->set_regular_price( '20' );
$pdblocks_tee->set_description( 'Original long description.' );
$pdblocks_tee->set_short_description( 'Original short description.' );
$pdblocks_tee->set_featured( true );
$pdblocks_tee->set_catalog_visibility( 'search' );
$pdblocks_tee->set_image_id( $pdblocks_images[0] );
$pdblocks_tee->set_gallery_image_ids( array( $pdblocks_images[1], $pdblocks_images[2] ) );
$pdblocks_tee->save();

$pdblocks_size = new WC_Product_Attribute();
$pdblocks_size->set_name( 'Size' );
$pdblocks_size->set_options( array( 'Small', 'Large' ) );
$pdblocks_size->set_visible( true );
$pdblocks_size->set_variation( true );

$pdblocks_hoodie = new WC_Product_Variable();
$pdblocks_hoodie->set_name( 'Block Hoodie' );
$pdblocks_hoodie->set_sku( 'pdblocks-hoodie' );
$pdblocks_hoodie->set_attributes( array( $pdblocks_size ) );
$pdblocks_hoodie->save();

$pdblocks_variations = array();
foreach ( array( 'Small' => '30', 'Large' => '32' ) as $pdblocks_option => $pdblocks_price ) {
	$pdblocks_variation = new WC_Product_Variation();
	$pdblocks_variation->set_parent_id( $pdblocks_hoodie->get_id() );
	$pdblocks_variation->set_attributes( array( 'size' => $pdblocks_option ) );
	$pdblocks_variation->set_regular_price( $pdblocks_price );
	$pdblocks_variation->save();
	$pdblocks_variations[] = $pdblocks_variation->get_id();
}
WC_Product_Variable::sync( $pdblocks_hoodie->get_id() );

printf(
	"Seeded. Tee %d (featured, search only, gallery %s). Hoodie %d (variations %s).\n",
	(int) $pdblocks_tee->get_id(),
	esc_html( implode( ',', $pdblocks_tee->get_gallery_image_ids() ) ),
	(int) $pdblocks_hoodie->get_id(),
	esc_html( implode( ',', $pdblocks_variations ) )
);
