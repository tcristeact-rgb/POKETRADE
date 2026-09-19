---
name: implementa-spec
description: Implementa una especificación de docs/specs/, la verifica contra sus criterios de aceptación y registra el resultado en docs/decisions/. Úsalo para aplicar una spec ya escrita.
argument-hint: [ruta-de-la-spec]
---

Implementa la especificación: $ARGUMENTS

Si no se ha pasado ruta, lista `docs/specs/` y pregunta cuál antes de seguir.

## 1. Leer y validar

Lee la spec entera y `CLAUDE.md`.

Si la spec **no tiene criterios de aceptación verificables**, PARA aquí y dilo.
Sin criterios no puedes cerrar el bucle tú solo: acabarías preguntándole al
usuario si funciona, que es justo lo que este flujo evita. Pide que se añadan y no
implementes nada.

## 2. Planificar

Presenta el plan antes de tocar ningún archivo, para que el usuario lo revise.

## 3. Implementar

Solo lo que dice la spec. Respeta el apartado "Fuera de alcance".
No modifiques el archivo de la spec.

## 4. Verificar — obligatorio

Comprueba **tú** cada criterio de aceptación: ejecuta los tests, el build, el
linter o el servidor de desarrollo, o inspecciona el resultado directamente.

No preguntes al usuario si funciona. Averígualo.

Si un criterio falla, arréglalo y vuelve a verificar. Si no puedes cumplirlo,
no lo tapes: se registra como fallido en el paso siguiente.

## 5. Registrar la decisión

Escribe `docs/decisions/<mismo-nombre-que-la-spec>.md` siguiendo
`docs/decisions/_plantilla.md`. Obligatorio:

- Qué implementaste
- **En qué te desviaste de la spec y por qué** — si no hubo desviaciones, dilo
- Archivos tocados
- Verificación criterio a criterio: qué ejecutaste y qué salió
- Qué queda pendiente

## 6. Cerrar

Actualiza el campo `estado:` de la spec a `implementada` o `parcial`.

Resume en tres líneas: criterios cumplidos, criterios fallidos, y la ruta del
archivo de decisión.

No hagas commit ni push salvo que el usuario lo pida explícitamente.
