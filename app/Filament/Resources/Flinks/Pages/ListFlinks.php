<?php

namespace App\Filament\Resources\Flinks\Pages;

use App\Filament\Resources\Flinks\FlinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlinks extends ListRecords
{
    protected static string $resource = FlinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
