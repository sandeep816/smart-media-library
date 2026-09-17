<?php

namespace FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    public static function factory(): object
    {
        return new class
        {
            public function create(array $attributes = []): User
            {
                return User::query()->create(array_merge([
                    'name' => 'Test User',
                    'email' => 'user_'.uniqid().'@example.com',
                    'password' => bcrypt('password'),
                ], $attributes));
            }
        };
    }
}
