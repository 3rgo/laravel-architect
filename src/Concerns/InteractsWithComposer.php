<?php

namespace LaravelArchitect\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Symfony\Component\Process\PhpExecutableFinder;

trait InteractsWithComposer
{
    protected Composer $composer;

    protected function getComposer(string|null $directory = null): Composer
    {
        if (!$this->composer) {
            if (!$directory) {
                $directory = getcwd();
            }
            $this->composer = new Composer(new Filesystem(), $directory);
        }

        return $this->composer;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        return implode(' ', $this->composer->findComposer());
    }


    /**
     * Configure the Composer "dev" script.
     *
     * @param  string  $directory
     * @return void
     */
    protected function configureComposerDevScript(string $directory): void
    {
        $this->composer->modify(function (array $content) {
            if (windows_os()) {
                $content['scripts']['dev'] = [
                    'Composer\\Config::disableProcessTimeout',
                    "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite'",
                ];
            }

            return $content;
        });
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? \Illuminate\Support\php_binary()
            : (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }
}
