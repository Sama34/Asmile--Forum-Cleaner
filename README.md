<p align="center">
    <a href="" rel="noopener">
        <img width="700" height="400" src="https://github.com/user-attachments/assets/dab8a886-4c35-4fac-adb8-aa0653ea467b" alt="Project logo">
    </a>
</p>

<h3 align="center">MyBB Forum Cleaner</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/MyBB-Forum-Cleaner.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/MyBB-Forum-Cleaner.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> A MyBB plugin to help Administrators keep things clean.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
    - [Template Modifications](#template_modifications)
- [Settings](#settings)
- [Templates](#templates)
- [Usage](#usage)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

A MyBB plugin to help Administrators keep things clean.

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [MyBB-PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) >= 13

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── inc
   │ ├── languages
   │ │ ├── english
   │ │ │ ├── admin
   │ │ │ │ ├── forumcleaner.lang.php
   │ │ │ ├── forumcleaner.lang.php
   │ ├── plugins
   │ │ ├── ougc
   │ │ │ ├── ForumCleaner
   │ │ │ │ ├── hooks
   │ │ │ │ │ ├── admin.php
   │ │ │ ├── templates
   │ │ │ │ ├── forumbit.html)
   │ │ │ │ ├── threadlist.html
   │ │ │ ├── settings.json
   │ │ │ ├── admin.php
   │ │ │ ├── core.php
   │ │ ├── forumcleaner.php
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package from the [MyBB Extend](https://community.mybb.com/mods.php) site or
   from the [repository releases](https://github.com/OUGC-Network/MyBB-Forum-Cleaner/releases/latest).
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Configuration » Plugins_ and install this plugin by clicking _Install & Activate_.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.

### Template Modifications <a name = "template_modifications"></a>

To display Awards data it is required that you edit the following template for each of your themes.

1. Place `{$forum['ForumCleanerMessages']}` in the `forumbit_depth2_forum` template to display a description for each
   rule in the forum bits.
2. Place `{$foruminfo['ForumCleanerMessages']}` in the `forumdisplay` template to display a description for each
   rule in the forum display page.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Main Settings

- **Thread transactions limit** `numeric`
    - _Limit number of threads processed in one Forum at one run for one action.
    - _Default 30 (if not set or set to 0)._
- **Users transactions limit** `numeric`
    - _Limit number of users deleted during one run._
    - _Default 50 (if not set or set to 0)._
- **Grace period for Awaiting Activation users** `numeric`
    - _Input days of Grace period for Awaiting Activation users. After this period from registration date users will be
      deleted._
    - _No users will be deleted if set to 0._
- **Grace period for inactive users** `numeric`
    - _Input days of Grace period for inactive users (with no posts). After this period from last visit users will be
      deleted._
    - _No users will be deleted if set to 0._
- **Group exception list for inactive users** `select`
    - _Administrators will be not deleted as inactive users. Here you can add additional exceptions._

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin.

- `forumcleaner_forumbit`
    - _front end_;
- `forumcleaner_threadlist`
    - _front end_;

[Go up to Table of Contents](#table_of_contents)

## 📖 Usage <a name="usage"></a>

This plugin has no additional configurations; after activating make sure to modify the global settings in order to get
this plugin working.

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)