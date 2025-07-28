<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'salt',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'salt',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    /**
     * Hash password với Argon2 và salt ngẫu nhiên
     */
    public function setPasswordAttribute($password)
    {
        // Tạo salt ngẫu nhiên 64 ký tự
        $salt = Str::random(64);

        // Hash password với Argon2ID và salt
        $hashedPassword = password_hash($password . $salt, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);

        $this->attributes['salt'] = $salt;
        $this->attributes['password'] = $hashedPassword;
    }

    /**
     * Kiểm tra password
     */
    public function checkPassword($password)
    {
        return password_verify($password . $this->salt, $this->password);
    }

    /**
     * Cập nhật thời gian đăng nhập cuối
     */
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }
}
