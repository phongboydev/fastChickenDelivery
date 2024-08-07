<?php

namespace App\GraphQL\Directives;

use App\Models\ClientWorkflowSetting;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use App\Support\Constant;
use Illuminate\Support\Facades\Auth;

class CanManageClientDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return
            /** @lang GraphQL */
            <<<'GRAPHQL'
        """
        A description of what this directive does.
        """
        directive @canManageClient(
            """
            Directives can have arguments to parameterize them.
            """
        ) on ARGUMENT_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $user = Auth::user();
            $role = $user->getRole();
            $clientId = $args['client_id'];

            if (!$user->isInternalUser()) {
                $permissions = ["manage-employee"];
                $advancedPermissions = ["advanced-manage-employee-list", "advanced-manage-employee-list-read"];
                $settingAdvancedPermissionFlow = $user->getSettingAdvancedPermissionFlow($user->client_id);

                if (!$user->checkHavePermission($permissions, $advancedPermissions, $settingAdvancedPermissionFlow)) {
                    throw new AuthenticationException(__("error.permission"));
                }
            } else {
                if (!($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($clientId))) {
                    throw new AuthenticationException(__("error.permission"));
                }
            }

            // Call the actual resolver
            return $resolver($root, $args, $context, $resolveInfo);
        });

        // Keep the chain of adding field middleware going by calling the next handler.
        // Calling this before or after ->setResolver() allows you to control the
        // order in which middleware is wrapped around the field.
        return $next($fieldValue);
    }
}
