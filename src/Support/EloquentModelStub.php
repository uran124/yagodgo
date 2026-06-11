<?php
namespace Illuminate\Database\Eloquent;

/**
 * Minimal fallback for environments where illuminate/database is not installed.
 *
 * The application primarily uses the model classes as DTOs/static helpers; this
 * stub prevents autoload failures without attempting to emulate Eloquent.
 */
class Model
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        return new static($attributes);
    }

    public function save(): bool
    {
        return true;
    }

    public function belongsTo(string $related, string $foreignKey): mixed
    {
        return null;
    }

    public function hasMany(string $related, string $foreignKey): array
    {
        return [];
    }
}
