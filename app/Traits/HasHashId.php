<?php

namespace App\Traits;

use App\Services\HashIdService;

trait HasHashId
{
    public function getHashIdAttribute(): string
    {
        $id = $this->getKey();

        if (is_string($id) && strlen($id) > 10) {
            $hexId = str_replace('-', '', $id);
            return HashIdService::encodeString($hexId, $this->getTable());
        }

        return HashIdService::encode($id, $this->getTable());
    }

    public function getRouteKey()
    {
        return $this->hashid;
    }

    public function getRouteKeyName()
    {
        return 'hashid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === 'hashid' || $field === null) {
            $id = $this->decodeHashId($value);
            if ($id === null) {
                return null;
            }
            return $this->where($this->getKeyName(), $id)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public static function findByHashId($hashId)
    {
        $instance = new static;
        $id = $instance->decodeHashId($hashId);

        if ($id === null) {
            return null;
        }

        return static::find($id);
    }

    public static function findByHashIdOrFail($hashId)
    {
        $model = static::findByHashId($hashId);

        if ($model === null) {
            abort(404);
        }

        return $model;
    }

    protected function decodeHashId($hashId)
    {
        $keyType = $this->getKeyType();

        if ($keyType === 'string') {
            $hexId = HashIdService::decodeString($hashId, $this->getTable());
            if ($hexId === null) {
                return null;
            }
            if (strlen($hexId) === 32) {
                return sprintf(
                    '%s-%s-%s-%s-%s',
                    substr($hexId, 0, 8),
                    substr($hexId, 8, 4),
                    substr($hexId, 12, 4),
                    substr($hexId, 16, 4),
                    substr($hexId, 20, 12)
                );
            }
            return $hexId;
        }

        return HashIdService::decode($hashId, $this->getTable());
    }
}
