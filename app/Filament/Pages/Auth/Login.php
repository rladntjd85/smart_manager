<?php
namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        // 포트폴리오 게스트용 자동 채우기
        $this->form->fill([
            'email' => 'guest@gmail.com',
            'password' => 'guest1234',
            'remember' => true,
        ]);
    }
}
