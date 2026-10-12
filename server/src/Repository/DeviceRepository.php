<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Dashboard;
use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    /**
     * Liseuse active qui affiche le dashboard, pour que l'aperçu montre sa batterie et son rafraîchissement.
     */
    public function findForPreview(?Dashboard $dashboard): ?Device
    {
        return null !== $dashboard ? $this->findOneBy(['dashboard' => $dashboard, 'enabled' => true], ['id' => 'ASC']) : null;
    }
}
