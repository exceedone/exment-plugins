<?php
// The class "Plugin" of each plugin in this repository whose config.json declares several types
// (ExmentGroupware, ExmentPM, ExmentSupportDesk, ExmentPMBacklog ...).
//
// Exment loads Plugin<Type> (PluginPage, PluginBatch ...) when plugins.plugin_types lists several
// types, and a class called Plugin when it lists one. A site whose files were replaced without
// uploading the zip can keep an older row with one type ("page"): Exment then builds Plugin and
// calls _getLoadView() on it on every request (ExmentServiceProvider::load()). An empty Plugin
// class there is a fatal error on every page, login included.
//
// So for each such plugin: Plugin.php is either not there (Exment finds no file and skips it), or
// its Plugin is the plugin's main type (the first in plugin_type): a subclass of Plugin<Main>,
// built with the plugin only, with _getLoadView() for a page. With a PluginSetting, Plugin also
// gives the settings form of it (the plugin screen asks the same class for it).
//
//   php groupware/tools/test-plugin-class.php            (stubs of Exment's base classes)
//   php groupware/tools/test-plugin-class.php --exment   (Exment from .github/ci/vendor; composer update there first)

namespace {
    $withExment = in_array('--exment', $argv ?? [], true);
    if ($withExment) {
        require __DIR__ . '/../../.github/ci/bootstrap.php';
    }
}

// Only what the page and setting classes of the plugins need to be declared; not used with --exment.
namespace Exceedone\Exment\Services\Plugin {
    if (!class_exists(PluginPageBase::class)) {
        trait PluginBase
        {
            protected $plugin;

            public function useCustomOption()
            {
                return false;
            }

            public function setCustomOptionForm(&$form)
            {
            }
        }

        class PluginPageBase
        {
            use PluginBase;

            public function __construct($plugin)
            {
                $this->plugin = $plugin;
            }

            public function _getLoadView()
            {
                return null;
            }
        }
    }
}

namespace {
    $root = realpath(__DIR__ . '/../..');
    $ok = 0;
    $bad = 0;
    $check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
        echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
        $cond ? $ok++ : $bad++;
    };
    $pascal = function (string $s): string {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $s)));
    };

    // the plugin folders: a config.json with plugin_name, uuid and plugin_type
    $plugins = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        if ($file->getFilename() !== 'config.json' || preg_match('#/(\.git|\.github|vendor|node_modules)/#', $path)) {
            continue;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['plugin_name']) || empty($json['uuid']) || empty($json['plugin_type'])) {
            continue;
        }
        $plugins[dirname($path)] = $json;
    }
    ksort($plugins);
    $check('plugin folders found', count($plugins) >= 5, array_keys($plugins));

    // App\Plugins\<Name>\X from the folder of the plugin, as Exment's autoloader
    $namespaces = [];
    spl_autoload_register(function ($class) use (&$namespaces) {
        foreach ($namespaces as $prefix => $dir) {
            if (strpos($class, $prefix) === 0) {
                $file = $dir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });

    $baseOf = [
        'page' => 'Exceedone\Exment\Services\Plugin\PluginPageBase',
    ];

    $multi = 0;
    foreach ($plugins as $dir => $json) {
        $types = array_values(array_filter(array_map('trim', explode(',', strtolower((string) $json['plugin_type'])))));
        $rel = ltrim(substr($dir, strlen(str_replace(DIRECTORY_SEPARATOR, '/', $root))), '/');
        if (count($types) < 2) {
            continue;
        }
        $multi++;
        $ns = 'App\Plugins\\' . $pascal((string) $json['plugin_name']) . '\\';
        $namespaces[$ns] = $dir;
        $main = $types[0];

        if (!is_file($dir . '/Plugin.php')) {
            $check("$rel: no Plugin.php (Exment skips the plugin on a one-type row)", true);
            continue;
        }

        $plugin = $ns . 'Plugin';
        $mainClass = $ns . 'Plugin' . $pascal($main);
        $check("$rel: class Plugin is in Plugin.php", class_exists($plugin));
        if (!class_exists($plugin)) {
            continue;
        }
        $ref = new ReflectionClass($plugin);
        $check("$rel: Plugin can be built (not abstract)", $ref->isInstantiable());
        $check("$rel: Plugin is the main type $main (a subclass of Plugin" . $pascal($main) . ')', class_exists($mainClass) && $ref->isSubclassOf($mainClass), $ref->getParentClass() ? $ref->getParentClass()->getName() : null);
        if (isset($baseOf[$main])) {
            $check("$rel: Plugin is a $baseOf[$main]", $ref->isSubclassOf($baseOf[$main]));
        }
        $ctor = $ref->getConstructor();
        $check("$rel: Plugin is built with the plugin only", $ctor !== null && $ctor->getNumberOfRequiredParameters() <= 1 && $ctor->getNumberOfParameters() >= 1);
        if ($main === 'page') {
            $check("$rel: Plugin::_getLoadView() can be called", $ref->hasMethod('_getLoadView') && $ref->getMethod('_getLoadView')->isPublic() && !$ref->getMethod('_getLoadView')->isAbstract());
        }
        if (is_file($dir . '/PluginSetting.php')) {
            foreach (['useCustomOption', 'setCustomOptionForm'] as $m) {
                $check("$rel: Plugin::$m() is the one of PluginSetting", $ref->hasMethod($m) && $ref->getMethod($m)->getDeclaringClass()->getName() === $plugin);
            }
        }

        // with Exment: the class name Exment builds on a row with the main type only
        if ($withExment) {
            $short = \Exceedone\Exment\Enums\PluginType::getPluginClassShortName(null, (object) ['plugin_types' => [$main]]);
            $check("$rel: Exment's class name on a one-type row is Plugin", $short === 'Plugin', $short);
        }
    }
    $check('plugins with several types found', $multi >= 3, $multi);

    echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
    exit($bad === 0 ? 0 : 1);
}
