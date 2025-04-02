<?php

/***************************************************************************
 *
 *    Forum Cleaner plugin (/inc/plugins/ougc/ForumCleaner/hooks/admin.php)
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

namespace ForumCleaner\Hooks\Admin;

use MyBB;

use function ForumCleaner\Core\executeTask;
use function ForumCleaner\Core\loadLanguage;

use const ForumCleaner\DEBUG;
use const ForumCleaner\SYSTEM_NAME;
use const ForumCleaner\SYSTEM_NAME_AVATARS;
use const ForumCleaner\Admin\URL;
use const ForumCleaner\Admin\URL_AVATARS;

function admin_config_plugins_deactivate()
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'forumcleaner' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=forumcleaner'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }
}

function admin_forum_menu(array &$sub_menu): array
{
    global $lang;

    loadLanguage();

    $sub_menu[] = [
        'id' => SYSTEM_NAME,
        'title' => $lang->forumcleaner,
        'link' => URL
    ];

    return $sub_menu;
}

function admin_forum_action_handler(array &$actions): array
{
    $actions[SYSTEM_NAME] = [
        'active' => SYSTEM_NAME,
        'file' => 'settings.php'
    ];

    return $actions;
}

function admin_forum_permissions(array &$admin_permissions): array
{
    global $lang;

    loadLanguage();

    $admin_permissions[SYSTEM_NAME] = $lang->sprintf($lang->forumcleaner_can_manage, $lang->forumcleaner);

    return $admin_permissions;
}

function admin_user_menu(array &$sub_menu): array
{
    global $lang;

    loadLanguage();

    $sub_menu[] = [
        'id' => SYSTEM_NAME_AVATARS,
        'title' => $lang->forumcleaner_avaname,
        'link' => URL_AVATARS
    ];

    return $sub_menu;
}

function admin_user_action_handler(array &$actions): array
{
    global $lang;

    loadLanguage();

    $actions[SYSTEM_NAME_AVATARS] = [
        'active' => SYSTEM_NAME_AVATARS,
        'file' => 'settings.php'
    ];

    return $actions;
}

function admin_user_permissions(array &$admin_permissions): array
{
    global $lang;

    loadLanguage();

    $admin_permissions[SYSTEM_NAME_AVATARS] = $lang->sprintf(
        $lang->forumcleaner_can_manage,
        $lang->forumcleaner_avaname
    );

    return $admin_permissions;
}

function admin_load(): void
{
    global $lang;

    loadLanguage();

    if (DEBUG) {
        executeTask();
    }

    global $page;

    if ($page->active_action == SYSTEM_NAME) {
        forumcleaner_process_forumactions();
    } elseif ($page->active_action == SYSTEM_NAME_AVATARS) {
        forumcleaner_process_orphanavatars();
    }
}

function forumcleaner_process_forumactions(): void
{
    global $db, $page, $mybb, $forum_cache, $lang;

    if (!is_array($forum_cache)) {
        cache_forums();
    }

    $action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'config');

    $pageUrl = URL;

    // silently ignore unknown actions
    if (!in_array($action, ['config', 'add', 'addtask', 'edit', 'enable', 'disable', 'delete'])) {
        admin_redirect($pageUrl);
    }

    $page->add_breadcrumb_item($lang->forumcleaner);
    $page->output_header($lang->forumcleaner);

    $systemName = SYSTEM_NAME;

    // Warnings.
    $result = $db->simple_select('tasks', 'tid, enabled, file', "file = '{$systemName}'");
    $task = $db->fetch_array($result);
    if (!file_exists(MYBB_ROOT . "inc/tasks/{$task['file']}.php")) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_task_file,
                $lang->forumcleaner,
                '<code>inc/tasks/' . $systemName . '.php</code>'
            )
        );
    }
    if (!$db->num_rows($result)) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_no_task_added,
                $lang->forumcleaner,
                '<code>inc/tasks/' . $systemName . '.php</code>',
                "{$pageUrl}&amp;action=addtask"
            )
        );
    }
    if (!$task['enabled']) {
        $page->output_alert(
            $lang->sprintf(
                $lang->forumcleaner_alert_task_disabled,
                $lang->forumcleaner,
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
            $result = $db->simple_select($systemName, '*', "xid = '{$xid}'");
            if ($db->num_rows($result)) {
                $db_array = $db->fetch_array($result);

                $db_array['hasPrefixID'] = explode(',', $db_array['hasPrefixID']);
            } else {
                $action = 'config';
            }
        }
    }

    if ($action == 'addtask') {
        if (forumcleaner_add_task()) {
            flash_message($lang->sprintf($lang->forumcleaner_task_added, $lang->forumcleaner), 'success');
        } else {
            flash_message($lang->sprintf($lang->forumcleaner_task_exists, $lang->forumcleaner), 'success');
        }
        admin_redirect($pageUrl);
    }

    if ($action == 'delete') {
        if ($xid >= 0) {
            $result = $db->simple_select($systemName, '*', "xid = '$xid'");
            if ($expunge = $db->fetch_array($result)) {
                log_admin_action(['xid' => $xid, 'fid' => $expunge['fid']]);
                $db->delete_query($systemName, "xid = '{$xid}'", 1);
            }
            flash_message($lang->forumcleaner_action_deleted, 'success');
        }
        admin_redirect($pageUrl);
    }

    if ($action == 'disable' || $action == 'enable') {
        if ($xid >= 0) {
            $find = 1;
            $update = 0;
            if ($action == 'enable') {
                $find = 0;
                $update = 1;
            }

            $result = $db->simple_select($systemName, '*', "xid = '$xid' AND enabled = {$find}");
            if ($expunge = $db->fetch_array($result)) {
                log_admin_action(['xid' => $xid, 'fid' => $expunge['fid']]);
                $db->update_query($systemName, ['enabled' => $update], "xid = '{$xid}'");
            }
        }
        flash_message(
            ($action == 'disable' ? $lang->forumcleaner_action_disabled : $lang->forumcleaner_action_enabled),
            'success'
        );
        admin_redirect($pageUrl);
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
        $update_array['hasPrefixID'] = array_map('intval', $mybb->get_input('hasPrefixID', MyBB::INPUT_ARRAY));
        $update_array['forumslist_display'] = $mybb->get_input('forumslist_display', MyBB::INPUT_INT);
        $update_array['threadslist_display'] = $mybb->get_input('threadslist_display', MyBB::INPUT_INT);

        if ($update_array['action'] == 'move') {
            $update_array['tofid'] = $mybb->get_input('tofid', MyBB::INPUT_INT);
        }

        if (!count($errors)) {
            $errors = forumcleaner_validate_action($update_array);
        }

        if (count($errors) == 0) {
            $update_array['hasPrefixID'] = implode(',', $update_array['hasPrefixID']);

            // update or insert new action
            if ($xid < 0) {
                // insert
                $db->insert_query($systemName, $update_array);
                $mybb->input['action'] = 'add';
                log_admin_action(['xid' => $db->insert_id(), 'fid' => $update_array['fid']]);
            } else {
                // update
                unset($update_array['xid']);
                $db->update_query($systemName, $update_array, "xid = '$xid'");
                $mybb->input['action'] = 'update';
                log_admin_action(['xid' => $xid, 'fid' => $update_array['fid']]);
            }

            flash_message($lang->sprintf($lang->forumcleaner_rules_updated, $lang->forumcleaner), 'success');
            admin_redirect($pageUrl);
        }
    }

    $navtabs = [];
    $navtabs['config'] = [
        'title' => $lang->forumcleaner_configuration,
        'link' => $pageUrl,
        'description' => $lang->forumcleaner_configuration_desc,
    ];

    if ($action == 'edit') {
        $navtabs['edit'] = [
            'title' => $lang->forumcleaner_edit_forum_action,
            'link' => "{$pageUrl}&amp;action=edit&amp;xid={$xid}",
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
            'link' => "{$pageUrl}&amp;action=add",
            'description' => $lang->forumcleaner_add_forum_action_desc,
        ];
    }

    $page->output_nav_tabs($navtabs, $action);

    if ($action == 'add' || $action == 'edit') {
        // Create form.
        $form = new \Form("{$pageUrl}&amp;action={$action}", 'post');

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
                'hasPrefixID' => [-1],
                'threadslist_display' => 0,
                'forumslist_display' => 0,
            ];
            $forum_checked['all'] = "checked=\"checked\"";
            $forum_checked['custom'] = '';
            $mybb->input['forum_type'] = 'all';
        }

        $form_container = new \FormContainer(
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
                'hasPrefixID[]',
                (function () use ($mybb, $lang): array {
                    $prefix_cache = $mybb->cache->read('threadprefixes') ?? [];

                    $selectObjects = [-1 => $lang->all_prefix, 0 => $lang->none];

                    foreach ($prefix_cache as $prefix) {
                        $selectObjects[$prefix['pid']] = htmlspecialchars_uni($prefix['prefix']);
                    }

                    return $selectObjects;
                })(),
                $update_array['hasPrefixID'],
                ['multiple' => true, 'size' => 5, 'id' => 'hasPrefixID']
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
        $table = new \Table();
        $table->construct_header($lang->forumcleaner_forum);
        $table->construct_header($lang->forumcleaner_action, ['class' => 'align_center']);
        $table->construct_header($lang->forumcleaner_age, ['class' => 'align_center']);
        $table->construct_header($lang->forumcleaner_controls, ['class' => 'align_center']);

        // List forums.
        $result = $db->simple_select($systemName);

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
            usort($rows, function (array $a, array $b): int {
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
            });

            foreach ($rows as $row) {
                if ($row['enabled']) {
                    $icon = "<a href=\"{$pageUrl}&amp;action=disable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"{$lang->forumcleaner_enabled}\" title=\"{$lang->forumcleaner_enabled_title}\" style=\"vertical-align: middle;\" /></a>";
                } else {
                    $icon = "<a href=\"{$pageUrl}&amp;action=enable&amp;xid={$row['xid']}\"><img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"{$lang->forumcleaner_disabled}\" title=\"{$lang->forumcleaner_disabled_title}\" style=\"vertical-align: middle;\" /></a>";
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
                    "<a href=\"{$pageUrl}&amp;action=edit&amp;xid={$row['xid']}\">" .
                    $forum_name . '</a></strong></div>'
                );
                $table->construct_cell($forumactions[$row['action']], ['class' => 'align_center']);

                $prefixesCache = $mybb->cache->read('threadprefixes') ?? [];

                $lang_str = "forumcleaner_agetype_{$row['agetype']}";
                $table->construct_cell(
                    $lang->sprintf(
                        $lang->forumcleaner_thread_age_text,
                        $row['age'],
                        $lang->{$lang_str},
                        $row['lastpost'] ? $lang->forumcleaner_thread_last_post : $lang->forumcleaner_thread_first_post,
                        $row['threadLastEdit'] ? $lang->sprintf(
                            $lang->forumcleaner_thread_edit_time,
                            $row['threadLastEdit'],
                            $lang->{"forumcleaner_agetype_{$row['threadLastEditType']}"}
                        ) : '',
                        $row['hasPrefixID'] ? (function (array $prefixIDs) use ($lang, $prefixesCache): string {
                            $prefixList = [];

                            if (in_array(-1, $prefixIDs)) {
                                $prefixList[] = $lang->all_prefix;
                            } else {
                                if (in_array(0, $prefixIDs)) {
                                    $prefixList[] = $lang->none;
                                }

                                foreach ($prefixesCache as $prefixData) {
                                    if (in_array($prefixData['pid'], $prefixIDs)) {
                                        $prefixList[] = $prefixData['prefix'];
                                    }
                                }
                            }

                            return $lang->sprintf(
                                $lang->forumcleaner_thread_all_prefixes,
                                implode(', ', $prefixList)
                            );
                        })(
                            explode(',', $row['hasPrefixID'])
                        ) : ''
                    ),
                    ['class' => 'align_center']
                );

                $popup = new \PopupMenu("action_{$row['xid']}", $lang->forumcleaner_options);
                $popup->add_item($lang->forumcleaner_edit, "{$pageUrl}&amp;action=edit&amp;xid={$row['xid']}");
                if ($row['enabled']) {
                    $popup->add_item(
                        $lang->forumcleaner_disable,
                        "{$pageUrl}&amp;action=disable&amp;xid={$row['xid']}"
                    );
                } else {
                    $popup->add_item(
                        $lang->forumcleaner_enable,
                        "{$pageUrl}&amp;action=enable&amp;xid={$row['xid']}"
                    );
                }

                $popup->add_item(
                    $lang->forumcleaner_delete,
                    "{$pageUrl}&amp;action=delete&amp;xid={$row['xid']}&amp;my_post_key={$mybb->post_code}",
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

    echo "<br /><small><strong>{$lang->forumcleaner} plugin &copy; 2010</strong></small>";

    $page->output_footer(true);
}// function forumcleaner_process_forumactions()

function forumcleaner_process_orphanavatars(): void
{
    global $db, $page, $mybb, $lang;

    $action = ($mybb->get_input('action') ? $mybb->get_input('action') : 'find');

    $pageUrl = URL_AVATARS;

    // silently ignore unknown actions
    if (!in_array($action, ['find', 'delete'])) {
        admin_redirect($pageUrl);
    }

    $page->add_breadcrumb_item($lang->forumcleaner_avaname);
    $page->output_header($lang->forumcleaner_avaname);

    $files = [];
    $files = find_orphaned_avatars();
    $numfiles = count($files);

    $navtabs = [];
    $navtabs['find'] = [
        'title' => $lang->forumcleaner_avafind,
        'link' => $pageUrl,
        'description' => $lang->forumcleaner_avafind_desc,
    ];

    if ($numfiles) {
        $navtabs['delete'] = [
            'title' => $lang->forumcleaner_avadelete,
            'link' => "{$pageUrl}&amp;action=delete",
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

        admin_redirect($pageUrl);
    }

    echo "<br /><small><strong>{$lang->forumcleaner} plugin &copy; 2010</strong></small>";
    $page->output_footer(true);
}// function forumcleaner_process_orphanavatars()

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

    if ($action['hasPrefixID']) {
        if (in_array(-1, $action['hasPrefixID'])) {
            $action['hasPrefixID'] = [-1];
        } else {
            foreach ($action['hasPrefixID'] as $prefixID) {
                if ($prefixID !== 0 && !in_array($prefixID, $prefixesIDs)) {
                    _dump($prefixID, $prefixesIDs);
                    $errors['invalid_thread_last_edit_type'] = $lang->ForumCleanerActionInvalidPrefix;

                    break;
                }
            }
        }
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