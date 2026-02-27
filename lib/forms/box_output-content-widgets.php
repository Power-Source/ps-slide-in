<?php
if (is_active_sidebar('slide-in')) {
	echo '<div class="wdsi-slide-columns">';
	dynamic_sidebar('slide-in');
	echo '</div>';
} else {
	$content = apply_filters('wdsi-sidebar-empty_sidebar_text', __('Bitte fügen Deiner Seitenleiste einige Widgets hinzu.', 'wdsi'));
	if ($content) echo $content;
}