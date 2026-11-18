<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Preserve the public model API while storing particulars outside the shared core row. */
trait HasExtensionAttributes
{
    private array $pendingExtensionAttributes = [];

    private array $extensionAttributeCache = [];

    /** @return array<string, string> Attribute => cast. */
    abstract protected function extensionAttributeCasts(): array;

    /** @return array{table: ?string, key: string, fields: list<string>} */
    abstract protected function extensionAttributeStorage(): array;

    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->extensionAttributeCasts())) {
            $this->pendingExtensionAttributes[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $casts = $this->extensionAttributeCasts();
        if (! array_key_exists($key, $casts)) {
            return parent::getAttribute($key);
        }
        if (array_key_exists($key, $this->pendingExtensionAttributes)) {
            $value = $this->pendingExtensionAttributes[$key];
        } else {
            $storage = $this->extensionAttributeStorage();
            $table = $storage['table'];
            if (! $this->exists || $table === null || ! in_array($key, $storage['fields'], true)) {
                return null;
            }
            if (! array_key_exists($table, $this->extensionAttributeCache)) {
                $this->extensionAttributeCache[$table] = (array) DB::table($table)->where($storage['key'], $this->getKey())->first();
            }
            $value = $this->extensionAttributeCache[$table][$key] ?? null;
        }
        if ($value === null) {
            return null;
        }

        return match ($casts[$key]) {
            'array' => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    public function attributesToArray()
    {
        return array_merge(parent::attributesToArray(), $this->only(array_keys($this->extensionAttributeCasts())));
    }

    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->pendingExtensionAttributes = [];
        $this->extensionAttributeCache = [];

        return parent::setRawAttributes($attributes, $sync);
    }

    public function save(array $options = [])
    {
        $storage = $this->extensionAttributeStorage();
        $values = $this->pendingExtensionAttributes;
        foreach ($values as $field => $value) {
            if ($value !== null && ! in_array($field, $storage['fields'], true)) {
                throw new InvalidArgumentException('Particular does not belong to the selected application type: '.$field);
            }
        }
        if ($storage['table'] === null && array_filter($values, fn ($value) => $value !== null) !== []) {
            throw new InvalidArgumentException('No department extension is registered for these particulars.');
        }

        return $this->getConnection()->transaction(function () use ($options, $storage, $values) {
            if (! parent::save($options)) {
                return false;
            }
            if ($storage['table'] !== null) {
                $values = array_intersect_key($values, array_flip($storage['fields']));
                foreach ($values as $field => &$value) {
                    if ($value !== null && $this->extensionAttributeCasts()[$field] === 'array') {
                        $value = json_encode($value, JSON_THROW_ON_ERROR);
                    }
                }
                unset($value);
                $query = $this->getConnection()->table($storage['table'])->where($storage['key'], $this->getKey());
                if ($query->exists()) {
                    if ($values !== []) {
                        $query->update($values + ['updated_at' => $this->updated_at]);
                    }
                } else {
                    $this->getConnection()->table($storage['table'])->insert($values + [
                        $storage['key'] => $this->getKey(), 'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
                    ]);
                }
            }
            $this->pendingExtensionAttributes = [];
            $this->extensionAttributeCache = [];

            return true;
        });
    }
}
