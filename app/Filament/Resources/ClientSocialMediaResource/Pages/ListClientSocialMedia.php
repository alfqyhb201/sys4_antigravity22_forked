<?php

namespace App\Filament\Resources\ClientSocialMediaResource\Pages;

use App\Filament\Resources\ClientSocialMediaResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClientSocialMedia extends ListRecords
{
    protected static string $resource = ClientSocialMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'with_platforms' => Tab::make('لديهم منصات')
                ->badge(fn () => Client::has('socialMedia')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->has('socialMedia')),
            'without_platforms' => Tab::make('بدون منصات')
                ->badge(fn () => Client::doesntHave('socialMedia')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->doesntHave('socialMedia')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'with_platforms';
    }
}
