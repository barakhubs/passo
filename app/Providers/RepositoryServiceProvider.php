<?php

namespace App\Providers;

use App\Interfaces\BusinessRepositoryInterface;
use App\Interfaces\CategoryRepositoryInterface;
use App\Interfaces\CustomerRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\SaleRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    protected $repositories = [
        CategoryRepositoryInterface::class => \App\Repositories\CategoryRepository::class,
        ProductRepositoryInterface::class => \App\Repositories\ProductRepository::class,
        BusinessRepositoryInterface::class => \App\Repositories\BusinessRepository::class,
        CustomerRepositoryInterface::class => \App\Repositories\CustomerRepository::class,
        SaleRepositoryInterface::class => \App\Repositories\SaleRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        foreach ($this->repositories as $interface => $repository) {
            $this->app->bind($interface, $repository);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
