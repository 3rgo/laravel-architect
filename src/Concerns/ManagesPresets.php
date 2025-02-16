<?php

namespace LaravelArchitect\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use LaravelArchitect\Services\PresetManager;
use LaravelArchitect\ValueObject\Preset;

trait ManagesPresets
{
    protected ?PresetManager $presetManager = null;

    /**
     * Gets the preset manager
     */
    protected function getPresetManager(): PresetManager
    {
        return $this->presetManager ??= new PresetManager();
    }
}
