---
name: nueva-spec
description: Crea una especificación nueva en docs/specs/ a partir de una descripción de lo que se quiere cambiar. No implementa nada. Úsalo antes de empezar cualquier cambio no trivial.
argument-hint: [descripción del cambio]
---

Escribe una especificación para: $ARGUMENTS

1. Explora el repositorio lo justo para saber qué archivos tocarías y qué
   convenciones ya existen. Lee `CLAUDE.md` primero.
2. Si algo del alcance es ambiguo **y cambia la solución**, pregunta antes de
   escribir. Si no cambia nada, decide y sigue.
3. Rellena la plantilla `docs/specs/_plantilla.md` y guárdala como
   `docs/specs/AAAA-MM-DD-<slug>.md`.

Los **criterios de aceptación** son el campo crítico. Cada uno tiene que poder
comprobarse sin preguntar a una persona: un comando que pasa, o un comportamiento
observable y concreto. Si no sabes escribir un criterio verificable para algo, ese
algo todavía no está bien definido — dilo en vez de rellenar.

NO implementes nada. El resultado de este comando es un archivo de spec y nada más.
