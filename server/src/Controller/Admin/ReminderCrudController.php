<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reminder;
use App\Form\ReminderWindowType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Reminder>
 */
class ReminderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reminder::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Rappel')
            ->setEntityLabelInPlural('Rappels')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un rappel')
            ->setHelp(Crud::PAGE_INDEX, 'Un rappel s\'affiche dans la tuile « Rappels » des dashboards pendant ses plages, chaque semaine.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('text', 'Texte');
        yield CollectionField::new('windows', 'Plages')
            ->setEntryType(ReminderWindowType::class)
            ->setEntryIsComplex()
            ->addFormTheme('admin/reminder_windows_theme.html.twig')
            ->setColumns('col-12 col-xxl-9')
            ->setCustomOption(CollectionField::OPTION_MAX_LENGTH, 200)
            ->setHelp('Chaque semaine, du jour et de l\'heure de début à ceux de fin : du dimanche 16:00 au lundi 09:00.');
        yield BooleanField::new('enabled', 'Activé');
    }
}
