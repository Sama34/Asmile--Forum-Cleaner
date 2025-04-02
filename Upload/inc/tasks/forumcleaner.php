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

function task_forumcleaner(array &$task): array
{
    forumcleaner_task($task);

    return $task;
}// function task_forumcleaner($task)