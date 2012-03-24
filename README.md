# xoops-watch-template.php

Template auto-update tool for XOOPS.

![image](https://github.com/suin/xoops-watch-template/raw/master/image.png)

## Install

There are two ways to install.

```
# install via git
git clone git://github.com/suin/xoops-watch-template.git

# install via wget
wget https://raw.github.com/suin/xoops-watch-template/master/xoops-watch-template.php
```

## Usage

```
php xoops-watch-template.php <mainfile_path> <module_dirname> <watch_dir>
```

or

```
chomod +x xoops-watch-template.php
./xoops-watch-template.php <mainfile_path> <module_dirname> <watch_dir>
```

* mainfile_path  : Full path to mainfile.php
* module_dirname : Module directory name
* watch_dir      : Directory to watch

### Usage example

Auto update bulletin module templates.

```
php xoops-watch-template.php /var/www/html/mainfile.php \
                             bulletin \
                             /var/www/xoops_trust_path/modules/bulletin/templates/
```
