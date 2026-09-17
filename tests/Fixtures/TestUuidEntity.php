<?php

namespace FilamentMediaLibrary\Tests\Fixtures;

use FilamentMediaLibrary\Concerns\InteractsWithMediaAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TestUuidEntity extends Model
{
    use InteractsWithMediaAttachments;

    protected $table = 'test_uuid_entities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (TestUuidEntity $entity): void {
            if (empty($entity->id)) {
                $entity->id = (string) Str::uuid();
            }
        });
    }
}
