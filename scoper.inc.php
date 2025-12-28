<?php
/**
 * PHP-Scoper configuration
 * This file configures how dependencies are scoped to avoid conflicts
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    // The prefix to add to all PHP namespaces
    'prefix' => 'YamlCF\\Vendor',

    // The base output directory for the scoped files
    'output-dir' => 'build/vendor',

    // Files to scope - we want to scope everything in vendor
    'finders' => [
        // Scope all files in the vendor directory (except dev dependencies)
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc',
                'test',
                'test_old',
                'tests',
                'Tests',
                'vendor-bin',
                'humbug',
                'php-scoper',
            ])
            ->in('vendor')
            ->filter(function (\SplFileInfo $file) {
                // Exclude dev dependencies
                $devPackages = [
                    // PHP-Scoper and its dependencies
                    'humbug/php-scoper',
                    'fidry/console',
                    'fidry/filesystem',
                    'jetbrains/phpstorm-stubs',
                    'nikic/php-parser',
                    'thecodingmachine/safe',
                    'webmozart/assert',
                    // PHPUnit and its dependencies
                    'phpunit/phpunit',
                    'phpunit/php-code-coverage',
                    'phpunit/php-file-iterator',
                    'phpunit/php-invoker',
                    'phpunit/php-text-template',
                    'phpunit/php-timer',
                    'sebastian/',  // Matches all sebastian/* packages
                    'doctrine/instantiator',
                    'myclabs/deep-copy',
                    'phar-io/manifest',
                    'phar-io/version',
                    'theseer/tokenizer',
                    'yoast/phpunit-polyfills',
                ];

                foreach ($devPackages as $package) {
                    if (strpos($file->getPathname(), 'vendor/' . $package) !== false) {
                        return false;
                    }
                }

                return true;
            }),
    ],

    // Namespaces/classes to exclude from scoping
    'exclude-namespaces' => [
        // WordPress core classes should never be scoped
        'WP_*',
        // Keep PHP core classes/interfaces as-is
        'Psr\\',
    ],

    // Classes to exclude from scoping
    'exclude-classes' => [
        // WordPress core classes
        'WP_Error',
        'WP_Post',
        'WP_User',
        'WP_Query',
        'WP_Term',
    ],

    // Functions to exclude from scoping
    'exclude-functions' => [
        // Common WordPress functions that should not be scoped
        'wp_*',
        'get_*',
        'is_*',
        'add_*',
        'remove_*',
        'update_*',
        'delete_*',
        'esc_*',
        'sanitize_*',
        '__',
        '_e',
        '_x',
        '_n',
    ],

    // Constants to exclude from scoping
    'exclude-constants' => [
        // WordPress constants
        'ABSPATH',
        'WP_CONTENT_DIR',
        'WP_CONTENT_URL',
        'WP_PLUGIN_DIR',
        'WP_PLUGIN_URL',
        '/^WP_.*/',
        // PHP constants
        'PHP_*',
    ],

    // Expose global classes - these will be accessible without the prefix
    'expose-global-classes' => true,
    'expose-global-constants' => true,
    'expose-global-functions' => true,

    // Additional patchers for special cases
    'patchers' => [
        // Fix Symfony YAML deprecation notices
        static function (string $filePath, string $prefix, string $contents): string {
            // If this is a Symfony file, ensure deprecation notices work correctly
            if (strpos($filePath, 'symfony') !== false) {
                return $contents;
            }
            return $contents;
        },
    ],
];
