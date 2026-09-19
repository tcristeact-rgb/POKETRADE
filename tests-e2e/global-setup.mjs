// Antes de la suite: borra los usuarios que crean los tests de verificación,
// para que registrarse con el mismo email vuelva a funcionar en cada ejecución.
// Toca la BD de desarrollo (api/database/database.sqlite) directamente con el
// módulo sqlite de Node, sin dependencias.
import { DatabaseSync } from 'node:sqlite';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const BD = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../api/database/database.sqlite');
const EMAILS = ['e2e-verificacion@poketrade.local', 'e2e-sin-verificar@poketrade.local'];

export default function globalSetup() {
  if (!fs.existsSync(BD)) return;
  const db = new DatabaseSync(BD);
  try {
    const borrar = db.prepare('DELETE FROM users WHERE email = ?');
    for (const email of EMAILS) borrar.run(email);
  } finally {
    db.close();
  }
}
