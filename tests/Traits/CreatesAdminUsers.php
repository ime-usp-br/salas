<?php

namespace Tests\Traits;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

trait CreatesAdminUsers
{
    /**
     * Create an admin user and authenticate via Sanctum
     *
     * @return User
     */
    protected function actingAsAdmin(): User
    {
        // Create admin role if it doesn't exist
        Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        
        // Create user and assign admin role
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        // Authenticate via Sanctum
        Sanctum::actingAs($user);
        
        return $user;
    }
    
    /**
     * Create an admin user without authentication
     *
     * @return User
     */
    protected function createAdmin(): User
    {
        // Create admin role if it doesn't exist
        Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        
        // Create user and assign admin role
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        return $user;
    }
}