<?php

namespace Bolt\Composer;

use Bolt\Exception\LowlevelException;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandler
{
    /** @var \Silex\Application */
    private static $app;

    /**
     * Install Bolt's assets.
     *
     * This should be ran on "post-autoload-dump" event.
     *
     * @param Event $event
     */
    public static function installAssets(Event $event)
    {
        $webDir = static::getWebDir($event);
        if ($webDir === null) {
            return;
        }

        $filesystem = new Filesystem();

        $originDir = __DIR__ . '/../../app/view/';
        $targetDir = $webDir . '/bolt-public/view/';

        $event->getIO()->writeError(sprintf('Installing assets to <info>%s</info>', rtrim($targetDir, '/')));
        foreach (['css', 'fonts', 'img', 'js'] as $dir) {
            $filesystem->mirror($originDir . $dir, $targetDir . $dir, null, ['override' => true, 'delete' => true]);
        }
    }

    /**
     * Install Bolt's default themes and files.
     *
     * This should be ran on "post-create-project-cmd" event.
     *
     * @param Event $event
     */
    public static function installThemesAndFiles(Event $event)
    {
        static::configureDirMode($event);

        $webDir = static::getWebDir($event);
        if ($webDir === null) {
            return;
        }

        $filesystem = new Filesystem();

        $root = __DIR__ . '/../../';

        $target = static::getDir($event, 'files');
        $event->getIO()->writeError(sprintf('Installing <info>files</info> to <info>%s</info>', $target));
        $filesystem->mirror($root . 'files', $target, null, ['override' => true]);

        $target = static::getDir($event, 'themebase');
        $event->getIO()->writeError(sprintf('Installing <info>themes</info> to <info>%s</info>', $target));
        $filesystem->mirror($root . 'theme', $target, null, ['override' => true]);
    }

    /**
     * Gets the directory mode value, sets umask with it, and returns it.
     *
     * @param Event $event
     *
     * @return number
     */
    protected static function configureDirMode(Event $event)
    {
        $dirMode = static::getOption($event, 'dir-mode', 0777);
        $dirMode = is_string($dirMode) ? octdec($dirMode) : $dirMode;

        umask(0777 - $dirMode);

        return $dirMode;
    }

    /**
     * Gets the web directory either from configured application or composer's extra section/environment variable.
     *
     * If the web directory doesn't exist an error is emitted and null is returned.
     *
     * @param Event $event
     *
     * @return string|null
     */
    protected static function getWebDir(Event $event)
    {
        $webDir = static::getDir($event, 'web', 'public');

        if (!is_dir($webDir)) {
            $error = '<error>The web directory (%s) was not found in %s, can not install assets.</error>';
            $event->getIO()->write(sprintf($error, $webDir, getcwd()));

            return null;
        }

        return $webDir;
    }

    /**
     * Gets the directory requested either from configured application or composer's extra section/environment variable.
     *
     * @param Event       $event
     * @param string      $name
     * @param string|null $default
     *
     * @return string
     */
    protected static function getDir(Event $event, $name, $default = null)
    {
        try {
            $app = static::getApp($event);

            $dir = $app['resources']->getPath($name);
        } catch (LowlevelException $e) {
            $dir = static::getOption($event, $name . '-dir', $default);
        }

        return rtrim($dir, '/');
    }

    /**
     * Loads the application once from bootstrap file (which is configured with .bolt.yml/.bolt.php file).
     *
     * NOTE: This only works on the "post-autoload-dump" command as the autoload.php file has not been generated before
     * that point.
     *
     * @param Event $event
     *
     * @return \Silex\Application
     */
    protected static function getApp(Event $event)
    {
        if (static::$app === null) {
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            static::$app = require $vendorDir . '/bolt/bolt/app/bootstrap.php';
        }

        return static::$app;
    }

    /**
     * Get an option from environment variable or composer's extra section.
     *
     * Example: With key "dir-mode" it checks for "BOLT_DIR_MODE" environment variable,
     * then "bolt-dir-mode" in composer's extra section, then returns given default value.
     *
     * @param Event  $event
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected static function getOption(Event $event, $key, $default = null)
    {
        $key = 'bolt-' . $key;

        if ($value = getenv(strtoupper(str_replace('-', '_', $key)))) {
            return $value;
        }

        $extra = $event->getComposer()->getPackage()->getExtra();

        return isset($extra[$key]) ? $extra[$key] : $default;
    }
}
