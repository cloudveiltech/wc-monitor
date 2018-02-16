<?php
/*
  Plugin Name: WooCommerce Check Webhooks Status Report
  Plugin URI:
  Description: This plugin generate a mail report of bad status or deactivated WooCommerce Webhooks.
  Version: 1.0.0
  Author: CloudVeil Technology
  Author URI:

  Copyright: CloudVeil Technology
  License: GPLv2 or later
  License URI: URI: https://www.gnu.org/licenses/gpl-2.0.html

  Developers:
 */

defined('ABSPATH') or exit;

register_deactivation_hook(__FILE__, 'cv_status_deactivation');

function cv_status_deactivation() {
	delete_option('cv_status_emails');
}

class CV_Check_Status {

	private static $instance = null;
	private $options;

	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	function __construct() {
		$this->options = get_option('cv_status_emails', false) ? get_option('cv_status_emails') : array();
		$this->dir = plugin_dir_path(__FILE__);
		$this->url = plugin_dir_url(__FILE__);
		$this->init();
	}

	private function init() {

		if (is_admin()) {
			add_action('admin_menu', array($this, 'adm_menu'));
			add_action('init', array($this, 'enqueue'));
			
			add_action('wp_ajax_cv_delete_item', array($this, 'delete_item'));
			add_action('wp_ajax_cv_save_item', array($this, 'update_item'));
			add_action('wp_ajax_cv_add_item', array($this, 'add_item'));
		}
		
		register_activation_hook(__FILE__, array($this, 'cs_on_activation_actions'));
		
		add_action('init', array($this, 'check_for_webhooks'));
	}

	public function cs_on_activation_actions() {
		update_option('cs_webhooks_statuses', $this->get_webhooks());
	}

	public function get_webhooks() {
		$args = array(
			'post_type' => 'shop_webhook',
			'posts_per_page' => -1
		);

		$loop = new WP_Query($args);
		$webhooks_array = array();
		while ($loop->have_posts()) : $loop->the_post();
			global $post;
			switch ($post->post_status) {

				case 'publish':
					$status = 'active';
					break;

				case 'draft':
					$status = 'paused';
					break;

				case 'pending':
					$status = 'disabled';
					break;

				default:
					$status = 'paused';
			}
			$webhooks_array[$post->ID] = $status;

		endwhile;
		wp_reset_query();

		return $webhooks_array;
	}

	public function enqueue() {

		wp_enqueue_style('cv_status_css', $this->url . 'assets/css/cv_status.css');

		wp_enqueue_script('cv_status_js', $this->url . 'assets/js/cv_status.js', array('jquery'), '1.0', true);
		wp_localize_script('cv_status_js', 'cv_status', array(
			'ajax_url' => admin_url('admin-ajax.php')
		));
	}

	public function adm_menu() {
		add_menu_page('CloudVeil WC Status', 'CloudVeil WC Status', 'manage_options', 'cv_wc_status', array($this, 'setting_page'));
	}

	public function setting_page() {

		$this->check_for_webhooks();
		?>
		<div class="wrap">
			<h2>CloudVeil WooCommerce Status</h2>

			<h4>Enabled E-mail for send status letter</h4>
			<div class="email_array">
				<?php foreach ($this->options as $key => $row) { ?>
					<div class="cv_set_email cv_set_email-<?= $key ?>" data-key="<?= $key ?>">                    
						<input type="text" class="button" value="<?= $row ?>" name="cv_email_<?= $key ?>" disabled/>
						<input type="button" class="button  button-cv_set-edit" value="Edit" name="cv_email_<?= $key ?>" />
						<input type="button" class="button  button-cv_set-delete" value="Delete" name="cv_email_<?= $key ?>" />
					</div>                
				<?php } ?> 
			</div>	

			<h4>Add new E-mail for send status</h4>

			<form method="post">
				<div class="cv_add_email">                    
					<input type="text" class="button" value="" name="cv_email_add" placeholder="E-mail" />                                    
					<input type="submit" class="button  button-primary button-cv_add_new" value="Add New +" name="add_emails" />                    
				</div>                
			</form>

		</div>

		<?php
	}

	public function delete_item() {
		$item_to_delete = $_POST['key'];
		unset($this->options[$item_to_delete]);
		echo update_option('cv_status_emails', $this->options);
		wp_die();
	}

	public function update_item() {
		$item_to_update = $_POST['key'];
		$this->options[$item_to_update] = $_POST['value'];
		echo update_option('cv_status_emails', $this->options);
		wp_die();
	}

	public function add_item() {
		$this->options[] = $_POST['value'];
		update_option('cv_status_emails', $this->options);
		foreach ($this->options as $key => $row) {
			?>
			<div class="cv_set_email cv_set_email-<?= $key ?>" data-key="<?= $key ?>">                    
				<input type="text" class="button" value="<?= $row ?>" name="cv_email_<?= $key ?>" disabled/>
				<input type="button" class="button  button-cv_set-edit" value="Edit" name="cv_email_<?= $key ?>" />
				<input type="button" class="button  button-cv_set-delete" value="Delete" name="cv_email_<?= $key ?>" />
			</div>  <?php
		}
		wp_die();
	}

	public function check_for_webhooks() {

		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			
			$result_array = array_diff_assoc($this->get_webhooks(), get_option('cs_webhooks_statuses'));
			
			update_option('cs_webhooks_statuses', $this->get_webhooks());
			
			if (sizeof($result_array) > 0) {

				$to = implode(',', $this->options);
				$subject = 'Webhook has been disabled';
				$headers = array('Content-Type: text/html; charset=UTF-8');
				$message = '';

				foreach ($result_array as $key => $value) {
					if ($value != 'active') {
						$webhook = new WC_Webhook($key);

						$message = $message . ' <div>' . $webhook->get_name() . ' - Status: ' . $value . '</div>';
					}
				}

				if ($message != '') {
					wp_mail($to, $subject, $message, $headers);
				}
			}
		} else {
			$to = implode(',', $this->options);
			$subject = 'Woocommerce plugin has been disabled';
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$message = 'Woocommerce plugin has been disabled';

			wp_mail($to, $subject, $message, $headers);
		}
	}

}

CV_Check_Status::get_instance();
