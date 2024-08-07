<?php

namespace App\GraphQL\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CanAccessDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<GRAPHQL
"""
Limit field access to users of a certain role.
"""
directive @canAccess(
  """
  The name of the role authorized users need to have.
  """
  requiredRole: String!
) on FIELD_DEFINITION
GRAPHQL;
    }

    function checkPermission($requiredRole) {
        foreach (Auth::user()->permissions->toArray() as $key => $value) {
            if ($value['name'] == $requiredRole) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $originalResolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($originalResolver) {
                    $requiredRole = $this->directiveArgValue('requiredRole');
                    // Throw in case of an invalid schema definition to remind the developer
                    if ($requiredRole === null) {
                        throw new DefinitionException("Missing argument 'requiredRole' for directive '@canAccess'.");
                    }
                    $check = $this->checkPermission($requiredRole);
                    if (!$check) {
                        return null;
                    }

                    return $originalResolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }
}