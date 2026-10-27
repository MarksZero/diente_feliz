<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $guarded = ['id'];
    public function roles() { return $this->belongsToMany(Role::class); }
    public function branches() { return $this->belongsToMany(Branch::class); }
    public function permits(string $permission): bool { return $this->roles->contains(fn($role) => in_array($permission, $role->permissions, true)); }
    public function enabled(): bool { return $this->active && !($this->disabled_from && now()->gte($this->disabled_from) && (!$this->disabled_until || now()->lt($this->disabled_until))); }
    public function branchIds(): array { return $this->branches()->pluck('branches.id')->all(); }
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'disabled_from' => 'datetime',
            'disabled_until' => 'datetime',
        ];
    }
}
