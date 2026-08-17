<?php

namespace App\Models;

use App\ActionType;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $admin
 * @property string|null $profile_image
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'admin', 'profile_image'])]
#[Hidden(['password', 'remember_token'])]
#[Appends(['avatar'])]
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

    protected function avatar(): Attribute
    {
        return Attribute::make(get: fn () => $this->profileURL());
    }

    public function profileURL(): string
    {
        if ($this->profile_image) {
            if (self::profileDisk()->exists(self::profileStoragePath($this->profile_image))) {
                return self::profileDisk()->url(self::profileStoragePath($this->profile_image));
            }

            $this->update(['profile_image' => null]);
        }

        return 'https://www.gravatar.com/avatar/'.
            md5(strtolower(trim($this->email))).
            '?d=mp&s=40';
    }

    /**
     * The "public" disk, on which profile images are stored - resolved
     * lazily (not cached in a static) so tests can swap in Storage::fake().
     */
    public static function profileDisk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function profileStoragePath(string $filename): string
    {
        return 'profile/'.trim($filename, '/');
    }

    public static function removeOrphanProfileImages(): int
    {
        $count = 0;
        $disk = self::profileDisk();

        foreach ($disk->files('profile') as $path) {
            $filename = basename($path);
            $user = static::where('profile_image', $filename)->first();
            if ($user === null) {
                $disk->delete($path);
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
