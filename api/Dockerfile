# ─────────────────────────────────────────────────────────────────────
# Dockerfile — Backend Laravel de PokeTrade (para Render, plan free)
#
# Estrategia: imagen mínima basada en PHP CLI que aplica las migraciones
# y sirve la API con `php artisan serve`. Está pensada para una DEMO de
# portfolio, priorizando simplicidad y facilidad de depuración sobre la
# robustez de un servidor de producción (nginx + php-fpm).
#
# Render no tiene runtime nativo de PHP, por eso desplegamos vía Docker.
# ─────────────────────────────────────────────────────────────────────

# Imagen base: PHP 8.2 en su variante "cli" (línea de comandos).
# Es ligera y trae el binario `php`; encima instalamos solo lo que
# Laravel necesita y que no viene activado por defecto.
FROM php:8.2-cli

# Paquetes del sistema necesarios para COMPILAR las extensiones de PHP:
#   - libpq-dev   → cabeceras de PostgreSQL, requeridas por pdo_pgsql
#   - libonig-dev → librería Oniguruma, requerida por mbstring
#   - libzip-dev  → soporte de archivos .zip (lo usa Composer)
#   - unzip, git  → Composer los usa para descargar y extraer dependencias
# Borramos la caché de apt al final para que la imagen pese menos.
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP que NO vienen activadas en la imagen base:
#   - pdo_pgsql → driver PDO de PostgreSQL. SIN esto Laravel no puede
#                 conectar con Supabase y falla con "could not find driver".
#   - mbstring  → manejo de cadenas multibyte; Laravel lo usa de forma
#                 generalizada (helpers Str::, validación, etc.).
#   - bcmath    → aritmética de precisión arbitraria que algunas partes
#                 del framework esperan tener disponible.
#   - opcache   → guarda el bytecode compilado en memoria. Sin él, PHP vuelve a
#                 leer, parsear y compilar los ~3.000 ficheros de Laravel EN CADA
#                 PETICIÓN. Es la diferencia entre arrancar el framework en 15 ms
#                 o en 100. Es una línea, y estaba sin poner.
RUN docker-php-ext-install pdo_pgsql mbstring bcmath opcache

# Configuración de OPcache para un contenedor: el código no cambia en caliente,
# así que se compila una vez y no se vuelve a mirar el disco nunca más
# (validate_timestamps=0). enable_cli=1 porque `artisan serve` corre bajo el SAPI
# de línea de comandos: sin esto, OPcache estaría instalado y apagado.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Composer: lo copiamos ya compilado desde su imagen oficial en vez de
# instalarlo a mano. Es la forma recomendada, reproducible y más limpia.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Directorio de trabajo dentro del contenedor: todo el código vivirá aquí.
WORKDIR /app

# Copiamos el código del backend. El contexto de build es la carpeta api/
# porque en Render se configura Root Directory = api.
COPY . /app

# Instalamos las dependencias PHP optimizadas para producción:
#   --no-dev              → omite paquetes de desarrollo (PHPUnit, etc.)
#   --optimize-autoloader → genera un mapa de clases más rápido de cargar
RUN composer install --no-dev --optimize-autoloader

# Render asigna el puerto mediante la variable de entorno $PORT. Exponemos
# 8000 de forma informativa (Render gestiona el mapeo real automáticamente).
EXPOSE 8000

# El servidor embebido de PHP atiende UNA petición a la vez. El catálogo dispara
# tres llamadas a la API en paralelo desde el navegador, y se las encontraba en
# cola de una en una: medido, tardaban lo mismo en paralelo que en serie (493 ms
# frente a 497 ms). Con varios workers, lo que tarda es la más lenta.
#
# Ojo: ServeCommand IGNORA esta variable si no se le pasa --no-reload (avisa por
# consola y arranca un solo proceso). Por eso el CMD lo lleva.
ENV PHP_CLI_SERVER_WORKERS=4

# Comando de arranque del contenedor:
#   1) Las cachés de Laravel. Sin ellas, cada petición vuelve a leer y montar
#      TODOS los ficheros de config/ y a registrar TODAS las rutas. Se generan al
#      arrancar el contenedor y no al construirlo a propósito: config:cache
#      congela las variables de entorno, y en Render solo existen en tiempo de
#      ejecución. (route:cache exige que ninguna ruta sea una closure: ver
#      routes/web.php.)
#   2) `migrate --force` aplica las migraciones pendientes contra Supabase.
#      Es idempotente: si ya están aplicadas no hace nada, así que es seguro
#      que se ejecute en cada arranque (incluido el "despertar" de Render
#      tras dormirse). --force es obligatorio porque el entorno no es
#      interactivo y Laravel, si no, pediría confirmación.
#   3) `artisan serve` levanta el servidor PHP embebido escuchando en todas
#      las interfaces (0.0.0.0) y en el puerto que indique Render. El valor
#      por defecto 8000 permite probar la imagen también en local. --no-reload
#      quita el vigilante de ficheros (aquí no cambia ninguno) y es lo que
#      habilita los workers de arriba.
CMD php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan migrate --force && php artisan serve --no-reload --host 0.0.0.0 --port ${PORT:-8000}
