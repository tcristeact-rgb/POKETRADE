// Pruebas unitarias de las funciones puras de utils.js
// Ejecutar con:  npm test   (desde la carpeta frontend/)

import {
  escapeHtml,
  calcularRareza,
  calcularGeneracion,
  traducirTipo,
  capitalizarNombre,
} from '../js/utils.js';

describe('escapeHtml', () => {
  test('escapa los caracteres peligrosos de HTML', () => {
    expect(escapeHtml('<script>alert(1)</script>'))
      .toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
  });

  test('escapa ampersand y comillas dobles', () => {
    expect(escapeHtml('Tom & "Jerry"')).toBe('Tom &amp; &quot;Jerry&quot;');
  });

  test('devuelve cadena vacía para valores nulos o vacíos', () => {
    expect(escapeHtml('')).toBe('');
    expect(escapeHtml(null)).toBe('');
    expect(escapeHtml(undefined)).toBe('');
  });
});

describe('calcularRareza', () => {
  test('asigna "Común" cuando no hay experiencia o es muy baja', () => {
    expect(calcularRareza(0)).toBe('Común');
    expect(calcularRareza(undefined)).toBe('Común');
    expect(calcularRareza(30)).toBe('Común');
  });

  test('asigna la rareza correcta según los umbrales de experiencia', () => {
    expect(calcularRareza(50)).toBe('Poco común');
    expect(calcularRareza(100)).toBe('Rara');
    expect(calcularRareza(150)).toBe('Rara Holo');
    expect(calcularRareza(250)).toBe('Ultra Rara');
  });
});

describe('calcularGeneracion', () => {
  test('devuelve la generación según el ID del Pokémon', () => {
    expect(calcularGeneracion(1)).toBe('I');
    expect(calcularGeneracion(151)).toBe('I');
    expect(calcularGeneracion(152)).toBe('II');
    expect(calcularGeneracion(1000)).toBe('IX');
  });
});

describe('traducirTipo', () => {
  test('traduce los tipos conocidos al español', () => {
    expect(traducirTipo('fire')).toBe('Fuego');
    expect(traducirTipo('water')).toBe('Agua');
    expect(traducirTipo('grass')).toBe('Planta');
  });

  test('devuelve el valor original si el tipo no está mapeado', () => {
    expect(traducirTipo('desconocido')).toBe('desconocido');
  });
});

describe('capitalizarNombre', () => {
  test('pone en mayúscula la primera letra del nombre', () => {
    expect(capitalizarNombre('charizard')).toBe('Charizard');
    expect(capitalizarNombre('pikachu')).toBe('Pikachu');
  });
});
