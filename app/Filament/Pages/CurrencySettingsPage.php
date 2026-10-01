<?php

namespace App\Filament\Pages;

use App\Models\CurrencySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CurrencySettingsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.currency-settings-page';

    protected static ?string $navigationGroup = 'الاعدادات';

    protected static ?string $navigationLabel = 'إعدادات العملات';

    protected static ?string $title = 'إعدادات نظام العملات';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'default_decimal_places' => CurrencySetting::get('default_decimal_places', 2),
            'rounding_method' => CurrencySetting::get('rounding_method', 'round'),
            'allow_delete_with_transactions' => CurrencySetting::get('allow_delete_with_transactions', '0'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('إعدادات الحسابات والتقريب')
                    ->description('تخصيص سلوك الحسابات المالية ونظام العملات')
                    ->schema([
                        Forms\Components\Select::make('default_decimal_places')
                            ->label('عدد المنازل العشرية الافتراضي')
                            ->options([
                                '0' => 'بدون منازل (0)',
                                '2' => 'منزلتين (0.00)',
                                '3' => 'ثلاث منازل (0.000)',
                                '4' => 'أربع منازل (0.0000)',
                            ])
                            ->required(),

                        Forms\Components\Select::make('rounding_method')
                            ->label('طريقة تقريب المبالغ')
                            ->options([
                                'round' => 'التقريب العادي (Half Up)',
                                'floor' => 'التقريب للأدنى (Floor)',
                                'ceil' => 'التقريب للأعلى (Ceil)',
                            ])
                            ->required(),

                        Forms\Components\Toggle::make('allow_delete_with_transactions')
                            ->label('السماح بحذف عملة مرتبطة بمعاملات مالية')
                            ->helperText('غير موصى به محاسبياً للحفاظ على سلامة التقرير المالي')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        CurrencySetting::set('default_decimal_places', $state['default_decimal_places']);
        CurrencySetting::set('rounding_method', $state['rounding_method']);
        CurrencySetting::set('allow_delete_with_transactions', $state['allow_delete_with_transactions'] ? '1' : '0');

        Notification::make()
            ->title('تم حفظ إعدادات العملات بنجاح')
            ->success()
            ->send();
    }
}
