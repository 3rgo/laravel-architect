<?php

namespace LaravelArchitect;


// use Laravel\Installer\Console\NewCommand as LaravelInstallerNewCommand;

use LaravelArchitect\ValueObject\Preset;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\{clear, confirm, error, info, multiselect, pause, select, spin, text, warning};

/**
 * @author Romain GONÇALVES <romain@3rgo.tech>
 */
class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\InteractsWithComposer;
    use Concerns\InteractsWithDevEnvironment;
    use Concerns\InteractsWithGit;
    use Concerns\InteractsWithLaravelInstall;
    use Concerns\ManagesPresets;
    use Concerns\RunsCommands;

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
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the application')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactively create a new application')
            ->addOption('preset', 'p', InputOption::VALUE_REQUIRED, 'Loads a preset')
            ->addOption('advanced', 'a', InputOption::VALUE_NONE, 'Advanced mode')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run the commands')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Debug the command');
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
                        'preset'      => 'Create a new application from a preset',
                        'interactive' => 'Interactively create a new application',
                    ],
                    default: 'preset',
                    validate: function ($value) {
                        if ($value !== 'preset' && $value !== 'interactive') {
                            return 'Invalid option.';
                        }
                        if ($value === 'preset' && $this->getPresetManager()->list()->isEmpty()) {
                            return 'No presets found. Please select the interactive option to create a new application interactively.';
                        }
                    }
                );
                if ($type === 'preset') {
                    $presets = $this->getPresetManager()->list();
                    if ($presets->isEmpty()) {
                        warning('No presets found.');
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
        } else if ($presetName = $input->getOption('preset')) {
            if (!$this->getPresetManager()->exists($presetName)) {
                error("Preset '{$presetName}' not found.");
                return 1;
            }
        }

        if (!$input->getArgument('name')) {
            $this->newScreen($output);
            $name = text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: function ($value) use ($input) {
                    if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }
                }
            );

            try {
                $this->verifyApplicationDoesntExist($this->getInstallationDirectory($name));
            } catch (RuntimeException $e) {
                error('Application already exists.');
                $force = confirm(label: 'Would you like to force the installation?', default: false);
                if (!$force) {
                    info('Installation cancelled.');
                    return 1;
                }
                $input->setOption('force', true);
            }

            $input->setArgument('name', $name);
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

        if ($mode === 'preset') {
            $presetName = $input->getOption('preset');
            $preset = $this->getPresets()->firstWhere('name', $presetName);

            return $this->executePreset($preset, $input, $output);
        } else if ($mode === 'interactive') {
            return $this->executeInteractive($input, $output);
        }
    }

    /**
     * Execute the preset.
     *
     * @param  \LaravelArchitect\ValueObject\Preset  $preset
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function executePreset(Preset $preset, InputInterface $input, OutputInterface $output): int
    {
        // TODO : Get preset commands and run them
        // $commands = $preset->commands;
        // return $this->runCommands($commands, $input, $output);
        return 0;
    }

    /**
     * Execute in interactive mode.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function executeInteractive(InputInterface $input, OutputInterface $output): int
    {
        $preset = new Preset();

        if ($this->isAdvancedMode($input)) {
            $laravelVersion = $this->promptForLaravelVersion($output);
            $preset->setLaravelVersion(strval($laravelVersion));

            [$devEnvironment, $devEnvironmentOptions] = $this->promptForDevEnvironment($output);
        }

        [$stack, $stackOptions] = $this->promptForStack($output);
        [$database, $migrate]   = $this->promptForDatabase($output);


        $testFramework = select(
            label: 'Which testing framework do you prefer?',
            options: [
                'pest' => 'Pest',
                'phpunit' => 'PHPUnit',
            ],
            default: 'pest',
        );

        // TODO : prompt for git options


        $preset->setLaravelOptions([
            'stack'                 => $stack,
            'stackOptions'          => $stackOptions,
            'database'              => $database,
            'migrate'               => $migrate,
            'devEnvironment'        => $devEnvironment ?? null,
            'devEnvironmentOptions' => $devEnvironmentOptions ?? [],
            'testFramework'         => $testFramework,
        ]);

        // TODO : prompt for first-party packages (horizon, pulse, echo, reverb, telescope, cashier, spark)

        // TODO : prompt for admin panel (nova, filament, backpack)

        // TODO : prompt for auth (sanctum, passport, socialite)

        // TODO : prompt for popular packages through search (autocomplete)


        // TODO : review choices and save preset

        // TODO : run preset
        return 0;
    }

    /**
     * Prompt for the Laravel version.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function promptForLaravelVersion(OutputInterface $output): string
    {
        $this->newScreen($output);
        $laravelVersions = spin(
            message: 'Fetching Laravel versions...',
            callback: fn() => $this->getLaravelVersions()
        );
        return select(
            label: 'Which version of Laravel would you like to install?',
            options: $laravelVersions,
            default: array_keys($laravelVersions)[0],
        );
    }

    /**
     * Prompt for the stack.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return array
     */
    protected function promptForStack(OutputInterface $output): array
    {
        $this->newScreen($output);
        $starterKit = select(
            label: 'Would you like to install a first-party starter kit?',
            options: [
                'none'      => 'No starter kit',
                'breeze'    => 'Laravel Breeze',
                'jetstream' => 'Laravel Jetstream',
            ],
            default: 'none',
        );
        $stack = null;
        $stackOptions = [];
        if ($starterKit === 'breeze') {
            $stack = select(
                label: 'Which Breeze stack would you like to install?',
                options: [
                    'blade' => 'Blade with Alpine',
                    'livewire' => 'Livewire (Volt Class API) with Alpine',
                    'livewire-functional' => 'Livewire (Volt Functional API) with Alpine',
                    'react' => 'React with Inertia',
                    'vue' => 'Vue with Inertia',
                    'api' => 'API only',
                ],
                default: 'blade',
            );
            if (in_array($stack, ['react', 'vue'])) {
                $stackOptions = multiselect(
                    label: 'Would you like any optional features? (SPACE to toggle, ENTER to proceed)',
                    options: [
                        'dark' => 'Dark mode',
                        'ssr' => 'Inertia SSR',
                        'typescript' => 'TypeScript',
                        'eslint' => 'ESLint with Prettier',
                    ],
                    default: [],
                );
            } else if (in_array($stack, ['blade', 'livewire', 'livewire-functional'])) {
                $darkMode = confirm(
                    label: 'Would you like dark mode support?',
                    default: false,
                );
                if ($darkMode) {
                    $stackOptions = ['dark'];
                }
            }
        } else if ($starterKit === 'jetstream') {
            $stack = select(
                label: 'Which Jetstream stack would you like to install?',
                options: [
                    'livewire' => 'Livewire',
                    'inertia' => 'Vue with Inertia',
                ],
                default: 'livewire',
            );
            multiselect(
                label: 'Would you like any optional features?',
                options: collect([
                    'api'          => 'API support',
                    'dark'         => 'Dark mode',
                    'verification' => 'Email verification',
                    'teams'        => 'Team support',
                ])->when(
                    $stack === 'inertia',
                    fn($options) => $options->put('ssr', 'Inertia SSR')
                )->all(),
                default: [],
            );
        }

        return [$stack, $stackOptions];
    }

    /**
     * Prompt for the database.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return array
     */
    protected function promptForDatabase(OutputInterface $output): array
    {
        $this->newScreen($output);
        $databaseOptions = collect([
            'sqlite'  => ['SQLite', extension_loaded('pdo_sqlite')],
            'mysql'   => ['MySQL', extension_loaded('pdo_mysql')],
            'mariadb' => ['MariaDB', extension_loaded('pdo_mysql')],
            'pgsql'   => ['PostgreSQL', extension_loaded('pdo_pgsql')],
            'sqlsrv'  => ['SQL Server', extension_loaded('pdo_sqlsrv')],
        ])
            ->sortBy(fn($database) => $database[1] ? 0 : 1)
            ->map(fn($database) => $database[0] . ($database[1] ? '' : ' (Missing PDO extension)'))
            ->all();

        $defaultDatabase = collect($databaseOptions)->keys()->first();

        $database = select(
            label: 'Which database will your application use?',
            options: $databaseOptions,
            default: $defaultDatabase,
        );

        $migrate = confirm(
            label: 'Would you like to run the database migrations after installation?',
            default: true
        );

        return [$database, $migrate];
    }

    /**
     * Prompt for the development environment.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return array
     */
    protected function promptForDevEnvironment(OutputInterface $output): array
    {
        $this->newScreen($output);
        $devEnvironmentOptions = [];

        $devEnvironments = $this->listDevEnvironments();
        $devEnvironment = select(
            label: 'Which development environment would you like to use?',
            options: $devEnvironments,
            default: collect($devEnvironments)->keys()->first(),
            validate: function ($value) {
                if ($value === 'sail') {
                    return 'Sail integration is not available yet';
                }
            }
        );

        if ($devEnvironment === 'sail') {
            // TODO : sail implementation
            // $devEnvironmentOptions['services'] = multiselect(
            //     label: 'Which services would you like to install?',
            //     options: ['mysql', 'redis', 'meilisearch', 'mailhog', 'minio'],
            //     default: [],
            // );
        }
        if ($devEnvironment === 'none') {
            $devEnvironmentOptions['port'] = text(label: 'Which port would you like to use?', default: '8000');
            // TODO : solo implementation
            // $solo = confirm(
            //     label: 'Would you like to use the Solo for Laravel ?',
            //     default: false,
            // );
            // $devEnvironmentOptions['solo'] = $solo;
        }

        return [$devEnvironment, $devEnvironmentOptions];
    }

    /**
     * Get the installation directory.
     *
     * @param  string  $name
     * @return string
     */
    protected function getInstallationDirectory(string $name)
    {
        return $name !== '.' ? getcwd() . '/' . $name : '.';
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Check if the advanced mode is enabled.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return bool
     */
    protected function isAdvancedMode(InputInterface $input): bool
    {
        return $input->getOption('advanced');
    }

    /**
     * Build the Laravel versions array, discarding EOL versions except for the latest one and adding "dev-master" after the current major version.
     *
     * @see https://laravelversions.com List of laravel versions made by the guys from Thighten
     *
     * @return array
     */
    protected function getLaravelVersions(): array
    {
        $allVersions = json_decode(file_get_contents('https://laravelversions.com/api/versions'), true);
        $versions = [];
        foreach ($allVersions['data'] as $version) {
            $latest = false;
            if (count($versions) === 0) {
                $latest = true;
            }
            $eol = $version['status'] === 'end-of-life';
            $label = trim(sprintf('Laravel %d %s', $version['major'], match (true) {
                $latest => '(Latest)',
                $eol    => '(End of life)',
                default => '',
            }));
            $versions[$version['major']] = $label;
            if ($latest) {
                $versions['dev-master'] = 'Next version (dev-master)';
            }
            if ($eol) {
                break;
            }
        }
        return $versions;
    }
}
