<?php

namespace App\GraphQL\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


class HasAnyPermissionDirective extends BaseDirective implements FieldMiddleware
{
    // TODO implement the directive https://lighthouse-php.com/master/custom-directives/getting-started.html

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
            """
            A description of what this directive does.
            """
            directive @hasAnyPermission(
                """
                Directives can have arguments to parameterize them.
                """
                name: [String!]
            ) on ARGUMENT_DEFINITION
    GRAPHQL;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $auth = Auth::user();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use (
            $resolver,
            $auth
        ) {
            if (!$auth->isInternalUser()) {
                $permissions = true;
                $normalPermissions = $this->directiveArgValue('normal');
                $advancedPermissions = $this->directiveArgValue('advanced');
                $isAdvanced = $auth->getSettingAdvancedPermissionFlow($auth->client_id);
                // Check permission
                if ($isAdvanced || !empty($normalPermissions)) {
                    $permissions = $auth->checkHavePermission($normalPermissions, $advancedPermissions, $isAdvanced);
                }
                if (!$permissions) {
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
