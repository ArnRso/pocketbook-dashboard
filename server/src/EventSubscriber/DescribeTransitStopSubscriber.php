<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\TransitStop;
use App\Transit\TransitNetwork;
use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Relève les noms des arrêts TBM enregistrés depuis l'admin ; le formulaire a déjà vérifié qu'ils existent.
 */
final class DescribeTransitStopSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransitNetwork $network,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'describe',
            BeforeEntityUpdatedEvent::class => 'describe',
        ];
    }

    /**
     * @param AbstractLifecycleEvent<object> $event
     */
    public function describe(AbstractLifecycleEvent $event): void
    {
        $stop = $event->getEntityInstance();
        if ($stop instanceof TransitStop) {
            $this->network->describe($stop);
        }
    }
}
