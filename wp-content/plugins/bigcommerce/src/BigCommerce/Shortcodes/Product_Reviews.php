<?php


namespace BigCommerce\Shortcodes;


use BigCommerce\Post_Types\Product\Product;
use BigCommerce\Templates\Product_Reviews as Template_Product_Reviews;
use BigCommerce\Templates\Review_Single;
use Pimple\Container;

class Product_Reviews implements Shortcode {

	const NAME = 'bigcommerce_reviews';

	/**
	 * Set default attributes
	 *
	 * @return array
	 */
	public static function default_attributes() {
		return [
			'id'      => '', // BigCommerce product ID
			'post_id' => '', // WordPress product post ID
		];
	}

	/**
	 * @param array $attributes key id is expected
	 * @param int   $instance
	 *
	 * @return string
	 */
	public function render( $attributes, $instance ) {
		$attr = shortcode_atts( self::default_attributes(), $attributes, self::NAME );

		if ( ! empty( $attr[ 'id' ] ) ) {
			// The $attr['id'] is the BC product ID
			try {
				$product = Product::by_product_id( absint( $attr[ 'id' ] ) );
			} catch ( \Exception $e ) {
				return '';
			}
		} else {
			$post_id = empty( $attr[ 'post_id' ] ) ? get_the_ID() : absint( $attr[ 'post_id' ] );
			if ( empty( $post_id ) || get_post_type( $post_id ) !== Product::NAME ) {
				return '';
			}
			$product = new Product( $post_id );
		}

		/**
		 * Filter the number of product reviews to show per page.
		 *
		 * @param int $per_page The number of reviews to show per page
		 * @param int $post_id  The ID of the product post
		 */
		$per_page = absint( apply_filters( 'bigcommerce/products/reviews/per_page', 12, $product->post_id() ) );

		if ( empty( $per_page ) ) {
			return '';
		}

		$total_reviews  = $product->get_review_count();
		$total_pages    = empty( $total_reviews ) ? 0 : ceil( $total_reviews / $per_page );

		$controller = Template_Product_Reviews::factory( [
			Template_Product_Reviews::PRODUCT        => $product,
			Template_Product_Reviews::FIRST_PAGE_URL => $this->next_page_url( $product->post_id(), $per_page, 0, $total_pages ),
			Template_Product_Reviews::NEXT_PAGE_URL  => $this->next_page_url( $product->post_id(), $per_page, 1, $total_pages ),
		] );

		return $controller->render();
	}

	/**
	 * Build the URL for the next page of reviews
	 *
	 * @param int $post_id
	 * @param int $per_page
	 * @param int $current_page
	 * @param int $max_pages
	 *
	 * @return string
	 */
	private function next_page_url( $post_id, $per_page, $current_page, $max_pages ) {
		if ( $current_page >= $max_pages ) {
			return '';
		}

		/**
		 * Filters product reviews REST url.
		 *
		 * @param string $base_url URL.
		 * @param int    $post_id  Post id.
		 */
		$base_url = apply_filters( 'bigcommerce/product/reviews/rest_url', '', $post_id );

		$attr = [
			'per_page' => $per_page,
			'paged'    => $current_page + 1,
			'ajax'     => 1,
		];

		$url = add_query_arg( $attr, $base_url );
		$url = wp_nonce_url( $url, 'wp_rest' );

		return $url;
	}
}
