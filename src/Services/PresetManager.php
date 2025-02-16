<?php

namespace LaravelArchitect\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use LaravelArchitect\ValueObject\Preset;

class PresetManager
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
    public function list(bool $forceReload = false): Collection
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

    public function get(string $presetName): ?Preset
    {
        return $this->list()->firstWhere('name', $presetName);
    }

    public function exists(string $presetName): bool
    {
        return $this->list()->has($presetName);
    }
}
