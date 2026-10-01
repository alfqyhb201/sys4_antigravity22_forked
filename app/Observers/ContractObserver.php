<?php

namespace App\Observers;

use App\Models\Contract;
use App\Services\ClientLifecycleService;

class ContractObserver
{
    public function saved(Contract $contract): void
    {
        if ($contract->wasChanged('status') && $contract->client) {
            $lifecycle = app(ClientLifecycleService::class);

            if ($contract->status === 'suspended') {
                $lifecycle->suspend($contract->client);
            } elseif ($contract->status === 'active') {
                $lifecycle->resume($contract->client);
            }
        }
    }
}
