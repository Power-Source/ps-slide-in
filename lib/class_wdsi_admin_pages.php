<?php
/**
 * Admin pages handler.
 */
class Wdsi_AdminPages {
	private $_data;
	
	private $_wdsi;

	private function __construct () {
		$this->_wdsi = Wdsi_SlideIn::get_instance();
		$this->_data = new Wdsi_Options;
	}

	public static function serve () {
		$me = new Wdsi_AdminPages;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('admin_init', array($this, 'register_settings'));
		$hook = (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN) ? 'network_admin_menu' : 'admin_menu';
		add_action($hook, array($this, 'create_admin_menu_entry'));

		// Post meta boxes
		add_action('admin_init', array($this, 'add_meta_boxes'));
		add_action('save_post', array($this, 'save_meta'), 10, 3);

		add_action('admin_print_scripts', array($this, 'js_print_scripts'));
		add_action('admin_print_styles', array($this, 'css_print_styles'));

		// AJAX actions
		add_action('wp_ajax_wdsi_mailchimp_subscribe', array($this, 'json_mailchimp_subscribe'));
		add_action('wp_ajax_nopriv_wdsi_mailchimp_subscribe', array($this, 'json_mailchimp_subscribe'));

		add_action('wp_ajax_wdsi_preview_slide', array($this, 'json_preview'));
	}

	function json_mailchimp_subscribe () {
		$is_error = 1;
		if (!check_ajax_referer('wdsi_mailchimp', 'nonce', false)) {
			die(json_encode(array(
				'is_error' => $is_error,
				'message' => __('Ungultige Anfrage', 'wdsi'),
			)));
		}

		$post_id = !empty($_POST['post_id']) ? absint($_POST['post_id']) : false;
		$opts = get_post_meta($post_id, 'wdsi-type', true);

		$default_api_key = $this->_data->get_option('mailchimp-api_key');
		$api_key = wdsi_getval($opts, 'mailchimp-api_key', $default_api_key);
		if (!$api_key) die(json_encode(array(
			'is_error' => $is_error,
			'message' => __('MailChimp nicht konfiguriert', 'wdsi'),
		)));

		$default_list = $this->_data->get_option('mailchimp-default_list');
		$list = wdsi_getval($opts, 'mailchimp-default_list', $default_list);
		if (!$list) die(json_encode(array(
			'is_error' => $is_error,
			'message' => __('Unbekannte Liste', 'wdsi'),
		)));

		$email = sanitize_email(wdsi_getval($_POST, 'email'));
		if (!is_email($email)) die(json_encode(array(
			'is_error' => $is_error,
			'message' => __('Ungültige E-Mail', 'wdsi'),
		)));

		$mailchimp = new Wdsi_Mailchimp($api_key);
		$result = $mailchimp->subscribe_to($list, $email);

		if (true === $result) {
			$global_message = $this->_data->get_option('mailchimp-subscription_message');
			$subscription_message = wdsi_getval($opts, 'mailchimp-subscription_message', $global_message);
			$subscription_message = $subscription_message ? $subscription_message : __('Alles gut, danke!', 'wdsi');
			$subscription_message = wp_strip_all_tags($subscription_message);
			die(json_encode(array(
				'is_error' => 0,
				'message' => $subscription_message,
			)));
		} else if (is_array($result) && isset($result['error'])) {
			die(json_encode(array(
				'is_error' => $is_error,
				'message' => $result['error'],
			)));
		} else if (is_string($result)) {
			die(json_encode(array(
				'is_error' => $is_error,
				'message' => $result,
			)));
		} else die(json_encode(array(
				'is_error' => $is_error,
				'message' => __('Fehler', 'wdsi'),
			)));
		die;
	}

	function add_meta_boxes () {
		$types = get_post_types(array(
			'public' => true,
		));
		foreach ($types as $type) {
			add_meta_box(
				'wdsi_message_override',
				__('Slide-In Nachricht Überschreiben', 'wdsm'),
				array($this, 'render_message_override_box'),
				$type,
				'side',
				'low'
			);
		}
	}

	function render_message_override_box () {
		global $post;
		if (!$post || !($post instanceof WP_Post)) return;
		wp_nonce_field('wdsi_admin_meta', 'wdsi_admin_meta_nonce');
		$msg_id = get_post_meta($post->ID, 'wdsi_message_id', true);
		$do_not_show = get_post_meta($post->ID, 'wdsi_do_not_show', true);
		$query = new WP_Query(array(
			'post_type' => Wdsi_SlideIn::POST_TYPE,
			'post_status' => Wdsi_SlideIn::NOT_IN_POOL_STATUS,
		));
		$messages = $query->posts;

		_e('This post will not get a slide-in message from the pool - it will always use this message', 'wdsi');
		echo '<select name="wdsi-message_override">';
		echo '<option value=""></option>';
		foreach ($messages as $message) {
			$selected = ($message->ID == $msg_id) ? 'selected="selected"' : '';
			echo "<option value='{$message->ID}' {$selected}>{$message->post_title}</option>";
		}
		echo '</select>';

		echo '<br />';
		echo '' .
			'<input type="hidden" name="wdsi-do_not_show" value="" />' .
			'<input type="checkbox" name="wdsi-do_not_show" id="wdsi-do_not_show" value="1"' . checked($do_not_show, true, false) . ' />' .
			'&nbsp;' .
			'<label for="wdsi-do_not_show">' . __('Zeige auf dieser Seite kein Slide-In an', 'wdsi') . '</label>' .
		'<br />';
	}

	function save_meta ($post_id = 0, $post = null, $update = null) {
		if (!$post || !($post instanceof WP_Post)) {
			$post = get_post($post_id);
		}
		if (!$post) return false;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return false;
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return false;
		if (!isset($_POST['wdsi_admin_meta_nonce']) || !wp_verify_nonce($_POST['wdsi_admin_meta_nonce'], 'wdsi_admin_meta')) {
			return false;
		}
		if (!current_user_can('edit_post', $post_id)) return false;
		//if ('post' != $post->post_type) return false; // Deprecated
		if (isset($_POST['wdsi-message_override'])) {
			if ($_POST['wdsi-message_override']) update_post_meta($post->ID, 'wdsi_message_id', $_POST['wdsi-message_override']);
			else update_post_meta($post->ID, 'wdsi_message_id', false);
		}
		if (isset($_POST['wdsi-do_not_show'])) {
			$do_not_show = !empty($_POST['wdsi-do_not_show']);
			update_post_meta($post->ID, 'wdsi_do_not_show', $do_not_show);
		}
	}
	
	function register_settings () {
		$form = new Wdsi_AdminFormRenderer;
		
		register_setting('wdsi', 'wdsi');
		
		add_settings_section('wdsi_behavior', __('Verhaltenseinstellungen', 'wdsi'), '', 'wdsi_options_page');
		add_settings_field('wdsi_show_after', __('Zeige Nachricht', 'wdsi'), array($form, 'create_show_after_box'), 'wdsi_options_page', 'wdsi_behavior');
		add_settings_field('wdsi_show_for', __('Nachricht ausblenden nach', 'wdsi'), array($form, 'create_show_for_box'), 'wdsi_options_page', 'wdsi_behavior');
		add_settings_field('wdsi_closing', __('Nachricht schließen', 'wdsi'), array($form, 'create_closing_box'), 'wdsi_options_page', 'wdsi_behavior');


		add_settings_section('wdsi_appearance', __('Aussehenseinstellungen', 'wdsi'), '', 'wdsi_options_page');
		add_settings_field('wdsi_position', __('Nachrichtenposition', 'wdsi'), array($form, 'create_position_box'), 'wdsi_options_page', 'wdsi_appearance');
		add_settings_field('wdsi_width', __('Nachrichtenbreite', 'wdsi'), array($form, 'create_msg_width_box'), 'wdsi_options_page', 'wdsi_appearance');
		add_settings_field('wdsi_appearance', __('Nachrichtenstil', 'wdsi'), array($form, 'create_appearance_box'), 'wdsi_options_page', 'wdsi_appearance');
		add_settings_field('wdsi_color_scheme', __('Farbschema', 'wdsi'), array($form, 'create_color_scheme_box'), 'wdsi_options_page', 'wdsi_appearance');
		
		add_settings_field('wdsi_services', __('Social Media Dienste', 'wdsi'), array($form, 'create_services_box'), 'wdsi_options_page', 'wdsi_appearance');
		add_settings_field('wdsi_mailchimp', __('MailChimp-Abonnements', 'wdsi'), array($form, 'create_mailchimp_box'), 'wdsi_options_page', 'wdsi_appearance');
		
		add_settings_field('wdsi_css', __('Benutzerdefiniertes CSS', 'wdsi'), array($form, 'create_custom_css_box'), 'wdsi_options_page', 'wdsi_appearance');

		add_settings_field('wdsi_advanced', __('Erweitert', 'wdsi'), array($form, 'create_advanced_box'), 'wdsi_options_page', 'wdsi_appearance');
	}
	
	function create_admin_menu_entry () {
		$page = "edit.php?post_type=" . Wdsi_SlideIn::POST_TYPE;
		$perms = is_multisite() ? 'manage_network_options' : 'manage_options';
		if (current_user_can($perms) && !empty($_POST) && isset($_POST['option_page'])) {
			check_admin_referer('wdsi_options_save', 'wdsi_options_nonce');
			$changed = false;
			if ('wdsi_options_page' == wdsi_getval($_POST, 'option_page')) {
				$services = !empty($_POST['wdsi']['services']) ? $_POST['wdsi']['services'] : array();
				$services = is_array($services) ? $services : array();
				if (!empty($_POST['wdsi']['new_service']['name']) && !empty($_POST['wdsi']['new_service']['code'])) {
					$services[] = $_POST['wdsi']['new_service'];
					unset($_POST['wdsi']['new_service']);
				}
				foreach ($services as $key=>$service) {
					if (!empty($service['code'])) {
						$services[$key]['code'] = stripslashes($service['code']);
					}
				}
				$_POST['wdsi']['services'] = $services;
				update_option('wdsi', $_POST['wdsi']);
				$changed = true;
			}

			if ($changed) {
				$goback = add_query_arg('settings-updated', 'true',  wp_get_referer());
				wp_redirect($goback);
				die;
			}
		}
		add_submenu_page($page, __('Globale Einstellungen', 'wdsi'), __('Globale Einstellungen', 'wdsi'), $perms, 'wdsi', array($this, 'create_admin_page'));
	}
	
	function create_admin_page () {
		include(WDSI_PLUGIN_BASE_DIR . '/lib/forms/plugin_settings.php');
	}
	
	function js_print_scripts () {
		global $post;
		if (
			(isset($_GET['page']) && 'wdsi' == $_GET['page']) 
			||
			(is_object($post) && isset($post->post_type) && Wdsi_SlideIn::POST_TYPE == $post->post_type)
		) {
			wp_enqueue_script( array("jquery", "jquery-ui-core", "jquery-ui-sortable", 'jquery-ui-dialog') );
			
			// Preview scripts - enqueue first so dependencies work correctly
			wp_enqueue_script('wdsi', WDSI_PLUGIN_URL . '/js/wdsi.js', array('jquery'), WDSI_CURRENT_VERSION);
			wp_localize_script('wdsi', '_wdsi_data', array(
				'reshow' => array(
					'timeout' => 0,
					'name' => 'test',
					'path' => null,
					'all' => false,
				),
			));
			
			wp_enqueue_script('wdsi-admin', WDSI_PLUGIN_URL . '/js/wdsi-admin.js', array("jquery", "jquery-ui-core", "jquery-ui-sortable", 'jquery-ui-dialog', 'wdsi'));
			wp_localize_script('wdsi-admin', 'l10nWdsi', array(
				'clear_set' => __('<strong>&times;</strong> Lösche dieses Set', 'wdsi'),
				'preview_nonce' => wp_create_nonce('wdsi_preview'),
			));
			
			// Add inline script to make preview function available early
			$preview_nonce = wp_create_nonce('wdsi_preview');
			$inline_script = '<script type="text/javascript">' . "\n";
			$inline_script .= 'var wdsi_preview_timeout = 1;' . "\n";
			$inline_script .= 'var wdsi_preview_nonce = "' . esc_attr($preview_nonce) . '";' . "\n";
			$inline_script .= <<<'INLINE_SCRIPT'

function wdsi_preview_slide(e) {
	if (!jQuery) return false;
	if (e && e.preventDefault) {
		e.preventDefault();
		e.stopPropagation();
	}
	
	var $ = jQuery;
	var $button = $(e.target || event.target);
	var normal = $button.text();
	var working = $button.attr("data-working");
	
	var services = {};
	$('[name^="wdsi[services]["][type="checkbox"]:checked').each(function() {
		var name = $(this).attr('name');
		var key = name.match(/wdsi\[services\]\[([^\]]+)\]/)[1];
		services[key] = key;
	});
	
	$.post(ajaxurl, {
		action: "wdsi_preview_slide",
		_ajax_nonce: wdsi_preview_nonce,
		opts: {
			"theme": $('[name="wdsi[theme]"]:checked').val(),
			"show_after-condition": "timeout",
			"show_after-rule": "" + wdsi_preview_timeout,
			"show_for-time": $('[name="wdsi[show_for-time]"]').val() || '',
			"variation": $('[name="wdsi[variation]"]:checked').val(),
			"position": $('[name="wdsi[position]"]:checked').val(),
			"scheme": $('[name="wdsi[scheme]"]:checked').val(),
			"width": ($("#wdsi-full_width").is(":checked") ? 'full' : $("#wdsi-width").val()),
			"services": services
		}
	}).done(function (resp) {
		if (!(resp && "data" in resp && resp.data && "out" in resp.data && resp.data.out)) {
			return false;
		}
		// Ensure _wdsi_data is initialized for preview
		if (!window._wdsi_data) {
			window._wdsi_data = {
				'reshow': {
					'timeout': 0,
					'name': 'test',
					'path': null,
					'all': false,
				}
			};
		}
		$("body")
			.find("#wdsi-slide_in").remove().end()
			.append(resp.data.out)
		;

		$(document).trigger("wdsi-init");
		
		// For preview, immediately make the slide visible
		var $slide = $("#wdsi-slide_in");
		// Remove inline style="display:none" by removing the attribute
		$slide.removeAttr('style');
		$slide.addClass('wdsi-slide-active').css({
			'visibility': 'visible',
			'position': 'fixed',
			'display': 'block',
			'z-index': 999999
		});
		
		// Get the timeout from the form field (not from data attribute which may be wrong)
		var timeoutVal = $('[name="wdsi[show_for-time]"]').val();
		var timeout = parseInt(timeoutVal, 10);
		if (timeout > 0) {
			setTimeout(function() {
				$slide.css({
					'visibility': 'hidden',
					'display': 'none'
				});
			}, timeout * 1000);
		}
		
		// Add close button handler for preview
		try {
			var $closeBtn = $slide.find('.wdsi-slide-close a');
			$closeBtn.on('click', function(e) {
				e.preventDefault();
				$slide.css({
					'visibility': 'hidden',
					'display': 'none'
				});
				return false;
			});
		} catch(err) {
			// Error handler omitted
		}
		
	}).fail(function (xhr, status, error) {
	}).always(function () {
		setTimeout(function () {
			$button.text(normal);
		}, wdsi_preview_timeout * 1100);
	});
	
	$button.text(working);
	return false;
}
</script>
INLINE_SCRIPT;
			echo $inline_script;

		}
	}

	function css_print_styles () {
		// Menu icon hack goes into all admin pages, so add it inline instead of queueing up yet another stylehseet just for this
		$base_url = WDSI_PLUGIN_URL;
		echo <<<EoWdsiAdminCss
<style type="text/css">
li.menu-icon-slide_in div.wp-menu-image { background: url({$base_url}/img/admin-menu-icon.png) no-repeat bottom; }
li.menu-icon-slide_in:hover div.wp-menu-image, 
li.menu-icon-slide_in.wp-has-current-submenu div.wp-menu-image 
{ background-position: top; }
li.menu-icon-slide_in div.wp-menu-image img { display: none; }
</style>
EoWdsiAdminCss;
		// The rest is slide in specific, enqueue only when needed
		if (isset($_GET['page']) && 'wdsi' == $_GET['page']) {
			wp_enqueue_style('wdsi-admin', WDSI_PLUGIN_URL . '/css/wdsi-admin.css');
			// Preview scripts
			wp_enqueue_style('wdsi', WDSI_PLUGIN_URL . '/css/wdsi.css', array(), WDSI_CURRENT_VERSION);
		}
		global $post;
		if (is_object($post) && isset($post->post_type) && Wdsi_SlideIn::POST_TYPE == $post->post_type) {
			wp_enqueue_style('wdsi-admin', WDSI_PLUGIN_URL . '/css/wdsi-admin.css');
		}
	}

	public function json_preview () {
		if (!check_ajax_referer('wdsi_preview', '_ajax_nonce', false)) {
			wp_send_json_error(array('message' => __('Ungultige Anfrage', 'wdsi')));
		}
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Nicht erlaubt', 'wdsi')));
		}
		$data = stripslashes_deep($_POST);
		$opts = $data['opts'];
		$opts = is_array($opts) ? $opts : array();
		$message = (object)array(
			'ID' => true,
			'post_title' => __('Slide-In Vorschau', 'wdsi'),
			'post_content' => __('Diese Vorschau zeigt die aktuellen Einstellungen für Position, Breite, Thema, Variation und Farbschema. Bitte vergiss nicht, Deine Änderungen zu speichern, sobald Du mit dem Ergebnis zufrieden bist.', 'wdsi'),
		);
		// Only set default show_for-time if not provided
		if (empty($opts['show_for-time'])) {
			$opts['show_for-time'] = DAY_IN_SECONDS;
		}
		$out = Wdsi_SlideIn::message_markup($message, $opts, false);
		wp_send_json_success(array(
			'out' => $out,
		));
	}

}