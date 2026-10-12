<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Device\DeviceManager;
use App\Entity\Device;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Device>
 */
class DeviceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly DeviceManager $devices,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Device::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Liseuse')
            ->setEntityLabelInPlural('Liseuses')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter une liseuse')
            ->setHelp(Crud::PAGE_INDEX, 'Chaque liseuse s\'authentifie avec sa propre clé d\'accès. Désactiver une liseuse lui retire immédiatement l\'accès.');
    }

    public function configureActions(Actions $actions): Actions
    {
        $token = Action::new('token', 'Générer une clé', 'fa fa-key')
            ->linkToCrudAction('token');

        return $actions
            ->add(Crud::PAGE_INDEX, $token)
            ->add(Crud::PAGE_DETAIL, $token);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield IntegerField::new('refreshSeconds', 'Rafraîchissement (s)')
            ->setHelp('Délai entre deux mises à jour de l\'écran, entre 60 et 86400 secondes.');
        yield AssociationField::new('dashboard', 'Dashboard')
            ->setHelp('Écran affiché par la liseuse.');
        yield BooleanField::new('enabled', 'Active');
        yield BooleanField::new('hasToken', 'Clé générée')
            ->renderAsSwitch(false)
            ->hideOnForm();
        yield DateTimeField::new('lastSeenAt', 'Dernière connexion')
            ->hideOnForm();
    }

    #[AdminRoute('/{id}/token', name: 'token', options: ['methods' => ['GET', 'POST']])]
    public function token(Device $device, Request $request): Response
    {
        $token = null;
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('device-token-'.$device->getId(), $request->getPayload()->getString('_token'))) {
            $token = $this->devices->generateToken($device);
        }

        return $this->render('admin/device_token.html.twig', [
            'device' => $device,
            'token' => $token,
        ]);
    }
}
