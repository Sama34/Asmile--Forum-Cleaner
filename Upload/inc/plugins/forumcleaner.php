<?php
  
/*
	Forum Cleaner - A MyBB plugin to help Administrators keep things clean.
												  
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
 * This file is MYBB_ROOT/inc/plugins/forumcleaner.php
 * There should also be a files
 *  MYBB_ROOT/inc/tasks/forumcleaner.php
 *  MYBB_ROOT/inc/languages/english/admin/forumcleaner.lang.php
 *  MYBB_ROOT/inc/languages/english/forumcleaner.lang.php
 */

/*
 * This plugin based on heavily rewritten AutoExpunge plugin (Created by The forum.kde.org team) 
 * and lots of Copy&Paste's from Admin CP tools
 */


// Don't allow direct initialization.
if (! defined('IN_MYBB')) {
	die('Nope.');
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// The info for this plugin.
function forumcleaner_info() 
{
	global $lang;
	
	$lang->load('forumcleaner');

	$language_fine = 1;

	if( defined('IN_ADMINCP') && !strlen($lang->setting_group_forumcleaner) ) 
	{
		$language_fine = 0;
	}
	elseif ( !defined('IN_ADMINCP') && !strlen($lang->forumcleaner_topics_closed) ) 
	{
		$language_fine = 0;
	};

	if ( !$language_fine )
	{
		error("<strong>No appropriate language file loaded !</strong>", "Forum Cleaner error");
	}

	return array(
		'name'			=> 'Forum Cleaner',
		'avaname'		=> $lang->forumcleaner_avaname,
		'description'	=> $lang->forumcleaner_desc,
		'avadesc'		=> $lang->forumcleaner_avadesc,
		'website'		=> 'http://community.mybb.com/thread-77074.html', 
		'author'		=> 'Andriy Smilyanets',
		'authorsite'	=> 'http://community.mybb.com/user-18581.html',
		'version'		=> '2.5.1',
		'compatibility'	=> '18*',
		'codename'		=> 'ougc_forumcleaner',
		'sysname'		=> 'forumcleaner',
		'avasysname'	=> 'orphanavatars',
		'cfglink'		=> 'index.php?module=forum-forumcleaner',
		'avalink'		=> 'index.php?module=user-orphanavatars',
		'files'			=> array(
			'inc/plugins/forumcleaner.php',
			'inc/tasks/forumcleaner.php',
			'inc/languages/english/admin/forumcleaner.lang.php',
			'inc/languages/english/forumcleaner.lang.php',
		),
	);

}

// Hooks.
$plugins->add_hook('admin_forum_menu', 'forumcleaner_admin_forum_menu');
$plugins->add_hook('admin_forum_action_handler', 'forumcleaner_admin_forum_action_handler');
$plugins->add_hook('admin_forum_permissions', 'forumcleaner_admin_forum_permissions');

$plugins->add_hook('admin_user_menu', 'forumcleaner_admin_user_menu');
$plugins->add_hook('admin_user_action_handler', 'forumcleaner_admin_user_action_handler');
$plugins->add_hook('admin_user_permissions', 'forumcleaner_admin_user_permissions');

$plugins->add_hook('admin_load', 'forumcleaner_admin_load');

$plugins->add_hook('build_forumbits_forum','forumcleaner_build_forumbits');
$plugins->add_hook('forumdisplay_start','forumcleaner_build_threadlist');

// Action to take to install the plugin.
function forumcleaner_install() 
{
	global $db,$lang;

	// Retrieve plugin info.
	$me = forumcleaner_info();

	// Create the table for the settings.
	$result = $db->query("
		CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "{$me['sysname']}`
		(
			`xid` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`fid` TEXT NOT NULL DEFAULT '',
			`enabled` BOOL NOT NULL DEFAULT 1,
			`threadslist_display` BOOL NOT NULL DEFAULT 0,
			`forumslist_display` BOOL NOT NULL DEFAULT 0,
			`action` VARCHAR(20) NOT NULL,
			`age` INT NOT NULL DEFAULT 0,
			`agetype` VARCHAR(8) NOT NULL,
			`agesecs` INT NOT NULL DEFAULT 0,
			`lastpost` BOOL NOT NULL DEFAULT 1,
			`tofid` INT NOT NULL DEFAULT -1
		)
	");

	// Log.
	log_admin_action($me['name']);
}

// Return TRUE if plugin is installed, FALSE otherwise.
function forumcleaner_is_installed() 
{
	global $db;

	$me = forumcleaner_info();

	// Plugin is installed if table exists.
	return $db->table_exists($me['sysname']);
}

// Action to take to activate the plugin.
function forumcleaner_activate() 
{
	global $message,$lang, $PL;
	$PL or require_once PLUGINLIBRARY;

	$me = forumcleaner_info();

	// Add task if task file exists, warn otherwise.
	if (! file_exists(MYBB_ROOT . "inc/tasks/{$me['sysname']}.php")) 
	{
		$message = $lang->sprintf($lang->forumcleaner_task_file_not_exist,$me['name'],'<code>inc/tasks/'.$me['sysname'].'.php</code>');
	}
	else 
	{
		forumcleaner_add_task();
	}
	
	// Set admin permissions for tab.
	change_admin_permission('forum', $me['sysname']); 
	change_admin_permission('user', $me['avasysname']); 

	// upgrade from 2.3 to 2.4

	// table upgrade tested only for mysql, 
	// I have no other database engines to test them, so if it not compatible with others, 
	// feel free to modify this or add columns manually
	global $db;

	if(!$db->field_exists('threadslist_display', $me['sysname']))
	{
		$db->add_column($me['sysname'], 'threadslist_display', 'BOOL NOT NULL DEFAULT 0');
	}
	if(!$db->field_exists('forumslist_display', $me['sysname']))
	{
		$db->add_column($me['sysname'], 'forumslist_display', 'BOOL NOT NULL DEFAULT 0');
	}

	// upgrade from 2.4 to 2.5
	// table upgrade tested only for mysql, 
	// I have no other database engines to test them, so if it not compatible with others, 
	// feel free to modify this or modify 'fid' column type from int to text manually
	$db->modify_column($me['sysname'], 'fid', "TEXT NOT NULL DEFAULT ''");

	// Add settings
	$PL->settings($me['sysname'], $lang->setting_group_forumcleaner, $lang->setting_group_forumcleaner_desc, array(
		'threadlimit'	=> array(
		   'title'			=> $lang->setting_forumcleaner_threadlimit,
		   'description'	=> $lang->setting_forumcleaner_threadlimit_desc,
		   'optionscode'	=> 'text',
			'value'			=>	30,
		),
		'userlimit'	=> array(
		   'title'			=> $lang->setting_forumcleaner_userlimit,
		   'description'	=> $lang->setting_forumcleaner_userlimit_desc,
		   'optionscode'	=> 'text',
			'value'			=>	50,
		),
		'awaitingdays'	=> array(
		   'title'			=> $lang->setting_forumcleaner_awaitingdays,
		   'description'	=> $lang->setting_forumcleaner_awaitingdays_desc,
		   'optionscode'	=> 'text',
			'value'			=>	0,
		),
		'inactivedays'	=> array(
		   'title'			=> $lang->setting_forumcleaner_inactivedays,
		   'description'	=> $lang->setting_forumcleaner_inactivedays_desc,
		   'optionscode'	=> 'text',
			'value'			=>	0,
		),
		'groupids'	=> array(
		   'title'			=> $lang->setting_forumcleaner_groupids,
		   'description'	=> $lang->setting_forumcleaner_groupids_desc,
		   'optionscode'	=> 'groupselect',
			'value'			=>	'3,4,6',
		)
	));

	// add templates if not exist
	forumcleaner_addtemplates();
	// end of upgrade code

	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	//	modify default templates
	find_replace_templatesets('forumbit_depth2_forum','#\\{.modlist}#','{\$forum[\''.$me['sysname'].'_forumbit\']}{\$modlist}');
	find_replace_templatesets('forumdisplay_threadlist','#^#','{\$mybb->input[\''.$me['sysname'].'_threadlist\']}');

}


function forumcleaner_addtemplates()
{
	global $db, $PL;
	$PL or require_once PLUGINLIBRARY;

	$me = forumcleaner_info();

	// Add template group
	$PL->templates($me['sysname'], $me['name'], array(
		'forumbit'		=> '<br /><strong>{$messages}</strong>',
		'threadlist'	=> '<div class="pm_alert"><strong>{$messages}</strong></div>',
	));
}


// Action to take to deactivate the plugin.
function forumcleaner_deactivate() 
{
	global $db, $mybb;

	$me = forumcleaner_info();

	// Disable task
	$db->update_query('tasks', array('enabled' => 0), "file = '{$me['sysname']}'");

	// recover templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('forumbit_depth2_forum','#\\{.forum\\[.'.$me['sysname'].'_forumbit.\\]}#','',0);
	find_replace_templatesets('forumdisplay_threadlist','#\\{.mybb->input\\[.'.$me['sysname'].'_threadlist.\\]}#',"",0);

	// Change admin permission.
	change_admin_permission('forum', $me['sysname'], 0);
	change_admin_permission('user', $me['avasysname'], 0);
}

// Action to take to uninstall the plugin.
function forumcleaner_uninstall() 
{
	global $db, $lang, $PL, $mybb;
	$PL or require_once PLUGINLIBRARY;

	$me = forumcleaner_info();

	// Drop database.
	$db->drop_table($me['sysname']);

	$PL->settings_delete($me['sysname']);
	$PL->templates_delete('ougcawards');

	// Remove task.

	// Switch modules and actions.
	$prev_module = $mybb->get_input('module');
	$prev_action = $mybb->get_input('action');
	$mybb->input['module'] = 'tools-tasks';
	$mybb->input['action'] = 'delete';

	// Fetch ID and title.
	$result = $db->simple_select('tasks', 'tid, title', "file = '{$me['sysname']}'");
	while ($task = $db->fetch_array($result)) 
	{
		// Log.
		log_admin_action($task['tid'], $task['title']);
	}

	// Delete.
	$result = $db->delete_query('tasks', "file = '{$me['sysname']}'");

	// Reset module.
	$mybb->input['module'] = $prev_module;

	// Reset action.
	$mybb->input['action'] = $prev_action;

	// Log.
	log_admin_action($me['name']);

	// Remove admin permission.
	change_admin_permission('forum', $me['sysname'], -1);
	change_admin_permission('user', $me['avasysname'], -1);

	flash_message($lang->sprintf($lang->forumcleaner_plugin_uninstalled, $me['name'], '<ul><li>' . join('</li><li>', $me['files']) . '</li></ul>'), 'success');
}

// Add a menu item in the AdminCP.
function forumcleaner_admin_forum_menu(&$sub_menu) 
{
	$me = forumcleaner_info();
	$sub_menu[] = array(
		'id' => $me['sysname'], 
		'title' => $me['name'], 
		'link' => $me['cfglink'],
	);
}

// The file to use for configuring the plugin.
function forumcleaner_admin_forum_action_handler(&$actions) 
{
	$me = forumcleaner_info();
	$actions[$me['sysname']] = array(
		'active' => $me['sysname'], 
		'file' => 'settings.php'
	);
}

// The text for the entry in the admin permissions page.
function forumcleaner_admin_forum_permissions(&$admin_permissions) 
{
	global $lang;

	$me = forumcleaner_info();
	
	$admin_permissions[$me['sysname']] = $lang->sprintf($lang->forumcleaner_can_manage,$me['name']);
}

function get_seconds($age,$type) 
{
	$age_type_secs = array(
		"hours"     => 60*60,
		"days"      => 24*60*60,
		"weeks"     => 7*24*60*60,
		"months"    => 30*24*60*60,
	);

	return $age * $age_type_secs[$type];
}

function forumcleaner_validate_action(&$action) 
{
	global $forum_cache,$lang,$db,$mybb;

	$me = forumcleaner_info();

	$errors = array();

	if ($action['age'] == 0) 
	{
		$errors['invalid_age'] = $lang->forumcleaner_invalid_age;
	}

	if (!in_array($action['agetype'],array('hours','days','weeks','months')))
	{
		$errors['invalid_agetype'] = $lang->forumcleaner_invalid_agetype;
	}

	$action['agesecs'] = get_seconds($action['age'],$action['agetype']);

	if (!in_array($action['action'],array('delete','close','move', 'del_redirects'))) 
	{
		$errors['invalid_action'] = $lang->forumcleaner_invalid_action;
	}
	
	$forums_verify = explode(',',$action['fid']);

    if ( $action['fid'] == '-1' )
	{
		// All forums IS allowed for delete,close,del_redirects
		if ( $action['action'] == 'move' )
		{
			$errors['all_is_not_allowed'] = $lang->forumcleaner_all_not_allowed;
			return $errors;
		}
	}
	else
	{ 
		foreach ($forums_verify as $fid_verify)
		{
			if (!array_key_exists($fid_verify,$forum_cache)) 
			{
				$errors['invalid_forum_id'] = $lang->forumcleaner_invalid_forum_id;
			}
			elseif ($forum_cache[$fid_verify]['type'] != 'f')
			{
				$errors['invalid_forum_id'] = $lang->forumcleaner_source_category_not_allowed;
			}
		}
	} 

	if ( $action['action'] == 'del_redirects') 
	{
		// doesn't apply to del_redirects
		$action['forumslist_display']=0;
		$action['threadslist_display']=0;
	}

	if ($action['lastpost'] != 1)
	{
		// Lastpost should be 0 or 1; default to 0, don't trigger an error.
		$action['lastpost'] = 0;
	}

	if ( $action['action'] == 'move' ) 
	{
		if (!array_key_exists($action['tofid'],$forum_cache)) 
		{
			$errors['invalid_target_forum_id'] = $lang->forumcleaner_invalid_target_forum_id;
		}
		elseif ($forum_cache[$action['tofid']]['type'] != 'f')
		{
			$errors['invalid_target_forum_id'] = $lang->forumcleaner_target_category_not_allowed;
		}
		elseif (in_array($action['tofid'],$forums_verify)) 
		{
			$errors['target_selected'] = $lang->forumcleaner_target_selected;
		}
	}
	
	return $errors;
}


// simple sort key for forums
function get_sort_key($fid)
{
	global $forum_cache;
		
	if ($fid)
	{
		return get_sort_key($forum_cache[$fid]['pid']).sprintf("%04d",$forum_cache[$fid]['disporder']);
	}
	else
	{
		return '';
	}
}

// to sort action list
function actions_cmp($a,$b) 
{
	$cmp = strcmp($a['sort_key'],$b['sort_key']);
	if ($cmp != 0)
	{
		return $cmp;
	}
	if ($a['agesecs'] < $b['agesecs'])
	{
		return -1;
	}
	if ($a['agesecs'] > $b['agesecs'])
	{
		return 1;
	}
	return 0;
}

// Configuration page.
function forumcleaner_admin_load() 
{
	global $page;

	$me = forumcleaner_info();

	if ($page->active_action == $me['sysname']) 
	{
		forumcleaner_process_forumactions();
	}
	elseif ($page->active_action == $me['avasysname'])
	{
		forumcleaner_process_orphanavatars();
	}
} // function forumcleaner_admin_load() 

function forumcleaner_process_forumactions()
{
	global $db, $page, $mybb, $forum_cache, $lang;

	$me = forumcleaner_info();

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	$action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'config');

	// silently ignore unknown actions
	if (!in_array($action,array('config','add','addtask','edit','enable','disable','delete')))
	{
		admin_redirect($me['cfglink']);
	}

	$page->add_breadcrumb_item($me['name']);
	$page->output_header($me['name']);

	// Warnings.
	$result = $db->simple_select('tasks', 'tid, enabled, file', "file = '{$me['sysname']}'");
	$task = $db->fetch_array($result);
	if (! file_exists(MYBB_ROOT . "inc/tasks/{$task['file']}.php")) 
	{
		$page->output_alert($lang->sprintf($lang->forumcleaner_alert_task_file,
			$me['name'],
			'<code>inc/tasks/'.$me['sysname'].'.php</code>'
		));
	}
	if (! $db->num_rows($result)) 
	{
		$page->output_alert($lang->sprintf($lang->forumcleaner_alert_no_task_added,
			$me['name'],
			'<code>inc/tasks/'.$me['sysname'].'.php</code>',
			"{$me['cfglink']}&amp;action=addtask"
		));
	}
	if (! $task['enabled']) 
	{
		$page->output_alert($lang->sprintf($lang->forumcleaner_alert_task_disabled,
			$me['name'],
			"index.php?module=tools/tasks&amp;action=enable&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}"
		));
	}

	$xid = '-1';

	if ($mybb->get_input('xid', 1) > 0)
	{
		$xid = $mybb->get_input('xid', 1);
	}

	$db_array = array();

	// silently ignore edit action without xid provided or non-exist xid
	if ($action == 'edit')
	{    
		if ($xid < 0) 
		{
			$action = 'config';
		}
		else 
		{
			$result = $db->simple_select($me['sysname'], '*', "xid = '{$xid}'");
			if ($db->num_rows($result))
			{
				$db_array = $db->fetch_array($result);
			}
			else
			{
				$action = 'config';
			} 
		}
	}

	if ($action == 'addtask') 
	{
		if (forumcleaner_add_task()) 
		{
			flash_message($lang->sprintf($lang->forumcleaner_task_added,$me['name']), 'success');
		}
		else 
		{
			flash_message($lang->sprintf($lang->forumcleaner_task_exists,$me['name']), 'success');
		}
		admin_redirect($me['cfglink']);
	}

	if ($action == 'delete') 
	{
		if ($xid>=0) 
		{
			$result = $db->simple_select($me['sysname'], '*', "xid = '$xid'");
			if ($expunge = $db->fetch_array($result)) 
			{
				log_admin_action(array('xid' => $xid, 'fid' => $expunge['fid']));
				$db->delete_query($me['sysname'], "xid = '{$xid}'", 1);
			}
			flash_message($lang->forumcleaner_action_deleted, 'success');
		}
		admin_redirect($me['cfglink']);
	}

	if ($action == 'disable' or $action == 'enable') 
	{
		if ($xid>=0) 
		{
			$find = 1;
			$update = 0;
			if ($action == 'enable') 
			{
				$find = 0;
				$update = 1;
			}

			$result = $db->simple_select($me['sysname'], '*', "xid = '$xid' and enabled = {$find}");
			if ($expunge = $db->fetch_array($result)) 
			{
				log_admin_action(array('xid' => $xid, 'fid' => $expunge['fid']));
				$db->update_query($me['sysname'], array('enabled' => $update), "xid = '{$xid}'");
			}
		}
		flash_message(($action=='disable'?$lang->forumcleaner_action_disabled:$lang->forumcleaner_action_enabled), 'success');
		admin_redirect($me['cfglink']);
	}

	$forumactions = array(
		'close'          => $lang->forumcleaner_close_threads,
		'delete'         => $lang->forumcleaner_delete_threads,
		'move'           => $lang->forumcleaner_move_threads,
		'del_redirects'  => $lang->forumcleaner_delete_redirects,
	);

	$errors = array();
	$update_array = array();
	$forum_checked['all'] = '';
	$forum_checked['custom'] = '';

	// Form received.
	if ($mybb->request_method == "post") {
		if ($xid >= 0) 
		{
			$update_array['xid']=$xid;
			$action = 'edit';
		}
		else 
		{
			$action = 'add';
		}
		if ($mybb->get_input('forum_type') == 'custom')
		{
			if (count($mybb->get_input('forum_1_forums', 2))<1)
			{
				$errors[] = $lang->forumcleaner_no_forum_selected;
			}
			$forum_checked['custom'] = "checked=\"checked\"";

			if ($mybb->get_input('forum_1_forums', 2))
			{
				$checked = array();
				foreach ($mybb->get_input('forum_1_forums', 2) as $fid)
				{
					$checked[] = (int)$fid;
				}
				$update_array['fid'] = implode(',',$checked);
			}
		}
		else 
		{ 
			$forum_checked['all'] = "checked=\"checked\"";
			$mybb->input['forum_1_forums']='';
			$update_array['fid'] = '-1';
		}

		$update_array['age'] = $mybb->get_input('age', 1);
		$update_array['agetype']=$mybb->get_input('agetype');
		$update_array['action']=$mybb->input['forumaction'];
		$update_array['lastpost'] = $mybb->get_input('lastpost', 1);
		$update_array['forumslist_display'] = $mybb->get_input('forumslist_display', 1);
		$update_array['threadslist_display'] = $mybb->get_input('threadslist_display', 1);

		if ($update_array['action'] == 'move' ) 
		{
			$update_array['tofid'] = $mybb->get_input('tofid', 1);
		}

		if (!count($errors))
		{
			$errors = forumcleaner_validate_action($update_array);
		}

		if (count($errors) == 0) 
		{
			// update or insert new action
			if ($xid < 0) 
			{
				// insert
				$db->insert_query($me['sysname'], $update_array);
				$mybb->input['action'] = 'add';
				log_admin_action(array('xid' => $db->insert_id(), 'fid' => $update_array['fid']));
			}
			else
			{
				// update
				unset($update_array['xid']);
				$db->update_query($me['sysname'], $update_array, "xid = '$xid'");
				$mybb->input['action'] = 'update';
				log_admin_action(array('xid' => $xid, 'fid' => $update_array['fid'])); 
			}

			flash_message($lang->sprintf($lang->forumcleaner_rules_updated,$me['name']), 'success');
			admin_redirect($me['cfglink']);
		}
	}

	$navtabs = array();
	$navtabs['config'] = array(
		'title'			=> $lang->forumcleaner_configuration,
		'link'			=> $me['cfglink'],
		'description'	=> $lang->forumcleaner_configuration_desc,
	);

	if ($action == 'edit') 
	{
		$navtabs['edit'] = array(
			'title'			=> $lang->forumcleaner_edit_forum_action,
			'link'			=> "{$me['cfglink']}&amp;action=edit&amp;xid={$xid}",
			'description'	=> $lang->forumcleaner_edit_forum_action_desc,
		);

		if (!count($update_array))
		{
			$update_array = $db_array;
			if ($update_array['fid'] == '-1')
			{
				$forum_checked['all'] = "checked=\"checked\"";
				$forum_checked['custom'] = '';
				$mybb->input['forum_type'] = 'all';
			}
			else
			{
				$forum_checked['all'] = '';
				$forum_checked['custom'] = "checked=\"checked\"";
				$mybb->input['forum_type'] = 'custom';
				$mybb->input['forum_1_forums'] = explode(',',$update_array['fid']);
			}
		}

	}
	else 
	{                
		$navtabs['add'] = array(
			'title'			=> $lang->forumcleaner_add_forum_action,
			'link'			=> "{$me['cfglink']}&amp;action=add",
			'description'	=> $lang->forumcleaner_add_forum_action_desc,
		);
	};

	$page->output_nav_tabs($navtabs, $action);

	if ($action == 'add' or $action == 'edit') 
	{
		// Create form.
		$form = new Form("{$me['cfglink']}&amp;action={$action}", 'post');

		if ($action == 'edit') 
		{
			echo $form->generate_hidden_field("xid", $xid);
		}

		if (count($errors))
		{
			$page->output_inline_error($errors);
		}

		if (count($update_array)) 
		{
			$update_array['forumaction']=$update_array['action'];
		}
		else
		{
			// clean add
			$update_array = array(
				'fid' => '-1',
				'tofid' => -1,
				'forumaction' => 'close',
				'age' => 1,
				'agetype' => 'days',
				'lastpost' => 1,
				'threadslist_display' => 0,
				'forumslist_display' => 0,
			);
			$forum_checked['all'] = "checked=\"checked\"";
			$forum_checked['custom'] = '';
			$mybb->input['forum_type'] = 'all';
		}

		$form_container = new FormContainer($action=='edit'?$lang->forumcleaner_edit_forum_action:$lang->forumcleaner_add_forum_action);

		// copy&paste from thread_prefixes.php
		print_selection_javascript();

		$actions = "
		<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
			<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
				<table cellpadding=\"4\">
					<tr>
						<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
						<td>".$form->generate_forum_select('forum_1_forums[]', $mybb->get_input('forum_1_forums', 2), array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
					</tr>
				</table>
			</dd>
		</dl>
		<script type=\"text/javascript\">
			checkAction('forums');
		</script>";
		/*$actions = "<script type=\"text/javascript\">
		function checkAction(id)
		{
			var checked = '';

			$$('.'+id+'s_check').each(function(e)
			{
				if(e.checked == true)
				{
					checked = e.value;
				}
			});
			$$('.'+id+'s').each(function(e)
			{
				Element.hide(e);
			});
			if($(id+'_'+checked))
			{
				Element.show(id+'_'+checked);
			}
		}    
</script>
<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
<dt><label style=\"display: block;\">
	<input type=\"radio\" name=\"forum_type\" value=\"1\" 
		{$forum_checked[1]} class=\"forums_check\" onclick=\"checkAction('forum');\" 
		style=\"vertical-align: middle;\" /> 
	<strong>{$lang->forumcleaner_all}</strong>
</label></dt>
<dt><label style=\"display: block;\">
	<input type=\"radio\" name=\"forum_type\" value=\"2\" 
		{$forum_checked[2]} class=\"forums_check\" onclick=\"checkAction('forum');\" 
		style=\"vertical-align: middle;\" /> 
	<strong>{$lang->forumcleaner_select}</strong>
</label></dt>
<dd style=\"margin-top: 4px;\" id=\"forum_2\" class=\"forums\">".
$form->generate_forum_select('forum_1_forums[]', 
	$mybb->get_input('forum_1_forums', 2), 
	array('multiple' => true, 'size' => 10)).
"
</dd>
</dl>
<script type=\"text/javascript\">
checkAction('forum');
</script>";*/
		$form_container->output_row($lang->forumcleaner_source_forum." <em>*</em>", 
									$lang->forumcleaner_source_forum_desc, 
									$actions);

		$agetypes = array(
			"hours"     => $lang->forumcleaner_agetype_hours,
			"days"      => $lang->forumcleaner_agetype_days,
			"weeks"     => $lang->forumcleaner_agetype_weeks,
			"months"    => $lang->forumcleaner_agetype_months,
		);
		$form_container->output_row($lang->forumcleaner_thread_age, $lang->forumcleaner_thread_age_desc, 
		$form->generate_text_box('age', $update_array['age'], array('id' => 'age'))." ".
		$form->generate_select_box('agetype', $agetypes, $update_array['agetype'], array('id' => 'agetype')), 'age');

		$form_container->output_row($lang->forumcleaner_thread_post_select, $lang->forumcleaner_thread_post_select_desc,
		$form->generate_select_box('lastpost', array(0 => $lang->forumcleaner_thread_first_post, 1 => $lang->forumcleaner_thread_last_post), 
			$update_array['lastpost'],array('id'=>'lastpost')),'lastpost');

		$form_container->output_row($lang->forumcleaner_thread_action, $lang->forumcleaner_thread_action_desc,
		$form->generate_select_box('forumaction', $forumactions, $update_array['forumaction'], 
			array('id' => 'forumaction')), 'forumaction');

		$form_container->output_row($lang->forumcleaner_target_forum, $lang->forumcleaner_target_forum_desc, 
		$form->generate_forum_select('tofid', $update_array['tofid'], array('id' => 'tofid', 'main_option' => $lang->forumcleaner_none)), 'tofid');

		$form_container->output_row($lang->forumcleaner_forumslist_display, $lang->forumcleaner_forumslist_display_desc,
		$form->generate_yes_no_radio('forumslist_display',$update_array['forumslist_display']), 'forumslist_display');		
		
		$form_container->output_row($lang->forumcleaner_threadslist_display, $lang->forumcleaner_threadslist_display_desc,
		$form->generate_yes_no_radio('threadslist_display',$update_array['threadslist_display']),'threadslist_display');

		$form_container->end();
		// Close form.
		$buttons = array($form->generate_submit_button($lang->forumcleaner_save));
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{ 
		//config

		// Init table.
		$table = new Table;
		$table->construct_header($lang->forumcleaner_forum);
		$table->construct_header($lang->forumcleaner_action, array("class" => "align_center"));
		$table->construct_header($lang->forumcleaner_age, array("class" => "align_center"));
		$table->construct_header($lang->forumcleaner_controls, array("class" => "align_center"));

		// List forums.
		$result = $db->simple_select($me['sysname']);

		if ($db->num_rows($result) > 0) 
		{
			$rows = array();

			while ($row = $db->fetch_array($result)) 
			{   
				if ($row['fid'] == '-1') 
				{
					$row['sort_key'] = '0000';
				}
				else 
				{
					$forums = explode(',',$row['fid']);
					$fid = array_shift($forums);
					$row['sort_key'] = get_sort_key($fid) . ' ' . $forum_cache[$fid]['name'];
				}
				array_push($rows,$row);
			}
			// sort actions by forum and treads age
			usort($rows,'actions_cmp');

			foreach ($rows as $row)
			{
				if($row['enabled'])
				{
					$icon = "<a href=\"{$me['cfglink']}&amp;action=disable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"{$lang->forumcleaner_enabled}\" title=\"{$lang->forumcleaner_enabled_title}\" style=\"vertical-align: middle;\" /></a>";
				}
				else
				{
					$icon = "<a href=\"{$me['cfglink']}&amp;action=enable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"{$lang->forumcleaner_disabled}\" title=\"{$lang->forumcleaner_disabled_title}\" style=\"vertical-align: middle;\" /></a>";
				}

				$forum_name = $lang->forumcleaner_all_forums;
				if ($row['fid'] != '-1')
				{
					$forums = explode(',',$row['fid']);
					$forum_name = '';
					foreach ($forums as $fid)
					{
					 	if (strlen($forum_name)) 
						{
							$forum_name .= '<br />';
						}
					   	$forum_name .= htmlspecialchars_uni(strip_tags($forum_cache[$fid]['name']));
					}
				}

				$table->construct_cell("<div class=\"float_right\">{$icon}</div><div><strong>".
					"<a href=\"{$me['cfglink']}&amp;action=edit&amp;xid={$row['xid']}\">".
					$forum_name."</a></strong></div>");
				$table->construct_cell($forumactions[$row['action']], array("class" => "align_center"));

				$lang_str = "forumcleaner_agetype_{$row['agetype']}";
				$table->construct_cell(
					$lang->sprintf($lang->forumcleaner_thread_age_text,
						$row['age'], 
						$lang->$lang_str, 
						$row['lastpost']?$lang->forumcleaner_thread_last_post:$lang->forumcleaner_thread_first_post
					), 
					array("class" => "align_center")
				);

				$popup = new PopupMenu("action_{$row['xid']}", $lang->forumcleaner_options);
				$popup->add_item($lang->forumcleaner_edit, "{$me['cfglink']}&amp;action=edit&amp;xid={$row['xid']}");
				if($row['enabled'])
				{
					$popup->add_item($lang->forumcleaner_disable,"{$me['cfglink']}&amp;action=disable&amp;xid={$row['xid']}");
				}
				else
				{
					$popup->add_item($lang->forumcleaner_enable,"{$me['cfglink']}&amp;action=enable&amp;xid={$row['xid']}");
				}

				$popup->add_item($lang->forumcleaner_delete,"{$me['cfglink']}&amp;action=delete&amp;xid={$row['xid']}&amp;my_post_key={$mybb->post_code}",
					"return AdminCP.deleteConfirmation(this, '{$lang->forumcleaner_confirm_forum_action_deletion}')");

				$table->construct_cell($popup->fetch(), array("class" => "align_center"));

				$table->construct_row();
			}
		}
		else 
		{
			$table->construct_cell($lang->forumcleaner_no_forums, array('colspan' => 4));
			$table->construct_row();
		}
		$table->output($lang->forumcleaner_forums);
	}
	echo "<br /><small><strong>{$me['name']} plugin &copy; 2010</strong></small>";
	$page->output_footer(TRUE);
	
}// function forumcleaner_process_forumactions()

// Function to add task to task system.
function forumcleaner_add_task() 
{
	global $db, $mybb, $lang;

	require_once MYBB_ROOT . 'inc/functions_task.php';

	$me = forumcleaner_info();

	$result = $db->simple_select('tasks', 'count(tid) as count', "file = '{$me['sysname']}'");
	if (! $db->fetch_field($result, 'count')) 
	{
		// Switch modules and actions.
		$prev_module = $mybb->get_input('module');
		$prev_action = $mybb->get_input('action');
		$mybb->input['module'] = 'tools-tasks';
		$mybb->input['action'] = 'add';

		// Create task. Have it run every 15 minutes by default.
		$insert_array = array(
			'title'			=> $me['name'],
			'description'	=> $lang->forumcleaner_task_desc,
			'file'			=> $me['sysname'],
			'minute'		=> '3,18,33,48',
			'hour'			=> '*',
			'day'			=> '*',
			'month'			=> '*',
			'weekday'		=> '*',
			'lastrun'		=> 0,
			'enabled'		=> 1,
			'logging'		=> 1,
			'locked'		=> 0,
		);
		$insert_array['nextrun'] = fetch_next_run($insert_array);
		$result = $db->insert_query('tasks', $insert_array);
		$tid = $db->insert_id();

		log_admin_action($tid, $me['name']);

		// Reset module and action.
		$mybb->input['module'] = $prev_module;
		$mybb->input['action'] = $prev_action;

		return TRUE;
	}
	else
	{
		// Enable task
		$db->update_query('tasks', array('enabled' => 1), "file = '{$me['sysname']}'");

		return TRUE;
	}
}


// Add a menu item in the AdminCP.
function forumcleaner_admin_user_menu(&$sub_menu) 
{
	$me = forumcleaner_info();
	$sub_menu[] = array(
		'id' => $me['avasysname'], 
		'title' => $me['avaname'], 
		'link' => $me['avalink'],
	);
}

// The file to use for configuring the plugin.
function forumcleaner_admin_user_action_handler(&$actions) 
{
	$me = forumcleaner_info();
	$actions[$me['avasysname']] = array(
		'active' => $me['avasysname'], 
		'file' => 'settings.php'
	);
}

// The text for the entry in the admin permissions page.
function forumcleaner_admin_user_permissions(&$admin_permissions) 
{
	global $lang;

	$me = forumcleaner_info();
	
	$admin_permissions[$me['avasysname']] = $lang->sprintf($lang->forumcleaner_can_manage,$me['avaname']);
}


function forumcleaner_process_orphanavatars()
{
	global $db, $page, $mybb, $lang;

	$me = forumcleaner_info();

	$action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'find');

	// silently ignore unknown actions
	if (!in_array($action,array('find','delete')))
	{
		admin_redirect($me['avalink']);
	}

	$page->add_breadcrumb_item($me['avaname']);
	$page->output_header($me['avaname']);

	$files = array();
	$files = find_orphaned_avatars();
	$numfiles = count($files);

	$navtabs = array();
	$navtabs['find'] = array(
		'title'			=> $lang->forumcleaner_avafind,
		'link'			=> $me['avalink'],
		'description'	=> $lang->forumcleaner_avafind_desc,
	);

	if ($numfiles) 
	{
		$navtabs['delete'] = array(
			'title'			=> $lang->forumcleaner_avadelete,
			'link'			=> "{$me['avalink']}&amp;action=delete",
			'description'	=> "",
		);
	}

	$page->output_nav_tabs($navtabs, 'find');

	if ($action == 'find') 
	{ 
		if ($numfiles)
		{
			$page->output_alert($lang->sprintf($lang->forumcleaner_avafound, $numfiles));
		}
		else
		{
			$page->output_success($lang->forumcleaner_avanotfound);
		}
	}

	if ($action == 'delete') 
	{
		if ($numfiles) 
		{
			$avatarpath = $mybb->settings['avataruploadpath'];

			if(defined('IN_ADMINCP'))
			{
				$avatarpath = '../'.$avatarpath;
			}

			if (! preg_match("#/$#",$avatarpath) ) 
			{
				$avatarpath .= '/';
			}

			foreach ($files as $file) 
			{
				@unlink($avatarpath.$file);
			}

			flash_message($lang->sprintf($lang->forumcleaner_avatars_deleted,$numfiles), 'success');
			$files = array();
		}
		else
		{
			flash_message($lang->forumcleaner_avatarnotfound, 'error');
		}

		admin_redirect($me['avalink']);
	}

	echo "<br /><small><strong>{$me['name']} plugin &copy; 2010</strong></small>";
	$page->output_footer(TRUE);
	
}// function forumcleaner_process_orphanavatars()



function find_orphaned_avatars()
{
	global $mybb,$db;
	
	if(defined('IN_ADMINCP'))
	{
		$avatarpath = '../'.$mybb->settings['avataruploadpath'];
	}
	else
	{
		$avatarpath = $mybb->settings['avataruploadpath'];
	}

	$query = $db->query("select uid,avatar from ".TABLE_PREFIX."users where avatartype='upload'");
	$dir = opendir($avatarpath);

	$user_avatars = array();
	$file_avatars = array();

	if($dir)
	{
		$file_notfinished = 1;
		$user_notfinished = 1;

		while ( $file_notfinished || $user_notfinished )
		{
			if ( $file_notfinished and ( $file = @readdir($dir) ) ) 
			{
				if ( array_key_exists($file,$user_avatars) )
				{
					unset($user_avatars[$file]);
				}
				else
				{
					$file_avatars[$file] = 1;
				}
			}
			else
			{
				$file_notfinished = 0;
			}

			if ( $user_notfinished and ( $ufile = $db->fetch_array($query) ) )
			{
				$file = preg_replace("#.+/#","",$ufile['avatar']);
				$file = preg_replace("#\\?.+#","",$file);

				if ( array_key_exists($file,$file_avatars) )
				{
					unset($file_avatars[$file]);
				}
				else
				{
					$user_avatars[$file] = $ufile['uid'];
				}
			}
			else
			{
				$user_notfinished = 0;
			}
		}

		unset($file_avatars['.']);
		unset($file_avatars['..']);
		unset($file_avatars['index.html']);

		closedir($dir);

		return array_keys($file_avatars);
	}
	else
	{
		return array();
	}
}



// forms message to user with Forum Action description
function get_forumaction_desc($fid, $where)
{
	static $forumaction_cache;
	global $db, $lang, $forum_cache;

	$me = forumcleaner_info();


	// keep information cached
	if (!is_array($forumaction_cache))
	{
		$query = $db->simple_select($me['sysname'],'*','enabled = 1 AND (forumslist_display = 1 OR threadslist_display = 1)');
		
		$forumaction_cache = array();
		while ($fa = $db->fetch_array($query))
		{
			$forums = $fa['fid'];
			if ($forums == '-1' || $fa['action'] == 'del_redirects')
			{
				continue;
			}
			$forums = array_map('intval',explode(',',$forums));
			foreach ($forums as $f) 
			{
				if ( $fa['forumslist_display'] == 1 )
				{
					$forumaction_cache['forums'][$f][] = $fa;
				}
				if ( $fa['threadslist_display'] == 1 )
				{
					$forumaction_cache['threads'][$f][] = $fa;
				}
			}
		}
	}

	$agetypes = array(
		"hours"     => $lang->forumcleaner_topics_hours,
		"days"      => $lang->forumcleaner_topics_days,
		"weeks"     => $lang->forumcleaner_topics_weeks,
		"months"    => $lang->forumcleaner_topics_months,
	);

	$forumactions = array(
		'close'  => $lang->forumcleaner_topics_closed,
		'delete' => $lang->forumcleaner_topics_deleted,
		'move'   => $lang->forumcleaner_topics_moved,
	);

	$lastpost = array(
		0	=> $lang->forumcleaner_topics_firstpost,
		1	=> $lang->forumcleaner_topics_lastpost,
	);


	if (count($forumaction_cache) and isset($forumaction_cache[$where]) and 
		count($forumaction_cache[$where]) and 
		array_key_exists($fid,$forumaction_cache[$where]))
	{
		$actions = array();
		$actions = $forumaction_cache[$where][$fid];

		$ret = '';
		$comma = '';
		foreach ($actions as $fa) 
		{
			$ret .= $comma;
			if ($fa['action'] == 'close' or $fa['action'] == 'delete')
			{
				$ret .= $lang->sprintf($forumactions[$fa['action']],
					$lastpost[$fa['lastpost']], 
					$fa['age'], 
					$agetypes[$fa['agetype']]
				);
			}
			else
			{
				if(!is_array($forum_cache))
				{
					cache_forums();
				}
				
				$ret .= $lang->sprintf($forumactions['move'],
					htmlspecialchars_uni(strip_tags($forum_cache[$fa['tofid']]['name'])),
					$lastpost[$fa['lastpost']], 
					$fa['age'], 
					$agetypes[$fa['agetype']]
				);
			}
			$comma = '<br />';
		}
		return $ret;
	}
	else
	{
		return '';
	}
}


function forumcleaner_build_forumbits(&$forum) 
{
	global $templates;
	$me = forumcleaner_info();

	$messages = get_forumaction_desc($forum['fid'],'forums');
	if (strlen($messages)) 
	{
		$subst = '';

		eval("\$subst = \"".$templates->get($me['sysname']."_forumbit")."\";");

		$forum[$me['sysname'].'_forumbit'] = $subst;
	}
}

function forumcleaner_build_threadlist()
{
	global $templates,$mybb;
	$me = forumcleaner_info();

	$messages = get_forumaction_desc($mybb->get_input('fid', 1),'threads');
	if (strlen($messages)) 
	{
		$subst = '';

		eval("\$subst = \"".$templates->get($me['sysname']."_threadlist")."\";");

		$mybb->input[$me['sysname'].'_threadlist'] = $subst;
	}
}
