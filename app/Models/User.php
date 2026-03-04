<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'interface_type',
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

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function branches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function productTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProductType::class);
    }

    public function permittedAttributes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProductAttribute::class);
    }

    public function getPermittedBranchCodes(): array
    {
        if ($this->role === 'admin') {
            return Branch::pluck('code')->toArray();
        }
        
        return $this->branches()->pluck('code')->toArray();
    }

    public function getPermittedProductTypeIds(): array
    {
        if ($this->role === 'admin') {
            return ProductType::pluck('id')->toArray();
        }
        
        return $this->productTypes()->pluck('product_types.id')->toArray();
    }

    public function getPermittedRMTypes(): array
    {
        if ($this->role === 'admin') {
            return ProductAttribute::where('type', 'rm_type')->pluck('value')->toArray();
        }
        
        return $this->permittedAttributes()
            ->where('type', 'rm_type')
            ->pluck('value')
            ->toArray();
    }

    public function hasPermission(string $pageKey, string $permissionType = 'view'): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $permission = $this->permissions()->where('page_key', $pageKey)->first();

        if (!$permission) {
            return false;
        }

        return match ($permissionType) {
            'view'   => (bool) $permission->can_view,
            'create' => (bool) $permission->can_create,
            'edit'   => (bool) $permission->can_edit,
            'delete' => (bool) $permission->can_delete,
            'print'  => (bool) $permission->can_print,
            'excel'  => (bool) $permission->can_export_excel,
            'pdf'    => (bool) $permission->can_export_pdf,
            default  => false,
        };
    }

    public function hasFeature(string $pageKey, string $featureKey): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $permission = $this->permissions()->where('page_key', $pageKey)->first();

        if (!$permission || !$permission->features) {
            return false;
        }

        return (bool) ($permission->features[$featureKey] ?? false);
    }
}
