<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Dashboard;
use App\Repository\DashboardRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(DashboardRepository::class)]
final class RepositoryTest extends KernelTestCase
{
    public function testPreviewShowsTheRequestedDashboard(): void
    {
        $this->dashboard('Principal');
        $kitchen = $this->dashboard('Cuisine');

        self::assertSame($kitchen, self::getContainer()->get(DashboardRepository::class)->findForPreview($kitchen->getId()));
    }

    public function testPreviewDefaultsToTheFirstDashboardByName(): void
    {
        $this->dashboard('Principal');
        $this->dashboard('Cuisine');

        self::assertSame('Cuisine', self::getContainer()->get(DashboardRepository::class)->findForPreview(null)?->getName());
    }

    public function testPreviewWithoutDashboard(): void
    {
        self::assertNull(self::getContainer()->get(DashboardRepository::class)->findForPreview(null));
        self::assertNull(self::getContainer()->get(DashboardRepository::class)->findForPreview(999));
    }

    private function dashboard(string $name): Dashboard
    {
        $dashboard = new Dashboard()->setName($name);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($dashboard);
        $entityManager->flush();

        return $dashboard;
    }
}
