<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/18/18
 * Time: 10:48 AM
 */


class Brizy_Admin_Fonts_Api extends Brizy_Admin_AbstractApi {

	const nonce = 'brizy-api';

	const AJAX_CREATE_FONT_ACTION = 'brizy-create-font';
	const AJAX_DELETE_FONT_ACTION = 'brizy-delete-font';
	const AJAX_GET_FONTS_ACTION = 'brizy-get-fonts';

	/**
	 * @return Brizy_Admin_Fonts_Api
	 */
	public static function _init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}


	protected function getRequestNonce() {
		return $this->param( 'hash' );
	}

	protected function initializeApiActions() {
		add_action( 'wp_ajax_' . self::AJAX_CREATE_FONT_ACTION, array( $this, 'actionCreateFont' ) );
		add_action( 'wp_ajax_' . self::AJAX_DELETE_FONT_ACTION, array( $this, 'actionDeleteFont' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_FONTS_ACTION, array( $this, 'actionGetFonts' ) );
	}

	public function actionGetFonts() {

		global $wpdb;

		$fonts = get_posts( array(
			'post_type'   => Brizy_Admin_Fonts_Main::CP_FONT,
			'post_status' => 'publish',
			'numberposts' => - 1,
		) );

		$result = array();

		foreach ( $fonts as $font ) {

			$weights = $wpdb->get_results( $wpdb->prepare(
				"SELECT m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON  m.post_id=p.ID && p.post_parent=%d && m.meta_key='brizy-font-weight'", array( $font->ID )
			), ARRAY_A );

			$result[] = array(
				'family'  => $font->post_title,
				'weights' => array_map( function ( $v ) {
					return $v['meta_value'];
				}, $weights )
			);
		}

		$this->success( $result );
	}


	/**
	 *
	 */
	public function actionCreateFont() {
		try {

			global $wpdb;

			if ( ! ( $family = $this->param( 'family' ) ) ) {
				$this->error( 400, 'Invalid font family' );
			}

			if ( ! isset( $_FILES['fonts'] ) ) {
				$this->error( 400, 'Invalid font files' );
			}


			$existingFont = get_posts(
				[
					'post_title'  => $family,
					'post_name'   => $family,
					'post_type'   => Brizy_Admin_Fonts_Main::CP_FONT,
					'post_status' => 'publish',
				]
			);

			if ( count( $existingFont ) != 0 ) {
				$this->error( 400, 'This font family already exists.' );
			}

			$wpdb->query( 'START TRANSACTION ' );

			try {

				// create font post
				$fontId = wp_insert_post( [
					'post_title'  => $family,
					'post_name'   => $family,
					'post_type'   => Brizy_Admin_Fonts_Main::CP_FONT,
					'post_status' => 'publish',

				] );

				if ( ! $fontId ) {
					$this->error( 400, 'Unable to create font' );
				}


				$uid = md5( $fontId . time() );
				update_post_meta( $fontId, 'brizy_post_uid', $uid );

				// create font attachments
				foreach ( $_FILES['fonts']['name'] as $weight => $attachments ) {
					foreach ( $attachments as $type => $file ) {
						$file = array(
							'name'     => $_FILES['fonts']['name'][ $weight ][ $type ],
							'type'     => $_FILES['fonts']['type'][ $weight ][ $type ],
							'tmp_name' => $_FILES['fonts']['tmp_name'][ $weight ][ $type ],
							'error'    => $_FILES['fonts']['error'][ $weight ][ $type ],
							'size'     => $_FILES['fonts']['size'][ $weight ][ $type ]
						);

						$id = media_handle_sideload( $file, $fontId, "Font attachment" );

						update_post_meta( $id, 'brizy-font-weight', $weight );
						update_post_meta( $id, 'brizy-font-file-type', $type );

						if ( is_wp_error( $id ) ) {
							throw new Exception( 'Unable to handle font sideload' );
						}
					}
				}

				$wpdb->query( 'COMMIT' );

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				Brizy_Logger::instance()->debug( 'Create font ERROR', [ $e ] );
				$this->error( 400, $e->getMessage() );
			}

			$this->success( [ 'uid' => $uid, 'postId' => $fontId, 'family' => $family ] );

		} catch ( Exception $exception ) {
			$this->error( 400, $exception->getMessage() );
		}
	}

	public function actionDeleteFont() {

		global $wpdb;

		if ( ! ( $family = $this->param( 'family' ) ) ) {
			$this->error( 400, 'Invalid font family' );
		}

		$font = get_posts( [
			'post_type'  => Brizy_Admin_Fonts_Main::CP_FONT,
			'post_title' => $family,
			'post_name'  => $family
		] );


		if ( count( $font ) > 0 ) {
			$font = $font[0];
		} else {
			$font = null;
		}

		if ( ! $font ) {
			$this->error( 404, 'Font not found' );
		}

		$wpdb->query( 'START TRANSACTION ' );

		try {

			// delete all attachments first

			$attachments = get_attached_media( '', $font->ID );

			foreach ( $attachments as $attachment ) {
				wp_delete_attachment( $attachment->ID, 'true' );
			}

			// delete font
			wp_delete_post( $font->ID );

			$wpdb->query( 'COMMIT' );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			Brizy_Logger::instance()->debug( 'Delete font ERROR', [ $e ] );
			$this->error( 400, $e->getMessage() );
		}

		$this->success( [] );
	}
}