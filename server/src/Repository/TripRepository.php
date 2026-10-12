<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    public function findActiveAt(\DateTimeImmutable $moment): ?Trip
    {
        $trips = $this->findBy(['enabled' => true], ['startTime' => 'ASC']);

        foreach ($trips as $trip) {
            if ($trip->isActiveAt($moment)) {
                return $trip;
            }
        }

        return null;
    }
}
