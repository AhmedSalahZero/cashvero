<?php

namespace App\Support;

use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\RoutePermissionMap;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * اسم القسم اللي بيتكتب في جدول logs لما المستخدم يفتح صفحة.
 *
 * الأول بنجرب اسم الموديول من PermissionRegistry (نفس الاسم اللي في
 * شاشة الصلاحيات) لو الـ route مربوط بصلاحية واحدة. لو لأ، بنعمل الاسم
 * من اسم الـ route نفسه: view / index / show بيتشالوا، و create / edit
 * بيبقوا لاحقة. الباراميترات النصية (زي modelType) بتتضاف بين قوسين
 * عشان "أرصدة العملاء" تتفرق عن "أرصدة الموردين".
 */
class SectionLabel
{
    private const SILENT_ACTIONS = ['view', 'index', 'show'];

    private const NAMED_ACTIONS = ['create' => 'Create', 'edit' => 'Edit'];

    private const IGNORED_PARAMETERS = ['company', 'locale', 'lang'];

    private const MAX_LENGTH = 200;

    public static function forRoute(Route $route): string
    {
        return self::fromName((string) $route->getName(), $route->parameters());
    }

    public static function fromName(string $routeName, array $parameters = []): string
    {
        [$base, $suffix] = self::fromPermissionRegistry($routeName) ?? self::fromRouteName($routeName);

        $label = $base;

        $details = self::descriptiveParameters($parameters);
        if ($details !== []) {
            $label .= ' ('.implode(', ', $details).')';
        }

        if ($suffix !== null) {
            $label .= ' - '.$suffix;
        }

        return Str::limit($label, self::MAX_LENGTH, '');
    }

    /**
     * @return array{0:string, 1:?string}|null
     */
    private static function fromPermissionRegistry(string $routeName): ?array
    {
        $keys = RoutePermissionMap::for($routeName);
        if ($keys === null || count($keys) !== 1) {
            return null;
        }

        $permission = PermissionRegistry::all()[$keys[0]] ?? null;
        if ($permission === null) {
            return null;
        }

        $suffix = $permission['action'] === 'view' ? null : $permission['action_label'];

        return [$permission['module_label'], $suffix];
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private static function fromRouteName(string $routeName): array
    {
        $tokens = array_values(array_filter(
            preg_split('/[.\-_\s]+/', Str::snake($routeName, '.')),
            fn ($token) => $token !== ''
        ));
        $suffix = null;

        foreach ([0, count($tokens) - 1] as $edge) {
            if (count($tokens) < 2 || ! isset($tokens[$edge])) {
                break;
            }
            $token = strtolower($tokens[$edge]);
            if (in_array($token, self::SILENT_ACTIONS, true)) {
                array_splice($tokens, $edge, 1);
                break;
            }
            if (isset(self::NAMED_ACTIONS[$token])) {
                $suffix = self::NAMED_ACTIONS[$token];
                array_splice($tokens, $edge, 1);
                break;
            }
        }

        return [Str::headline(implode(' ', $tokens)), $suffix];
    }

    /**
     * بس الباراميترات اللي هي كلمة (CustomerInvoice / Customer ...).
     * الأرقام والتواريخ والموديلات المربوطة بتتجاهل عشان الاسم يفضل ثابت.
     */
    private static function descriptiveParameters(array $parameters): array
    {
        $details = [];
        foreach ($parameters as $name => $value) {
            if (in_array($name, self::IGNORED_PARAMETERS, true) || ! is_string($value)) {
                continue;
            }
            if (preg_match('/^[A-Za-z][A-Za-z_-]*$/', $value) !== 1) {
                continue;
            }
            $details[] = Str::headline($value);
        }

        return $details;
    }
}
