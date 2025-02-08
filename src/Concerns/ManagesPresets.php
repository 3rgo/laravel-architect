<?php

namespace LaravelArchitect\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use LaravelArchitect\ValueObject\Preset;

trait ManagesPresets
{
    protected ?Collection $presets = null;
    protected ?Filesystem $filesystem = null;

    /**
     * Gets the presets directory (creates it if it doesn't exist)
     */
    protected function getPresetsDirectory()
    {
        $homeDir = getenv(PHP_OS_FAMILY == 'Windows' ? 'USERPROFILE' : 'HOME');
        if (empty($homeDir)) {
            throw new \Exception("Could not determine user's home directory.");
        }

        $configDir = $homeDir . '/.laravel-architect'; // Create a directory for your package
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true); // Create the directory recursively
        }

        return $configDir;
    }

    /**
     * Get the available presets.
     *
     * @return \Illuminate\Support\Collection<string, \LaravelArchitect\ValueObject\Preset>
     */
    protected function getPresets(bool $forceReload = false): Collection
    {
        if (blank($this->presets) || $forceReload) {
            $this->filesystem = $this->filesystem ?? new Filesystem();
            $directory = $this->getPresetsDirectory();
            $files     = $this->filesystem->files($directory);
            $this->presets = collect($files)
                ->filter(fn($file) => $file->getExtension() === 'json' && json_validate($file->getContents()))
                ->keyBy(fn($file) => $file->getFilenameWithoutExtension())
                ->map(fn($file) => Preset::makeFromFile($file));
        }

        return $this->presets;
    }

    protected function doesPresetExist(string $presetName): bool
    {
        return $this->getPresets()->has($presetName);
    }
}
