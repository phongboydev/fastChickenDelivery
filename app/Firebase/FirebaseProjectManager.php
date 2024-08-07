<?php

namespace App\Firebase;

use Kreait\Firebase\Exception\InvalidArgumentException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Laravel\Firebase\FirebaseProject;
use Kreait\Laravel\Firebase\FirebaseProjectManager as BaseManager;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

class FirebaseProjectManager extends BaseManager
{
    protected function configure(string $name): FirebaseProject
    {
        $factory = new Factory();

        $config = $this->configuration($name);

        if ($tenantId = $config['auth']['tenant_id'] ?? null) {
            $factory = $factory->withTenantId($tenantId);
        }

        // credentials is expected to be an array
        if ($credentials = $config['credentials'] ?? null) {
            $factory = $factory->withServiceAccount($credentials);
        }

        $enableAutoDiscovery = $config['credentials']['auto_discovery'] ?? ($this->getDefaultProject() === $name);
        if (!$enableAutoDiscovery) {
            $factory = $factory->withDisabledAutoDiscovery();
        }

        if ($databaseUrl = $config['database']['url'] ?? null) {
            $factory = $factory->withDatabaseUri($databaseUrl);
        }

        if ($authVariableOverride = $config['database']['auth_variable_override'] ?? null) {
            $factory = $factory->withDatabaseAuthVariableOverride($authVariableOverride);
        }

        if ($defaultStorageBucket = $config['storage']['default_bucket'] ?? null) {
            $factory = $factory->withDefaultStorageBucket($defaultStorageBucket);
        }

        if ($cacheStore = $config['cache_store'] ?? null) {
            $cache = $this->app->make('cache')->store($cacheStore);

            if ($cache instanceof CacheInterface) {
                $cache = new Psr16Adapter($cache);
            } else {
                throw new InvalidArgumentException('The cache store must be an instance of a PSR-6 or PSR-16 cache');
            }

            $factory = $factory
                ->withVerifierCache($cache)
                ->withAuthTokenCache($cache)
            ;
        }

        if ($logChannel = $config['logging']['http_log_channel'] ?? null) {
            $factory = $factory->withHttpLogger(
                $this->app->make('log')->channel($logChannel)
            );
        }

        if ($logChannel = $config['logging']['http_debug_log_channel'] ?? null) {
            $factory = $factory->withHttpDebugLogger(
                $this->app->make('log')->channel($logChannel)
            );
        }

        $options = HttpClientOptions::default();

        if ($proxy = $config['http_client_options']['proxy'] ?? null) {
            $options = $options->withProxy($proxy);
        }

        if ($timeout = $config['http_client_options']['timeout'] ?? null) {
            $options = $options->withTimeOut((float) $timeout);
        }

        $factory = $factory->withHttpClientOptions($options);

        return new FirebaseProject($factory, $config);
    }
}
