<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reminder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    /**
     * Rappels activés, dans l'ordre de création.
     *
     * @return list<Reminder>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['id' => 'ASC']);
    }
}
