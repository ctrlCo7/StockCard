<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'designation', 'office_id', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token', 'signature_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Mirrors the column default so new instances are active before a refresh. */
    protected $attributes = ['is_active' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'signature_updated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'requested_by');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_message_id');
    }

    /**
     * False for addresses on reserved, non-routable test domains (RFC 2606 / 6761), such as
     * the seeded "@demo.local" accounts. Mailing them would only produce bounce messages.
     */
    public function hasDeliverableEmail(): bool
    {
        return ! preg_match('/\.(local|test|invalid|localhost|example)$/i', (string) $this->email);
    }

    public function hasRole(RoleEnum|string ...$roles): bool
    {
        $names = array_map(fn ($r) => $r instanceof RoleEnum ? $r->value : $r, $roles);

        return $this->roles->pluck('name')->intersect($names)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleEnum::Admin);
    }

    public function roleLabels(): string
    {
        return $this->roles->map(fn (Role $r) => $r->label)->implode(', ');
    }
}
