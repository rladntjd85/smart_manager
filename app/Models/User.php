<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser; // 1. 필수 추가
use Filament\Panel;                         // 2. 필수 추가
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser // 3. implements 추가
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 필라멘트 패널 접속 권한 설정
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 모든 가입자가 대시보드에 들어올 수 있게 하려면 true를 반환합니다.
        // 특정 이메일만 허용하려면 return str_ends_with($this->email, '@yourdomain.com'); 등을 사용하세요.
        return true;
    }
}
