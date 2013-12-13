<?php
function mqtrans_import_settings_from_qtrans() {
	global $wpdb;
	
	$option_names = $wpdb->get_col("SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE 'qtranslate\_%'");
	foreach ($option_names as $name)
	{		
		$opt = get_option($name);
		
		$nn = "m{$name}";
		if ( false !== get_option($nn) )
			update_option($nn, $opt);
		else
			add_option($nn, $opt);
	}
}

function mqtrans_export_setting_to_qtrans($updateOnly = false) {
	global $wpdb;
	
	$option_names = $wpdb->get_col("SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE 'mqtranslate\_%'");
	foreach ($option_names as $name)
	{
		$opt = get_option($name);
	
		$nn = substr($name, 1);
		if ( false !== get_option($nn) )
			update_option($nn, $opt);
		else if (!$updateOnly)
			add_option($nn, $opt);
	}
}

function mqtrans_currentUserCanEdit($lang) {
	$cu = wp_get_current_user();
	if ($cu->has_cap('edit_users'))
		return true;
	else
	{
		$user_langs = get_user_meta($cu->ID, 'mqtranslate_language_access', true);
		if (empty($user_langs))
			return false;
		$user_langs = explode(',', $user_langs);
		return in_array($lang, $user_langs);
	}
}

function mqtrans_currentUserCanView($lang) {
	global $q_config;
	
	$cu = wp_get_current_user();
	if ($cu->has_cap('edit_users'))
		return true;
	else
	{
		$master_lang = get_user_meta($cu->ID, 'mqtranslate_master_language', true);
		if (empty($master_lang))
			return ($lang === $q_config['default_language']);
		else
			return ($lang === $master_lang || $lang === $q_config['default_language']);
	}
}

function mqtrans_userProfile($user) {
	global $q_config;

	$cu = wp_get_current_user();
	$langs = qtrans_getSortedLanguages();
	
	echo '<h3>'.__('mqTranslate User Language Settings', 'mqtranslate') . "</h3>\n";
	echo "<table class=\"form-table\">\n<tbody>\n";
	
	// Editable languages
	$user_langs = get_user_meta($user->ID, 'mqtranslate_language_access', true);
	if (empty($user_langs))
		$user_langs = array();
	else
		$user_langs = explode(',', $user_langs);
	echo "<tr>\n";
	if ($cu->ID == $user->ID)
		echo '<th>'.__('You can edit posts in', 'mqtranslate') . "</th>\n";
	else
		echo '<th>'.__('This user can edit posts in', 'mqtranslate') . "</th>\n";
	echo "<td>";
	if ($user->has_cap('edit_users'))
	{
		if (empty($langs))
			_e('No language available', 'mqtranslate');
		else if ($cu->ID == $user->ID)
			_e('As an Administrator, you can edit posts in all languages.', 'mqtranslate');
		else
			_e('As an Administrator, this user can edit posts in all languages.', 'mqtranslate');
	}
	else if ($cu->has_cap('edit_users'))
	{
		if (empty($langs))
			_e('No language available', 'mqtranslate')."\n";
		else
		{
			$checkboxes = array();
			foreach ($langs as $l) {
				$name = "mqtrans_user_lang_{$l}";
				$checked = (in_array($l, $user_langs)) ? 'checked' : '';
				$checkboxes[] = "<label for=\"{$name}\"><input type=\"checkbox\" name=\"mqtrans_user_lang[]\" id=\"{$name}\" value=\"{$l}\" {$checked} /> {$q_config['language_name'][$l]}</label>\n";
			}
			echo implode("<br />\n", $checkboxes);
		}
	}
	else
	{
		$intersect = array_intersect($langs, $user_langs);
		if (empty($intersect))
			_e('No language selected', 'mqtranslate')."\n";
		else
		{
			$languages = array();
			foreach ($intersect as $l)
				$languages[] = $q_config['language_name'][$l];
			echo implode(', ', $languages);
		}
	}
	echo "</td>\n";
	echo "</tr>\n";
	
	// Master language
	$user_master_lang = get_user_meta($user->ID, 'mqtranslate_master_language', true);
	echo "<tr>\n";
	echo '<th>' . __('Master language', 'mqtranslate') . "</th>\n";
	echo "<td>\n";
	if ($user->has_cap('edit_users'))
		_e('Not applicable to Administrators', 'mqtranslate');
	else if ($cu->has_cap('edit_users'))
	{
		echo "<select name=\"mqtrans_master_lang\">\n";
		echo '<option value="">' . __('Default Language', 'mqtranslate') . "</option>\n";
		foreach ($langs as $l)
		{
			if ($l == $q_config['default_language'])
				continue;
			$selected = ($user_master_lang == $l) ? ' selected' : '';
			echo "<option value=\"{$l}\"{$selected}>{$q_config['language_name'][$l]}</option>\n";
		}
		echo "</select>\n";
		echo '<span class="description">' . __('Language from which texts should be translated by this user', 'mqtranslate') . "</span>\n";
	}
	else
	{
		if (empty($langs) || empty($user_master_lang) || !in_array($user_master_lang, $langs))
			_e('Default Language', 'mqtranslate');
		else
			echo $q_config['language_name'][$user_master_lang];
	}
	echo "</td>\n";
	echo "</tr>\n";
	
	echo "</tbody>\n</table>\n";
}

function mqtrans_userProfileUpdate($user_id) {
	$cu = wp_get_current_user();
	if ($cu->has_cap('edit_users')) {
		// Editable languages
		$langs = (empty($_POST['mqtrans_user_lang'])) ? array() : $_POST['mqtrans_user_lang'];
		if (!is_array($langs))
			$langs = array();
		if (empty($langs))
			delete_user_meta($user_id, 'mqtranslate_language_access');
		else
			update_user_meta($user_id, 'mqtranslate_language_access', implode(',', $langs));
		
		// Master language
		if (empty($_POST['mqtrans_master_lang']))
			delete_user_meta($user_id, 'mqtranslate_master_language');
		else
			update_user_meta($user_id, 'mqtranslate_master_language', $_POST['mqtrans_master_lang']);
	}
}

function qtrans_isEmptyContent($value) {
	$str = trim(strip_tags($value, '<img>,<embed>,<object>'));
	return empty($str);
}

function mqtrans_postUpdated($post_ID, $after, $before) {
	global $wpdb;

	$cu = wp_get_current_user();
	if ($cu->has_cap('edit_users'))
	{
		$title = $after->post_title;

		$content = qtrans_split($after->post_content);
		foreach ($content as $k => $v) {
			if (qtrans_isEmptyContent($v))
				unset($content[$k]);
		}
	}
	else
	{
		$titleBefore = qtrans_split($before->post_title);
		$titleAfter = qtrans_split($after->post_title);
		foreach ($titleAfter as $k => $v) {
			if (!mqtrans_currentUserCanEdit($k))
				unset($titleAfter[$k]);
		}
		$title = array_merge($titleBefore, $titleAfter);

		$contentBefore = qtrans_split($before->post_content);
		$contentAfter = qtrans_split($after->post_content);
		foreach ($contentAfter as $k => $v) {
			if (qtrans_isEmptyContent($v) || !mqtrans_currentUserCanEdit($k))
				unset($contentAfter[$k]);
		}
		$content = array_merge($contentBefore, $contentAfter);
	}
	
	$data = array('post_title' => qtrans_join($title), 'post_content' => qtrans_join($content));
	$data = stripslashes_deep($data);
	$where = array('ID' => $post_ID);
		
	$wpdb->update($wpdb->posts, $data, $where);
}

function mqtrans_filterHomeURL($url, $path, $orig_scheme, $blog_id) {
	return (empty($path) || $path == '/') ? qtrans_convertURL($url) : $url;
}

function mqtrans_filterPostMetaData($original_value, $object_id, $meta_key, $single) {
	if ($meta_key == '_menu_item_url')
	{
		$meta = wp_cache_get($object_id, 'post_meta');
		if (!empty($meta) && array_key_exists($meta_key, $meta) && !empty($meta[$meta_key]))
		{
			if ($single === false)
			{
				if (is_array($meta[$meta_key]))
					$meta = $meta[$meta_key];
				else
					$meta = array($meta[$meta_key]);
				$meta = array_map('qtrans_convertURL', $meta);
			}
			else
			{
				if (is_array($meta[$meta_key]))
					$meta = $meta[$meta_key][0];
				else
					$meta = $meta[$meta_key];
				$meta = qtrans_convertURL($meta);
			}
			return $meta;
		}
	}
	return null;
}

if (!defined('WP_ADMIN'))
{
	add_filter('home_url', 'mqtrans_filterHomeURL', 10, 4);
	add_filter('get_post_metadata', 'mqtrans_filterPostMetaData', 10, 4);
}

add_action('edit_user_profile', 			'mqtrans_userProfile');
add_action('show_user_profile',				'mqtrans_userProfile');
add_action('profile_update',				'mqtrans_userProfileUpdate');
add_action('post_updated',					'mqtrans_postUpdated', 10, 3);
?>