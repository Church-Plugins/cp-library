<?php

namespace CP_Library;

// Exit if accessed directly
use CP_Library\Controllers\Item;

if ( ! defined( 'ABSPATH' ) ) exit;

class Download {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Init
	 *
	 * @return Download
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Download ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', [ $this, 'process_download' ], 100 );
		add_filter( 'cp_requested_file', [ $this, 'set_requested_file_scheme' ], 10 );
	}

	/**
	 * Process Download
	 *
	 * Handles the file download process.
	 *
	 * @access      private
	 * @return      void
	 * @since       1.0
	 */
	function process_download() {

		if ( ! isset( $_GET['item_id'], $_GET['key'] ) ) {
			return;
		}

		$args = apply_filters( 'cp_process_download_args', array(
			'item_id'    => ( isset( $_GET['item_id'] ) ) ? (int) $_GET['item_id'] : '',
			'key'        => ( isset( $_GET['key'] ) ) ? $_GET['key'] : '',
			'has_access' => true,
			'file_name'  => ( isset( $_GET['name'] ) ) ? $_GET['name'] : '',
		) );

		if ( ! $args['has_access'] ) {
			$error_message = __( 'You do not have permission to download this file', 'cp-library' );
			wp_die( apply_filters( 'cp_deny_download_message', $error_message, __( 'Download Verification Failed', 'cp-library' ) ), __( 'Error', 'cp-library' ), array( 'response' => 403 ) );
		}

		do_action( 'cp_process_verified_download', $args );

		// Determine the download method set in settings
		$method = apply_filters( 'cp_download_method', 'direct', $args );

		try {
			$item = new Item( $args['item_id'] );
			$file = $item->get_audio();

			if ( ! $file ) {
				wp_die( __( 'Error downloading file. Please contact support.', 'cp-library' ), __( 'File download error', 'cp-library' ), 501 );
			}

			$requested_file = $file;
		} catch ( Exception $e ) {
			wp_die( $e->getMessage(), __( 'File download error', 'cp-library' ), 501 );
		}

		// future enhancement to support attachment downloads
		$attachment_id  = false; // ! empty( $download_files[ $args['file_key'] ]['attachment_id'] ) ? absint( $download_files[ $args['file_key'] ]['attachment_id'] ) : false;
		$thumbnail_size = false ; // ! empty( $download_files[ $args['file_key'] ]['thumbnail_size'] ) ? sanitize_text_field( $download_files[ $args['file_key'] ]['thumbnail_size'] ) : false;

		/*
		 * If we have an attachment ID stored, use get_attached_file() to retrieve absolute URL
		 * If this fails or returns a relative path, we fail back to our own absolute URL detection
		 */
		$from_attachment_id = false;
		if ( $this->is_local_file( $requested_file ) && $attachment_id && 'attachment' == get_post_type( $attachment_id ) ) {

			if ( 'pdf' === strtolower( $this->get_file_extension( $requested_file ) ) ) {
				// Do not ever grab the thumbnail for PDFs.
				$thumbnail_size = false;
			}

			if ( 'redirect' == $method ) {

				if ( $thumbnail_size ) {
					$attached_file = wp_get_attachment_image_url( $attachment_id, $thumbnail_size, false );
				} else {
					$attached_file = wp_get_attachment_url( $attachment_id );
				}

			} else {

				if ( $thumbnail_size ) {

					$attachment_data = wp_get_attachment_image_src( $attachment_id, $thumbnail_size, false );

					if ( false !== $attachment_data && ! empty( $attachment_data[0] ) && filter_var( $attachment_data[0], FILTER_VALIDATE_URL ) !== false ) {
						$attached_file = $attachment_data['0'];
						$attached_file = str_replace( site_url(), '', $attached_file );
						$attached_file = realpath( ABSPATH . $attached_file );
					}

				}

				if ( empty( $attached_file ) ) {
					$attached_file = get_attached_file( $attachment_id, false );
				}

				// Confirm the file exists
				if ( ! file_exists( $attached_file ) ) {
					$attached_file = false;
				}

			}

			if ( $attached_file ) {

				$from_attachment_id = true;
				$requested_file     = $attached_file;

			}

		}

		// Allow the file to be altered before any headers are sent
		$requested_file = apply_filters( 'cp_requested_file', $requested_file, $args );

		if ( 'x_sendfile' == $method && ( ! function_exists( 'apache_get_modules' ) || ! in_array( 'mod_xsendfile', apache_get_modules() ) ) ) {
			// If X-Sendfile is selected but is not supported, fallback to Direct
			$method = 'direct';
		}

		$file_details = parse_url( $requested_file );
		$schemes      = array( 'http', 'https' ); // Direct URL schemes

		$supported_streams = stream_get_wrappers();
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' && isset( $file_details['scheme'] ) && ! in_array( $file_details['scheme'], $supported_streams ) ) {
			wp_die( __( 'Error downloading file. Please contact support.', 'cp-library' ), __( 'File download error', 'cp-library' ), 501 );
		}

		if ( ( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], $schemes ) ) && isset( $file_details['path'] ) && file_exists( $requested_file ) ) {

			/**
			 * Download method is set to Redirect in settings but an absolute path was provided
			 * We need to switch to a direct download in order for the file to download properly
			 */
			$method = 'direct';

		}

		/**
		 * Allow extensions to run actions prior to recording the file download log entry
		 *
		 * @since 2.6.14
		 */
		do_action( 'cp_process_download_pre_record_log', $requested_file, $args, $method );

		// Record this file download in the log
		$user_info          = array();
		$user_info['email'] = $args['email'];
		if ( is_user_logged_in() ) {
			$user_data         = get_userdata( get_current_user_id() );
			$user_info['id']   = get_current_user_id();
			$user_info['name'] = $user_data->display_name;
		}

		$file_extension = $this->get_file_extension( $requested_file );
		$ctype          = $this->get_file_ctype( $file_extension );

		if ( ! $this->is_func_disabled( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		// If we're using an attachment ID to get the file, even by path, we can ignore this check.
		if ( false === $from_attachment_id ) {
			$file_is_in_allowed_location = $this->local_file_location_is_allowed( $file_details, $schemes, $requested_file );
			if ( false === $file_is_in_allowed_location ) {
				wp_die( __( 'Sorry, this file could not be downloaded.', 'cp-library' ), __( 'Error Downloading File', 'cp-library' ), 403 );
			}
		}

		@session_write_close();
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', 'Off' );

		do_action( 'cp_process_download_headers', $requested_file, $args['download'], $args['email'], $args['payment'] );

		if ( ! $args['file_name'] ) {
			$args['file_name'] = basename( $requested_file );
		}

		nocache_headers();
		header( "Robots: none" );
		header( "Content-Type: " . $ctype . "" );
		header( "Content-Description: File Transfer" );
		header( "Content-Disposition: attachment; filename=\"" . apply_filters( 'cp_requested_file_name', $args['file_name'], $args ) . "\"" );
		header( "Content-Transfer-Encoding: binary" );

		// If the file isn't locally hosted, process the redirect
		if ( filter_var( $requested_file, FILTER_VALIDATE_URL ) && ! $this->is_local_file( $requested_file ) ) {
			$this->deliver_download( $requested_file );
			exit;
		}

		switch ( $method ) :

			case 'redirect' :

				// Redirect straight to the file
				$this->deliver_download( $requested_file, true );
				break;

			case 'direct' :
			default:

				$direct    = false;
				$file_path = $requested_file;

				if ( ( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], $schemes ) ) && isset( $file_details['path'] ) && file_exists( $requested_file ) ) {

					/** This is an absolute path */
					$direct    = true;
					$file_path = $requested_file;

				} else if ( defined( 'UPLOADS' ) && strpos( $requested_file, UPLOADS ) !== false ) {

					/**
					 * This is a local file given by URL so we need to figure out the path
					 * UPLOADS is always relative to ABSPATH
					 * site_url() is the URL to where WordPress is installed
					 */
					$file_path = str_replace( site_url(), '', $requested_file );
					$file_path = realpath( ABSPATH . $file_path );
					$direct    = true;

				} else if ( strpos( $requested_file, content_url() ) !== false ) {

					/** This is a local file given by URL so we need to figure out the path */
					$file_path = str_replace( content_url(), WP_CONTENT_DIR, $requested_file );
					$file_path = realpath( $file_path );
					$direct    = true;

				} else if ( strpos( $requested_file, set_url_scheme( content_url(), 'https' ) ) !== false ) {

					/** This is a local file given by an HTTPS URL so we need to figure out the path */
					$file_path = str_replace( set_url_scheme( content_url(), 'https' ), WP_CONTENT_DIR, $requested_file );
					$file_path = realpath( $file_path );
					$direct    = true;

				}

				// Set the file size header
				header( "Content-Length: " . @filesize( $file_path ) );

				// Now deliver the file based on the kind of software the server is running / has enabled
				if ( stristr( getenv( 'SERVER_SOFTWARE' ), 'lighttpd' ) ) {

					header( "X-LIGHTTPD-send-file: $file_path" );

				} elseif ( $direct && ( stristr( getenv( 'SERVER_SOFTWARE' ), 'nginx' ) || stristr( getenv( 'SERVER_SOFTWARE' ), 'cherokee' ) ) ) {

					$ignore_x_accel_redirect_header = apply_filters( 'cp_ignore_x_accel_redirect', false );

					if ( ! $ignore_x_accel_redirect_header ) {
						// We need a path relative to the domain
						$file_path = str_ireplace( realpath( $_SERVER['DOCUMENT_ROOT'] ), '', $file_path );
						header( "X-Accel-Redirect: /$file_path" );
					}

				}

				if ( $direct ) {

					$this->deliver_download( $file_path );

				} else {

					// The file supplied does not have a discoverable absolute path
					$this->deliver_download( $requested_file, true );

				}

				break;

		endswitch;

		wp_die();
	}

	/**
	 * Deliver the download file
	 *
	 * If enabled, the file is symlinked to better support large file downloads
	 *
	 * @param string $file
	 * @param bool   $redirect True if we should perform a header redirect instead of calling $this->readfile_chunked()
	 *
	 * @return   void
	 */
	function deliver_download( $file = '', $redirect = false ) {

		if ( $redirect ) {

			header( 'Location: ' . $file );

		} else {

			// Read the file and deliver it in chunks
			$this->readfile_chunked( $file );

		}

	}

	/**
	 * Determine if the file being requested is hosted locally or not
	 *
	 * @param string $requested_file The file being requested
	 *
	 * @return bool                   If the file is hosted locally or not
	 * @since  1.0.0
	 */
	function is_local_file( $requested_file ) {
		$home_url       = preg_replace( '#^https?://#', '', home_url() );
		$requested_file = preg_replace( '#^(https?|file)://#', '', $requested_file );

		$is_local_url  = strpos( $requested_file, $home_url ) === 0;
		$is_local_path = strpos( $requested_file, '/' ) === 0;

		return ( $is_local_url || $is_local_path );
	}

	/**
	 * Get the file content type
	 *
	 * @param string    file extension
	 *
	 * @return   string
	 */
	function get_file_ctype( $extension ) {
		switch ( $extension ):
			case 'ac'       :
				$ctype = "application/pkix-attr-cert";
				break;
			case 'adp'      :
				$ctype = "audio/adpcm";
				break;
			case 'ai'       :
				$ctype = "application/postscript";
				break;
			case 'aif'      :
				$ctype = "audio/x-aiff";
				break;
			case 'aifc'     :
				$ctype = "audio/x-aiff";
				break;
			case 'aiff'     :
				$ctype = "audio/x-aiff";
				break;
			case 'air'      :
				$ctype = "application/vnd.adobe.air-application-installer-package+zip";
				break;
			case 'apk'      :
				$ctype = "application/vnd.android.package-archive";
				break;
			case 'asc'      :
				$ctype = "application/pgp-signature";
				break;
			case 'atom'     :
				$ctype = "application/atom+xml";
				break;
			case 'atomcat'  :
				$ctype = "application/atomcat+xml";
				break;
			case 'atomsvc'  :
				$ctype = "application/atomsvc+xml";
				break;
			case 'au'       :
				$ctype = "audio/basic";
				break;
			case 'aw'       :
				$ctype = "application/applixware";
				break;
			case 'avi'      :
				$ctype = "video/x-msvideo";
				break;
			case 'bcpio'    :
				$ctype = "application/x-bcpio";
				break;
			case 'bin'      :
				$ctype = "application/octet-stream";
				break;
			case 'bmp'      :
				$ctype = "image/bmp";
				break;
			case 'boz'      :
				$ctype = "application/x-bzip2";
				break;
			case 'bpk'      :
				$ctype = "application/octet-stream";
				break;
			case 'bz'       :
				$ctype = "application/x-bzip";
				break;
			case 'bz2'      :
				$ctype = "application/x-bzip2";
				break;
			case 'ccxml'    :
				$ctype = "application/ccxml+xml";
				break;
			case 'cdmia'    :
				$ctype = "application/cdmi-capability";
				break;
			case 'cdmic'    :
				$ctype = "application/cdmi-container";
				break;
			case 'cdmid'    :
				$ctype = "application/cdmi-domain";
				break;
			case 'cdmio'    :
				$ctype = "application/cdmi-object";
				break;
			case 'cdmiq'    :
				$ctype = "application/cdmi-queue";
				break;
			case 'cdf'      :
				$ctype = "application/x-netcdf";
				break;
			case 'cer'      :
				$ctype = "application/pkix-cert";
				break;
			case 'cgm'      :
				$ctype = "image/cgm";
				break;
			case 'class'    :
				$ctype = "application/octet-stream";
				break;
			case 'cpio'     :
				$ctype = "application/x-cpio";
				break;
			case 'cpt'      :
				$ctype = "application/mac-compactpro";
				break;
			case 'crl'      :
				$ctype = "application/pkix-crl";
				break;
			case 'csh'      :
				$ctype = "application/x-csh";
				break;
			case 'css'      :
				$ctype = "text/css";
				break;
			case 'cu'       :
				$ctype = "application/cu-seeme";
				break;
			case 'davmount' :
				$ctype = "application/davmount+xml";
				break;
			case 'dbk'      :
				$ctype = "application/docbook+xml";
				break;
			case 'dcr'      :
				$ctype = "application/x-director";
				break;
			case 'deploy'   :
				$ctype = "application/octet-stream";
				break;
			case 'dif'      :
				$ctype = "video/x-dv";
				break;
			case 'dir'      :
				$ctype = "application/x-director";
				break;
			case 'dist'     :
				$ctype = "application/octet-stream";
				break;
			case 'distz'    :
				$ctype = "application/octet-stream";
				break;
			case 'djv'      :
				$ctype = "image/vnd.djvu";
				break;
			case 'djvu'     :
				$ctype = "image/vnd.djvu";
				break;
			case 'dll'      :
				$ctype = "application/octet-stream";
				break;
			case 'dmg'      :
				$ctype = "application/octet-stream";
				break;
			case 'dms'      :
				$ctype = "application/octet-stream";
				break;
			case 'doc'      :
				$ctype = "application/msword";
				break;
			case 'docx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
				break;
			case 'dotx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.template";
				break;
			case 'dssc'     :
				$ctype = "application/dssc+der";
				break;
			case 'dtd'      :
				$ctype = "application/xml-dtd";
				break;
			case 'dump'     :
				$ctype = "application/octet-stream";
				break;
			case 'dv'       :
				$ctype = "video/x-dv";
				break;
			case 'dvi'      :
				$ctype = "application/x-dvi";
				break;
			case 'dxr'      :
				$ctype = "application/x-director";
				break;
			case 'ecma'     :
				$ctype = "application/ecmascript";
				break;
			case 'elc'      :
				$ctype = "application/octet-stream";
				break;
			case 'emma'     :
				$ctype = "application/emma+xml";
				break;
			case 'eps'      :
				$ctype = "application/postscript";
				break;
			case 'epub'     :
				$ctype = "application/epub+zip";
				break;
			case 'etx'      :
				$ctype = "text/x-setext";
				break;
			case 'exe'      :
				$ctype = "application/octet-stream";
				break;
			case 'exi'      :
				$ctype = "application/exi";
				break;
			case 'ez'       :
				$ctype = "application/andrew-inset";
				break;
			case 'f4v'      :
				$ctype = "video/x-f4v";
				break;
			case 'fli'      :
				$ctype = "video/x-fli";
				break;
			case 'flv'      :
				$ctype = "video/x-flv";
				break;
			case 'gif'      :
				$ctype = "image/gif";
				break;
			case 'gml'      :
				$ctype = "application/srgs";
				break;
			case 'gpx'      :
				$ctype = "application/gml+xml";
				break;
			case 'gram'     :
				$ctype = "application/gpx+xml";
				break;
			case 'grxml'    :
				$ctype = "application/srgs+xml";
				break;
			case 'gtar'     :
				$ctype = "application/x-gtar";
				break;
			case 'gxf'      :
				$ctype = "application/gxf";
				break;
			case 'hdf'      :
				$ctype = "application/x-hdf";
				break;
			case 'hqx'      :
				$ctype = "application/mac-binhex40";
				break;
			case 'htm'      :
				$ctype = "text/html";
				break;
			case 'html'     :
				$ctype = "text/html";
				break;
			case 'ice'      :
				$ctype = "x-conference/x-cooltalk";
				break;
			case 'ico'      :
				$ctype = "image/x-icon";
				break;
			case 'ics'      :
				$ctype = "text/calendar";
				break;
			case 'ief'      :
				$ctype = "image/ief";
				break;
			case 'ifb'      :
				$ctype = "text/calendar";
				break;
			case 'iges'     :
				$ctype = "model/iges";
				break;
			case 'igs'      :
				$ctype = "model/iges";
				break;
			case 'ink'      :
				$ctype = "application/inkml+xml";
				break;
			case 'inkml'    :
				$ctype = "application/inkml+xml";
				break;
			case 'ipfix'    :
				$ctype = "application/ipfix";
				break;
			case 'jar'      :
				$ctype = "application/java-archive";
				break;
			case 'jnlp'     :
				$ctype = "application/x-java-jnlp-file";
				break;
			case 'jp2'      :
				$ctype = "image/jp2";
				break;
			case 'jpe'      :
				$ctype = "image/jpeg";
				break;
			case 'jpeg'     :
				$ctype = "image/jpeg";
				break;
			case 'jpg'      :
				$ctype = "image/jpeg";
				break;
			case 'js'       :
				$ctype = "application/javascript";
				break;
			case 'json'     :
				$ctype = "application/json";
				break;
			case 'jsonml'   :
				$ctype = "application/jsonml+json";
				break;
			case 'kar'      :
				$ctype = "audio/midi";
				break;
			case 'latex'    :
				$ctype = "application/x-latex";
				break;
			case 'lha'      :
				$ctype = "application/octet-stream";
				break;
			case 'lrf'      :
				$ctype = "application/octet-stream";
				break;
			case 'lzh'      :
				$ctype = "application/octet-stream";
				break;
			case 'lostxml'  :
				$ctype = "application/lost+xml";
				break;
			case 'm3u'      :
				$ctype = "audio/x-mpegurl";
				break;
			case 'm4a'      :
				$ctype = "audio/mp4a-latm";
				break;
			case 'm4b'      :
				$ctype = "audio/mp4a-latm";
				break;
			case 'm4p'      :
				$ctype = "audio/mp4a-latm";
				break;
			case 'm4u'      :
				$ctype = "video/vnd.mpegurl";
				break;
			case 'm4v'      :
				$ctype = "video/x-m4v";
				break;
			case 'm21'      :
				$ctype = "application/mp21";
				break;
			case 'ma'       :
				$ctype = "application/mathematica";
				break;
			case 'mac'      :
				$ctype = "image/x-macpaint";
				break;
			case 'mads'     :
				$ctype = "application/mads+xml";
				break;
			case 'man'      :
				$ctype = "application/x-troff-man";
				break;
			case 'mar'      :
				$ctype = "application/octet-stream";
				break;
			case 'mathml'   :
				$ctype = "application/mathml+xml";
				break;
			case 'mbox'     :
				$ctype = "application/mbox";
				break;
			case 'me'       :
				$ctype = "application/x-troff-me";
				break;
			case 'mesh'     :
				$ctype = "model/mesh";
				break;
			case 'metalink' :
				$ctype = "application/metalink+xml";
				break;
			case 'meta4'    :
				$ctype = "application/metalink4+xml";
				break;
			case 'mets'     :
				$ctype = "application/mets+xml";
				break;
			case 'mid'      :
				$ctype = "audio/midi";
				break;
			case 'midi'     :
				$ctype = "audio/midi";
				break;
			case 'mif'      :
				$ctype = "application/vnd.mif";
				break;
			case 'mods'     :
				$ctype = "application/mods+xml";
				break;
			case 'mov'      :
				$ctype = "video/quicktime";
				break;
			case 'movie'    :
				$ctype = "video/x-sgi-movie";
				break;
			case 'm1v'      :
				$ctype = "video/mpeg";
				break;
			case 'm2v'      :
				$ctype = "video/mpeg";
				break;
			case 'mp2'      :
				$ctype = "audio/mpeg";
				break;
			case 'mp2a'     :
				$ctype = "audio/mpeg";
				break;
			case 'mp21'     :
				$ctype = "application/mp21";
				break;
			case 'mp3'      :
				$ctype = "audio/mpeg";
				break;
			case 'mp3a'     :
				$ctype = "audio/mpeg";
				break;
			case 'mp4'      :
				$ctype = "video/mp4";
				break;
			case 'mp4s'     :
				$ctype = "application/mp4";
				break;
			case 'mpe'      :
				$ctype = "video/mpeg";
				break;
			case 'mpeg'     :
				$ctype = "video/mpeg";
				break;
			case 'mpg'      :
				$ctype = "video/mpeg";
				break;
			case 'mpg4'     :
				$ctype = "video/mpeg";
				break;
			case 'mpga'     :
				$ctype = "audio/mpeg";
				break;
			case 'mrc'      :
				$ctype = "application/marc";
				break;
			case 'mrcx'     :
				$ctype = "application/marcxml+xml";
				break;
			case 'ms'       :
				$ctype = "application/x-troff-ms";
				break;
			case 'mscml'    :
				$ctype = "application/mediaservercontrol+xml";
				break;
			case 'msh'      :
				$ctype = "model/mesh";
				break;
			case 'mxf'      :
				$ctype = "application/mxf";
				break;
			case 'mxu'      :
				$ctype = "video/vnd.mpegurl";
				break;
			case 'nc'       :
				$ctype = "application/x-netcdf";
				break;
			case 'oda'      :
				$ctype = "application/oda";
				break;
			case 'oga'      :
				$ctype = "application/ogg";
				break;
			case 'ogg'      :
				$ctype = "application/ogg";
				break;
			case 'ogx'      :
				$ctype = "application/ogg";
				break;
			case 'omdoc'    :
				$ctype = "application/omdoc+xml";
				break;
			case 'onetoc'   :
				$ctype = "application/onenote";
				break;
			case 'onetoc2'  :
				$ctype = "application/onenote";
				break;
			case 'onetmp'   :
				$ctype = "application/onenote";
				break;
			case 'onepkg'   :
				$ctype = "application/onenote";
				break;
			case 'opf'      :
				$ctype = "application/oebps-package+xml";
				break;
			case 'oxps'     :
				$ctype = "application/oxps";
				break;
			case 'p7c'      :
				$ctype = "application/pkcs7-mime";
				break;
			case 'p7m'      :
				$ctype = "application/pkcs7-mime";
				break;
			case 'p7s'      :
				$ctype = "application/pkcs7-signature";
				break;
			case 'p8'       :
				$ctype = "application/pkcs8";
				break;
			case 'p10'      :
				$ctype = "application/pkcs10";
				break;
			case 'pbm'      :
				$ctype = "image/x-portable-bitmap";
				break;
			case 'pct'      :
				$ctype = "image/pict";
				break;
			case 'pdb'      :
				$ctype = "chemical/x-pdb";
				break;
			case 'pdf'      :
				$ctype = "application/pdf";
				break;
			case 'pki'      :
				$ctype = "application/pkixcmp";
				break;
			case 'pkipath'  :
				$ctype = "application/pkix-pkipath";
				break;
			case 'pfr'      :
				$ctype = "application/font-tdpfr";
				break;
			case 'pgm'      :
				$ctype = "image/x-portable-graymap";
				break;
			case 'pgn'      :
				$ctype = "application/x-chess-pgn";
				break;
			case 'pgp'      :
				$ctype = "application/pgp-encrypted";
				break;
			case 'pic'      :
				$ctype = "image/pict";
				break;
			case 'pict'     :
				$ctype = "image/pict";
				break;
			case 'pkg'      :
				$ctype = "application/octet-stream";
				break;
			case 'png'      :
				$ctype = "image/png";
				break;
			case 'pnm'      :
				$ctype = "image/x-portable-anymap";
				break;
			case 'pnt'      :
				$ctype = "image/x-macpaint";
				break;
			case 'pntg'     :
				$ctype = "image/x-macpaint";
				break;
			case 'pot'      :
				$ctype = "application/vnd.ms-powerpoint";
				break;
			case 'potx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.presentationml.template";
				break;
			case 'ppm'      :
				$ctype = "image/x-portable-pixmap";
				break;
			case 'pps'      :
				$ctype = "application/vnd.ms-powerpoint";
				break;
			case 'ppsx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.presentationml.slideshow";
				break;
			case 'ppt'      :
				$ctype = "application/vnd.ms-powerpoint";
				break;
			case 'pptx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.presentationml.presentation";
				break;
			case 'prf'      :
				$ctype = "application/pics-rules";
				break;
			case 'ps'       :
				$ctype = "application/postscript";
				break;
			case 'psd'      :
				$ctype = "image/photoshop";
				break;
			case 'qt'       :
				$ctype = "video/quicktime";
				break;
			case 'qti'      :
				$ctype = "image/x-quicktime";
				break;
			case 'qtif'     :
				$ctype = "image/x-quicktime";
				break;
			case 'ra'       :
				$ctype = "audio/x-pn-realaudio";
				break;
			case 'ram'      :
				$ctype = "audio/x-pn-realaudio";
				break;
			case 'ras'      :
				$ctype = "image/x-cmu-raster";
				break;
			case 'rdf'      :
				$ctype = "application/rdf+xml";
				break;
			case 'rgb'      :
				$ctype = "image/x-rgb";
				break;
			case 'rm'       :
				$ctype = "application/vnd.rn-realmedia";
				break;
			case 'rmi'      :
				$ctype = "audio/midi";
				break;
			case 'roff'     :
				$ctype = "application/x-troff";
				break;
			case 'rss'      :
				$ctype = "application/rss+xml";
				break;
			case 'rtf'      :
				$ctype = "text/rtf";
				break;
			case 'rtx'      :
				$ctype = "text/richtext";
				break;
			case 'sgm'      :
				$ctype = "text/sgml";
				break;
			case 'sgml'     :
				$ctype = "text/sgml";
				break;
			case 'sh'       :
				$ctype = "application/x-sh";
				break;
			case 'shar'     :
				$ctype = "application/x-shar";
				break;
			case 'sig'      :
				$ctype = "application/pgp-signature";
				break;
			case 'silo'     :
				$ctype = "model/mesh";
				break;
			case 'sit'      :
				$ctype = "application/x-stuffit";
				break;
			case 'skd'      :
				$ctype = "application/x-koan";
				break;
			case 'skm'      :
				$ctype = "application/x-koan";
				break;
			case 'skp'      :
				$ctype = "application/x-koan";
				break;
			case 'skt'      :
				$ctype = "application/x-koan";
				break;
			case 'sldx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.presentationml.slide";
				break;
			case 'smi'      :
				$ctype = "application/smil";
				break;
			case 'smil'     :
				$ctype = "application/smil";
				break;
			case 'snd'      :
				$ctype = "audio/basic";
				break;
			case 'so'       :
				$ctype = "application/octet-stream";
				break;
			case 'spl'      :
				$ctype = "application/x-futuresplash";
				break;
			case 'spx'      :
				$ctype = "audio/ogg";
				break;
			case 'src'      :
				$ctype = "application/x-wais-source";
				break;
			case 'stk'      :
				$ctype = "application/hyperstudio";
				break;
			case 'sv4cpio'  :
				$ctype = "application/x-sv4cpio";
				break;
			case 'sv4crc'   :
				$ctype = "application/x-sv4crc";
				break;
			case 'svg'      :
				$ctype = "image/svg+xml";
				break;
			case 'swf'      :
				$ctype = "application/x-shockwave-flash";
				break;
			case 't'        :
				$ctype = "application/x-troff";
				break;
			case 'tar'      :
				$ctype = "application/x-tar";
				break;
			case 'tcl'      :
				$ctype = "application/x-tcl";
				break;
			case 'tex'      :
				$ctype = "application/x-tex";
				break;
			case 'texi'     :
				$ctype = "application/x-texinfo";
				break;
			case 'texinfo'  :
				$ctype = "application/x-texinfo";
				break;
			case 'tif'      :
				$ctype = "image/tiff";
				break;
			case 'tiff'     :
				$ctype = "image/tiff";
				break;
			case 'torrent'  :
				$ctype = "application/x-bittorrent";
				break;
			case 'tr'       :
				$ctype = "application/x-troff";
				break;
			case 'tsv'      :
				$ctype = "text/tab-separated-values";
				break;
			case 'txt'      :
				$ctype = "text/plain";
				break;
			case 'ustar'    :
				$ctype = "application/x-ustar";
				break;
			case 'vcd'      :
				$ctype = "application/x-cdlink";
				break;
			case 'vrml'     :
				$ctype = "model/vrml";
				break;
			case 'vsd'      :
				$ctype = "application/vnd.visio";
				break;
			case 'vss'      :
				$ctype = "application/vnd.visio";
				break;
			case 'vst'      :
				$ctype = "application/vnd.visio";
				break;
			case 'vsw'      :
				$ctype = "application/vnd.visio";
				break;
			case 'vxml'     :
				$ctype = "application/voicexml+xml";
				break;
			case 'wav'      :
				$ctype = "audio/x-wav";
				break;
			case 'wbmp'     :
				$ctype = "image/vnd.wap.wbmp";
				break;
			case 'wbmxl'    :
				$ctype = "application/vnd.wap.wbxml";
				break;
			case 'webp'     :
				$ctype = "image/webp";
				break;
			case 'wm'       :
				$ctype = "video/x-ms-wm";
				break;
			case 'wml'      :
				$ctype = "text/vnd.wap.wml";
				break;
			case 'wmlc'     :
				$ctype = "application/vnd.wap.wmlc";
				break;
			case 'wmls'     :
				$ctype = "text/vnd.wap.wmlscript";
				break;
			case 'wmlsc'    :
				$ctype = "application/vnd.wap.wmlscriptc";
				break;
			case 'wmv'      :
				$ctype = "video/x-ms-wmv";
				break;
			case 'wmx'      :
				$ctype = "video/x-ms-wmx";
				break;
			case 'wrl'      :
				$ctype = "model/vrml";
				break;
			case 'xbm'      :
				$ctype = "image/x-xbitmap";
				break;
			case 'xdssc'    :
				$ctype = "application/dssc+xml";
				break;
			case 'xer'      :
				$ctype = "application/patch-ops-error+xml";
				break;
			case 'xht'      :
				$ctype = "application/xhtml+xml";
				break;
			case 'xhtml'    :
				$ctype = "application/xhtml+xml";
				break;
			case 'xla'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xlam'     :
				$ctype = "application/vnd.ms-excel.addin.macroEnabled.12";
				break;
			case 'xlc'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xlm'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xls'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xlsx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
				break;
			case 'xlsb'     :
				$ctype = "application/vnd.ms-excel.sheet.binary.macroEnabled.12";
				break;
			case 'xlt'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xltx'     :
				$ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.template";
				break;
			case 'xlw'      :
				$ctype = "application/vnd.ms-excel";
				break;
			case 'xml'      :
				$ctype = "application/xml";
				break;
			case 'xpm'      :
				$ctype = "image/x-xpixmap";
				break;
			case 'xsl'      :
				$ctype = "application/xml";
				break;
			case 'xslt'     :
				$ctype = "application/xslt+xml";
				break;
			case 'xul'      :
				$ctype = "application/vnd.mozilla.xul+xml";
				break;
			case 'xwd'      :
				$ctype = "image/x-xwindowdump";
				break;
			case 'xyz'      :
				$ctype = "chemical/x-xyz";
				break;
			case 'zip'      :
				$ctype = "application/zip";
				break;
			default         :
				$ctype = "application/force-download";
		endswitch;

		if ( wp_is_mobile() ) {
			$ctype = 'application/octet-stream';
		}

		return apply_filters( 'cp_file_ctype', $ctype );
	}

	/**
	 * Reads file in chunks so big downloads are possible without changing PHP.INI
	 * See http://codeigniter.com/wiki/Download_helper_for_large_files/
	 *
	 * @param string  $file     The file
	 * @param boolean $retbytes Return the bytes of file
	 *
	 * @return   bool|string        If string, $status || $cnt
	 */
	function readfile_chunked( $file, $retbytes = true ) {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		ob_start();

		// If output buffers exist, make sure they are closed.
		if ( ob_get_length() ) {
			ob_clean();
		}

		$chunksize = 1024 * 1024;
		$cnt       = 0;
		$handle    = @fopen( $file, 'rb' );

		if ( $size = @filesize( $file ) ) {
			header( "Content-Length: " . $size );
		}

		if ( false === $handle ) {
			return false;
		}

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			list( $size_unit, $range ) = explode( '=', $_SERVER['HTTP_RANGE'], 2 );
			if ( 'bytes' === $size_unit ) {
				if ( strpos( ',', $range ) ) {
					list( $range ) = explode( ',', $range, 1 );
				}
			} else {
				header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
				exit;
			}
		} else {
			$range = '';
		}

		if ( empty( $range ) ) {
			$seek_start = null;
			$seek_end   = null;
		} else {
			list( $seek_start, $seek_end ) = explode( '-', $range, 2 );
		}

		$seek_end   = ( empty( $seek_end ) ) ? ( $size - 1 ) : min( abs( intval( $seek_end ) ), ( $size - 1 ) );
		$seek_start = ( empty( $seek_start ) || $seek_end < abs( intval( $seek_start ) ) ) ? 0 : max( abs( intval( $seek_start ) ), 0 );

		// Only send partial content header if downloading a piece of the file (IE workaround)
		if ( $seek_start > 0 || $seek_end < ( $size - 1 ) ) {
			header( 'HTTP/1.1 206 Partial Content' );
			header( 'Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $size );
			header( 'Content-Length: ' . ( $seek_end - $seek_start + 1 ) );
		} else {
			header( "Content-Length: $size" );
		}

		header( 'Accept-Ranges: bytes' );

		if ( ! $this->is_func_disabled( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		fseek( $handle, $seek_start );

		while ( ! @feof( $handle ) ) {
			$buffer = @fread( $handle, $chunksize );
			echo $buffer;
			ob_flush();

			if ( $retbytes ) {
				$cnt += strlen( $buffer );
			}

			if ( connection_status() != 0 ) {
				@fclose( $handle );
				exit;
			}
		}

		ob_flush();

		$status = @fclose( $handle );

		if ( $retbytes && $status ) {
			return $cnt;
		}

		return $status;
	}

	/**
	 * Given a local URL, make sure the requests matches the request scheme
	 *
	 * @param string $requested_file The Requested File
	 *
	 * @return string                 The file (if local) with the matched scheme
	 * @since  1.0.0
	 */
	function set_requested_file_scheme( $requested_file ) {

		// If it's a URL and it's local, let's make sure the scheme matches the requested scheme
		if ( filter_var( $requested_file, FILTER_VALIDATE_URL ) && $this->is_local_file( $requested_file ) ) {

			if ( false === strpos( $requested_file, 'https://' ) && is_ssl() ) {
				$requested_file = str_replace( 'http://', 'https://', $requested_file );
			} elseif ( ! is_ssl() && 0 === strpos( $requested_file, 'https://' ) ) {
				$requested_file = str_replace( 'https://', 'http://', $requested_file );
			}

		}

		return $requested_file;

	}

	/**
	 * Determines if a file should be allowed to be downloaded by making sure it's within the wp-content directory.
	 *
	 * @param $file_details
	 * @param $schemas
	 * @param $requested_file
	 *
	 * @return boolean
	 * @since 1.0.0
	 *
	 */
	function local_file_location_is_allowed( $file_details, $schemas, $requested_file ) {
		$should_allow = true;

		// If the file is an absolute path, make sure it's in the wp-content directory, to prevent store owners from accidentally allowing privileged files from being downloaded.
		if ( ( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], $schemas ) ) && isset( $file_details['path'] ) ) {

			/** This is an absolute path */
			$requested_file         = wp_normalize_path( realpath( $requested_file ) );
			$normalized_abspath     = wp_normalize_path( ABSPATH );
			$normalized_content_dir = wp_normalize_path( WP_CONTENT_DIR );

			if ( 0 !== strpos( $requested_file, $normalized_abspath ) || false === strpos( $requested_file, $normalized_content_dir ) ) {
				// If the file is not within the WP_CONTENT_DIR, it should not be able to be downloaded.
				$should_allow = false;
			}

		}

		return apply_filters( 'cp_local_file_location_is_allowed', $should_allow, $file_details, $schemas, $requested_file );
	}

	function is_func_disabled( $function ) {
		$disabled = explode( ',',  ini_get( 'disable_functions' ) );

		return in_array( $function, $disabled );
	}

	/**
	 * Get File Extension
	 *
	 * Returns the file extension of a filename.
	 *
	 * @param unknown $str File name
	 *
	 * @return mixed File extension
	 * @since 1.0.0
	 *
	 */
	function get_file_extension( $str ) {
		$parts = explode( '.', $str );

		return end( $parts );
	}
}
