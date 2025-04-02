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

declare(strict_types=1);

// Don't allow direct initialization.
use function ForumCleaner\Admin\pluginActivate;
use function ForumCleaner\Admin\pluginDeactivate;
use function ForumCleaner\Admin\pluginInfo;
use function ForumCleaner\Admin\pluginIsInstalled;
use function ForumCleaner\Admin\pluginUninstall;

use const ForumCleaner\DEBUG;
use const ForumCleaner\ROOT;
use const ForumCleaner\SYSTEM_NAME;

if (!defined('IN_MYBB')) {
    die('Nope.');
}

define('ForumCleaner\SYSTEM_NAME', 'forumcleaner');

define('ForumCleaner\ROOT', MYBB_ROOT . 'inc/plugins/ougc/ForumCleaner');

define('ForumCleaner\DEBUG', false);

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
}

// The info for this plugin.
function forumcleaner_info(): array
{
    return pluginInfo();
}

global $plugins;

// Hooks.
$plugins->add_hook('admin_forum_menu', 'forumcleaner_admin_forum_menu');
$plugins->add_hook('admin_forum_action_handler', 'forumcleaner_admin_forum_action_handler');
$plugins->add_hook('admin_forum_permissions', 'forumcleaner_admin_forum_permissions');

$plugins->add_hook('admin_user_menu', 'forumcleaner_admin_user_menu');
$plugins->add_hook('admin_user_action_handler', 'forumcleaner_admin_user_action_handler');
$plugins->add_hook('admin_user_permissions', 'forumcleaner_admin_user_permissions');

$plugins->add_hook('admin_load', 'forumcleaner_admin_load');

$plugins->add_hook('build_forumbits_forum', 'forumcleaner_build_forumbits');
$plugins->add_hook('forumdisplay_threadlist', 'forumcleaner_build_threadlist');

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

// Add a menu item in the AdminCP.
function forumcleaner_admin_forum_menu(array &$sub_menu): array
{
    $me = forumcleaner_info();
    $sub_menu[] = [
        'id' => $me['sysname'],
        'title' => $me['name'],
        'link' => $me['cfglink'],
    ];

    return $sub_menu;
}

// The file to use for configuring the plugin.
function forumcleaner_admin_forum_action_handler(array &$actions): array
{
    $me = forumcleaner_info();
    $actions[$me['sysname']] = [
        'active' => $me['sysname'],
        'file' => 'settings.php'
    ];

    return $actions;
}

// The text for the entry in the admin permissions page.
function forumcleaner_admin_forum_permissions(array &$admin_permissions): array
{
    global $lang;

    $me = forumcleaner_info();

    $admin_permissions[$me['sysname']] = $lang->sprintf($lang->forumcleaner_can_manage, $me['name']);

    return $admin_permissions;
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

function forumcleaner_validate_action(array &$action): array
{
    global $forum_cache, $lang, $db, $mybb;

    $me = forumcleaner_info();

    $errors = [];

    if ($action['age'] == 0) {
        $errors['invalid_age'] = $lang->forumcleaner_invalid_age;
    }

    if (!in_array($action['agetype'], ['hours', 'days', 'weeks', 'months'])) {
        $errors['invalid_agetype'] = $lang->forumcleaner_invalid_agetype;
    }

    $action['agesecs'] = get_seconds((int)$action['age'], $action['agetype']);

    if (!in_array($action['action'], ['delete', 'close', 'move', 'del_redirects'])) {
        $errors['invalid_action'] = $lang->forumcleaner_invalid_action;
    }

    $forums_verify = explode(',', $action['fid']);

    if ($action['fid'] == '-1') {
        // All forums IS allowed for delete,close,del_redirects
        if ($action['action'] == 'move') {
            $errors['all_is_not_allowed'] = $lang->forumcleaner_all_not_allowed;
            return $errors;
        }
    } else {
        foreach ($forums_verify as $fid_verify) {
            if (!array_key_exists($fid_verify, $forum_cache)) {
                $errors['invalid_forum_id'] = $lang->forumcleaner_invalid_forum_id;
            } elseif ($forum_cache[$fid_verify]['type'] != 'f') {
                $errors['invalid_forum_id'] = $lang->forumcleaner_source_category_not_allowed;
            }
        }
    }

    if ($action['action'] == 'del_redirects') {
        // doesn't apply to del_redirects
        $action['forumslist_display'] = 0;
        $action['threadslist_display'] = 0;
    }

    if ($action['lastpost'] != 1) {
        // Lastpost should be 0 or 1; default to 0, don't trigger an error.
        $action['lastpost'] = 0;
    }

    $prefixesIDs = array_column($mybb->cache->read('threadprefixes'), 'pid');

    if ($action['hasPrefixID'] && $action['hasPrefixID'] !== -1 && !in_array($action['hasPrefixID'], $prefixesIDs)) {
        $errors['invalid_prefix'] = $lang->ForumCleanerActionInvalidPrefix;
    }

    if (!in_array($action['threadLastEditType'], ['hours', 'days', 'weeks', 'months'])) {
        $errors['invalid_thread_last_edit_type'] = $lang->ForumCleanerActionInvalidThreadLastEditType;
    }

    if ($action['action'] == 'move') {
        if (!array_key_exists($action['tofid'], $forum_cache)) {
            $errors['invalid_target_forum_id'] = $lang->forumcleaner_invalid_target_forum_id;
        } elseif ($forum_cache[$action['tofid']]['type'] != 'f') {
            $errors['invalid_target_forum_id'] = $lang->forumcleaner_target_category_not_allowed;
        } elseif (in_array($action['tofid'], $forums_verify)) {
            $errors['target_selected'] = $lang->forumcleaner_target_selected;
        }
    }

    return $errors;
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

// to sort action list
function actions_cmp(array $a, array $b): int
{
    $cmp = strcmp($a['sort_key'], $b['sort_key']);
    if ($cmp != 0) {
        return $cmp;
    }
    if ($a['agesecs'] < $b['agesecs']) {
        return -1;
    }
    if ($a['agesecs'] > $b['agesecs']) {
        return 1;
    }
    return 0;
}

// Configuration page.
function forumcleaner_admin_load(): void
{
    if (DEBUG) {
        forumcleaner_task();
    }

    global $page;

    $me = forumcleaner_info();

    if ($page->active_action == $me['sysname']) {
        forumcleaner_process_forumactions();
    } elseif ($page->active_action == $me['avasysname']) {
        forumcleaner_process_orphanavatars();
    }
} // function forumcleaner_admin_load() 

function forumcleaner_process_forumactions(): void
{
    global $db, $page, $mybb, $forum_cache, $lang;

    $me = forumcleaner_info();

    if (!is_array($forum_cache)) {
        cache_forums();
    }

    $action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'config');

    // silently ignore unknown actions
    if (!in_array($action, ['config', 'add', 'addtask', 'edit', 'enable', 'disable', 'delete'])) {
        admin_redirect($me['cfglink']);
    }

    $page->add_breadcrumb_item($me['name']);
    $page->output_header($me['name']);

    // Warnings.
    $result = $db->simple_select('tasks', 'tid, enabled, file', "file = '{$me['sysname']}'");
    $task = $db->fetch_array($result);
    if (!file_exists(MYBB_ROOT . "inc/tasks/{$task['file']}.php")) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_task_file,
                $me['name'],
                '<code>inc/tasks/' . $me['sysname'] . '.php</code>'
            )
        );
    }
    if (!$db->num_rows($result)) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_no_task_added,
                $me['name'],
                '<code>inc/tasks/' . $me['sysname'] . '.php</code>',
                "{$me['cfglink']}&amp;action=addtask"
            )
        );
    }
    if (!$task['enabled']) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_task_disabled,
                $me['name'],
                "index.php?module=tools/tasks&amp;action=enable&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}"
            )
        );
    }

    $xid = '-1';

    if ($mybb->get_input('xid', MyBB::INPUT_INT) > 0) {
        $xid = $mybb->get_input('xid', MyBB::INPUT_INT);
    }

    $db_array = [];

    // silently ignore edit action without xid provided or non-exist xid
    if ($action == 'edit') {
        if ($xid < 0) {
            $action = 'config';
        } else {
            $result = $db->simple_select($me['sysname'], '*', "xid = '{$xid}'");
            if ($db->num_rows($result)) {
                $db_array = $db->fetch_array($result);
            } else {
                $action = 'config';
            }
        }
    }

    if ($action == 'addtask') {
        if (forumcleaner_add_task()) {
            flash_message($lang->sprintf($lang->forumcleaner_task_added, $me['name']), 'success');
        } else {
            flash_message($lang->sprintf($lang->forumcleaner_task_exists, $me['name']), 'success');
        }
        admin_redirect($me['cfglink']);
    }

    if ($action == 'delete') {
        if ($xid >= 0) {
            $result = $db->simple_select($me['sysname'], '*', "xid = '$xid'");
            if ($expunge = $db->fetch_array($result)) {
                log_admin_action(['xid' => $xid, 'fid' => $expunge['fid']]);
                $db->delete_query($me['sysname'], "xid = '{$xid}'", 1);
            }
            flash_message($lang->forumcleaner_action_deleted, 'success');
        }
        admin_redirect($me['cfglink']);
    }

    if ($action == 'disable' || $action == 'enable') {
        if ($xid >= 0) {
            $find = 1;
            $update = 0;
            if ($action == 'enable') {
                $find = 0;
                $update = 1;
            }

            $result = $db->simple_select($me['sysname'], '*', "xid = '$xid' AND enabled = {$find}");
            if ($expunge = $db->fetch_array($result)) {
                log_admin_action(['xid' => $xid, 'fid' => $expunge['fid']]);
                $db->update_query($me['sysname'], ['enabled' => $update], "xid = '{$xid}'");
            }
        }
        flash_message(
            ($action == 'disable' ? $lang->forumcleaner_action_disabled : $lang->forumcleaner_action_enabled),
            'success'
        );
        admin_redirect($me['cfglink']);
    }

    $forumactions = [
        'close' => $lang->forumcleaner_close_threads,
        'delete' => $lang->forumcleaner_delete_threads,
        'move' => $lang->forumcleaner_move_threads,
        'del_redirects' => $lang->forumcleaner_delete_redirects,
    ];

    $errors = [];
    $update_array = [];
    $forum_checked['all'] = '';
    $forum_checked['custom'] = '';

    // Form received.
    if ($mybb->request_method == 'post') {
        if ($xid >= 0) {
            $update_array['xid'] = $xid;
            $action = 'edit';
        } else {
            $action = 'add';
        }
        if ($mybb->get_input('forum_type') == 'custom') {
            if (count($mybb->get_input('forum_1_forums', MyBB::INPUT_ARRAY)) < 1) {
                $errors[] = $lang->forumcleaner_no_forum_selected;
            }
            $forum_checked['custom'] = "checked=\"checked\"";

            if ($mybb->get_input('forum_1_forums', MyBB::INPUT_ARRAY)) {
                $checked = [];
                foreach ($mybb->get_input('forum_1_forums', MyBB::INPUT_ARRAY) as $fid) {
                    $checked[] = (int)$fid;
                }
                $update_array['fid'] = implode(',', $checked);
            }
        } else {
            $forum_checked['all'] = "checked=\"checked\"";
            $mybb->input['forum_1_forums'] = '';
            $update_array['fid'] = '-1';
        }

        $update_array['age'] = $mybb->get_input('age', MyBB::INPUT_INT);
        $update_array['agetype'] = $mybb->get_input('agetype');
        $update_array['action'] = $mybb->input['forumaction'];
        $update_array['lastpost'] = $mybb->get_input('lastpost', MyBB::INPUT_INT);
        $update_array['threadLastEdit'] = $mybb->get_input('threadLastEdit', MyBB::INPUT_INT);
        $update_array['threadLastEditType'] = $mybb->get_input('threadLastEditType');
        $update_array['hasPrefixID'] = $mybb->get_input('hasPrefixID', MyBB::INPUT_INT);
        $update_array['forumslist_display'] = $mybb->get_input('forumslist_display', MyBB::INPUT_INT);
        $update_array['threadslist_display'] = $mybb->get_input('threadslist_display', MyBB::INPUT_INT);

        if ($update_array['action'] == 'move') {
            $update_array['tofid'] = $mybb->get_input('tofid', MyBB::INPUT_INT);
        }

        if (!count($errors)) {
            $errors = forumcleaner_validate_action($update_array);
        }

        if (count($errors) == 0) {
            // update or insert new action
            if ($xid < 0) {
                // insert
                $db->insert_query($me['sysname'], $update_array);
                $mybb->input['action'] = 'add';
                log_admin_action(['xid' => $db->insert_id(), 'fid' => $update_array['fid']]);
            } else {
                // update
                unset($update_array['xid']);
                $db->update_query($me['sysname'], $update_array, "xid = '$xid'");
                $mybb->input['action'] = 'update';
                log_admin_action(['xid' => $xid, 'fid' => $update_array['fid']]);
            }

            flash_message($lang->sprintf($lang->forumcleaner_rules_updated, $me['name']), 'success');
            admin_redirect($me['cfglink']);
        }
    }

    $navtabs = [];
    $navtabs['config'] = [
        'title' => $lang->forumcleaner_configuration,
        'link' => $me['cfglink'],
        'description' => $lang->forumcleaner_configuration_desc,
    ];

    if ($action == 'edit') {
        $navtabs['edit'] = [
            'title' => $lang->forumcleaner_edit_forum_action,
            'link' => "{$me['cfglink']}&amp;action=edit&amp;xid={$xid}",
            'description' => $lang->forumcleaner_edit_forum_action_desc,
        ];

        if (!count($update_array)) {
            $update_array = $db_array;
            if ($update_array['fid'] == '-1') {
                $forum_checked['all'] = "checked=\"checked\"";
                $forum_checked['custom'] = '';
                $mybb->input['forum_type'] = 'all';
            } else {
                $forum_checked['all'] = '';
                $forum_checked['custom'] = "checked=\"checked\"";
                $mybb->input['forum_type'] = 'custom';
                $mybb->input['forum_1_forums'] = explode(',', $update_array['fid']);
            }
        }
    } else {
        $navtabs['add'] = [
            'title' => $lang->forumcleaner_add_forum_action,
            'link' => "{$me['cfglink']}&amp;action=add",
            'description' => $lang->forumcleaner_add_forum_action_desc,
        ];
    }

    $page->output_nav_tabs($navtabs, $action);

    if ($action == 'add' || $action == 'edit') {
        // Create form.
        $form = new Form("{$me['cfglink']}&amp;action={$action}", 'post');

        if ($action == 'edit') {
            echo $form->generate_hidden_field('xid', $xid);
        }

        if (count($errors)) {
            $page->output_inline_error($errors);
        }

        if (count($update_array)) {
            $update_array['forumaction'] = $update_array['action'];
        } else {
            // clean add
            $update_array = [
                'fid' => '-1',
                'tofid' => -1,
                'forumaction' => 'close',
                'age' => 1,
                'agetype' => 'days',
                'lastpost' => 1,
                'threadLastEdit' => 0,
                'threadLastEditType' => 'days',
                'hasPrefixID' => -1,
                'threadslist_display' => 0,
                'forumslist_display' => 0,
            ];
            $forum_checked['all'] = "checked=\"checked\"";
            $forum_checked['custom'] = '';
            $mybb->input['forum_type'] = 'all';
        }

        $form_container = new FormContainer(
            $action == 'edit' ? $lang->forumcleaner_edit_forum_action : $lang->forumcleaner_add_forum_action
        );

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
						<td>" . $form->generate_forum_select(
                'forum_1_forums[]',
                $mybb->get_input('forum_1_forums', MyBB::INPUT_ARRAY),
                ['id' => 'forums', 'multiple' => true, 'size' => 5]
            ) . "</td>
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
    $mybb->get_input('forum_1_forums', \MyBB::INPUT_ARRAY),
    array('multiple' => true, 'size' => 10)).
"
</dd>
</dl>
<script type=\"text/javascript\">
checkAction('forum');
</script>";*/
        $form_container->output_row(
            $lang->forumcleaner_source_forum . ' <em>*</em>',
            $lang->forumcleaner_source_forum_desc,
            $actions
        );

        $agetypes = [
            'hours' => $lang->forumcleaner_agetype_hours,
            'days' => $lang->forumcleaner_agetype_days,
            'weeks' => $lang->forumcleaner_agetype_weeks,
            'months' => $lang->forumcleaner_agetype_months,
        ];
        $form_container->output_row(
            $lang->forumcleaner_thread_age,
            $lang->forumcleaner_thread_age_desc,
            $form->generate_numeric_field('age', $update_array['age'], ['id' => 'age']) . ' ' .
            $form->generate_select_box('agetype', $agetypes, $update_array['agetype'], ['id' => 'agetype']),
            'age'
        );

        $form_container->output_row(
            $lang->forumcleaner_thread_post_select,
            $lang->forumcleaner_thread_post_select_desc,
            $form->generate_select_box(
                'lastpost',
                [0 => $lang->forumcleaner_thread_first_post, 1 => $lang->forumcleaner_thread_last_post],
                $update_array['lastpost'],
                ['id' => 'lastpost']
            ),
            'lastpost'
        );

        $form_container->output_row(
            $lang->ForumCleanerActionThreadLastEdit,
            $lang->ForumCleanerActionThreadLastEditDescription,
            $form->generate_numeric_field(
                'threadLastEdit',
                $update_array['threadLastEdit'],
                ['id' => 'threadLastEdit']
            ) . ' ' .
            $form->generate_select_box(
                'threadLastEditType',
                $agetypes,
                $update_array['threadLastEditType'],
                ['id' => 'threadLastEditType']
            ),
            'threadLastEdit'
        );

        $form_container->output_row(
            $lang->ForumCleanerActionThreadHasPrefixIDSelect,
            $lang->ForumCleanerActionThreadHasPrefixIDSelectDescription,
            $form->generate_select_box(
                'hasPrefixID',
                (function () use ($mybb, $lang): array {
                    $prefix_cache = $mybb->cache->read('threadprefixes') ?? [];

                    $selectObjects = [-1 => $lang->all_prefix, 0 => $lang->none];

                    foreach ($prefix_cache as $prefix) {
                        $selectObjects[$prefix['pid']] = htmlspecialchars_uni($prefix['prefix']);
                    }

                    return $selectObjects;
                })(),
                $update_array['hasPrefixID'],
                ['id' => 'hasPrefixID']
            ),
            'hasPrefixID'
        );

        $form_container->output_row(
            $lang->forumcleaner_thread_action,
            $lang->forumcleaner_thread_action_desc,
            $form->generate_select_box(
                'forumaction',
                $forumactions,
                $update_array['forumaction'],
                ['id' => 'forumaction']
            ),
            'forumaction'
        );

        $form_container->output_row(
            $lang->forumcleaner_target_forum,
            $lang->forumcleaner_target_forum_desc,
            $form->generate_forum_select(
                'tofid',
                $update_array['tofid'],
                ['id' => 'tofid', 'main_option' => $lang->forumcleaner_none]
            ),
            'tofid'
        );

        $form_container->output_row(
            $lang->forumcleaner_forumslist_display,
            $lang->forumcleaner_forumslist_display_desc,
            $form->generate_yes_no_radio('forumslist_display', $update_array['forumslist_display']),
            'forumslist_display'
        );

        $form_container->output_row(
            $lang->forumcleaner_threadslist_display,
            $lang->forumcleaner_threadslist_display_desc,
            $form->generate_yes_no_radio('threadslist_display', $update_array['threadslist_display']),
            'threadslist_display'
        );

        $form_container->end();
        // Close form.
        $buttons = [$form->generate_submit_button($lang->forumcleaner_save)];
        $form->output_submit_wrapper($buttons);
        $form->end();
    } else {
        //config

        // Init table.
        $table = new Table();
        $table->construct_header($lang->forumcleaner_forum);
        $table->construct_header($lang->forumcleaner_action, ['class' => 'align_center']);
        $table->construct_header($lang->forumcleaner_age, ['class' => 'align_center']);
        $table->construct_header($lang->forumcleaner_controls, ['class' => 'align_center']);

        // List forums.
        $result = $db->simple_select($me['sysname']);

        if ($db->num_rows($result) > 0) {
            $rows = [];

            while ($row = $db->fetch_array($result)) {
                if ($row['fid'] == '-1') {
                    $row['sort_key'] = '0000';
                } else {
                    $forums = explode(',', $row['fid']);
                    $fid = array_shift($forums);
                    $row['sort_key'] = get_sort_key((int)$fid) . ' ' . $forum_cache[$fid]['name'];
                }
                $rows[] = $row;
            }
            // sort actions by forum and treads age
            usort($rows, 'actions_cmp');

            foreach ($rows as $row) {
                if ($row['enabled']) {
                    $icon = "<a href=\"{$me['cfglink']}&amp;action=disable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"{$lang->forumcleaner_enabled}\" title=\"{$lang->forumcleaner_enabled_title}\" style=\"vertical-align: middle;\" /></a>";
                } else {
                    $icon = "<a href=\"{$me['cfglink']}&amp;action=enable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"{$lang->forumcleaner_disabled}\" title=\"{$lang->forumcleaner_disabled_title}\" style=\"vertical-align: middle;\" /></a>";
                }

                $forum_name = $lang->forumcleaner_all_forums;
                if ($row['fid'] != '-1') {
                    $forums = explode(',', $row['fid']);
                    $forum_name = '';
                    foreach ($forums as $fid) {
                        if (strlen($forum_name)) {
                            $forum_name .= '<br />';
                        }
                        $forum_name .= htmlspecialchars_uni(strip_tags($forum_cache[$fid]['name']));
                    }
                }

                $table->construct_cell(
                    "<div class=\"float_right\">{$icon}</div><div><strong>" .
                    "<a href=\"{$me['cfglink']}&amp;action=edit&amp;xid={$row['xid']}\">" .
                    $forum_name . '</a></strong></div>'
                );
                $table->construct_cell($forumactions[$row['action']], ['class' => 'align_center']);

                $lang_str = "forumcleaner_agetype_{$row['agetype']}";
                $table->construct_cell(
                    $lang->sprintf(
                        $lang->forumcleaner_thread_age_text,
                        $row['age'],
                        $lang->$lang_str,
                        $row['lastpost'] ? $lang->forumcleaner_thread_last_post : $lang->forumcleaner_thread_first_post
                    ),
                    ['class' => 'align_center']
                );

                $popup = new PopupMenu("action_{$row['xid']}", $lang->forumcleaner_options);
                $popup->add_item($lang->forumcleaner_edit, "{$me['cfglink']}&amp;action=edit&amp;xid={$row['xid']}");
                if ($row['enabled']) {
                    $popup->add_item(
                        $lang->forumcleaner_disable,
                        "{$me['cfglink']}&amp;action=disable&amp;xid={$row['xid']}"
                    );
                } else {
                    $popup->add_item(
                        $lang->forumcleaner_enable,
                        "{$me['cfglink']}&amp;action=enable&amp;xid={$row['xid']}"
                    );
                }

                $popup->add_item(
                    $lang->forumcleaner_delete,
                    "{$me['cfglink']}&amp;action=delete&amp;xid={$row['xid']}&amp;my_post_key={$mybb->post_code}",
                    "return AdminCP.deleteConfirmation(this, '{$lang->forumcleaner_confirm_forum_action_deletion}')"
                );

                $table->construct_cell($popup->fetch(), ['class' => 'align_center']);

                $table->construct_row();
            }
        } else {
            $table->construct_cell($lang->forumcleaner_no_forums, ['colspan' => 4]);
            $table->construct_row();
        }
        $table->output($lang->forumcleaner_forums);
    }

    echo "<br /><small><strong>{$me['name']} plugin &copy; 2010</strong></small>";

    $page->output_footer(true);
}// function forumcleaner_process_forumactions()

// Add a menu item in the AdminCP.
function forumcleaner_admin_user_menu(array &$sub_menu): array
{
    $me = forumcleaner_info();
    $sub_menu[] = [
        'id' => $me['avasysname'],
        'title' => $me['avaname'],
        'link' => $me['avalink'],
    ];

    return $sub_menu;
}

// The file to use for configuring the plugin.
function forumcleaner_admin_user_action_handler(array &$actions): array
{
    $me = forumcleaner_info();
    $actions[$me['avasysname']] = [
        'active' => $me['avasysname'],
        'file' => 'settings.php'
    ];

    return $actions;
}

// The text for the entry in the admin permissions page.
function forumcleaner_admin_user_permissions(array &$admin_permissions): array
{
    global $lang;

    $me = forumcleaner_info();

    $admin_permissions[$me['avasysname']] = $lang->sprintf($lang->forumcleaner_can_manage, $me['avaname']);

    return $admin_permissions;
}

function forumcleaner_process_orphanavatars(): void
{
    global $db, $page, $mybb, $lang;

    $me = forumcleaner_info();

    $action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'find');

    // silently ignore unknown actions
    if (!in_array($action, ['find', 'delete'])) {
        admin_redirect($me['avalink']);
    }

    $page->add_breadcrumb_item($me['avaname']);
    $page->output_header($me['avaname']);

    $files = [];
    $files = find_orphaned_avatars();
    $numfiles = count($files);

    $navtabs = [];
    $navtabs['find'] = [
        'title' => $lang->forumcleaner_avafind,
        'link' => $me['avalink'],
        'description' => $lang->forumcleaner_avafind_desc,
    ];

    if ($numfiles) {
        $navtabs['delete'] = [
            'title' => $lang->forumcleaner_avadelete,
            'link' => "{$me['avalink']}&amp;action=delete",
            'description' => '',
        ];
    }

    $page->output_nav_tabs($navtabs, 'find');

    if ($action == 'find') {
        if ($numfiles) {
            $page->output_alert($lang->sprintf($lang->forumcleaner_avafound, $numfiles));
        } else {
            $page->output_success($lang->forumcleaner_avanotfound);
        }
    }

    if ($action == 'delete') {
        if ($numfiles) {
            $avatarpath = $mybb->settings['avataruploadpath'];

            if (defined('IN_ADMINCP')) {
                $avatarpath = '../' . $avatarpath;
            }

            if (!preg_match('#/$#', $avatarpath)) {
                $avatarpath .= '/';
            }

            foreach ($files as $file) {
                unlink($avatarpath . $file);
            }

            flash_message($lang->sprintf($lang->forumcleaner_avatars_deleted, $numfiles), 'success');
            $files = [];
        } else {
            flash_message($lang->forumcleaner_avatarnotfound, 'error');
        }

        admin_redirect($me['avalink']);
    }

    echo "<br /><small><strong>{$me['name']} plugin &copy; 2010</strong></small>";
    $page->output_footer(true);
}// function forumcleaner_process_orphanavatars()

function find_orphaned_avatars(): array
{
    global $mybb, $db;

    if (defined('IN_ADMINCP')) {
        $avatarpath = '../' . $mybb->settings['avataruploadpath'];
    } else {
        $avatarpath = $mybb->settings['avataruploadpath'];
    }

    $query = $db->query('select uid,avatar from ' . TABLE_PREFIX . "users where avatartype='upload'");
    $dir = opendir($avatarpath);

    $user_avatars = [];
    $file_avatars = [];

    if ($dir) {
        $file_notfinished = 1;
        $user_notfinished = 1;

        while ($file_notfinished || $user_notfinished) {
            if ($file_notfinished && ($file = readdir($dir))) {
                if (array_key_exists($file, $user_avatars)) {
                    unset($user_avatars[$file]);
                } else {
                    $file_avatars[$file] = 1;
                }
            } else {
                $file_notfinished = 0;
            }

            if ($user_notfinished && ($ufile = $db->fetch_array($query))) {
                $file = preg_replace('#.+/#', '', $ufile['avatar']);
                $file = preg_replace("#\\?.+#", '', $file);

                if (array_key_exists($file, $file_avatars)) {
                    unset($file_avatars[$file]);
                } else {
                    $user_avatars[$file] = $ufile['uid'];
                }
            } else {
                $user_notfinished = 0;
            }
        }

        unset($file_avatars['.']);
        unset($file_avatars['..']);
        unset($file_avatars['index.html']);

        closedir($dir);

        return array_keys($file_avatars);
    } else {
        return [];
    }
}

// forms message to user with Forum Action description
function get_forumaction_desc(int $fid, string $where): string
{
    static $forumaction_cache;
    global $db, $lang, $forum_cache, $mybb;

    $lang->load('forumcleaner');

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
            if ($forums == '-1' || $fa['action'] == 'del_redirects') {
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

        $prefix_cache = $mybb->cache->read('threadprefixes') ?? [];

        $ret = '';
        $comma = '';
        foreach ($actions as $fa) {
            $ret .= $comma;
            if ($fa['action'] == 'close' || $fa['action'] == 'delete') {
                $ret .= $lang->sprintf(
                    $forumactions[$fa['action']],
                    $lastpost[$fa['lastpost']],
                    $fa['age'],
                    $agetypes[$fa['agetype']]
                );
            } else {
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
            }

            $threadLastEdit = (int)$fa['threadLastEdit'];

            if ($threadLastEdit) {
                $ret .= $lang->sprintf(
                    $lang->forumcleaner_topics_last_edit,
                    $threadLastEdit,
                    $agetypes[$fa['threadLastEditType']]
                );
            }

            $hasPrefixID = (int)$fa['hasPrefixID'];

            if ($hasPrefixID !== -1 && !empty($prefix_cache[$hasPrefixID])) {
                $ret .= $lang->sprintf(
                    $lang->forumcleaner_topics_prefix,
                    htmlspecialchars_uni(strip_tags($prefix_cache[$hasPrefixID]['prefix']))
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
        $subst = eval($templates->render(SYSTEM_NAME . '_forumbit'));

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
        $subst = eval($templates->render(SYSTEM_NAME . '_threadlist'));

        $foruminfo['ForumCleanerMessages'] = $subst;
    }
}

function forumcleaner_task(array &$task = [])
{
    global $db, $mybb, $plugins;

    $sysname = 'forumcleaner';
    $threadlimit = (int)($mybb->settings[$sysname . '_threadlimit'] ?? 30);
    $userlimit = (int)($mybb->settings[$sysname . '_userlimit'] ?? 50);
    $awaitingdays = (int)($mybb->settings[$sysname . '_awaitingdays'] ?? 0);
    $inactivedays = (int)($mybb->settings[$sysname . '_inactivedays'] ?? 0);

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

        if (function_exists('add_task_log')) {
            add_task_log($task, count($users) . ' users deleted');
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
    $forumactions = $db->simple_select($sysname, '*', "enabled = '1'");

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
        add_task_log($task, 'The Forum Cleaning task successfully ran.');
    }
}