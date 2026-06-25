<?php

namespace Kunci\SSO;

// ServiceProvider ini opsional & hanya di-load jika dijalankan dalam framework Laravel
if (class_exists('Illuminate\Support\ServiceProvider')) {
    class KunciSSOServiceProvider extends \Illuminate\Support\ServiceProvider
    {
        /**
         * Bootstrap any package services.
         *
         * @return void
         */
        public function boot()
        {
            if ($this->app->runningInConsole()) {
                $this->publishes([
                    __DIR__ . '/../config/kunci-sso.php' => config_path('kunci-sso.php'),
                ], 'kunci-sso-config');
            }
        }

        /**
         * Register any application services.
         *
         * @return void
         */
        public function register()
        {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/kunci-sso.php',
                'kunci-sso'
            );

            $this->app->singleton(KunciSSOClient::class, function ($app) {
                // Mendukung config baru (kunci-sso) atau mapping legacy config services.sso
                $config = config('kunci-sso') ?: config('services.sso');
                return new KunciSSOClient($config);
            });
        }
    }
}
