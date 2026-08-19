<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;

/**
 * Accepts MySQL DATETIME / DATETIME(6) values without a timezone offset,
 * including fractional seconds that stock Doctrine refuses to parse.
 */
final class LenientDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                $this->getName(),
                ['null', 'string', DateTimeImmutable::class],
            );
        }

        $formats = array_values(array_unique([
            $platform->getDateTimeTzFormatString(),
            'Y-m-d H:i:s.u',
            'Y-m-d H:i:s.uP',
            'Y-m-d H:i:s.uO',
            'Y-m-d H:i:sP',
            'Y-m-d H:i:sO',
            'Y-m-d H:i:s',
        ]));

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                implode(' | ', $formats),
            );
        }
    }
}
