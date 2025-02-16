<?php

namespace LaravelArchitect\Concerns;

use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

trait InteractsWithDevEnvironment
{
    /**
     * Determine if the given directory is parked using Herd or Valet.
     *
     * @param  string  $directory
     * @return bool
     */
    public function isParkedOnHerdOrValet(string $directory)
    {
        $output = $this->runOnValetOrHerd('paths');

        return $output !== false ? in_array(dirname($directory), json_decode($output)) : false;
    }

    /**
     * Runs the given command on the "herd" or "valet" CLI.
     *
     * @param  string  $command
     * @return string|false
     */
    protected function runOnValetOrHerd(string $command)
    {
        foreach (['herd', 'valet'] as $tool) {
            $process = new Process([$tool, $command, '-v']);

            try {
                $process->run();

                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
            } catch (ProcessStartFailedException) {
            }
        }

        return false;
    }

    protected function listDevEnvironments(): array
    {
        return [
            'herd'  => 'Herd' . ($this->isHerdInstalled() ? '' : ' (Not installed)'),
            'none'  => 'PHP built-in server',
            'valet' => 'Valet' . ($this->isValetInstalled() ? '' : ' (Not installed)'),
            'sail'  => 'Laravel Sail' . ($this->isDockerInstalled() ? '' : ' (Docker not installed)'),
        ];
    }

    protected function isDockerInstalled(): bool
    {
        return $this->isExecutableAvailable('docker');
    }

    protected function isHerdInstalled(): bool
    {
        return $this->isExecutableAvailable('herd');
    }

    protected function isValetInstalled(): bool
    {
        return $this->isExecutableAvailable('valet');
    }

    protected function isExecutableAvailable(string $executable): bool
    {
        $process = new Process(windows_os()
            ? ['where', $executable]
            : ['command', '-v', $executable]);
        $process->run();

        return $process->isSuccessful();
    }
}
