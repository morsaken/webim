# Webim framework

Required PHP version above 5.4

# Usage

Include core.php file to your main file

    define('DS', DIRECTORY_SEPARATOR);

    // I recommend that; use the framework outside of the pub root
    define('SYS_ROOT', __DIR__ . DS . '..' . DS . 'sys' . DS)

    require(SYS_ROOT . 'Webim' . DS . 'core.php');

# See

You can see usage in a [Content Management System](https://github.com/morsaken/webim-cms).
