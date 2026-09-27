<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\SellerAccess;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        'role',
        'rbac_role_id',
        'status',
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
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== UserStatus::Active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->role->canAccessAdminPanel(),
            'seller' => in_array($this->role, [UserRole::VendorOwner, UserRole::VendorStaff], true)
                && SellerAccess::hasSellerAccess($this),
            default => false,
        };
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rbac_role_id');
    }

    public function hasPermission(string $permission): bool
    {
        if (in_array($this->role, [UserRole::SuperAdmin, UserRole::Admin], true)) {
            return true;
        }

        return $this->accessRole?->status === true
            && $this->accessRole->permissions()
                ->where('rbac_permissions.status', true)
                ->where('rbac_permissions.slug', $permission)
                ->exists();
    }

    public function ownedVendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'owner_id');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_user')
            ->withPivot(['role', 'status', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function incompleteOrders(): HasMany
    {
        return $this->hasMany(IncompleteOrder::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(CustomerOrderRequest::class);
    }

    public function paymentPreference(): HasOne
    {
        return $this->hasOne(CustomerPaymentPreference::class);
    }
}
