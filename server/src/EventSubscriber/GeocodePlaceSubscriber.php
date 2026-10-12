<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Place;
use App\Geo\GeocodingException;
use App\Geo\PlaceGeocoder;
use EasyCorp\Bundle\EasyAdminBundle\Event\AbstractLifecycleEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Géocode les lieux enregistrés depuis l'admin, et signale à l'utilisateur les adresses introuvables.
 */
final class GeocodePlaceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PlaceGeocoder $placeGeocoder,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'geocode',
            BeforeEntityUpdatedEvent::class => 'geocode',
        ];
    }

    /**
     * @param AbstractLifecycleEvent<object> $event
     */
    public function geocode(AbstractLifecycleEvent $event): void
    {
        $place = $event->getEntityInstance();
        if (!$place instanceof Place) {
            return;
        }

        try {
            $this->placeGeocoder->completeCoordinates($place);
        } catch (GeocodingException $e) {
            $session = $this->requestStack->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('danger', $e->getMessage().' Renseigne la latitude et la longitude à la main.');
            }
        }
    }
}
