<?php
	global $wp;
	$url = (is_home() || is_front_page()) ? site_url() : get_permalink();
	$wp_request = (isset($wp) && isset($wp->request)) ? $wp->request : '';
	$url = apply_filters('wdsi-url-current_url', ($url ? $url : site_url($wp_request))); // Fix for empty URLs
	$loaded_scripts = isset($GLOBALS['wdsi_loaded_scripts']) && is_array($GLOBALS['wdsi_loaded_scripts'])
		? $GLOBALS['wdsi_loaded_scripts']
		: array();
?>
<div class="wdsi-slide-control">
	<div class="wdsi-slide-share wdsi-clearfix">
		<?php if ($services) foreach ($services as $key=>$service) { ?>
			<?php $idx = is_array($service) ? strtolower(preg_replace('/[^-a-zA-Z0-9_]/', '', $service['name'])) : $key;?>
				<div class="wdsi-item" id="wdsi-service-<?php echo esc_attr($idx);?>">
				<?php if (is_array($service)) {
					echo $service['code'];
				} else {
					switch ($key) {
						case "x":
						case "twitter":
							$count = (in_array('x', $no_count) || in_array('twitter', $no_count))
								? 'none'
								: 'horizontal'
							;
								if (!in_array('x', $skip_script) && !in_array('twitter', $skip_script) && !in_array('x', $loaded_scripts, true)) {
									echo '<script type="text/javascript" src="https://platform.twitter.com/widgets.js"></script>';
									$loaded_scripts[] = 'x';
								}
							echo '<a href="https://twitter.com/intent/tweet" class="twitter-share-button" data-count="' . $count . '">Auf X teilen</a>';
							break;
						case "facebook":
							echo '<iframe src="https://www.facebook.com/plugins/like.php?href=' .
								rawurlencode($url) .
								'&amp;send=false&amp;layout=button_count&amp;width=120&amp;show_faces=false&amp;action=like&amp;colorscheme=light&amp;font&amp;height=20" ' .
								'scrolling="no" frameborder="0" style="border:none; width:120px; height:20px;" allowTransparency="true"></iframe>';
							break;
						
						/*case "stumble_upon":
							echo '<script src="https://www.stumbleupon.com/hostedbadge.php?s=1"></script>';
							break;*/
						/*case "delicious":
							echo '<a href="https://www.delicious.com/save" onclick="window.open(' .
								"'https://www.delicious.com/save?v=5&amp;noui&amp;jump=close&amp;url='+encodeURIComponent(location.href)+'&amp;title='+encodeURIComponent(document.title), 'delicious','toolbar=no,width=550,height=550'); return false;".
								'">' .
									'<img src="' . WDSI_PLUGIN_URL . '/img/delicious.24px.gif" alt="Delicious" />' .
								'</a>';
							break;*/
						case "reddit":
							if (!in_array('reddit', $loaded_scripts, true)) {
								echo '<script type="text/javascript" src="https://www.reddit.com/static/button/button1.js"></script>';
								$loaded_scripts[] = 'reddit';
							}
							break;
						case "linkedin":
							if (!in_array('linkedin', $skip_script) && !in_array('linkedin', $loaded_scripts, true)) {
								echo '<script src="https://platform.linkedin.com/in.js" type="text/javascript"></script>';
								$loaded_scripts[] = 'linkedin';
							}
							echo '<script type="IN/Share" data-counter="right"></script>';
							break;
						case "post_voting":
							if (function_exists('wdpv_get_vote_up_ms') && is_singular()) {
								global $blog_id;
								$post_id = get_the_ID();
								if ($post_id) {
									echo wdpv_get_vote_up_ms(false, $blog_id, $post_id);
									echo wdpv_get_vote_result_ms(true, $blog_id, $post_id);
								}
							}
							break;
						case "pinterest":
							if (!in_array('pinterest', $skip_script) && !in_array('pinterest', $loaded_scripts, true)) {
								echo '<script type="text/javascript" src="https://assets.pinterest.com/js/pinit.js"></script>';
								$loaded_scripts[] = 'pinterest';
							}
							$count = in_array('pinterest', $no_count)
								? 'none'
								: 'beside'
							;
							echo '<a data-pin-config="' . $count . '" href="//pinterest.com/pin/create/button/" data-pin-do="buttonBookmark" ><img src="//assets.pinterest.com/images/pidgets/pin_it_button.png" /></a>';
							break;
						case "whatsapp":
							$icon_url = WDSI_PLUGIN_URL . '/img/whatsapp.png';
							if (file_exists(WDSI_PLUGIN_BASE_DIR . '/img/whatsapp-icon.png')) {
								$icon_url = WDSI_PLUGIN_URL . '/img/whatsapp-icon.png';
							}
							echo '<a href="https://wa.me/?text=' . urlencode(get_the_title() . ' ' . $url) . '" target="_blank" rel="noopener noreferrer" class="whatsapp-share-button" title="Auf WhatsApp teilen">' .
								'<img src="' . $icon_url . '" width="20px" height="20px" alt="WhatsApp" />' .
								'</a>';
							break;
						case "email":
							$icon_url = WDSI_PLUGIN_URL . '/img/email.png';
							if (file_exists(WDSI_PLUGIN_BASE_DIR . '/img/email-icon.png')) {
								$icon_url = WDSI_PLUGIN_URL . '/img/email-icon.png';
							}
							echo '<a href="mailto:?subject=' . urlencode(get_the_title()) . '&body=' . urlencode(get_the_title() . ' - ' . $url) . '" class="email-share-button" title="Per E-Mail teilen">' .
								'<img src="' . $icon_url . '" width="20px" height="20px" alt="E-Mail" />' .
								'</a>';
							break;
					}
				}
				?>
			</div>
		<?php } ?>
	</div>
	<?php $GLOBALS['wdsi_loaded_scripts'] = $loaded_scripts; ?>
	<div class="wdsi-slide-close"><a href="#"><?php _e('Schließen', 'wdsi'); ?></a></div>
</div>