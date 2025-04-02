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

declare(strict_types=1);

function get_value(string $name, int $default): int
{
    global $mybb;

    return (int)($mybb->settings[$name] ?? $default);
}

function task_forumcleaner(array &$task): array
{
    global $db, $mybb, $plugins;

    $sysname = 'forumcleaner';
    $threadlimit = get_value($sysname . '_threadlimit', 30);
    $userlimit = get_value($sysname . '_userlimit', 50);
    $awaitingdays = get_value($sysname . '_awaitingdays', 0);
    $inactivedays = get_value($sysname . '_inactivedays', 0);

    if ($mybb->settings[$sysname . '_groupids'] == -1) {
        $exceptions = -1;
    } else {
        $exceptions = [4];// except Administrators

        foreach (explode(',', $mybb->settings[$sysname . '_groupids']) as $gid) {
            $exceptions[] = (int)$gid;
        }

        $exceptions = implode(',', $exceptions);
    }

    $users = [];

    // delete awaiting activation users
    if ($awaitingdays) {
        $breakdate = TIME_NOW - $awaitingdays * 24 * 60 * 60;

        $query = $db->simple_select(
            'awaitingactivation a, LEFT JOIN ' . TABLE_PREFIX . 'users u ON (a.uid=u.uid)',
            'a.uid',
            "a.type='r' AND a.dateline<'{$breakdate}' AND u.usergroup='5'",
            ['limit' => $userlimit]
        );

        while ($uid = $db->fetch_field($query, 'uid')) {
            $users[] = (int)$uid;
        }
    } // delete awaiting activation users

    // delete inactive users
    if ($inactivedays and count($users) < $userlimit) {
        $breakdate = TIME_NOW - $inactivedays * 24 * 60 * 60;

        $query = $db->simple_select(
            'users u',
            'u.uid, u.usergroup, u.additionalgroups, u.displaygroup',
            "u.lastvisit<'{$breakdate}' AND u.uid NOT IN (
				SELECT p.uid
				FROM " . TABLE_PREFIX . 'posts p
			)',
            ['limit' => $userlimit]
        );

        while ($result = $db->fetch_array($query)) {
            if ((int)$result['displaygroup']) {
                $result['additionalgroups'] .= ',' . $result['displaygroup'];
            }

            // if not in exception list
            if (!is_member($exceptions, $result)) {
                $users[] = $result['uid'];
                if (count($users) == $userlimit) {
                    break;
                }
            }
        }
    } // delete inactive users

    if (is_object($plugins)) {
        $args = [
            'users' => &$users,
        ];
        $plugins->run_hooks('task_forumcleaner_users', $args);
    }

    // Delete and Update forum stats
    if (count($users)) {
        // Set up user handler.
        require_once MYBB_ROOT . 'inc/datahandlers/user.php';
        $userhandler = new UserDataHandler('delete');

        // Delete the pruned users
        $userhandler->delete_user(
            $users,
            0
        ); // Default prune system uses $mybb->settings['prunethreads'] here but we are going to omit it for now since 0 is the plugin default value here

        add_task_log($task, count($users) . ' users deleted');
    }

    global $moderation;

    // obtain standard functions to perform actions
    //      $moderation->delete_thread($tid);
    //      $moderation->move_thread($tid, $new_fid, 'move');
    //      $moderation->close_threads($tids)
    if (!is_object($moderation)) {
        require_once MYBB_ROOT . 'inc/class_moderation.php';
        $moderation = new Moderation();
    }

    // Get action list
    $forumactions = $db->simple_select($sysname, '*', "enabled = '1'");

    while ($action = $db->fetch_array($forumactions)) {
        if ($action['lastpost'] == 1) {
            $timecheck = " AND lastpost < '" . (TIME_NOW - $action['agesecs']) . "'";
        } else {
            $timecheck = " AND dateline < '" . (TIME_NOW - $action['agesecs']) . "'";
        }

        $forum = '';

        if ($action['fid'] != '-1') {
            $forums = array_map('intval', explode(',', $action['fid']));
            if (count($forums) == 1) {
                $forum = "fid = '" . (int)array_shift($forums) . "' AND ";
            } else {
                $forum = 'fid IN (' . implode(',', $forums) . ') AND ';
            }
        }

        // delete permanent redirects
        if ($action['action'] == 'del_redirects') {
            $db->delete_query(
                'threads',
                'tid',
                $forum . "closed LIKE 'moved|%' AND deletetime = '0' AND sticky='0'" . $timecheck
            );
        } else {
            // find open threads, complicated because 'closed' content is not always '0' for open threads
            $not_closed = '';
            if ($action['action'] == 'close') {
                $not_closed = "closed <> '1' AND closed NOT LIKE 'moved|%' AND ";
            }

            $query = $db->simple_select(
                'threads',
                'tid',
                $forum . $not_closed . "sticky='0'" . $timecheck,
                ['limit' => $threadlimit]
            );

            _dump(
                $db->num_rows($query),
                'threads',
                'tid',
                $forum . $not_closed . "sticky='0'" . $timecheck,
                ['limit' => $threadlimit]
            );

            // nothing to do. required, because $moderation->close_threads do not check number of values.
            if (!$db->num_rows($query)) {
                continue;
            }

            if ($action['action'] == 'close') {
                $tids = [];

                while ($result = $db->fetch_array($query)) {
                    $tids[] = $result['tid'];
                }

                $moderation->close_threads($tids);
            } elseif ($action['action'] == 'move' || $action['action'] == 'delete') {
                while ($result = $db->fetch_array($query)) {
                    if ($action['action'] == 'move') {
                        if (strlen($forum)) // if looking not for all forums
                        {
                            $moderation->move_thread($result['tid'], $action['tofid'], 'move');
                        }
                    } else {
                        $moderation->delete_thread($result['tid']);
                    }
                }
            }
            // else ignore
        }
    }

    add_task_log($task, 'The Forum Cleaning task successfully ran.');

    return $task;
}// function task_forumcleaner($task)