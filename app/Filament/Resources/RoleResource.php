<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'المستخدمون';

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('معلومات الدور')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الدور')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Hidden::make('guard_name')
                            ->default('web'),
                    ]),
                Section::make('الصلاحيات')
                    ->description('تحديد الصلاحيات لهذا الدور')
                    ->schema(static::getPermissionSchema()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->url(fn ($record) => RoleResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()->url(fn ($record) => RoleResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Tables\Actions\ForceDeleteBulkAction::make(),
                    // Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        /** @var Role $record */
        $record = $infolist->getRecord();
        $assignedPermissions = $record->permissions;
        $grouped = $assignedPermissions->groupBy(fn ($p) => \App\Services\PermissionHelper::groupPermission($p->name));
        $groupColors = \App\Services\PermissionHelper::groupColors();

        $permissionSections = [];
        foreach ($grouped as $group => $perms) {
            $color = $groupColors[$group] ?? 'gray';
            $permissionSections[] = \Filament\Infolists\Components\Section::make($group.' ('.$perms->count().')')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('perm_'.Str::slug($group))
                        ->label('')
                        ->badge()
                        ->color($color)
                        ->state(fn () => $perms->map(fn ($p) => \App\Services\PermissionHelper::translatePermission($p->name))->toArray()),
                ])
                ->collapsible()
                ->compact();
        }

        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('معلومات الدور')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('اسم الدور')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large),
                        \Filament\Infolists\Components\TextEntry::make('total_permissions')
                            ->label('إجمالي الصلاحيات')
                            ->badge()
                            ->color('primary')
                            ->state(fn () => $assignedPermissions->count().' صلاحية'),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(3),
                \Filament\Infolists\Components\Section::make('الصلاحيات المسندة')
                    ->description('إجمالي '.$assignedPermissions->count().' صلاحية موزّعة على '.$grouped->count().' مجموعة')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema($permissionSections),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()
    //         ->withoutGlobalScopes([
    //             SoftDeletingScope::class,
    //         ]);
    // }

    public static function getPermissionSchema(): array
    {
        $grouped = \App\Services\PermissionHelper::getGroupedPermissions();
        $permissionSections = [];

        foreach ($grouped as $group => $perms) {
            $permissionSections[] = Section::make($group)
                ->schema([
                    CheckboxList::make('permissions_'.Str::slug($group))
                        ->label('')
                        ->options(
                            collect($perms)->mapWithKeys(fn ($p) => [$p['id'] => $p['translation']])->toArray()
                        )
                        ->loadStateFromRelationshipsUsing(function (CheckboxList $component, ?Role $record) use ($perms) {
                            if (! $record) {
                                return;
                            }
                            $rolePermissionIds = $record->permissions()->pluck('id')->toArray();
                            $component->state(
                                array_values(array_intersect($rolePermissionIds, collect($perms)->pluck('id')->toArray()))
                            );
                        })
                        ->dehydrated(false)
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row'),
                ])
                ->collapsible()
                ->compact();
        }

        return [
            \Filament\Forms\Components\Grid::make(3)
                ->schema($permissionSections),
        ];
    }
}
