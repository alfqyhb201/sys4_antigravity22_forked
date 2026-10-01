<?php

namespace App\Filament\Resources\ComplaintResource\Pages;

use App\Filament\Enums\ComplaintStatus;
use App\Filament\Resources\ComplaintResource;
use App\Models\Complaint;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(Complaint::query()->count()),
            'new' => Tab::make('الجديدة')
                ->badge(Complaint::query()->where('status', ComplaintStatus::New->value)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ComplaintStatus::New->value)),
            'resolved' => Tab::make('المحلولة')
                ->badge(Complaint::query()->where('status', ComplaintStatus::Resolved->value)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ComplaintStatus::Resolved->value)),
        ];
    }
}
