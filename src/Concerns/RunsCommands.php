<?php

namespace LaravelArchitect\Concerns;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

trait RunsCommands
{
    protected array $commands = [];

    protected function appendCommand(string $command): void
    {
        array_push($this->commands, $command);
    }

    protected function appendCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->appendCommand($command);
        }
    }

    protected function prependCommand(string $command): void
    {
        array_unshift($this->commands, $command);
    }

    protected function prependCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->prependCommand($command);
        }
    }

    protected function clearCommands(): void
    {
        $this->commands = [];
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $workingPath
     * @param  array  $env
     * @return \Symfony\Component\Process\Process
     */
    protected function runCommands(array $commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = [])
    {
        // TODO : redirect output to a log file in the project root
        if (! $output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value . ' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value . ' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    ' . $line);
        });

        return $process;
    }

    protected function runStashedCommands(InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = [])
    {
        $commands = $this->commands;
        $this->clearCommands();

        return $this->runCommands($commands, $input, $output, $workingPath, $env);
    }
}
