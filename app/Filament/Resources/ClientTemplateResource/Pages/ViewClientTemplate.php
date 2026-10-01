<?php

namespace App\Filament\Resources\ClientTemplateResource\Pages;

use App\Filament\Enums\ClientTemplateType;
use App\Filament\Resources\ClientTemplateResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewClientTemplate extends ViewRecord
{
    protected static string $resource = ClientTemplateResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Split::make([
                    Infolists\Components\Section::make('معلومات النموذج')
                        ->schema([
                            Infolists\Components\TextEntry::make('client.company')
                                ->label('العميل'),
                            Infolists\Components\TextEntry::make('type')
                                ->label('النوع')
                                ->badge()
                                ->formatStateUsing(fn ($state) => ClientTemplateType::from($state)?->getLabel() ?? $state),
                            Infolists\Components\TextEntry::make('updated_at')
                                ->label('آخر تحديث')
                                ->dateTime(),
                        ]),
                    Infolists\Components\Section::make('ملف النموذج')
                        ->schema([
                            Infolists\Components\ImageEntry::make('file')
                                ->label(''),
                        ]),
                ])->from('md')->columnSpanFull(),
                Infolists\Components\Section::make('المسار المحلي')
                    ->schema([
                        Infolists\Components\TextEntry::make('local_path')
                            ->label('')
                            ->copyable()
                            ->prose()
                            ->extraAttributes(['dir' => 'ltr']),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
