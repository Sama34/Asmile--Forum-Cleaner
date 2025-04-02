<?php

/***************************************************************************
 *
 *    Forum Cleaner plugin (/inc/tasks/forumcleaner.php)
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

use function ForumCleaner\Core\executeTask;

function task_forumcleaner(array &$task): array
{
    executeTask();

    return $task;
}// function task_forumcleaner($task)