<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Clusters;

use Refilament\Refilament\Clusters\Cluster;

/**
 * The demo page cluster (the page-clusters slice) — groups the account pages
 * and resources under one sidebar entry: the preferences page and the users
 * resource declare `$cluster = AccountCluster::class`. The cluster shows at
 * /refilament/account with a cog icon, and its URL redirects to the first
 * accessible member.
 */
class AccountCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;
}
