<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file CacheCommand.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Console;

use RZ\Roadiz\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Command line utils for managing Cache from terminal.
 */
class CacheCommand extends Command
{
    protected function configure()
    {
        $this->setName('cache')
            ->setDescription('Manage cache and compiled data.')
            ->addOption(
                'infos',
                null,
                InputOption::VALUE_NONE,
                'Get informations about caches.'
            )
            ->addOption(
                'clear-doctrine',
                null,
                InputOption::VALUE_NONE,
                'Clear doctrine metadata cache and entities proxies.'
            )
            ->addOption(
                'clear-routes',
                null,
                InputOption::VALUE_NONE,
                'Clear compiled route collections.'
            )
            ->addOption(
                'clear-assets',
                null,
                InputOption::VALUE_NONE,
                'Clear compiled route collections'
            )
            ->addOption(
                'clear-templates',
                null,
                InputOption::VALUE_NONE,
                'Clear compiled Twig templates.'
            )
            ->addOption(
                'clear-translations',
                null,
                InputOption::VALUE_NONE,
                'Clear compiled translations catalogues.'
            )
            ->addOption(
                'clear-all',
                null,
                InputOption::VALUE_NONE,
                'Clear all caches (Doctrine, proxies, routes, templates, assets and translations)'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $text="";

        if ($input->getOption('infos')) {
            $text .= static::getInformations();
        } elseif ($input->getOption('clear-all')) {
            $text .= static::clearDoctrine();
            $text .= static::clearRouteCollections();
            $text .= static::clearCachedAssets();
            $text .= static::clearTemplates();
            $text .= static::clearTranslations();

            $text .= '<info>All caches have been been purged…</info>'.PHP_EOL;
        } else {
            if ($input->getOption('clear-doctrine')) {
                $text .= static::clearDoctrine();
            }

            if ($input->getOption('clear-routes')) {
                $text .= static::clearRouteCollections();
            }

            if ($input->getOption('clear-assets')) {
                $text .= static::clearCachedAssets();
            }

            if ($input->getOption('clear-templates')) {
                $text .= static::clearTemplates();
            }

            if ($input->getOption('clear-translations')) {
                $text .= static::clearTranslations();
            }
        }

        $output->writeln($text);
    }

    public static function getInformations()
    {
        $text = '';

        $cacheDriver = Kernel::getService('em')->getConfiguration()->getResultCacheImpl();
        $text .= "Result cache driver: ".get_class($cacheDriver).PHP_EOL;

        $cacheDriver = Kernel::getService('em')->getConfiguration()->getHydrationCacheImpl();
        $text .= "Hydratation cache driver: ".get_class($cacheDriver).PHP_EOL;

        $cacheDriver = Kernel::getService('em')->getConfiguration()->getQueryCacheImpl();
        $text .= "Query cache driver: ".get_class($cacheDriver).PHP_EOL;

        $cacheDriver = Kernel::getService('em')->getConfiguration()->getMetadataCacheImpl();
        $text .= "Metadata cache driver: ".get_class($cacheDriver).PHP_EOL;

        return $text;
    }

    /**
     * Clear doctrine caches and rebuild entities proxies.
     *
     * @return string
     */
    public static function clearDoctrine()
    {
        $text = '';
        // Empty result cache
        $cacheDriver = Kernel::getService('em')->getConfiguration()->getResultCacheImpl();
        if ($cacheDriver !== null) {
            $text .= 'Result cache: '.$cacheDriver->getNamespace().' — ';
            $text .= $cacheDriver->deleteAll() ? 'OK' : 'FAIL';
            $text .= PHP_EOL;
        }

        // Empty hydratation cache
        $cacheDriver = Kernel::getService('em')->getConfiguration()->getHydrationCacheImpl();
        if ($cacheDriver !== null) {
            $text .= 'Hydratation cache: '.$cacheDriver->getNamespace().' — ';
            $text .= $cacheDriver->deleteAll() ? 'OK' : 'FAIL';
            $text .= PHP_EOL;
        }

        // Empty query cache
        $cacheDriver = Kernel::getService('em')->getConfiguration()->getQueryCacheImpl();
        if ($cacheDriver !== null) {
            $text .= 'Query cache: '.$cacheDriver->getNamespace().' — ';
            $text .= $cacheDriver->deleteAll() ? 'OK' : 'FAIL';
            $text .= PHP_EOL;
        }

        // Empty metadata cache
        $cacheDriver = Kernel::getService('em')->getConfiguration()->getMetadataCacheImpl();
        if ($cacheDriver !== null) {
            $text .= 'Metadata cache: '.$cacheDriver->getNamespace().' — ';
            $text .= $cacheDriver->deleteAll() ? 'OK' : 'FAIL';
            $text .= PHP_EOL;
        }

        /*
         * Recreate proxies files
         */
        $fs = new Filesystem();
        $finder = new Finder();
        $finder->files()->in(ROADIZ_ROOT . '/gen-src/Proxies');
        $fs->remove($finder);

        $meta = Kernel::getService('em')->getMetadataFactory()->getAllMetadata();
        $proxyFactory = Kernel::getService('em')->getProxyFactory();
        $proxyFactory->generateProxyClasses($meta, ROADIZ_ROOT . '/gen-src/Proxies');
        $text .= '<info>Doctrine proxy classes has been purged…</info>'.PHP_EOL;

        return $text;
    }

    /**
     * Clear compiled route collections.
     *
     * @return string
     */
    public static function clearRouteCollections()
    {
        $text = '';

        $fs = new Filesystem();
        $finder = new Finder();
        $finder->files()->in(ROADIZ_ROOT . '/gen-src/Compiled');
        $fs->remove($finder);

        $text .= '<info>Compiled route collections have been purged…</info>'.PHP_EOL;

        return $text;
    }

    /**
     * Clear compiled route collections.
     *
     * @return string
     */
    public static function clearCachedAssets()
    {
        $text = '';

        $fs = new Filesystem();
        $finder = new Finder();

        if (file_exists(ROADIZ_ROOT . '/cache/request') &&
            file_exists(ROADIZ_ROOT . '/cache/rendered')) {
            $finder->in(ROADIZ_ROOT . '/cache/request')
                   ->in(ROADIZ_ROOT . '/cache/rendered');
            $fs->remove($finder);

            $text .= '<info>Assets cache has been purged…</info>'.PHP_EOL;
        }

        return $text;
    }

    /**
     * Clear compiled route collections.
     *
     * @return string
     */
    public static function clearTemplates()
    {
        $text = '';

        $fs = new Filesystem();
        $finder = new Finder();
        $finder->in(ROADIZ_ROOT . '/cache/twig_cache');
        $fs->remove($finder);

        $text .= '<info>Compiled Twig templates have been purged…</info>'.PHP_EOL;

        return $text;
    }

    /**
     * Clear compiled translation catalogues.
     *
     * @return string
     */
    public static function clearTranslations()
    {
        $text = '';

        $fs = new Filesystem();
        $finder = new Finder();
        $finder->in(ROADIZ_ROOT . '/cache/translations');
        $fs->remove($finder);

        $text .= '<info>Compiled translation catalogues have been purged…</info>'.PHP_EOL;

        return $text;
    }
}
