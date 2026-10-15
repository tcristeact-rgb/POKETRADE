-- Keep-alive de la API de Render (plan free), programado dentro de Supabase.
--
-- Render apaga el contenedor tras 15 min sin tráfico, y la primera visita
-- después espera a que arranque. Antes esto lo hacía un cron de GitHub Actions
-- cada 5 min, pero GitHub no respeta el horario: en la práctica se ejecutaba
-- cada 2-10 HORAS (ver docs/decisions/2026-10-15-keep-alive-fiable-y-cache-larga.md).
-- pg_cron corre dentro de la base de datos y se ejecuta en hora.
--
-- Cómo aplicarlo: Supabase → SQL Editor → pegar este archivo → Run.
-- Es idempotente: cron.schedule con un nombre que ya existe actualiza ese job,
-- no crea otro.
--
-- El ping va DIRECTO a Render, no a través de Vercel: la CDN podría contestar
-- desde su caché y el contenedor se dormiría igual. /api/health no toca la base
-- de datos ni TCGdex: es el ping más barato.
--
-- Coste asumido: el contenedor queda encendido todo el mes (~744 de las 750 h
-- gratuitas de Render).

create extension if not exists pg_cron;
create extension if not exists pg_net;

-- Cada 10 min: deja margen de sobra frente a los 15 min de Render.
-- El timeout de 60 s cubre un arranque en frío; aunque se agotara, la petición
-- ya habría llegado a Render y lo habría despertado
select cron.schedule(
  'poketrade-keep-alive',
  '*/10 * * * *',
  $$ select net.http_get(
       url := 'https://poketrade-api-3nwm.onrender.com/api/health',
       timeout_milliseconds := 60000
     ) $$
);


-- ===== Comprobar que funciona (ejecutar aparte) =====
--
-- Últimas respuestas de Render (pg_net las guarda 6 h). Debería haber un 200
-- cada 10 min:
--
--   select created, status_code, timed_out, error_msg
--   from net._http_response
--   order by created desc
--   limit 20;
--
-- Ejecuciones del job en pg_cron:
--
--   select start_time, status, return_message
--   from cron.job_run_details
--   where jobid = (select jobid from cron.job where jobname = 'poketrade-keep-alive')
--   order by start_time desc
--   limit 10;
--
-- Quitarlo:
--
--   select cron.unschedule('poketrade-keep-alive');
