<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GeneralSettingsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.pages.general-settings-page';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $navigationLabel = 'الإعدادات العامة';

    protected static ?string $title = 'الإعدادات العامة للنظام';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->can('manage_settings'));
    }

    public function mount(): void
    {
        $maxSizeKb = SystemSetting::getMaxFileSize();
        $maxSizeMb = round($maxSizeKb / 1024, 2);

        $this->form->fill([
            'max_file_size_mb' => $maxSizeMb,
        ]);
    }

    public function form(Form $form): Form
    {
        $phpUploadMax = ini_get('upload_max_filesize') ?: 'غير محدد';
        $phpPostMax = ini_get('post_max_size') ?: 'غير محدد';

        return $form
            ->schema([
                Forms\Components\Section::make('إعدادات رفع وتخزين الملفات')
                    ->description('تخصيص الحد الأقصى للملفات والمرفقات المرفوعة عبر النظام')
                    ->schema([
                        Forms\Components\TextInput::make('max_file_size_mb')
                            ->label('الحد الأقصى لحجم الملف المرفوع')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(1024)
                            ->step(0.5)
                            ->suffix('ميجابايت (MB)')
                            ->helperText("الحد الأقصى المسموح به لرفع الملف في النظام (1 ميجابايت = 1024 كيلوبايت). حدود خادم PHP الحالية: upload_max_filesize = {$phpUploadMax} | post_max_size = {$phpPostMax}."),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $mb = (float) $state['max_file_size_mb'];
        $kb = (int) round($mb * 1024);

        SystemSetting::setMaxFileSize($kb);

        // تحديث إعدادات النظام الحالية في الجلسة
        config(['filesystems.max_file_size' => $kb]);

        Notification::make()
            ->title('تم حفظ الإعدادات العامة بنجاح')
            ->body("تم تعيين الحد الأقصى لحجم الملفات إلى {$mb} ميجابايت ({$kb} كيلوبايت).")
            ->success()
            ->send();
    }
}
