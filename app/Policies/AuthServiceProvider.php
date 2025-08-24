<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\Work;
use App\Policies\GroupPolicy;
use App\Policies\WorkPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Group::class => GroupPolicy::class,
        Work::class  => WorkPolicy::class,
        // Si tienes más modelos con políticas, mápéalos aquí.
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Con $policies definido, Laravel auto-registra las policies.
        // Si quisieras Gates adicionales, se definen aquí.
    }
}
