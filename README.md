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
php xoops-watch-template.php <mainfile_path> 
```

or

```
chomod +x xoops-watch-template.php
./xoops-watch-template.php <mainfile_path>
```

* mainfile_path  : Full path to mainfile.php

### Usage example

Auto update bulletin module templates.

```
php xoops-watch-template.php /var/www/html/mainfile.php
```
