<?php

namespace App\Filament\Resources;

use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use App\Filament\Resources\UsersResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
// use Filament\Infolists\Infolist;
// use Filament\Infolists\Components\TextEntry;

use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * مورد Filament لإدارة المستخدمين (Users).
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء وعرض وتعديل وحذف بيانات المستخدمين،
 * مع عرض تفصيلي لمعلوماتهم الشخصية والوظيفية.
 */
class UsersResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = User::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $slug = 'users';

    protected static ?string $navigationGroup = 'المستخدمون';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    /**
     * يقوم بإرجاع شارة (badge) التنقل للمورد مع تخزين مؤقت لتفادي الاستعلام المتكرر.
     */
    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::remember('filament_users_count', 300, fn () => static::getModel()::count());
    }

    /**
     * يقوم بإرجاع لون شارة التنقل للمورد.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل المستخدم.
     *
     * @param  \Filament\Infolists\Infolist  $infolist  قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make()
                    ->schema([
                        \Filament\Infolists\Components\Split::make([
                            \Filament\Infolists\Components\ImageEntry::make('profile_image')
                                ->hiddenLabel()
                                ->circular()
                                ->defaultImageUrl(asset('images/default-avatar.svg'))
                                ->grow(false),
                            \Filament\Infolists\Components\Grid::make(2)
                                ->schema([
                                    \Filament\Infolists\Components\Group::make([
                                        TextEntry::make('name')
                                            ->label('الاسم')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                        TextEntry::make('email')
                                            ->label('البريد الإلكتروني')
                                            ->icon('heroicon-m-envelope')
                                            ->copyable(),
                                    ]),
                                    \Filament\Infolists\Components\Group::make([
                                        TextEntry::make('username')
                                            ->label('اسم المستخدم')
                                            ->icon('heroicon-m-at-symbol'),
                                        TextEntry::make('status')
                                            ->label('الحالة')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط'),
                                    ]),
                                ]),
                        ])->from('md'),
                    ]),

                \Filament\Infolists\Components\Section::make('الأدوار الوظيفية')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('الأدوار المسندة')
                            ->badge()
                            ->color('primary')
                            ->separator(', ')
                            ->placeholder('لا يوجد أدوار مسندة'),
                    ]),

                \Filament\Infolists\Components\Section::make('معلومات الاتصال')
                    ->schema([
                        TextEntry::make('work_phone_number')
                            ->label('رقم هاتف العمل')
                            ->icon('heroicon-m-phone'),
                        TextEntry::make('personal_phone_number')
                            ->label('رقم الهاتف الشخصي')
                            ->icon('heroicon-m-device-phone-mobile'),
                    ])->columns(2),

                \Filament\Infolists\Components\Section::make('معلومات التوظيف')
                    ->schema([
                        TextEntry::make('hire_date')
                            ->label('تاريخ التوظيف')
                            ->date()
                            ->icon('heroicon-m-calendar')
                            ->placeholder('غير محدد'),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل المستخدمين.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // ─── القسم الأول: البيانات الأساسية + الاتصال ───
                Section::make('بيانات المستخدم')
                    ->description('المعلومات الأساسية للدخول والتواصل')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم الكامل')
                            ->placeholder('مثال: أحمد محمد')
                            ->prefixIcon('heroicon-m-user')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('username')
                            ->label('اسم المستخدم')
                            ->placeholder('مثال: ahmed99')
                            ->prefixIcon('heroicon-m-at-symbol')
                            ->required()
                            ->unique(table: 'users', column: 'username', ignorable: fn ($record) => $record)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->placeholder('مثال: ahmed@example.com')
                            ->prefixIcon('heroicon-m-envelope')
                            ->required()
                            ->email()
                            ->unique(table: 'users', column: 'email', ignorable: fn ($record) => $record)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->placeholder('اتركه فارغاً إذا لا تريد التغيير')
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context) => $context === 'create')
                            ->disabled(fn ($record) => $record && (($record->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) || ($record->hasRole('admin') && ! auth()->user()?->hasRole(['admin', 'super_admin']))))
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                            ->maxLength(255),

                        Forms\Components\Select::make('roles')
                            ->label('الأدوار الوظيفية')
                            ->relationship('roles', 'name', fn ($query) => auth()->user()?->hasRole('super_admin') ? $query : $query->where('name', '!=', 'super_admin'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => match ($record->name) {
                                'super_admin' => 'المدير العام الأعلى (Super Admin)',
                                'admin' => 'المدير العام (Admin)',
                                'hr' => 'الموارد البشرية (HR)',
                                'accountant' => 'المحاسب (Accountant)',
                                'designer' => 'المصمم (Designer)',
                                'reviewer' => 'المراجع (Reviewer)',
                                'supervisor' => 'المشرف (Supervisor)',
                                'social_media' => 'سوشيال ميديا (Social Media)',
                                'content_creator' => 'مدخل أفكار ومحتوى (Content Creator)',
                                default => $record->name,
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(fn ($record) => ($record && $record->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) || ! (auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('assign_roles')))
                            ->helperText(fn () => ! (auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('assign_roles')) ? 'تعديل الأدوار مقتصر على مدراء النظام' : null)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('work_phone_number')
                            ->label('رقم هاتف العمل')
                            ->placeholder('مثال: 777123456 أو +967...')
                            ->prefixIcon('heroicon-m-phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('personal_phone_number')
                            ->label('رقم الهاتف الشخصي')
                            ->placeholder('مثال: 711123456 أو +967...')
                            ->prefixIcon('heroicon-m-device-phone-mobile')
                            ->tel()
                            ->maxLength(20),
                    ])->columns(2),

                // ─── القسم الثاني: التوظيف والحالة والصورة ───
                Section::make('معلومات إضافية')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Forms\Components\DatePicker::make('hire_date')
                            ->label('تاريخ التوظيف')
                            ->prefixIcon('heroicon-m-calendar')
                            ->native(false)
                            ->format('Y-m-d')
                            ->nullable(),

                        Forms\Components\Toggle::make('status')
                            ->label('الحساب نشط')
                            ->onColor('success')
                            ->offColor('danger')
                            ->disabled(fn ($record) => ($record?->id === auth()->id()) || ($record && $record->hasRole(['admin', 'super_admin']) && ! auth()->user()?->hasRole(['admin', 'super_admin'])))
                            ->default(true)
                            ->helperText('تعطيل الحساب يمنع المستخدم من تسجيل الدخول'),

                        Forms\Components\FileUpload::make('profile_image')
                            ->label('الصورة الشخصية')
                            ->image()
                            ->disk('public')
                            ->directory('profile_images')
                            ->preserveFilenames()
                            ->imageEditor()
                            ->maxSize(config('filesystems.max_file_size', 10240))
                            ->columnSpanFull(),
                    ])->columns(2),

                // ─── القسم الثالث: الصلاحيات ───
                Section::make('إدارة الصلاحيات')
                    ->icon('heroicon-o-key')
                    ->hidden(fn (string $context) => $context === 'create' || ! (auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('manage_user_permissions')))
                    ->schema([
                        Actions::make([
                            Action::make('managePermissions')
                                ->label('إدارة الصلاحيات المباشرة')
                                ->icon('heroicon-o-key')
                                ->color('primary')
                                ->modalWidth('7xl')
                                ->modalHeading('إدارة صلاحيات المستخدم')
                                ->modalSubmitActionLabel('حفظ التغييرات')
                                ->stickyModalHeader()
                                ->form(fn (User $record) => static::getPermissionSchema($record))
                                ->action(function (array $data, User $record) {
                                    if (isset($data['roles'])) {
                                        $roleIds = $data['roles'];
                                        $roles = Role::whereIn('id', $roleIds)->get();
                                        $record->syncRoles($roles);
                                    } else {
                                        $record->syncRoles([]);
                                    }

                                    $allPermissionIds = [];
                                    foreach ($data as $key => $value) {
                                        if (Str::startsWith($key, 'permissions_')) {
                                            if (is_array($value)) {
                                                $allPermissionIds = array_merge($allPermissionIds, $value);
                                            }
                                        }
                                    }

                                    $allPermissionIds = array_map(fn ($id) => (int) $id, $allPermissionIds);
                                    $validPermissions = Permission::whereIn('id', $allPermissionIds)->get();
                                    $record->syncPermissions($validPermissions);

                                    Notification::make()
                                        ->title('تم تحديث الصلاحيات بنجاح')
                                        ->success()
                                        ->send();
                                }),
                        ])->fullWidth(),
                    ])->columnSpanFull(),

            ])->columns(2);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض المستخدمين.
     *
     * @param  \Filament\Tables\Table  $table  جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_image')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(asset('images/default-avatar.svg')),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم الكامل')
                    ->sortable()
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->description(fn ($record) => '@'.$record->username),

                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge()
                    ->separator(', ')
                    ->color('primary'),

                Tables\Columns\IconColumn::make('status')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('hire_date')
                    ->label('تاريخ التوظيف')
                    ->date()
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('work_phone_number')
                    ->label('هاتف العمل')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('personal_phone_number')
                    ->label('الهاتف الشخصي')
                    ->icon('heroicon-m-device-phone-mobile')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الاضافة')
                    ->date()
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('الحالة')
                    ->placeholder('جميع الحالات')
                    ->trueLabel('النشطون فقط')
                    ->falseLabel('غير النشطين'),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('بحسب الدور')
                    ->preload()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض')->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make()->slideOver()->modalWidth('lg'),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record) => $record->id === auth()->id() || ($record->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) || ($record->hasRole('admin') && ! auth()->user()?->hasRole(['admin', 'super_admin']))),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->filter(function ($r) {
                                if ($r->id === auth()->id()) {
                                    return false;
                                }
                                if ($r->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
                                    return false;
                                }
                                if ($r->hasRole('admin') && ! auth()->user()?->hasRole(['admin', 'super_admin'])) {
                                    return false;
                                }

                                return true;
                            })->each->delete();
                        }),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(UserExporter::class)
                    ->label('تصدير')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->fileDisk('exports'),
                ImportAction::make()
                    ->importer(UserImporter::class),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUsers::route('/create'),
            'edit' => Pages\EditUsers::route('/{record}/edit'),
            'view' => Pages\ViewUsers::route('/{record}'),
        ];
    }

    public static function getPermissionSchema(User $user): array
    {
        $roleTranslations = [
            'admin' => 'المدير العام (Admin)',
            'hr' => 'الموارد البشرية (HR)',
            'accountant' => 'المحاسب (Accountant)',
            'designer' => 'المصمم (Designer)',
            'reviewer' => 'المراجع (Reviewer)',
            'supervisor' => 'المشرف (Supervisor)',
            'social_media' => 'سوشيال ميديا (Social Media)',
            'content_creator' => 'مدخل أفكار ومحتوى (Content Creator)',
        ];

        // 1. Roles Section
        $schema = [
            Section::make('الأدوار')
                ->schema([
                    CheckboxList::make('roles')
                        ->label('الأدوار المسندة')
                        ->options(Role::all()->mapWithKeys(fn ($r) => [$r->id => $roleTranslations[$r->name] ?? $r->name])->toArray())
                        ->default(fn () => $user->roles->pluck('id')->toArray())
                        ->bulkToggleable()
                        ->columns(4)
                        ->gridDirection('row'),
                ])
                ->compact(),
        ];

        // 2. Permissions Grouping Logic
        $permissions = Permission::all();
        $userPermissionIds = $user->permissions()->pluck('id')->toArray();

        $groupedPermissions = $permissions->groupBy(fn ($p) => \App\Services\PermissionHelper::groupPermission($p->name));

        $translatePermission = fn ($name) => \App\Services\PermissionHelper::translatePermission($name);

        // 3. Build Permission Sections
        $permissionSections = [];
        foreach ($groupedPermissions as $group => $perms) {
            $permissionSections[] = Section::make($group)
                ->schema([
                    CheckboxList::make('permissions_'.Str::slug($group))
                        ->label('')
                        ->options($perms->mapWithKeys(fn ($p) => [$p->id => $translatePermission($p->name)])->toArray())
                        ->default(fn () => array_values(array_intersect($userPermissionIds, $perms->pluck('id')->toArray())))
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row'),
                ])
                ->collapsible()
                ->compact();
        }

        $schema[] = Section::make('الصلاحيات المباشرة')
            ->description('تحديد صلاحيات خاصة للمستخدم بعيداً عن الأدوار')
            ->schema([
                \Filament\Forms\Components\Grid::make(3)
                    ->schema($permissionSections),
            ]);

        return $schema;
    }
}
