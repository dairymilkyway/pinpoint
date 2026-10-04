<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Rbac;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * The Superadmin and the Admin read the whole address book; every other
     * role is scoped to the addresses it owns. This is the single predicate the
     * visibility scope and the policy both consult, so the table and the policy
     * cannot disagree.
     *
     * Reach is decided by role and never by ownership: both readers see every
     * address while owning none of their own. The two are not the same thing and
     * must not be conflated - the roles manage the book, the Customer holds part
     * of it. scopeOwningAccounts() is the same rule read the other way.
     */
    public function seesEveryAddress(): bool
    {
        return $this->hasAnyRole([Rbac::SUPERADMIN_ROLE, Rbac::ADMIN_ROLE]);
    }

    /**
     * The inverse of seesEveryAddress(): the accounts that own addresses rather
     * than read them all.
     *
     * The users list is built from this, so the two managing roles never appear
     * among the owners. It is a query rather than a collection filter because the
     * list paginates, and a filter applied after the fact would leave the total
     * and the page counts describing rows that are not on screen.
     */
    public function scopeOwningAccounts(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', [
            Rbac::SUPERADMIN_ROLE,
            Rbac::ADMIN_ROLE,
        ]));
    }
}
