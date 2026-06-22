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
RUN docker-php-ext-install pdo_pgsql mbstring bcmath

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

# Comando de arranque del contenedor:
#   1) `migrate --force` aplica las migraciones pendientes contra Supabase.
#      Es idempotente: si ya están aplicadas no hace nada, así que es seguro
#      que se ejecute en cada arranque (incluido el "despertar" de Render
#      tras dormirse). --force es obligatorio porque el entorno no es
#      interactivo y Laravel, si no, pediría confirmación.
#   2) `artisan serve` levanta el servidor PHP embebido escuchando en todas
#      las interfaces (0.0.0.0) y en el puerto que indique Render. El valor
#      por defecto 8000 permite probar la imagen también en local.
CMD php artisan migrate --force && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}
