<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->definirLimitesDePeticiones();
    }

    // Límites de peticiones por minuto. Sin esto no hay ninguno: en Laravel 12 el
    // grupo `api` solo lleva throttle si bootstrap/app.php llama a throttleApi(),
    // y ese middleware exige que el limiter 'api' exista.
    //
    // Se apoyan en el cache store por defecto (CACHE_STORE): 'database' en Render,
    // 'array' en tests. No hay Redis y no hace falta.
    private function definirLimitesDePeticiones(): void
    {
        // Toda la API. El doble del valor por defecto de Laravel (60): la portada
        // dispara tres llamadas en paralelo, y en una demo desde una misma red
        // varios navegadores comparten IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Login y registro: 5 intentos por minuto por email+IP. Por email para
        // frenar la fuerza bruta contra una cuenta concreta; por IP para que un
        // atacante no bloquee a la víctima a base de fallar con su email.
        //
        // El email se normaliza igual que lo guarda el registro. Si no llega como
        // cadena (array, null), se usa '' — el limiter corre ANTES que el validador
        // y no puede ser él quien reviente con un TypeError.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $clave = is_string($email) ? strtolower(trim($email)) : '';

            return Limit::perMinute(5)->by($clave.'|'.$request->ip());
        });

        // /cartas/buscar es un proxy en vivo a TCGdex: cada petición sale a
        // internet. 30 por minuto y por IP acota el gasto sin estorbar a nadie
        // que busque a mano. Revisar cuando exista el autocompletado.
        RateLimiter::for('buscar', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
