<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Waze répond à la recherche d'adresse par des listes positionnelles :
 * ["requête", [["Nom", 0, [], {lieu}], …]]. On n'en garde que les lieux.
 */
final class AutocompleteResponseDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const int SUGGESTIONS = 1;
    private const int PLACE = 3;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!\is_array($data) || !\is_array($data[self::SUGGESTIONS] ?? null)) {
            throw new UnexpectedValueException('La réponse de la recherche Waze devrait contenir une liste de suggestions.');
        }

        $places = [];
        foreach ($data[self::SUGGESTIONS] as $suggestion) {
            if (\is_array($suggestion) && \is_array($suggestion[self::PLACE] ?? null)) {
                $places[] = $this->denormalizer->denormalize($suggestion[self::PLACE], AutocompletePlace::class, $format, $context);
            }
        }

        return new AutocompleteResponse($places);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return AutocompleteResponse::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [AutocompleteResponse::class => true];
    }
}
