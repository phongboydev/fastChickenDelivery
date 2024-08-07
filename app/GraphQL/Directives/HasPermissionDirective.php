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

class HasPermissionDirective extends BaseDirective implements FieldMiddleware
{

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        """
        A description of what this directive does.
        """
        directive @hasPermission(
            """
            Directives can have arguments to parameterize them.
            """
            name: String!
        ) on ARGUMENT_DEFINITION
GRAPHQL;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $user = Auth::user();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use (
            $resolver,
            $user
        ) {
            // Do something before the resolver, e.g. validate $args, check authentication

            if (!$user) {
                throw new AuthenticationException(__("error.permission"));
            }

            $permissions = $this->directiveArgValue('name');

            $result = [];
            foreach (preg_split('/ (or|\|\|) /', $permissions) as $parts) {
                $bits = preg_split('/ (and|&&) /', $parts);
                for ($x = 0; $x < count($bits); $x++) {
                    $bits[$x] = preg_replace('/\s?(and|&&)\s?/', '', $bits[$x]);
                }
                $result[] = $bits;
            }

            if (!$result) {
                throw new AuthenticationException(__("error.permission"));
            }

            $logic = false;

            foreach ($result as $p) {
                $logic = $logic || $user->hasAllPermissions($p);
            }

            if (!$logic) {
                throw new AuthenticationException(__("error.permission"));
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
