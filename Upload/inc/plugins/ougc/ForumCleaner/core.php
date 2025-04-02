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

use Moderation;
use UserDataHandler;

use const ForumCleaner\DEBUG;
use const ForumCleaner\ROOT;
use const ForumCleaner\SETTINGS;
use const ForumCleaner\SYSTEM_NAME;

const COMPARISON_TYPE_GREATER_THAN = '>';

const COMPARISON_TYPE_GREATER_THAN_OR_EQUAL = '>=';

const COMPARISON_TYPE_EQUAL = '=';

const COMPARISON_TYPE_NOT_EQUAL = '!=';

const COMPARISON_TYPE_LESS_THAN_OR_EQUAL = '<=';

const COMPARISON_TYPE_LESS_THAN = '<';

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

    // obtain standard functions to perform actions
    //      $moderation->delete_thread($tid);
    //      $moderation->move_thread($tid, $new_fid, 'move');
    //      $moderation->close_threads($threadIDs)

    require_once MYBB_ROOT . 'inc/class_moderation.php';

    $moderation = new Moderation();

    $existingForumIDs = array_column(cache_forums(), 'fid');

    // Get action list
    $forumactions = $db->simple_select(
        SYSTEM_NAME,
        'xid, fid, enabled, threadslist_display, forumslist_display, action, age, agetype, agesecs, lastpost, threadLastEdit, threadLastEditType, hasPrefixID, softDeleteThreads, tofid, hasReplies, hasRepliesType',
        "enabled = '1'"
    );

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

        $forumActions = explode(',', $action['action']);

        // delete permanent redirects
        if (in_array('del_redirects', $forumActions)) {
            $db->delete_query(
                'threads t',
                implode(' AND ', array_merge($whereClauses, ["t.closed LIKE 'moved|%'", "t.deletetime = '0'"]))
            );
        }

        $hasReplies = (int)$action['hasReplies'];

        if ($hasReplies) {
            $whereClauses[] = "t.replies{$action['hasRepliesType']}'{$hasReplies}'";
        }

        $threadLastEdit = get_seconds((int)$action['threadLastEdit'], $action['threadLastEditType']);

        if ($threadLastEdit) {
            $threadLastEdit = TIME_NOW - $threadLastEdit;

            $whereClauses[] = "((p.edittime='0' AND t.dateline<'{$threadLastEdit}') OR (p.edittime!='0' AND p.edittime<'{$threadLastEdit}'))";
        }

        if ((int)$action['hasPrefixID'] !== -1) {
            $hasPrefixID = implode("','", array_map('intval', explode(',', $action['hasPrefixID'])));

            $whereClauses[] = "t.prefix IN ('{$hasPrefixID}')";
        }

        // find open threads, complicated because 'closed' content is not always '0' for open threads
        if (in_array('close', $forumActions)) {
            $query = $db->simple_select(
                "threads t LEFT JOIN {$db->table_prefix}posts p ON (p.pid=t.firstpost)",
                't.tid, p.edittime',
                implode(' AND ', array_merge($whereClauses, ["t.closed!='1'", "t.closed NOT LIKE 'moved|%'"])),
                ['limit' => $threadlimit]
            );

            if ($db->num_rows($query)) {
                $threadIDs = [];

                while ($threadData = $db->fetch_array($query)) {
                    $threadIDs = (int)$threadData['tid'];
                }

                $moderation->close_threads($threadIDs);
            }
        }

        $threadIDs = [];

        $query = $db->simple_select(
            "threads t LEFT JOIN {$db->table_prefix}posts p ON (p.pid=t.firstpost)",
            't.tid, p.edittime',
            implode(' AND ', $whereClauses),
            ['limit' => $threadlimit]
        );

        if ($db->num_rows($query)) {
            while ($threadData = $db->fetch_array($query)) {
                $threadIDs[] = (int)$threadData['tid'];
            }
        }

        // nothing to do. required, because $moderation->close_threads do not check number of values.
        if (!$threadIDs) {
            continue;
        }

        if (in_array('delete', $forumActions)) {
            if (empty($action['softDeleteThreads'])) {
                foreach ($threadIDs as $threadID) {
                    $moderation->delete_thread($threadID);
                }

                continue;
            }

            $moderation->soft_delete_threads($threadIDs);
        }

        if (in_array('move', $forumActions) && in_array($action['tofid'], $existingForumIDs)) {
            foreach ($threadIDs as $threadID) {
                $moderation->move_thread($threadID, $action['tofid'], 'move');
            }
        }
    }

    if (function_exists('add_task_log')) {
        add_task_log($taskData, 'The Forum Cleaning task successfully ran.');
    }
}