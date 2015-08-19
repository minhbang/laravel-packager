<?php

namespace JeroenG\Packager;

use Illuminate\Console\Command;

/**
 * Create a brand new package.
 *
 * @package Packager
 * @author JeroenG
 *
 **/
class PackagerNewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "packager:new {vendor} {name}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new package.';

    /**
     * Packager helper class
     *
     * @var object
     */
    protected $helper;

    /**
     * Create a new command instance.
     *
     * @param \JeroenG\Packager\PackagerHelper $helper
     */
    public function __construct(PackagerHelper $helper)
    {
        parent::__construct();
        $this->helper = $helper;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Start the progress bar
        $bar = $this->helper->barSetup($this->output->createProgressBar(8));
        $bar->start();

        // Common variables
        $vendor = $this->argument('vendor');
        $name = $this->argument('name');
        $path = getcwd() . '/packages/';
        $fullPath = $path . $vendor . '/' . $name;
        $cVendor = $this->helper->makeName($vendor);
        $cName = $this->helper->makeName($name);
        $requireSupport = '"illuminate/support": "~5.1",
        "php" : ' . '"' . config('packager.php_required') . '"';
        $requirement = '"psr-4": {
            "' . $cVendor . '\\\\' . $cName . '\\\\": "packages/' . $vendor . '/' . $name . '/src",';
        $appConfigLine = 'App\Providers\RouteServiceProvider::class,
        ' . $cVendor . '\\' . $cName . '\\' . $cName . 'ServiceProvider::class,';

        // Start creating the package        
        $this->info('Creating package ' . $vendor . '\\' . $name . '...');
        $this->helper->checkExistingPackage($path, $vendor, $name);
        $bar->advance();

        // Create the package directory
        $this->info('Creating packages directory...');
        $this->helper->makeDir($path);
        $bar->advance();

        // Create the vendor directory
        $this->info('Creating vendor...');
        $this->helper->makeDir($path . $vendor);
        $bar->advance();

        // Get the skeleton repo from the PHP League
        $this->info('Downloading skeleton...');
        $this->helper->download(
            $zipFile = $this->helper->makeFilename(),
            'http://github.com/thephpleague/skeleton/archive/master.zip'
        )
            ->extract($zipFile, $path . $vendor)
            ->cleanUp($zipFile);
        rename($path . $vendor . '/skeleton-master', $fullPath);
        $bar->advance();

        // Creating a Laravel Service Provider in the src directory
        $this->info('Creating service provider...');
        $newProvider = $fullPath . '/src/' . $cName . 'ServiceProvider.php';
        $this->helper->replaceAndSave(
            __DIR__ . '/ServiceProvider.stub',
            ['{{vendor}}', '{{name}}'],
            [$cVendor, $cName],
            $newProvider
        );
        $bar->advance();

        // Replacing skeleton namespaces
        $this->info('Replacing skeleton namespaces...');
        $this->helper->replaceAndSave(
            $fullPath . '/src/SkeletonClass.php',
            'namespace League\Skeleton;',
            'namespace ' . $cVendor . '\\' . $cName . ';'
        );

        // Replacing on composer.json
        $search = [
            'thephpleague',
            'league',
            ':package_name',
            '"php" : ">=5.3.0"',
            'League\\\\Skeleton\\\\',
            'League\\\\Skeleton\\\\Test\\\\',
            ':author_name',
            ':author_email',
            ':author_website',
            ':package_description',
        ];
        $replace = [
            config('packager.author_username'),
            $vendor,
            $name,
            $requireSupport,
            $cVendor . '\\\\' . $cName . '\\\\',
            $cVendor . '\\\\' . $cName . '\\\\Test\\\\',
            config('packager.author_name'),
            config('packager.author_email'),
            config('packager.author_website'),
            $this->helper->makeWords($cName) . ' Package',
        ];
        $this->helper->replaceAndSave($fullPath . '/composer.json', $search, $replace);
        $bar->advance();

        // Replacing correct information
        $this->info('Replacing correct information...');
        $this->helper->replaceAndSave(
            $fullPath . '/CHANGELOG.md',
            [':package_name'],
            ["$vendor/$name"]
        );

        $this->helper->replaceAndSave(
            $fullPath . '/CONTRIBUTING.md',
            ['thephpleague', ':package_name'],
            [config('packager.author_username'), $name]
        );

        $this->helper->replaceAndSave(
            $fullPath . '/LICENSE.md',
            [':author_name', ':author_email'],
            [config('packager.author_name'), config('packager.author_email')]
        );

        $this->helper->replaceAndSave(
            $fullPath . '/README.md',
            [
                'thephpleague',
                ':author_username',
                'league',
                ':package_name',
                ':author_name',
                '[link-author]',
            ],
            [
                config('packager.author_username'),
                config('packager.author_username'),
                $vendor,
                $name,
                config('packager.author_name'),
                '[' . config('packager.author_website') . ']',
            ]
        );
        $bar->advance();

        // Add it to composer.json
        $this->info('Adding package to composer and app...');
        $this->helper->replaceAndSave(getcwd() . '/composer.json', '"psr-4": {', $requirement);
        // And add it to the providers array in config/app.php
        $this->helper->replaceAndSave(
            getcwd() . '/config/app.php',
            'App\Providers\RouteServiceProvider::class,',
            $appConfigLine
        );
        $bar->advance();

        // Finished creating the package, end of the progress bar
        $bar->finish();
        $this->info('Package created successfully!');
        $bar = null;
        if (app()->environment('local') && $finish_cmd = config('packager.finish_cmd')) {
            $this->output->newLine(1);
            $this->info('Running finish command...');
            $this->output->newLine(1);
            shell_exec($finish_cmd);
        }
        $this->output->newLine(2);
    }
}
