<?php
function qtrans_currentUserCanEdit($lang) {
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

function qtrans_userProfile($user) {
	global $q_config;

	$cu = wp_get_current_user();

	echo '<h3>'.__('mqTranslate user language access', 'mqtranslate') . "</h3>\n";
	
	$user_langs = get_user_meta($user->ID, 'mqtranslate_language_access', true);
	if (empty($user_langs))
		$user_langs = array();
	else
		$user_langs = explode(',', $user_langs);
	$langs = qtrans_getSortedLanguages();
	
	echo "<table class=\"form-table\">\n<tbody>\n<tr>\n";
	if ($cu->ID == $user->ID)
		echo '<th>'.__('You can edit posts in', 'mqtranslate') . "</th>\n";
	else
		echo '<th>'.__('This user can edit posts in', 'mqtranslate') . "</th>\n";

	if ($user->has_cap('edit_users'))
	{
		echo "<td>";
		if (empty($langs))
			_e('No language available', 'mqtranslate');
		else if ($cu->ID == $user->ID)
			_e('As an Administrator, you can edit posts in all languages.', 'mqtranslate');
		else
			_e('As an Administrator, this user can edit posts in all languages.', 'mqtranslate');
		echo "</td>\n";
	}
	else if ($cu->has_cap('edit_users'))
	{
		echo "<td>\n";
		if (empty($langs))
			_e('No language available', 'mqtranslate')."\n";
		else
		{
			$checkboxes = array();
			foreach ($langs as $l) {
				$name = "qtrans_user_lang_{$l}";
				$checked = (in_array($l, $user_langs)) ? 'checked' : '';
				$checkboxes[] = "<label for=\"{$name}\"><input type=\"checkbox\" name=\"qtrans_user_lang[]\" id=\"{$name}\" value=\"{$l}\" {$checked} /> {$q_config['language_name'][$l]}</label>\n";
			}
			echo implode("<br />\n", $checkboxes);
		}
		echo "</td>\n";
	}
	else
	{
		echo "<td>\n";
		$intersect = array_intersect($langs, $user_langs);
		if (empty($intersect))
			_e('No language selected', 'mqtranslate')."\n";
		else
		{
			foreach ($intersect as $l)
				echo "{$q_config['language_name'][$l]}\n";
		}
		echo "</td>\n";
	}
	echo "</tr>\n</tbody>\n</table>\n";
}

function qtrans_userProfileUpdate($user_id) {
	$cu = wp_get_current_user();
	if ($cu->has_cap('edit_users')) {
		$langs = (empty($_POST['qtrans_user_lang'])) ? array() : $_POST['qtrans_user_lang'];
		if (!is_array($langs))
			$langs = array();
			
		if (empty($langs))
			delete_user_meta($user_id, 'mqtranslate_language_access');
		else
			update_user_meta($user_id, 'mqtranslate_language_access', implode(',', $langs));
	}
}

function qtrans_isEmptyContent($value) {
	$str = trim(strip_tags($value, '<img>,<embed>,<object>'));
	return empty($str);
}

function qtrans_postUpdated($post_ID, $after, $before) {
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
			if (!qtrans_currentUserCanEdit($k))
				unset($titleAfter[$k]);
		}
		$title = array_merge($titleBefore, $titleAfter);

		$contentBefore = qtrans_split($before->post_content);
		$contentAfter = qtrans_split($after->post_content);
		foreach ($contentAfter as $k => $v) {
			if (qtrans_isEmptyContent($v) || !qtrans_currentUserCanEdit($k))
				unset($contentAfter[$k]);
		}
		$content = array_merge($contentBefore, $contentAfter);
	}
		
	$data = array('post_title' => qtrans_join($title), 'post_content' => qtrans_join($content));
	$data = stripslashes_deep($data);
	$where = array('ID' => $post_ID);
		
	$wpdb->update($wpdb->posts, $data, $where);
}

add_action('edit_user_profile', 			'qtrans_userProfile');
add_action('show_user_profile',				'qtrans_userProfile');
add_action('profile_update',				'qtrans_userProfileUpdate');
add_action('post_updated',					'qtrans_postUpdated', 10, 3);
?>