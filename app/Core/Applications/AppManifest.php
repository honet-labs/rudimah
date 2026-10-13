<?php

namespace App\Core\Applications;

class AppManifest
{
    public function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $enabled,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
        ];
    }
}
