<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DependencyLoader;

use Composer\Autoload\ClassMapGenerator;
use Symfony\Component\Yaml\Parser;

class PackagesPhpFile
{
    private string $pluginDir;

    public function __construct(string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/') . '/';
    }

    public function save(): void
    {
        $installedPackages = $this->readInstalledPackages();
        $packagesToShipWithPlugin = $this->filterPackages($installedPackages);
        $packagesToShipWithPlugin = $this->buildClassMaps($packagesToShipWithPlugin);
        $this->generatePackagesPhpForPackages($packagesToShipWithPlugin);
    }

    private function readInstalledPackages(): array
    {
        echo "################################\n";
        echo "## Reading installed packages ##\n";
        echo "################################\n\n";

        $installedJsonPath = $this->pluginDir . 'vendor/composer/installed.json';

        $installed = json_decode(file_get_contents($installedJsonPath), true, 512, JSON_THROW_ON_ERROR);
        if (isset($installed['packages'])) {
            // Composer 2
            $installedPackages = $installed['packages'];
        } else {
            // Composer 1
            $installedPackages = $installed;
        }
        $installedPackages = array_map(
            function (array $package) {
                return [
                    'name' => $package['name'],
                    'version_normalized' => $package['version_normalized'],
                    'autoload' => $package['autoload'] ?? [],
                    'type' => $package['type'],
                ];
            },
            $installedPackages,
        );

        printf("%d installed packages found\n\n", count($installedPackages));

        return $installedPackages;
    }

    private function filterPackages(array $installedPackages): array
    {
        echo "#########################################################\n";
        echo "## Filtering dependencies that are shipped by Shopware ##\n";
        echo "#########################################################\n\n";

        $dependencyExcludeList = (new Parser())->parseFile(__DIR__ . '/../Resources/dependency-exclude-list.yaml');
        $packagesToShipWithPlugin = array_values(array_filter(
            $installedPackages,
            function ($package) use ($dependencyExcludeList) {
                if (mb_strtolower($package['type']) === 'composer-plugin') {
                    // Composer plugins have to be shipped always otherwise when Shopware tries to load the plugin
                    // package composer will fail with a cryptic error message because it cannot find the plugin.
                    return true;
                }

                return !$this->shouldPackageBeExcluded($package, $dependencyExcludeList);
            },
        ));

        echo "The following dependencies will be shipped with this plugin:\n\n";

        foreach ($packagesToShipWithPlugin as $package) {
            printf("    %s: %s\n", $package['name'], $package['version_normalized']);
        }

        printf("\nTotal: %s\n\n", count($packagesToShipWithPlugin));

        return $packagesToShipWithPlugin;
    }

    private function shouldPackageBeExcluded(array $package, array $excludeList): bool
    {
        return $this->shouldPackageBeExcludedUsingPackageProperty($package['name'], $excludeList['by-name'])
            || $this->shouldPackageBeExcludedUsingPackageProperty($package['type'], $excludeList['by-type']);
    }

    private function shouldPackageBeExcludedUsingPackageProperty(string $packageProperty, array $excludeList): bool
    {
        if (!array_key_exists($packageProperty, $excludeList)) {
            // The given package does not appear in the exclusion list, so there aren't any restrictions on that
            // package. It can be shipped
            return false;
        }

        $excludeListByNameEntry = $excludeList[$packageProperty];
        if ($excludeListByNameEntry === true) {
            // The given package is in the exclusion list without any conditions (it is `true`). It cannot be shipped.
            return true;
        }

        // The given package is in the exclusion list with a 'plugin' condition. Check the plugin (name) for which this
        // Package.php file is generated, to determine wether or not the package should be shipped.
        if (array_key_exists('plugin', $excludeListByNameEntry)) {
            $pluginRegex = $excludeListByNameEntry['plugin'];
            $pluginComposerJson = json_decode(file_get_contents($this->pluginDir . 'composer.json'), true);

            return $this->testValueAgainstModifiedRegex($pluginRegex, $pluginComposerJson['name']);
        }

        return false;
    }

    /**
     * As regular expressions themselves do not implement a negation character, this function supports ! as a global
     * negation before the opening delimiter of the expression. Example: !/some-regular-expression/
     */
    private function testValueAgainstModifiedRegex(string $modifiedRegex, string $value): bool
    {
        if (str_starts_with($modifiedRegex, '!')) {
            $negate = true;
            $regex = mb_substr($modifiedRegex, 1);
        } else {
            $negate = false;
            $regex = $modifiedRegex;
        }
        $result = preg_match($regex, $value) === 1;

        return $negate ? !$result : $result;
    }

    private function generatePackagesPhpForPackages(array $packagesToShipWithPlugin): void
    {
        echo "#############################\n";
        echo "## Generating Packages.php ##\n";
        echo "#############################\n\n";

        $packagesPhpFileName = $this->pluginDir . 'Packages.php';

        printf("Destination path: %s\n\n", $packagesPhpFileName);

        $packagesPhpTemplate = <<<PACKAGES_PHP_TEMPLATE
<?php
// THIS FILE IS AUTO-GENERATED.
// Do not modify it, your changes will be lost after the next "composer install/updates" execution.
// This file contains a list of composer packages that are shipped with this plugin and their autoloader information.
// This file is explicitly a PHP file (and not JSON or YAML) to make it cacheable for the OpCache.
return %s;
PACKAGES_PHP_TEMPLATE;
        $packagesPhpContents = sprintf($packagesPhpTemplate, var_export($packagesToShipWithPlugin, true));
        file_put_contents($packagesPhpFileName, $packagesPhpContents);

        echo "Packages.php has been generated!\n\n";
    }

    private function buildClassMaps(array $packages): array
    {
        foreach ($packages as &$package) {
            $classMap = $package['autoload']['classmap'] ?? [];
            $excludeFromClassMap = $package['autoload']['exclude-from-classmap'] ?? [];
            if (count($excludeFromClassMap) !== 0) {
                $blacklistRegex = self::convertBlacklistToRegex($excludeFromClassMap);
            } else {
                $blacklistRegex = null;
            }
            $classMaps = [];
            foreach ($classMap as $classMapPath) {
                $dependencyPath = $this->pluginDir . 'vendor/' . $package['name'] . '/';
                $classMap = ClassMapGenerator::createMap($dependencyPath . $classMapPath, $blacklistRegex);
                $classMapWithRelativePaths = $this->convertAbsolutePathsToRelativePaths($classMap, $dependencyPath);
                $classMaps[] = $classMapWithRelativePaths;
            }

            $package['autoload']['classmap'] = array_merge([], ...$classMaps);
            unset($package['autoload']['exclude-from-classmap']);
        }

        return $packages;
    }

    /**
     * Converts an array of matching-pattern to a single regex string.
     *
     * @param string[] $blacklist
     */
    private static function convertBlacklistToRegex(array $blacklist): string
    {
        return '{(' . implode('|', $blacklist) . ')}';
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function convertAbsolutePathsToRelativePaths(array $paths, string $basePath): array
    {
        foreach ($paths as &$path) {
            if (mb_strpos($path, $basePath) === 0) {
                $path = mb_substr($path, mb_strlen($basePath));
            }
        }

        return $paths;
    }
}
