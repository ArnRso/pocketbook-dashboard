<?php

declare(strict_types=1);

namespace App\Widget;

use App\Chaban\BridgeClosure;

final readonly class ChabanView
{
    /**
     * @param list<BridgeClosure> $closures fermetures à venir, la première éventuellement en cours
     */
    public function __construct(
        public int $days,
        public array $closures,
        public ?BridgeClosure $ongoing,
    ) {
    }
}
