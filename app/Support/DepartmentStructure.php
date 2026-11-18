<?php

declare(strict_types=1);

namespace App\Support;

/** Shared storage contract; this registry does not enable intake or grant permissions. */
final class DepartmentStructure
{
    public const TYPES = [
        'PKM' => ['perancang', 'app_pkm', 'JPBD'],
        'PB' => ['bangunan', 'app_pb', 'JBANG'],
        'PBK' => ['bangunan', 'app_pb', 'JBANG'],
        'PBS' => ['bangunan', 'app_pb', 'JBANG'],
        'PMT' => ['bangunan', 'app_pb', 'JBANG'],
        'TEL' => ['bangunan', 'app_pb', 'JBANG'],
        'PKT' => ['kejuruteraan', 'app_pkt', 'JKEJ'],
        'PKP' => ['kejuruteraan', 'app_pkp', 'JKEJ'],
        'LJUP' => ['kejuruteraan', 'app_ljup', 'JKEJ'],
        'PL' => ['landskap', 'app_pl', 'JLAND'],
    ];

    public static function applicationTable(string $type): ?string
    {
        $definition = self::TYPES[$type] ?? null;

        return $definition ? $definition[0].'.'.$definition[1].'_applications' : null;
    }

    public static function draftTable(string $type): ?string
    {
        $definition = self::TYPES[$type] ?? null;

        return $definition ? $definition[0].'.'.$definition[1].'_draft_particulars' : null;
    }
}
