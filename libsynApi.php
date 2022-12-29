<?php

/**
 * Plugin Name:       Savior Libsyn API
 * Plugin URI:        https://craftlit.com
 * Description:       Work with Libsyn API - plugin by savior team
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Savior Team
 * Author URI:        #
 * Text Domain:       sr-la
 */

class LibsynAPI{
	public function __construct(){
		add_action('plugins_loaded', [$this, 'savior_load_plugin_textdomain']);
		add_action('admin_menu', [$this, 'savior_add_admin_setting_page']);
		add_action('wp_ajax_nopriv_savior_get_episodes_from_api', [$this, 'savior_get_episodes_from_api']);
		add_action('wp_ajax_savior_get_episodes_from_api', [$this, 'savior_get_episodes_from_api']);
	}

	public function triger_wp_corn_check_update(){
		if (!wp_next_scheduled('update_episode_list')) {
			wp_schedule_event(time(), 'weekly', [$this, 'savior_get_episodes_from_api']);
		}
	}

	public function savior_get_episodes_from_api(){

		ob_start();
		$startPage = isset($_POST['startPage']) ? (int) $_POST['startPage'] : 1;
		$endPage = isset($_POST['endPage']) ? (int) $_POST['endPage'] : 50;
		$current_page = (isset($_POST['current_page'])) ? (int) $_POST['current_page'] : $startPage;

		$report_file = plugin_dir_path(__FILE__).'reports/reports.html';

		if(!isset($_POST['current_page'])){
			$style = "
			<style> 
			a.button.primary-button {
			    background: blue;
			    padding: 14px 25px;
			    border-radius: 30px;
			    color: #fff;
			    text-transform: uppercase;
			    font-family: sans-serif;
			    text-decoration: none;
			    margin-bottom: 50px;
			    display: inline-block;
			}

			p {
			    font-size: 14px;
			    font-family: sans-serif;
			}
			body{
				background: #fefefe;
			}
			br{
			display: none!important;
			}
			</style>
			";
			file_put_contents($report_file, $style, LOCK_EX);
		}

		if (isset($_POST['current_page'])) {
			$current_page = $current_page + 1;
			$result = $this->get_libsyn_data_api_data('', $current_page);
			$result = json_decode($result);

			if (empty($result->_embedded->item)) {
				echo 'No Data Found on page number ' . $current_page;
				return false;
			}

			foreach ($result->_embedded->item as $episode) {

				$img_url = $episode->thumbnail->url;
				$filename = $episode->thumbnail->filename;
				$post_args = [
					'post_type' => 'episodes',
					'post_title' => $episode->item_title,
					'post_name ' => $episode->item_slug,
					'post_status' => 'publish',
					'post_content' => $episode->body,
				];
				$path = $this->title_to_url($episode->item_title);
				$existing_episode = get_page_by_path($path, OBJECT, 'episodes');
				if ($existing_episode == null) {
					$created_post = wp_insert_post($post_args);
					// Update Post Meta
					$filable_fields = [
						'field_63a2af3d519f7' => 'id',
						'field_5ef1c1f4b713e' => 'item_title',
						'field_63a2b08d519f8' => 'last_updated',
					];
					foreach ($filable_fields as $key => $name) {
						update_field($key, $episode->$name, $created_post);
					}
					// check and create book and add book selction for the episode
					$book_data = [
						'api_id' => $episode->id,
						'item_slug' => $episode->item_slug,
						'post_id' => $created_post,
						'img_url' => $img_url,
						'filename' => $filename,
						'post_args' => $post_args,
					];
					$book = $this->savior_check_and_create_book_post_type($book_data);
					if($book){
						file_put_contents($report_file, "<p> $book </p>", FILE_APPEND);
					}
					if (!empty($img_url)) {
						$this->savior_create_post_thumbnail($created_post, $img_url, $filename);
					}
					file_put_contents($report_file, "<p>Created Episode: $episode->item_title </p><br>", FILE_APPEND);
				} else {
					// if is already created then check the updated date and update the post content
					$post_id = $existing_episode->ID;
					$existing_episode_timestamps = get_field('last_updated', $post_id);

					if ((int)$episode->last_updated >=  (int)$existing_episode_timestamps) {
						//	update post data and post meta data
						$updated_data = array(
							'ID'           => $post_id,
							'post_title'   => $episode->item_title,
							'post_content' => $episode->body,
						);
						wp_update_post($updated_data);
						$filable_fields = [
							'field_63a2af3d519f7' => 'id',
							'field_5ef1c1f4b713e' => 'item_title',
							'field_63a2b08d519f8' => 'last_updated',
						];
						foreach ($filable_fields as $key => $name) {
							update_field($key, $episode->$name, $post_id);
						}
						file_put_contents($report_file, "<p>Updated Episode: $episode->item_title </p><br>", FILE_APPEND);
					}
					// Check if book is associated with the epeisode and it's created or create new one
					$path = $this->title_to_url($episode->item_title);
					if (get_page_by_path($path, OBJECT, 'post') == null) {
						$book_data = [
							'api_id' => $episode->id,
							'item_slug' =>  $episode->item_slug,
							'post_id' => $post_id,
							'img_url' => $img_url,
							'filename' => $filename,
							'post_args' => $post_args,
						];
						$book = $this->savior_check_and_create_book_post_type($book_data);
						if($book){
							file_put_contents($report_file, "<p> $book </p><br>", FILE_APPEND);
						}
					}
				}
			}
		}

		if ($current_page <= $endPage) {
			$wp_remote_response = wp_remote_post(admin_url("admin-ajax.php?action=savior_get_episodes_from_api"), [
				'blocking'  => false,
				'method'    => 'POST',
				'sslverify' => false,
				'body' => [
					'current_page' => $current_page,
					'startPage'    => $startPage,
					'endPage'      => $endPage,
				]
			]);
			if (is_wp_error($wp_remote_response)) {
				$error_message = $wp_remote_response->get_error_message();
				wp_send_json_error("Something went wrong: $error_message");
				return false;
			}
		}else{
			$content = "
				<a target='_parent' class='button primary-button' href='" .esc_url( admin_url('admin.php?page=savior-setting') )."'>Import Finished Back to Libsyn API </a>
			";
			$content .= "<div id='stop_interval' class='stop_interval'></div>";
			file_put_contents($report_file, "<br><br><p> $content </p>", FILE_APPEND);
		}
		$data = ob_get_clean();
		wp_send_json_success($data);

	}

	/**
	 * @param Array Define API post id, post content, 
	 */
	public function savior_check_and_create_book_post_type($data = []){
		$self_url = "http://api.libsyn.com/post/" . $data['api_id'] . "?show_id=18385";
		$result = json_decode(wp_remote_retrieve_body(wp_remote_get(trim($self_url))));
		$book = $result->_embedded->post;
		$keywords = $book->item_keywords;
		if (!empty($keywords)) {
			foreach ($keywords as $keyword) {
				if (str_contains($keyword, 'Book:')) {
					$tagName = substr($keyword, strpos($keyword, ":") + 1);
					// check if any book already exist like that name
					$args = array(
						'numberposts' => -1,
						'post_type'   => 'post'
					);
					$books = get_posts( $args );
					foreach ( $books as $book ) {
						if(str_contains($book->post_title, $tagName)){
							// Update Episode of the book
							update_field('field_5ef0b6e7cdc3d', [$data['post_id']], $book->ID );
							return "Book $book->post_title Already Exist, Episode updated!";
						}
					}

					$data['post_args']['post_type'] = 'post';
					//check if the book is already exist
					$path = $this->title_to_url($data['post_args']['post_title']);
					$existing_book = get_page_by_path($path, OBJECT, 'post');
					if ($existing_book == null) {
						$book_id = wp_insert_post($data['post_args']);
						// Insert Book to the episode when the book is created
						update_field('field_5ef9b3472c64d', $book_id, $data['post_id']);
						// Update Episode of the book
						update_field('field_5ef0b6e7cdc3d', [$data['post_id']], $book_id );
						
						if (!empty($data['img_url'])) {
							$this->savior_create_post_thumbnail($book_id, $data['img_url'], $data['filename']);
						}

						return "Book ".$data['post_args']['post_title']." Created Successfully!";
					}
					// Update Episode of the book
					update_field('field_5ef0b6e7cdc3d', [$data['post_id']], $existing_book->ID );
					// check if existing book is already added in episode relationship or update it.
					if (!get_field('select_show', $existing_book->ID)) {
						update_field('field_5ef9b3472c64d', $existing_book->ID, $data['post_id']);
					}
					return "Book ".$data['post_args']['post_title']." Already Exist!";
				}
			}
		}
	}

	/**
	 * load plugin text domain for translation the plugin
	 * @return void
	 */
	public function savior_load_plugin_textdomain(){
		load_plugin_textdomain('savior-support', false, plugin_dir_path(__FILE__) . 'languages');
	}

	public function savior_add_admin_setting_page(){
		add_menu_page(
			__('Libsyn API', 'savior-support'),
			__('Libsyn API', 'savior-support'),
			'manage_options',
			'savior-setting',
			[$this, 'savior_add_setting_page_data'],
			'dashicons-welcome-view-site',
			'15'
		);
		add_submenu_page(
			__('Episode Import'),
			__('Episode Import'),
			'',
			'manage_options',
			'savior-import-success',
			[$this, 'savior_add_success_page'],
		);
	}
	public function savior_add_success_page(){
		require_once plugin_dir_path(__FILE__) . 'setting/success.php';
	}


	public function savior_add_setting_page_data(){
		require_once plugin_dir_path(__FILE__) . 'setting/view.php';
	}

	/**
	 * @param $post_id number Created Post ID
	 * @param $image_url string Remote Image Url
	 * @param $filename string image file name
	 *
	 * @return bool|int Thumbnail ID
	 */
	public function savior_create_post_thumbnail($post_id, $image_url, $filename){

		//check if the image already exist
		global $wpdb;
		$image_id =  intval($wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%/$filename'"));
		if (!null == $image_id) {
			$attach_image = set_post_thumbnail($post_id, $image_id);
			return $attach_image;
		} else {
			$upload_dir  = wp_upload_dir();
			$image_data  = file_get_contents($image_url);

			if (wp_mkdir_p($upload_dir['path'])) {
				$file = $upload_dir['path'] . '/' . $filename;
			} else {
				$file = $upload_dir['basedir'] . '/' . $filename;
			}
			file_put_contents($file, $image_data);
			$wp_filetype = wp_check_filetype($filename, null);
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => sanitize_file_name($filename),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			$attach_id = wp_insert_attachment($attachment, $file, $post_id);
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attach_data = wp_generate_attachment_metadata($attach_id, $file);
			wp_update_attachment_metadata($attach_id, $attach_data);
			$attach_image = set_post_thumbnail($post_id, $attach_id);
			return $attach_image;
		}
	}

	/**
	 *
	 * @param string Authonitaction Code
	 * @return mixed Data
	 */
	public function get_libsyn_data_api_accessToken($code){
		$data = array(
			'redirect_uri' => admin_url('admin.php?page=savior-setting'),
			'client_id' => 'TiA2fsgCukhX',
			'client_secret' => 'prf13aECm6VOxlwkR39Q',
			"code" => $code,
			"grant_type" => "authorization_code",
		);
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json'
		);
		$url = "https://api.libsyn.com/oauth";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($curl);
		curl_close($curl);
		return json_encode($response);
	}

	/**
	 * @param $accessToken string Access Token
	 * @param $current_page Number Current Page Number
	 *
	 * @return bool|string
	 */
	public function get_libsyn_data_api_data($accessToken = '', $current_page = 1){
		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $accessToken,
			'Accept: application/json'
		);
		$url = "https://api.libsyn.com/post?show_id=18385&page=$current_page";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return  $output;
	}

	/**
	 * @param string Single Post link
	 * @return mixed data
	 */
	public function get_libsyn_single_post_data($post_link){
		return $post_link;
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json'
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $post_link);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return  $output;
	}

	// Title To path convert
	private function title_to_url($string) {
		$string = strtolower(str_replace([' ', '.'], '-', $string));
		return str_replace('--', '-', preg_replace('/[^A-Za-z0-9\-]/', '', $string));

	}
}

new LibsynAPI();