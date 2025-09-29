<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\OrderManagement\Models\Order;
use App\Modules\OrderManagement\Models\Package;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasApiTokens;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            do {
                $token = 'secret_token_' . Str::random(128);
                $exists = DB::table('users')
                    ->where('secret_code_telegram', $token)
                    ->exists();
            } while ($exists);

            $user->secret_code_telegram = $token;
        });
    }

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'partner_id',
        'avatar',
        'provider',
        'provider_id',
        'google_id', 'yandex_id', 'vkontakte_id', 'mailru_id',

        'is_active',
        'is_banned',
        'banned_at',
        'ban_reason',
        'delete_requested_at',
        'delete_confirmation_token',

        'telegram_id',
        'telegram_support_chat_id'
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

            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',

            'delete_requested_at' => 'datetime'
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function moonshineUserRole(): BelongsTo
    {
        return $this->belongsTo(MoonshineUserRole::class, 'role_id');
    }

    public function packages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Package::class, 'user_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    // Связь с конфигурациями эквайринга
    public function acquirerConfigs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AcquirerConfig::class, 'user_id');
    }

    // Получить активную конфигурацию для определенного типа эквайринга
    public function activeAcquirerConfig(AcquirerType $type): ?AcquirerConfig
    {
        return $this->acquirerConfigs()->where('type', $type)->where('is_active', true)->first();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isAdminRole(): bool
    {
        return $this->role_id === Role::admin->value;
    }

    public function isDefaultUserRole(): bool
    {
        return $this->role_id === Role::user->value;
    }

    public function isPartnerRole(): bool
    {
        return $this->role_id === Role::partner->value;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function webPushSubscriptions(): HasMany
    {
        return $this->hasMany(UserWebPushSubscription::class);
    }
}
