<?php

/***************************************************************************
 *
 *    Forum Cleaner plugin (/inc/plugins/ougc/ForumCleaner/core.php)
 *    Author: Andriy Smilyanets
 *    Maintainer: Omar Gonzalez
 *
 *    A MyBB plugin to help Administrators keep things clean.
 *    This plugin based on heavily rewritten AutoExpunge plugin (Created by The forum.kde.org team) and lots of Copy&Paste's from Admin CP tools
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace ForumCleaner\Core;

use const ForumCleaner\DEBUG;
use const ForumCleaner\ROOT;
use const ForumCleaner\SETTINGS;
use const ForumCleaner\SYSTEM_NAME;

function addHooks(string $namespace): void
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            $isNegative = substr($hookName, -3, 1) === '_';

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            if ($isNegative) {
                $plugins->add_hook($hookName, $callable, -$priority);
            } else {
                $plugins->add_hook($hookName, $callable, $priority);
            }
        }
    }
}

function loadLanguage(bool $isDataHandler = false): void
{
    global $lang;

    if (!isset($lang->forumcleaner)) {
        $lang->load('forumcleaner', $isDataHandler);
    }
}

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return SYSTEM_NAME . "{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return SETTINGS[$settingKey] ?? (
        $mybb->settings[SYSTEM_NAME . '_' . $settingKey] ?? false
    );
}

function executeTask(array &$taskData = []): void
{
    global $db, $plugins;

    $threadlimit = (int)(getSetting('threadlimit') ?? 30);
    $userlimit = (int)(getSetting('userlimit') ?? 50);
    $awaitingdays = (int)(getSetting('awaitingdays') ?? 0);
    $inactivedays = (int)(getSetting('inactivedays') ?? 0);

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
    if ($inactivedays && count($users) < $userlimit) {
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
            if (!is_member(getSetting('groupids'), $result)) {
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

        if (function_exists('add_task_log')) {
            add_task_log($taskData, count($users) . ' users deleted');
        }
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

    $existingForumIDs = array_column(cache_forums(), 'fid');

    // Get action list
    $forumactions = $db->simple_select(SYSTEM_NAME, '*', "enabled = '1'");

    while ($action = $db->fetch_array($forumactions)) {
        $whereClauses = [];

        $whereClauses[] = "t.sticky='0'";

        if ($action['lastpost'] == 1) {
            $whereClauses[] = "t.lastpost < '" . (TIME_NOW - $action['agesecs']) . "'";
        } else {
            $whereClauses[] = "t.dateline < '" . (TIME_NOW - $action['agesecs']) . "'";
        }

        if ($action['fid'] != '-1') {
            $forums = implode("','", array_map('intval', explode(',', $action['fid'])));

            $whereClauses[] = "t.fid IN ('{$forums}')";
        }

        $hasPrefixID = (int)$action['hasPrefixID'];

        if ($hasPrefixID !== -1) {
            $whereClauses[] = "t.prefix='{$hasPrefixID}'";
        }

        $threadLastEdit = get_seconds((int)$action['threadLastEdit'], $action['threadLastEditType']);

        if ($threadLastEdit) {
            $threadLastEdit = TIME_NOW - $threadLastEdit;

            $whereClauses[] = "((p.edittime='0' AND t.dateline<'{$threadLastEdit}') OR (p.edittime!='0' AND p.edittime<'{$threadLastEdit}'))";
        }

        // delete permanent redirects
        if ($action['action'] == 'del_redirects') {
            $whereClauses[] = "t.closed LIKE 'moved|%'";

            $whereClauses[] = "t.deletetime = '0'";

            $db->delete_query(
                "threads t LEFT JOIN {$db->table_prefix}posts p ON (p.pid=t.firstpost)",
                't.tid',
                implode(' AND ', $whereClauses)
            );
        } else {
            // find open threads, complicated because 'closed' content is not always '0' for open threads
            if ($action['action'] == 'close') {
                $whereClauses[] = "t.closed <> '1'";

                $whereClauses[] = "t.closed NOT LIKE 'moved|%'";
            }

            $query = $db->simple_select(
                "threads t LEFT JOIN {$db->table_prefix}posts p ON (p.pid=t.firstpost)",
                't.tid, p.edittime',
                implode(' AND ', $whereClauses),
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
                if ($action['action'] == 'move' && !in_array($action['tofid'], $existingForumIDs)) {
                    continue;
                }

                while ($result = $db->fetch_array($query)) {
                    if ($action['action'] == 'move') {
                        $moderation->move_thread($result['tid'], $action['tofid'], 'move');
                    } else {
                        $moderation->delete_thread($result['tid']);
                    }
                }
            }
            // else ignore
        }
    }

    if (function_exists('add_task_log')) {
        add_task_log($taskData, 'The Forum Cleaning task successfully ran.');
    }
}