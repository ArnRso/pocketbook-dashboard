<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TransitStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransitStop>
 */
class TransitStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransitStop::class);
    }
}
