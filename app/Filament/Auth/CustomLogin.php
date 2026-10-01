<?php

namespace App\Filament\Auth;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;

class CustomLogin extends Login
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getLoginFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('اسم المستخدم / الايميل'))
            ->placeholder('أدخل اسم المستخدم أو البريد الإلكتروني')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->prefixIcon('heroicon-o-user-circle')
            ->extraInputAttributes([
                'tabindex' => 1,
                'class' => 'py-4 text-lg',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('كلمة المرور'))
            ->placeholder('أدخل كلمة المرور')
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->required()
            ->prefixIcon('heroicon-o-lock-closed')
            ->hint(filament()->hasPasswordReset() ? new \Illuminate\Support\HtmlString(
                Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="3" class="text-sm"> {{ __(\'نسيت كلمة المرور؟\') }}</x-filament::link>')
            ) : null)
            ->extraInputAttributes([
                'tabindex' => 2,
                'class' => 'py-4 text-lg',
            ]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('تذكرني')
            ->default(true);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $login_type => $data['login'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getHeading(): string|\Illuminate\View\View|\Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div class="text-center mb-2">'.
            '<h1 class="text-3xl font-bold text-gray-800 dark:text-white">مرحباً بعودتك</h1>'.
            '<p class="text-gray-500 dark:text-gray-400 mt-2">سجل دخولك للمتابعة</p>'.
            '</div>'
        );
    }
}
