<?php
/**
 *  Plugin  : Thumbs Post Rating
 *  Author  : TY Yew
 *  Version : 1.2
 *  Website : http://tyyew.com/mybb
 *  Contact : mybb@tyyew.com
 *
 *  Copyright 2010 TY Yew
 *  mybb@tyyew.com
 *
 *  This file is part of Thumbs Post Rating plugin for MyBB.
 *
 *  Thumbs Post Rating plugin for MyBB is free software; you can
 *  redistribute it and/or modify it under the terms of the GNU General
 *  Public License as published by the Free Software Foundation; either
 *  version 3 of the License, or (at your option) any later version.
 *
 *  Thumbs Post Rating plugin for MyBB is distributed in the hope that it
 *  will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See
 *  the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http:www.gnu.org/licenses/>.
 */

// No direct initiation
if( !defined('IN_MYBB') )
{
	die('Direct initialization of this file is not allowed.');
}

// Add hooks
$plugins->add_hook('xmlhttp','tpr_action');
$plugins->add_hook('global_start','tpr_global');
$plugins->add_hook('postbit','tpr_box');

// Plugin information
function thumbspostrating_info()
{
	global $lang;
	$lang->load('thumbspostrating');
	
	return array(
		'name' => $lang->tpr_info_name,
		'description' => $lang->tpr_info_description,
		'website' => 'http://community.mybb.com/thread-84250.html',
		'author' => 'TY Yew',
		'authorsite' => 'http://www.tyyew.com/mybb',
		'version' => '1.2',
		'guid' => '21de27b859c0095ec17f86f561fa3737',
		'compatibility' => '14*,15*,16*'
	);
}

// Install function
function thumbspostrating_install()
{
	global $db, $lang;
	
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts ADD `thumbsup` INT NOT NULL DEFAULT 0, ADD `thumbsdown` INT NOT NULL DEFAULT 0', true);
	$db->write_query('CREATE TABLE IF NOT EXISTS '.TABLE_PREFIX.'thumbspostrating (
		uid INT NOT NULL ,
		pid INT NOT NULL ,
		rating SMALLINT NOT NULL ,
		PRIMARY KEY ( uid, pid )
		) ENGINE = MYISAM ;'
	);
	
	$lang->load('thumbspostrating');
	$tpr_setting_group_1 = array(
		'name' => 'tpr_group',
		'title' => $db->escape_string($lang->setting_group_tpr_group),
		'description' => $db->escape_string($lang->setting_group_tpr_group_desc),
		'disporder' => '38',
		'isdefault' => 'no'
	);
	$db->insert_query('settinggroups',$tpr_setting_group_1);
	$gid = $db->insert_id();
	
	$disporder = 0;
	foreach(array(
		'usergroups' => array('text', '2,3,4,6'),
		'forums'     => array('text', '0'),
		'selfrate'   => array('yesno', 1),
	) as $name => $opts) {
		$lang_title = 'setting_tpr_'.$name;
		$lang_desc = 'setting_tpr_'.$name.'_desc';
		$db->insert_query('settings', array(
			'name'        => 'tpr_'.$name,
			'title'       => $db->escape_string($lang->$lang_title),
			'description' => $db->escape_string($lang->$lang_desc),
			'optionscode' => $opts[0],
			'value'       => $db->escape_string($opts[1]),
			'disporder'   => ++$disporder,
			'gid'         => $gid,
		));
	}
	rebuild_settings();
	
	$db->insert_query('templates', array(
		'title' => 'postbit_tpr',
		'template' => $db->escape_string('<div class="float_right"><table class="tpr_box" id="tpr_stat_{$post[\'pid\']}">
<tr>
	<td class="tu_stat" id="tu_stat_{$post[\'pid\']}">{$post[\'thumbsup\']}</td>
	<td>{$tu_img}</td>
	<td>{$td_img}</td>
	<td class="td_stat" id="td_stat_{$post[\'pid\']}">{$post[\'thumbsdown\']}</td>
</tr>
</table></div>'),
		'sid' => -1,
		'version' => 1600
	));
}

// Activate function
function thumbspostrating_activate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit','#'.preg_quote('<div class="post_body" id="pid_{$post[\'pid\']}">').'#','{$post[\'tprdsp\']}<div class="post_body" id="pid_{$post[\'pid\']}">');
	find_replace_templatesets('postbit_classic','#'.preg_quote('{$post[\'message\']}').'#','{$post[\'tprdsp\']}{$post[\'message\']}');
}

// Deactivate function
function thumbspostrating_deactivate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit','#'.preg_quote('{$post[\'tprdsp\']}').'#','');
	find_replace_templatesets('postbit_classic','#'.preg_quote('{$post[\'tprdsp\']}').'#','');
}

// Is Installed function
function thumbspostrating_is_installed()
{
	global $db;
	return (bool) $db->table_exists('thumbspostrating');
}

// Uninstall function
function thumbspostrating_uninstall()
{
	global $db;
	$gid = $db->fetch_field($db->simple_select('settinggroups','gid','name="tpr_group"'), 'gid');
	if($gid)
	{
		$db->delete_query('settings', 'gid='.$gid);
		$db->delete_query('settinggroups', 'gid='.$gid);
	}
	rebuild_settings();
	
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts DROP thumbsup, DROP thumbsdown', true);
	$db->write_query('DROP TABLE IF EXISTS '.TABLE_PREFIX.'thumbspostrating');
	
	$db->delete_query('templates', 'title="postbit_tpr" AND sid=-1');
}

function tpr_global()
{
	if($GLOBALS['current_page'] != 'showthread.php') return;
	$GLOBALS['templatelist'] .= ',postbit_tpr';
}

// returns true if ratings are enabled for this forum
function tpr_enabled_forum($fidcheck)
{
	$forums =& $GLOBALS['mybb']->settings['tpr_forums'];
	if( $forums != 0 )
	{
		foreach(array_map('trim', explode(',',$forums)) as $fid)
		{
			if( $fid == $fidcheck )
			{
				return false;
			}
		}
	}
	return true;
}

// returns true if the current user ($mybb->user) has permissions to rate
// the post (if $postuid is supplied), based on usergroup permissions
function tpr_user_can_rate($postuid=0)
{
	global $mybb;
	$user =& $mybb->user;
	// guests can never rate
	if(!$user['uid']) return false;
	
	// cache the group checking result
	static $can_rate = null;
	
	if(!isset($can_rate))
	{
		$can_rate = false;
		// first, gather all the groups the user is in
		$usergroups = array();
		if($user['additionalgroups'])
			$usergroups = array_flip(explode(',', $user['additionalgroups']));
		$usergroups[$user['usergroup']] = 1;
		// next, check that the groups are allowed
		foreach(array_map('intval', array_map('trim', explode(',',$mybb->settings['tpr_usergroups']))) as $grp)
		{
			if(isset($usergroups[$grp]))
			{
				$can_rate = true;
				break;
			}
		}
	}
	
	if($can_rate)
	{
		// check self rating perm
		return ($postuid != $user['uid'] || $mybb->settings['tpr_selfrate'] != 1);
	}
	return false;
}

// Display the RATEBOX
function tpr_box(&$post)
{
	global $db, $mybb, $templates, $lang, $current_page;
	$pid = (int) $post['pid'];
	if(!$pid) return; // paranoia
	
	static $done_init = false;
	static $user_rates = null;
	if(!$done_init)
	{
		$done_init = true;
		
		// Check whether the posts in the forum can be rated
		if(!tpr_enabled_forum($post['fid']))
		{
			global $plugins;
			$plugins->remove_hook('postbit', 'tpr_box');
			return;
		}
		
		// build user rating cache
		$user_rates = array();
		if($current_page == 'showthread.php')
		{
			// tricky little optimisation :P
			// - on AJAX new reply, it's impossible for this post to have been rated, therefore, we don't need to build a cache at all
			if($mybb->user['uid'])
			{
				if($mybb->input['mode'] == 'threaded')
					$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND pid='.(int)$mybb->input['pid']);
				else
					$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND '.$GLOBALS['pids']);
				
				while($ttrate = $db->fetch_array($query))
					$user_rates[$ttrate['pid']] = $ttrate['rating'];
				$db->free_result($query);
			}
			
			// stick in additional header stuff
			$GLOBALS['headerinclude'] .= '<script type="text/javascript" src="'.$mybb->settings['bburl'].'/jscripts/thumbspostrating.js?ver=1600"></script><link type="text/css" rel="stylesheet" href="'.$mybb->settings['bburl'].'/css/thumbspostrating.css" />';
		}
		
		$lang->load('thumbspostrating');
	}
	
	// Make the thumb
	// for user who cannot rate
	if( !tpr_user_can_rate($post['uid']) )
	{
		$tu_img = '<div class="tpr_thumb tu_rd"></div>';
		$td_img = '<div class="tpr_thumb td_ru"></div>';
	}
	// for user already rated thumb
	elseif( $user_rates[$pid] )
	{
		$ud = ($user_rates[$pid] == 1 ? 'u' : 'd');
		$tu_img = '<div class="tpr_thumb tu_r'.$ud.'"></div>';
		$td_img = '<div class="tpr_thumb td_r'.$ud.'"></div>';
	}
	// for user who can rate
	else
	{
		$url = $mybb->settings['bburl'].'/xmlhttp.php?action=tpr&amp;pid='.$pid.'&amp;my_post_key='.$mybb->post_code.'&amp;rating=';
		$tu_img = '<a href="'.$url.'1" class="tpr_thumb tu_nr" title="'.$lang->tpr_rate_up.'" onclick="return thumbRate(1,'.$pid.');"></a>';
		$td_img = '<a href="'.$url.'-1" class="tpr_thumb td_nr" title="'.$lang->tpr_rate_down.'" onclick="return thumbRate(-1,'.$pid.');"></a>';
		// eh, like who turns it off?
		if($mybb->settings['use_xmlhttprequest'] == 0)
		{
			$tu_img = str_replace('onclick="return thumbRate', 'rel="', $tu_img);
			$td_img = str_replace('onclick="return thumbRate', 'rel="', $td_img);
		}
	}
	
	$post['thumbsup'] = (int)$post['thumbsup'];
	$post['thumbsdown'] = (int)$post['thumbsdown'];
	
	// Display the rating box
	eval('$post[\'tprdsp\'] = "'.$templates->get('postbit_tpr').'";');
}

function tpr_action()
{
	global $mybb, $db, $lang;
	if($mybb->input['action'] != 'tpr') return;
	if(!verify_post_check($mybb->input['my_post_key'], true))
		xmlhttp_error($lang->invalid_post_code);
	
	$uid = $mybb->user['uid'];
	$rating = (int)$mybb->input['rating'];
	$pid = (int)$mybb->input['pid'];
	$lang->load('thumbspostrating');
	
	// check for invalid rating
	if($rating != 1 && $rating != -1) xmlhttp_error($lang->tpr_error_invalid_rating);
	
	//User has rated, first check whether the rating is valid
	// Check whether the user can rate
	if(!tpr_user_can_rate($pid)) xmlhttp_error($lang->tpr_error_cannot_rate);
	
	$post = get_post($pid);
	if(!$post['pid']) xmlhttp_error($lang->post_doesnt_exist);
	if(!tpr_enabled_forum($post['fid'])) xmlhttp_error($lang->tpr_error_cannot_rate);
	// TODO: check post visibility permissions too
	
	// Check whether the user has rated
	$rated = $db->simple_select('thumbspostrating','rating','uid='.$uid.' and pid='.$pid);
	if($db->num_rows($rated)) xmlhttp_error($lang->tpr_error_already_rated);
	$db->free_result($rated);
	
	$db->replace_query('thumbspostrating', array(
		'rating' => $rating,
		'uid' => $uid,
		'pid' => $pid
	));
	$field = ($rating == 1 ? 'thumbsup' : 'thumbsdown');
	$db->write_query('UPDATE '.TABLE_PREFIX.'posts SET '.$field.'='.$field.'+1 WHERE pid='.$pid);
	++$post[$field];
	
	if(!$mybb->input['ajax'])
	{
		header('Location: '.htmlspecialchars_decode(get_post_link($pid, $post['tid'])).'#pid'.$pid);
	}
	else
	{
		// push new values to client
		echo 'success/', $post['pid'], '/', (int)$post['thumbsup'], '/', (int)$post['thumbsdown'];
	}
	// TODO: for non-AJAX, it makes more sense to go through global.php
}

// TODO: perhaps include a rebuild thumb ratings section in ACP
// TODO: delete ratings when delete post/thread/forum
// TODO: provide ability for users to change ratings?
