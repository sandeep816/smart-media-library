<?php

namespace FilamentMediaLibrary\Tests\Fixtures;

use FilamentMediaLibrary\Concerns\InteractsWithMediaAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TestUlidEntity extends Model
{
    use InteractsWithMediaAttachments;

    protected $table = 'test_ulid_entities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (TestUlidEntity $entity): void {
            if (empty($entity->id)) {
                $entity->id = (string) Str::ulid();
            }
        });
    }
}
