<?php

namespace LaravelArchitect;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Laravel\Installer\Console\NewCommand as LaravelInstallerNewCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * @author Romain GONÇALVES <romain@3rgo.tech>
 */
class NewCommand extends LaravelInstallerNewCommand
{
    use Concerns\ManagesPresets;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Interactively creates a new Laravel application')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactively create a new application')
            ->addOption('preset', 'p', InputOption::VALUE_REQUIRED, 'Loads a preset');
    }

    /**
     * Wipes the screen and displays art.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function newScreen(OutputInterface $output): void
    {
        clear();

        $output->write(PHP_EOL . '  <fg=blue> _                               _                       _     _ _            _
  | |                             | |       /\            | |   (_) |          | |
  | |     __ _ _ __ __ ___   _____| |      /  \   _ __ ___| |__  _| |_ ___  ___| |_
  | |    / _` | \'__/ _` \ \ / / _ \ |     / /\ \ | \'__/ __| \'_ \| | __/ _ \/ __| __|
  | |___| (_| | | | (_| |\ V /  __/ |    / ____ \| | | (__| | | | | ||  __/ (__| |_
  |______\__,_|_|  \__,_| \_/ \___|_|   /_/    \_\_|  \___|_| |_|_|\__\___|\___|\__|</>' . PHP_EOL . PHP_EOL);
    }

    /**
     * Interact with the user before validating the input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->configurePrompts($input, $output);
        $this->newScreen($output);

        if (!$input->getOption('interactive') && !$input->getOption('preset')) {
            info('Welcome to Laravel Architect!');
            info('This tool will help you scaffold a new Laravel application.');
            info('You can choose to install a preset or interactively create a new application');

            do {
                if (isset($type)) {
                    $this->newScreen($output);
                }
                $type = select(
                    label: 'What do you want to do?',
                    options: [
                        'preset'      => 'Install a preset',
                        'interactive' => 'Interactively create a new application',
                    ],
                    default: 'preset'
                );
                if ($type === 'preset') {
                    $presets = $this->getPresets();
                    if ($presets->isEmpty()) {
                        warning('No presets found. Press ENTER to go back to the main menu.');
                        pause();
                    } else {
                        $preset = select(
                            label: 'Which preset do you want to install?',
                            options: [
                                ...$presets->mapWithKeys(fn($preset, $key) => [$key => $preset->name]),
                                'back' => 'Back to the main menu',
                            ],
                            default: 'back'
                        );
                    }
                }
            } while ($type !== 'interactive' && ($type !== 'preset' || ($preset ?? 'back') === 'back'));

            if ($type === 'interactive') {
                $input->setOption('interactive', true);
            }

            if ($type === 'preset') {
                $input->setOption('preset', $preset);
            }
        }
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->newScreen($output);
        $mode = $input->getOption('interactive') ? 'interactive' : 'preset';
        $presetName = $input->getOption('preset');
        info('Selected mode : ' . ($mode) . $mode === 'preset' ? ' (preset: ' . ($presetName ?? 'none') . ')' : '');

        if ($mode === 'preset') {
            $preset = $this->getPresets()->firstWhere('name', $presetName);
            if (!$preset) {
                error("Preset '{$presetName}' not found.");
                return 1;
            }
        }
    }
}
