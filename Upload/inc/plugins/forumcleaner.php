<?php

/***************************************************************************
 *
 *    Forum Cleaner plugin (/inc/plugins/forumcleaner.php)
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

use function ForumCleaner\Core\addHooks;
use function ForumCleaner\Admin\pluginActivate;
use function ForumCleaner\Admin\pluginDeactivate;
use function ForumCleaner\Admin\pluginInfo;
use function ForumCleaner\Admin\pluginIsInstalled;
use function ForumCleaner\Admin\pluginUninstall;
use function ForumCleaner\Core\getTemplate;

use const ForumCleaner\ROOT;
use const ForumCleaner\SYSTEM_NAME;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

define('ForumCleaner\SYSTEM_NAME', 'forumcleaner');

define('ForumCleaner\SYSTEM_NAME_AVATARS', 'orphanavatars');

// You can uncomment the lines below to avoid storing some settings in the DB
define('ForumCleaner\SETTINGS', [
    //'key' => '',
]);

define('ForumCleaner\DEBUG', false);

define('ForumCleaner\ROOT', MYBB_ROOT . 'inc/plugins/ougc/ForumCleaner');

require_once ROOT . '/core.php';

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('ForumCleaner\Hooks\Admin');
}

// The info for this plugin.
function forumcleaner_info(): array
{
    return pluginInfo();
}

global $plugins;

// Hooks.
$plugins->add_hook('build_forumbits_forum', 'forumcleaner_build_forumbits');
$plugins->add_hook('forumdisplay_end', 'forumcleaner_build_threadlist');

// Action to take to install the plugin.
function forumcleaner_install(): void
{
    pluginActivate();
}

// Return TRUE if plugin is installed, FALSE otherwise.
function forumcleaner_is_installed(): bool
{
    return pluginIsInstalled();
}

// Action to take to activate the plugin.
function forumcleaner_activate(): void
{
    pluginActivate();
}

function forumcleaner_addtemplates(): void
{
    pluginDeactivate();
}

// Action to take to deactivate the plugin.
function forumcleaner_deactivate(): void
{
    pluginDeactivate();
}

// Action to take to uninstall the plugin.
function forumcleaner_uninstall(): void
{
    pluginUninstall();
}

function get_seconds(int $age, string $type): float
{
    $age_type_secs = [
        'hours' => 60 * 60,
        'days' => 24 * 60 * 60,
        'weeks' => 7 * 24 * 60 * 60,
        'months' => 30 * 24 * 60 * 60,
    ];

    return $age * $age_type_secs[$type];
}

// simple sort key for forums
function get_sort_key(int $fid): string
{
    if ($fid) {
        global $forum_cache;

        return get_sort_key((int)$forum_cache[$fid]['pid']) . sprintf('%04d', $forum_cache[$fid]['disporder']);
    } else {
        return '';
    }
}

// forms message to user with Forum Action description
function get_forumaction_desc(int $fid, string $where): string
{
    static $forumaction_cache;
    global $db, $lang, $forum_cache, $mybb;

    $lang->load(SYSTEM_NAME);

    // keep information cached
    if (!is_array($forumaction_cache)) {
        $query = $db->simple_select(
            SYSTEM_NAME,
            '*',
            'enabled = 1 AND (forumslist_display = 1 OR threadslist_display = 1)'
        );

        $forumaction_cache = [];
        while ($fa = $db->fetch_array($query)) {
            $forums = $fa['fid'];

            if ($forums == '-1') {
                continue;
            }
            $forums = array_map('intval', explode(',', $forums));
            foreach ($forums as $f) {
                if ($fa['forumslist_display'] == 1) {
                    $forumaction_cache['forums'][$f][] = $fa;
                }
                if ($fa['threadslist_display'] == 1) {
                    $forumaction_cache['threads'][$f][] = $fa;
                }
            }
        }
    }

    $agetypes = [
        'hours' => $lang->forumcleaner_topics_hours,
        'days' => $lang->forumcleaner_topics_days,
        'weeks' => $lang->forumcleaner_topics_weeks,
        'months' => $lang->forumcleaner_topics_months,
    ];

    $forumactions = [
        'close' => $lang->forumcleaner_topics_closed,
        'delete' => $lang->forumcleaner_topics_deleted,
        'move' => $lang->forumcleaner_topics_moved,
    ];

    $lastpost = [
        0 => $lang->forumcleaner_topics_firstpost,
        1 => $lang->forumcleaner_topics_lastpost,
    ];

    if (count($forumaction_cache) && isset($forumaction_cache[$where]) &&
        count($forumaction_cache[$where]) &&
        array_key_exists($fid, $forumaction_cache[$where])) {
        $actions = [];
        $actions = $forumaction_cache[$where][$fid];

        $ret = '';
        $comma = '';
        foreach ($actions as $fa) {
            $ret .= $comma;

            $forumActions = explode(',', $fa['action']);

            if (in_array('delete', $forumActions)) {
                $ret .= $lang->sprintf(
                    $forumactions['delete'],
                    $lastpost[$fa['lastpost']],
                    $fa['age'],
                    $agetypes[$fa['agetype']]
                );
            } elseif (in_array('close', $forumActions) && in_array('move', $forumActions)) {
                $ret .= $lang->sprintf(
                    $lang->forumcleaner_topics_closed_moved,
                    $lastpost[$fa['lastpost']],
                    $fa['age'],
                    $agetypes[$fa['agetype']]
                );
            } elseif (in_array('close', $forumActions)) {
                if (!is_array($forum_cache)) {
                    cache_forums();
                }

                $ret .= $lang->sprintf(
                    $forumactions['close'],
                    htmlspecialchars_uni(strip_tags($forum_cache[$fa['tofid']]['name'])),
                    $lastpost[$fa['lastpost']],
                    $fa['age'],
                    $agetypes[$fa['agetype']]
                );
            } elseif (in_array('move', $forumActions)) {
                if (!is_array($forum_cache)) {
                    cache_forums();
                }

                $ret .= $lang->sprintf(
                    $forumactions['move'],
                    htmlspecialchars_uni(strip_tags($forum_cache[$fa['tofid']]['name'])),
                    $lastpost[$fa['lastpost']],
                    $fa['age'],
                    $agetypes[$fa['agetype']]
                );
            } else {
                continue;
            }

            $threadLastEdit = (int)$fa['threadLastEdit'];

            if ($threadLastEdit) {
                $ret .= $lang->sprintf(
                    $lang->forumcleaner_topics_last_edit,
                    $threadLastEdit,
                    $agetypes[$fa['threadLastEditType']]
                );
            }

            if ((int)$fa['hasPrefixID'] !== -1) {
                $prefixesCache = $mybb->cache->read('threadprefixes') ?? [];

                $hasPrefixIDs = array_map('intval', explode(',', $fa['hasPrefixID']));

                $prefixList = [];

                if (in_array(0, $hasPrefixIDs)) {
                    $prefixList[] = $lang->forumcleaner_topics_prefix_none;
                }

                foreach ($hasPrefixIDs as $prefixID) {
                    if ($prefixID !== 0) {
                        $prefixList[] = $prefixesCache[$prefixID]['displaystyle'];
                    }
                }

                $ret .= $lang->sprintf(
                    $lang->forumcleaner_topics_prefix,
                    implode($lang->comma, $prefixList)
                );
            }

            $comma = '<br />';
        }
        return $ret;
    } else {
        return '';
    }
}

function forumcleaner_build_forumbits(array &$forum): void
{
    global $templates;

    $forum['ForumCleanerMessages'] = '';

    $messages = get_forumaction_desc((int)$forum['fid'], 'forums');
    if (strlen($messages)) {
        $subst = eval(getTemplate('forumbit'));

        $forum['ForumCleanerMessages'] = $subst;
    }
}

function forumcleaner_build_threadlist(): void
{
    global $templates, $mybb;
    global $foruminfo;

    $foruminfo['ForumCleanerMessages'] = '';

    $messages = get_forumaction_desc($mybb->get_input('fid', MyBB::INPUT_INT), 'threads');
    if (strlen($messages)) {
        $subst = eval(getTemplate('threadlist'));

        $foruminfo['ForumCleanerMessages'] = $subst;
    }
}