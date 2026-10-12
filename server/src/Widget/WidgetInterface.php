<?php

declare(strict_types=1);

namespace App\Widget;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Bloc affiché sur l'écran des liseuses. Chaque widget déclare son nom avec
 * #[AsTaggedItem('nom')], utilisé dans les templates : {{ widget('nom') }}.
 */
#[AutoconfigureTag(self::TAG)]
interface WidgetInterface
{
    public const TAG = 'app.widget';

    public function template(): string;

    /**
     * Données passées au template sous le nom « data ».
     *
     * @throws DisplayableException quand le widget ne peut pas être affiché pour une raison connue
     */
    public function data(): object;
}
