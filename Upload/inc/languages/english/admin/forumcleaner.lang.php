<?php

/*
	Forum Cleaner - A MyBB plugin to help Administrators keep things clean.

    Copyright (C) 2010

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
 * This file is MYBB_ROOT/inc/languages/english/admin/forumcleaner.lang.php
 * There should also be a files
 *  MYBB_ROOT/inc/tasks/forumcleaner.php
 *  MYBB_ROOT/inc/plugins/forumcleaner.php
 *  MYBB_ROOT/inc/languages/english/forumcleaner.lang.php
 */

// Plugin API
$l['forumcleaner'] = 'Forum Cleaner';
$l['forumcleaner_desc'] = 'A MyBB plugin to help Administrators keep things clean.';

// Settings
$l['setting_group_forumcleaner'] = "Forum Cleaner options";
$l['setting_group_forumcleaner_desc'] = "Plugin allows automatically clean forums from old threads ".
	"and delete inactive and not activated users. Here you can manage plugin ".
	"options. To setup forums to clean please go to ".
	"Forums & Posts section of Admin CP.";

$l['setting_forumcleaner_threadlimit'] = 'Thread transactions limit';
$l['setting_forumcleaner_threadlimit_desc'] = 'Limit number of threads processed in one Forum at one run for one action.<br />Default 30 (if not set or set to 0). To setup Forum Actions please go <a href="index.php?module=forum-forumcleaner">here</a>';

$l['setting_forumcleaner_userlimit'] = "Users transactions limit";
$l['setting_forumcleaner_userlimit_desc'] = "Limit number of users deleted during one run.<br />Default 50 (if not set or set to 0).";

$l['setting_forumcleaner_awaitingdays'] = "Grace period for Awaiting Activation users";
$l['setting_forumcleaner_awaitingdays_desc'] = "Input days of Grace period for Awaiting Activation users. After this period from registration date users will be deleted.<br />No users will be deleted if set to 0.";

$l['setting_forumcleaner_inactivedays'] = "Grace period for inactive users";
$l['setting_forumcleaner_inactivedays_desc'] = "Input days of Grace period for inactive users (with no posts). After this period from last visit users will be deleted.<br />No users will be deleted if set to 0.";

$l['setting_forumcleaner_groupids'] = "Group exception list for inactive users";
$l['setting_forumcleaner_groupids_desc'] = "Administrators will be not deleted as inactive users. Here you can add additional exceptions.<br />Input comma separated list of Group IDs.";


$l['forumcleaner_task_desc'] = "Manage threads on specified forums when they reach a specified age. Delete inactive and not activated users.";

$l['forumcleaner_task_file_not_exist'] = "The {1} task file ({2}) does not exist. Install this first!";
$l['forumcleaner_plugin_uninstalled']= "The {1} plugin has been uninstalled.<br />You can now safely remove these files: {2}";
$l['forumcleaner_alert_task_file'] = "The {1} task file ({2}) does not exist. {1} will not function without it.";
$l['forumcleaner_alert_no_task_added'] = "The {1} task file ({2}) has not been added to the task system.<br /><a href=\"{3}\">Add it to the task system</a>.";
$l['forumcleaner_alert_task_disabled']="The {1} task is currently disabled and will <em>not</em> run.<br /><a href=\"{2}\">Enable the task</a>.";

$l['forumcleaner_can_manage'] = "Can manage {1} configuration.";

$l['forumcleaner_configuration'] = 'Configuration';
$l['forumcleaner_configuration_desc'] = 'Manage threads to be automatically closed, moved or deleted. Here you can edit, enable/disable or delete forum actions';

$l['forumcleaner_add_forum_action'] = 'Add New Forum action';
$l['forumcleaner_add_forum_action_desc'] = // modified in 2.5.0
'Here you can add forum to scan for threads and define action to found thread. <br />
<strong>Attention:</strong> you can define for single forum the same actions or different actions with the same age, but this have no sense. Please ensure common sense by your self.';

$l['forumcleaner_edit_forum_action'] = 'Edit Forum Action';
$l['forumcleaner_edit_forum_action_desc'] = // modified in 2.5.0
'Here you can modify forum action.<br />
<strong>Attention:</strong> you can define for single forum the same actions or different actions with the same age, but this have no sense. Please ensure common sense by your self.';

$l['forumcleaner_task_added'] = "The {1} task was successfully added to the task system.";
$l['forumcleaner_task_exists'] = "The {1} task already exists.";

$l['forumcleaner_action_enabled'] = "Action succesfuly enabled";
$l['forumcleaner_action_disabled'] = "Action succesfuly disabled";

$l['forumcleaner_confirm_forum_action_deletion'] = "Are you sure you wish to delete this forum action?";
$l['forumcleaner_action_deleted'] = "Action succesfuly deleted";


$l['forumcleaner_invalid_age'] = 'The age should be at least 1';
$l['forumcleaner_invalid_agetype'] = 'The age type is invalid';
$l['forumcleaner_invalid_forum_id'] = 'Provided forum id is invalid';
$l['forumcleaner_invalid_action'] = 'Provided action is invalid';
$l['forumcleaner_invalid_target_forum_id'] = 'Provided Target forum id is invalid';
$l['forumcleaner_duplicate_age'] = 'For this forum action with this age already exists';
$l['forumcleaner_duplicate_action'] = 'For this forum this action already exists';
$l['forumcleaner_source_category_not_allowed'] = 'Category is not allowed to be selected as forum';
$l['forumcleaner_target_category_not_allowed'] = 'Category is not allowed to be selected as Target forum';

$l['forumcleaner_rules_updated'] = "{1} rules updated successfully.";

$l['forumcleaner_agetype_hours'] = "Hour(s)";
$l['forumcleaner_agetype_days'] = "Day(s)";
$l['forumcleaner_agetype_weeks'] = "Week(s)";
$l['forumcleaner_agetype_months'] = "Month(s)";

$l['forumcleaner_source_forum'] = "Forums to Clean"; // modified in 2.5.0
$l['forumcleaner_source_forum_desc'] = "Select forums to find old threads."; // modified in 2.5.0

$l['forumcleaner_thread_age'] = "Thread age";
$l['forumcleaner_thread_age_desc'] = "How old thread should be to perform action on it.";

$l['forumcleaner_thread_post_select'] = "Which post time";
$l['forumcleaner_thread_post_select_desc'] = "Which post time to check";

$l['forumcleaner_thread_first_post'] = "First post in thread";
$l['forumcleaner_thread_last_post'] = "Last post in thread";

$l['forumcleaner_none'] = "Not selected";

$l['forumcleaner_close_threads'] = "Close Threads";
$l['forumcleaner_delete_threads'] = "Delete Threads";
$l['forumcleaner_move_threads'] = "Move Threads";
$l['forumcleaner_delete_redirects'] = "Delete Permanent Redirects";

$l['forumcleaner_thread_action'] = "Thread Action";
$l['forumcleaner_thread_action_desc'] = "Select action to be performed on found thread.";

$l['forumcleaner_target_forum'] = "Target Forum";
$l['forumcleaner_target_forum_desc'] = // modified in 2.5.0
	"Select target forum to move found threads. Required for 'Move Threads' Action.";

$l['forumcleaner_forumslist_display'] = 'Display in Forum index';
$l['forumcleaner_forumslist_display_desc'] = 'Display a message about this Action in Forum index. Ignored for Delete Permanent Redirects';

$l['forumcleaner_threadslist_display'] = 'Display in Forum Threads list';
$l['forumcleaner_threadslist_display_desc'] = 'Display a message about this Action in Forum Threads list. Ignored for Delete Permanent Redirects';

$l['forumcleaner_save'] = "Save";

$l['forumcleaner_forum'] = "Forum";
$l['forumcleaner_action'] = "Action";
$l['forumcleaner_age'] = "Age";
$l['forumcleaner_controls'] = "Controls";
$l['forumcleaner_all_forums'] = "All Forums";

$l['forumcleaner_enabled'] = "Enabled";
$l['forumcleaner_enabled_title'] = "Enabled. Click to disable.";
$l['forumcleaner_disabled'] = "Disabled";
$l['forumcleaner_disabled_title'] = "Disabled. Click to enable.";
$l['forumcleaner_enable'] = "Enable";
$l['forumcleaner_disable'] = "Disable";

$l['forumcleaner_thread_age_text'] = "{1} {2} for the {3}";

$l['forumcleaner_forums'] = 'Forums to clean periodically';
$l['forumcleaner_no_forums'] = 'No forums to display';

$l['forumcleaner_avaname'] = 'Orphaned Avatars';
$l['forumcleaner_avadesc'] = 'Allows to find and remove Orphaned Avatars';

$l['forumcleaner_avafind'] = 'Find Orphaned Avatars';
$l['forumcleaner_avafind_desc'] = 'Finds Orphaned Avatars';
$l['forumcleaner_avadelete'] = 'Delete found Orphaned Avatars';

$l['forumcleaner_avafound'] = "Found {1} Orphaned Avatars";
$l['forumcleaner_avanotfound'] = "Orphaned Avatars not found";
$l['forumcleaner_avatars_deleted'] = "{1} Orphaned Avatars deleted";


// variables, added or modified in version 2.5.0

$l['forumcleaner_options'] = "Options";
$l['forumcleaner_delete'] = "Delete";
$l['forumcleaner_edit'] = "Edit";
$l['forumcleaner_all'] = "All forums";
$l['forumcleaner_select'] = "Select forums";
$l['forumcleaner_all_not_allowed'] = "All forums is not allowed for Move action";
$l['forumcleaner_target_selected'] = "Target forum for Move action are selected as source forum too. That is not allowed."; 


// variables, deleted in version 2.5.0 
//$l['forumcleaner_copy_threads'] = "Copy Threads";
//$l['forumcleaner_none_or_all']



//$l['forumcleaner_'] = "";