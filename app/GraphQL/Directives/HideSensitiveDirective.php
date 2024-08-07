<?php

namespace App\GraphQL\Directives;

use App\Support\Sensitive;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class HideSensitiveDirective extends BaseDirective implements FieldMiddleware
{

    public static function definition(): string
    {
        return
            /** @lang GraphQL */
            <<<'GRAPHQL'
        """
        A description of what this directive does.
        """
        directive @hideSensitive on ARGUMENT_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $user = Auth::user();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use (
            $resolver,
            $user
        ) {
            $field = $this->directiveArgValue('name');

            $next = false;

            if (!$user->isInternalUser()) {
                if ($user->id == $root['user_id']) {
                    $next = true;
                } else {
                    if (isset(Sensitive::SALARY[$field])) {
                        // Init advanced permission
                        $advancedPermissions = [
                            'advanced-manage-payroll-list-read',
                            'advanced-manage-payroll-social-insurance-read'
                        ];
                        // Init normal permission
                        $normalPermissions = ['manage-social', 'manage-payroll', 'manage-employee-payroll'];

                        if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                            $next = true;
                        }
                    } elseif ($field == 'salary-history') {
                        // Init advanced permission
                        $advancedPermissions = [
                            'advanced-manage-payroll-salary-history-read'
                        ];
                        // Init normal permission
                        $normalPermissions = ['manage-payroll', 'manage-employee-payroll'];

                        if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                            $next = true;
                        }
                    } elseif (isset(Sensitive::PERSONAL_DATA[$field])) {
                        // Init advanced permission
                        $advancedPermissions = [
                            'advanced-manage-payroll-list-read',
                            'advanced-manage-employee-list-read',
                            'advanced-manage-payroll-social-insurance-read'
                        ];
                        // Init normal permission
                        $normalPermissions = ['manage-social', 'manage-employee', 'manage-payroll', 'manage-employee-payroll'];
                        if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                            $next = true;
                        }
                    } elseif (isset(Sensitive::BASIC_DATA[$field])) {
                        $next = true;
                    }
                }
            } else {
                $next = true;
            }

            $result = $next ? $root : null;

            return $resolver($result, $args, $context, $resolveInfo);
        });

        return $next($fieldValue);
    }
}
