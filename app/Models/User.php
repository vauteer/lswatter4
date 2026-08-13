<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\ActionType;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $admin
 * @property string|null $profile_image
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'admin', 'profile_image'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
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
            'admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Tournament, $this>
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'created_by');
    }

    /**
     * @return HasMany<Tracing, $this>
     */
    public function tracings(): HasMany
    {
        return $this->hasMany(Tracing::class);
    }

    public function lastLogin(): ?CarbonInterface
    {
        $lastLogin = $this->tracings()
            ->actionType(ActionType::Login)->orderByDesc('at')
            ->first();

        return $lastLogin?->at;
    }

    public function profileURL(): string
    {
        if ($this->profile_image) {
            $path = public_path('storage/profile/'.$this->profile_image);
            if (file_exists($path)) {
                return asset('storage/profile/'.$this->profile_image);
            } else {
                $this->update(['profile_image' => null]);
            }
        }

        return 'https://www.gravatar.com/avatar/'.
            md5(strtolower(trim($this->email))).
            '?d=mp&s=40';
    }

    public static function profilePath(string $stub = ''): string
    {
        return storage_path('app/public/profile').
            DIRECTORY_SEPARATOR.
            trim($stub, DIRECTORY_SEPARATOR);
    }

    public static function removeOrphanProfileImages(): int
    {
        $count = 0;
        foreach (glob(self::profilePath('*')) ?: [] as $filename) {
            $user = User::where('profile_image', basename($filename))->first();
            if ($user === null) {
                unlink($filename);
                $count++;
            }
        }

        return $count;
    }

    public function isUsed(): bool
    {
        return $this->tournaments()->count() > 0;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }
}
