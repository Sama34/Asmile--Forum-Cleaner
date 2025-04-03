<?php

/***************************************************************************
 *
 *    Forum Cleaner plugin (/inc/plugins/ougc/ForumCleaner/admin.php)
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

namespace ForumCleaner\Admin;

use DirectoryIterator;
use PluginLibrary;
use stdClass;

use const MYBB_ROOT;
use const ForumCleaner\ROOT;
use const ForumCleaner\SYSTEM_NAME;

const TABLES_DATA = [
    SYSTEM_NAME => [
        'xid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'fid' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'enabled' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'threadslist_display' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'forumslist_display' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'action' => [
            'type' => 'VARCHAR',
            'size' => 35,
            'default' => ''
        ],
        'age' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'agetype' => [
            'type' => 'VARCHAR',
            'size' => 8,
            'default' => ''
        ],
        'agesecs' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'lastpost' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'threadLastEdit' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'threadLastEditType' => [
            'type' => 'VARCHAR',
            'size' => 8,
            'default' => ''
        ],
        'hasPrefixID' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'hasReplies' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'hasRepliesType' => [
            'type' => 'VARCHAR',
            'size' => 3,
            'default' => '>'
        ],
        'softDeleteThreads' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'runCustomThreadTool' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'tofid' => [
            'type' => 'INT',
            'default' => -1
        ],
        // todo, criterion for closed, open, or both threads
        // todo, criterion for stuck, unstuck, or both threads
        // todo, criterion for approved, unapproved, or both threads
        // todo, has attachments
    ]
];

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

const URL = 'index.php?module=forum-forumcleaner';

const URL_AVATARS = 'index.php?module=user-orphanavatars';

function pluginInfo(): array
{
    global $lang;

    $lang->load('forumcleaner');

    $language_fine = 1;

    if (defined('IN_ADMINCP') && !strlen($lang->setting_group_forumcleaner)) {
        $language_fine = 0;
    } elseif (!defined('IN_ADMINCP') && !strlen($lang->forumcleaner_topics_closed)) {
        $language_fine = 0;
    }

    if (!$language_fine) {
        error('<strong>No appropriate language file loaded !</strong>', 'Forum Cleaner error');
    }

    return [
        'name' => 'Forum Cleaner',
        'avaname' => $lang->forumcleaner_avaname,
        'description' => $lang->forumcleaner_desc,
        'avadesc' => $lang->forumcleaner_avadesc,
        'website' => 'http://community.mybb.com/thread-77074.html',
        'author' => 'Andriy Smilyanets',
        'authorsite' => 'http://community.mybb.com/user-18581.html',
        'version' => '2.5.1',
        'versioncode' => 2501,
        'compatibility' => '18*',
        'codename' => 'ougc_forumcleaner',
        'sysname' => SYSTEM_NAME,
        'avasysname' => 'orphanavatars',
        'cfglink' => URL,
        'avalink' => URL_AVATARS,
        'files' => [
            'inc/plugins/forumcleaner.php',
            'inc/tasks/forumcleaner.php',
            'inc/languages/english/admin/forumcleaner.lang.php',
            'inc/languages/english/forumcleaner.lang.php',
        ],
    ];
}

function pluginActivate(): void
{
    global $PL, $cache, $lang;

    $pluginInfo = pluginInfo();

    loadPluginLibrary();

    $settingsContents = file_get_contents(ROOT . '/settings.json');

    $settingsData = json_decode($settingsContents, true);

    foreach ($settingsData as $settingKey => &$settingData) {
        if (empty($lang->{"setting_forumcleaner_{$settingKey}"})) {
            continue;
        }

        if ($settingData['optionscode'] == 'select' || $settingData['optionscode'] == 'checkbox') {
            foreach ($settingData['options'] as $optionKey) {
                $settingData['optionscode'] .= "\n{$optionKey}={$lang->{"setting_forumcleaner_{$settingKey}_{$optionKey}"}}";
            }
        }

        $settingData['title'] = $lang->{"setting_forumcleaner_{$settingKey}"};

        $settingData['description'] = $lang->{"setting_forumcleaner_{$settingKey}_desc"};
    }

    $PL->settings(
        $pluginInfo['sysname'],
        $lang->setting_group_forumcleaner,
        $lang->setting_group_forumcleaner_desc,
        $settingsData
    );

    $templates = [];

    if (file_exists($templateDirectory = ROOT . '/templates')) {
        $templatesDirIterator = new DirectoryIterator($templateDirectory);

        foreach ($templatesDirIterator as $template) {
            if (!$template->isFile()) {
                continue;
            }

            $pathName = $template->getPathname();

            $pathInfo = pathinfo($pathName);

            if ($pathInfo['extension'] === 'html') {
                $templates[$pathInfo['filename']] = file_get_contents($pathName);
            }
        }
    }

    if ($templates) {
        $PL->templates($pluginInfo['sysname'], $pluginInfo['name'], $templates);
    }

    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    if (!isset($plugins['ForumCleaner'])) {
        $plugins['ForumCleaner'] = $pluginInfo['versioncode'];
    }

    dbVerifyTables();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    enableTask();

    change_admin_permission('forum', $pluginInfo['sysname']);

    change_admin_permission('user', $pluginInfo['avasysname']);

    $plugins['ForumCleaner'] = $pluginInfo['versioncode'];

    $cache->update('ougc_plugins', $plugins);

    // Log.
    log_admin_action($pluginInfo['name']);
}

function pluginDeactivate(): void
{
    disableTask();

    $pluginInfo = pluginInfo();

    // Change admin permission.
    change_admin_permission('forum', $pluginInfo['sysname'], 0);

    change_admin_permission('user', $pluginInfo['avasysname'], 0);
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (TABLES_DATA as $tableName => $tableColumns) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstall(): void
{
    global $db, $PL, $cache;

    loadPluginLibrary();

    foreach (TABLES_DATA as $tableName => $tableData) {
        if ($db->table_exists($tableName)) {
            $db->drop_table($tableName);
        }
    }

    $pluginInfo = pluginInfo();

    $PL->settings_delete($pluginInfo['sysname']);

    $PL->templates_delete($pluginInfo['sysname']);

    deleteTask();

    // Delete version from cache
    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['ForumCleaner'])) {
        unset($plugins['ForumCleaner']);
    }

    if (!empty($plugins)) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $cache->delete('ougc_plugins');
    }

    // Remove admin permission.
    change_admin_permission('forum', $pluginInfo['sysname'], -1);
    change_admin_permission('user', $pluginInfo['avasysname'], -1);

    log_admin_action($pluginInfo['name']);
}

function pluginLibraryRequirements(): stdClass
{
    return (object)pluginInfo()['pl'];
}

function loadPluginLibrary(): void
{
    global $PL, $lang;

    $fileExists = file_exists(PLUGINLIBRARY);

    if ($fileExists && !($PL instanceof PluginLibrary)) {
        require_once PLUGINLIBRARY;
    }
}

function enableTask(int $action = TASK_ENABLE): bool
{
    global $db, $lang;

    $pluginInfo = pluginInfo();

    $taskFile = SYSTEM_NAME;

    if ($action === TASK_DELETE) {
        $db->delete_query('tasks', "file='{$taskFile}'");

        return true;
    }

    $query = $db->simple_select('tasks', '*', "file='{$taskFile}'", ['limit' => 1]);

    $task = $db->fetch_array($query);

    if ($task) {
        $db->update_query('tasks', ['enabled' => $action], "file='{$taskFile}'");
    } else {
        include_once MYBB_ROOT . 'inc/functions_task.php';

        $_ = $db->escape_string('*');

        $new_task = [
            'title' => $db->escape_string($pluginInfo['name']),
            'description' => $db->escape_string($lang->forumcleaner_task_desc),
            'file' => $db->escape_string($taskFile),
            'minute' => 0,
            'hour' => $_,
            'day' => $_,
            'weekday' => $_,
            'month' => $_,
            'enabled' => 1,
            'logging' => 1
        ];

        $new_task['nextrun'] = fetch_next_run($new_task);

        $db->insert_query('tasks', $new_task);
    }

    return true;
}

function disableTask(): bool
{
    enableTask(TASK_DEACTIVATE);

    return true;
}

function deleteTask(): bool
{
    enableTask(TASK_DELETE);

    return true;
}

function dbTables(): array
{
    $tables_data = [];

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tables_data[$tableName][$fieldName] = dbBuildFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tables_data[$tableName]['primary_key'] = $fieldName;
            }

            if ($fieldName === 'unique_key') {
                $tables_data[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tables_data;
}

function dbVerifyTables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$tableName}` (";

            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName != 'unique_key') {
                    $query_string .= "`{$fieldName}` {$fieldData},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(): bool
{
    global $db;

    foreach (dbTables() as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableColumns['unique_key'])) {
            foreach ($tableColumns['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($tableName, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$tableName} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }

    return true;
}

function dbBuildFieldDefinition(array $fieldData): string
{
    $field_definition = '';

    $field_definition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $field_definition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $field_definition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $field_definition;
}