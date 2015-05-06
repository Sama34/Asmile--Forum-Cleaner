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
 * This file is MYBB_ROOT/inc/tasks/forumcleaner.php
 * There should also be a files 
 *   MYBB_ROOT/inc/plugins/forumcleaner.php
 *   MYBB_ROOT/inc/languages/english/admin/forumcleaner.lang.php
 *   MYBB_ROOT/inc/languages/english/forumcleaner.lang.php
 */



function get_value($name,$default)
{
	global $mybb;
	$value = (int)$mybb->settings[$name];
	return $value ? $value : $default;
}


function delete_user($uid, $avatars = array()) 
{

	global $db,$mybb;

	$sql = "";
	if (is_array($uid))
	{
		$ulist = implode(",",$uid);
		$sql = "uid IN ( ".$ulist." ) ";
		$addsql = "OR adduid IN ( ".$ulist." ) ";
		$fldsql = "ufid IN ( ".$ulist." )";
        if ($mybb->version_code < 1600) 
        {
            $modsql = "uid IN ( ".$ulist. " )";
        }
        else
        {
		    $modsql = "id IN ( ".$ulist." ) and isgroup = '0'";
        }
	}
	else
	{
		$sql = "uid = '{$uid}' ";
		$addsql = "OR adduid = '{$uid}' ";
		$fldsql = "ufid = {$uid} )";
        if ($mybb->version_code < 1600)
        {
            $modsql = "uid = {$uid}";
        }
        else
        {
		    $modsql = "id = {$uid} and isgroup = '0'";
        }
	}

	$prefix = "";
	if ( defined('IN_ADMINCP') )
	{
		$prefix = "../";
	}

	if (!count($avatars)) 
	{
		$query = $db->simple_select("users","avatar",$sql." AND avatartype='upload'");
		while ($avatar = $db->fetch_field($query,'avatar'))
		{
            $avatar = preg_replace("#\\?.+#","",$avatar);
			unlink($prefix.$avatar);
		}
	}
	else
	{
		foreach ( $avatars as $avatar )
		{
            $avatar = preg_replace("#\\?.+#","",$avatar);
			unlink($prefix.$avatar);
		}
	}

	// Delete the user
	$db->update_query("posts", array('uid' => 0), $sql);
	$db->delete_query("userfields", $fldsql);
	$db->delete_query("privatemessages", $sql);
	$db->delete_query("events", $sql);
	$db->delete_query("moderators", $modsql);
	$db->delete_query("forumsubscriptions", $sql);
	$db->delete_query("threadsubscriptions", $sql);
	$db->delete_query("sessions", $sql);
	$db->delete_query("banned", $sql);
	$db->delete_query("threadratings", $sql);
	$db->delete_query("users", $sql);
	$db->delete_query("joinrequests", $sql);
	$db->delete_query("warnings", $sql);
	$db->delete_query("reputation", $sql.$addsql);
	$db->delete_query("awaitingactivation", $sql);
}

function task_forumcleaner($task) 
{
	global $db,$mybb, $plugins;

	$sysname = 'forumcleaner';
	$threadlimit = get_value($sysname.'_threadlimit', 30);
	$userlimit = get_value($sysname.'_userlimit',50);
	$awaitingdays = get_value($sysname.'_awaitingdays',0);
	$inactivedays = get_value($sysname.'_inactivedays',0);

	$exceptions = array(4);// except Administrators

	if($mybb->settings[$sysname.'_groupids'] == -1)
	{
		$exceptions = -1;
	}
	elseif($mybb->settings[$sysname.'_groupids'] != '')
	{
		foreach (explode(',',$mybb->settings[$sysname.'_groupids']) as $gid) 
		{
			if ($gid = (int)$gid) 
			{
				array_push($exceptions,$gid);   
			}
		}
	}

	$users = array();
	$avatars = array();

	// delete awaiting activation users
	if ($awaitingdays) 
	{
		$breakdate = TIME_NOW - $awaitingdays * 24 * 60 * 60;

		$query = $db->query("
			SELECT a.uid AS uid, u.avatar AS avatar, u.avatartype AS avatartype
			FROM ".TABLE_PREFIX."awaitingactivation a, ".TABLE_PREFIX."users u
			WHERE a.type = 'r'
			AND a.dateline < {$breakdate}
			AND a.uid = u.uid
			AND u.usergroup = '5'
			LIMIT {$userlimit}
		");   

		while ($result = $db->fetch_array($query))
		{
			array_push($users, $result['uid']);
			if ( $result['avatartype'] == 'upload' )
			{
				array_push($avatars,$result['avatar']);
			}
		}
	} // delete awaiting activation users

	// delete inactive users
	if ($inactivedays and count($users) < $userlimit) 
	{
		$breakdate = TIME_NOW - $inactivedays * 24 * 60 * 60;

		$query = $db->query("
			SELECT 
				u.uid AS uid, 
				u.usergroup AS usergroup,
				u.additionalgroups AS additionalgroups,
				u.displaygroup AS displaygroup,
				u.avatar AS avatar,
				u.avatartype AS avatartype
			FROM ".TABLE_PREFIX."users u
			WHERE u.lastvisit < '{$breakdate}'
			AND u.uid NOT
			IN (
				SELECT p.uid
				FROM ".TABLE_PREFIX."posts p
			) 
		");

		while ($result = $db->fetch_array($query))
		{
			// build user groups list
			$ugroups = array($result['usergroup']);

			if ((int)$result['displaygroup'])
			{
				array_push($ugroups,$result['displaygroup']);
			}

			foreach (explode(',',$result['additionalgroups']) as $ug) 
			{
				if ((int)$ug)
				{
					array_push($ugroups,$ug);
				}
			}

			// if not in exception list
			if (!count(array_intersect($exceptions,$ugroups)) && $exceptions !== -1)
			{
				array_push($users, $result['uid']);
				if ( $result['avatartype'] == 'upload' )
				{
					array_push($avatars,$result['avatar']);
				}
				if (count($users) == $userlimit)
				{
					break;
				}
			}
		}
	} // delete inactive users



	// Delete and Update forum stats
	if (count($users))
	{
		if(is_object($plugins))
		{
			$args = array(
				'users'	=> &$users,
			);
			$plugins->run_hooks('task_forumcleaner_users', $args);
		}
		delete_user($users,$avatars);
		update_stats(array('numusers' => '-'.count($users)));
		add_task_log($task, count($users). ' users deleted');
	}

	global $moderation;
    
	// obtain standard functions to perform actions
	//      $moderation->delete_thread($tid);
	//      $moderation->move_thread($tid, $new_fid, 'move');
	//      $moderation->close_threads($tids) 
	if(!is_object($moderation))
	{
		require_once MYBB_ROOT."inc/class_moderation.php";
		$moderation = new Moderation;
	}
                  
	// Get action list
	$forumactions = $db->simple_select($sysname,'*',"enabled = '1'");

	while ($action = $db->fetch_array($forumactions)) {

		if ( $action['lastpost'] == 1 )
		{
			$timecheck = "lastpost < '". (TIME_NOW - $action['agesecs']) . "'";
		}
		else
		{
			$timecheck = "dateline < '". (TIME_NOW - $action['agesecs']) . "'";
		}

		$forum = "";

		if ($action['fid'] != '-1') 
		{
			$forums = array_map('intval',explode(',',$action['fid']));
			if (count($forums) == 1)
			{
				$forum = "fid = '".(int)array_shift($forums)."' AND ";
			}
			else
			{
				$forum = "fid IN (".implode(',',$forums).") AND ";
			}
		}

		// delete permanent redirects
		if ($action['action'] == 'del_redirects') 
		{ 
			$db->delete_query('threads',
				$forum .
				"closed LIKE 'moved|%' AND " .
				"deletetime = '0' AND " .
				"sticky = '0' AND " .
				$timecheck // no limit required, it's simple delete.
			);
		}
		else 
		{
			// find open threads, complicated because 'closed' content is not always '0' for open threads
			$not_closed = "";
			if ( $action['action']=='close' )
			{
				$not_closed = "closed <> '1' AND closed NOT LIKE 'moved|%' AND";
			}

			$query = $db->simple_select('threads', 'tid', 
				$forum . " 
				{$not_closed} 
				sticky = 0 AND
				{$timecheck} 
				LIMIT {$threadlimit}" 
			);

			// nothing to do. required, because $moderation->close_threads do not check number of values.
			if (!$db->num_rows($query))
			{
				continue;
			}

			if ($action['action'] == 'close')
			{
				$tids = array();

				while ($result = $db->fetch_array($query))
				{
					array_push($tids,$result['tid']);
				}

				$moderation->close_threads($tids);
			}
			elseif ( $action['action'] == 'move' or $action['action'] == 'delete' )
			{
				while ($result = $db->fetch_array($query))
				{
					if ( $action['action'] == 'move' ) 
					{
						if (strlen($forum)) // if looking not for all forums
						{
							$moderation->move_thread($result['tid'],$action['tofid'],'move');
						}
					}
					else
					{
						$moderation->delete_thread($result['tid']);
					}
				}
			}
			// else ignore
		}
	}

	add_task_log($task, 'The Forum Cleaning task successfully ran.');

}// function task_forumcleaner($task) 